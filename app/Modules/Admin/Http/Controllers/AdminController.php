<?php
namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Players\Player;
use App\Modules\Sync\SyncService;
use App\Modules\SyncLogs\SyncLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);
        $stored = config('app.admin_password');

        if (!$stored || !Hash::check($request->password, $stored)) {
            return response()->json(['message' => 'Invalid password'], 401);
        }

        $token = Str::random(64);
        Cache::put("admin_token:{$token}", true, now()->addHours(8));
        return response()->json(['token' => $token]);
    }

    public function listPlayers(): JsonResponse
    {
        return response()->json(['players' => Player::orderBy('name')->get()]);
    }

    public function createPlayer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'   => 'required|string|max:50',
            'realm'  => 'required|string|max:50',
            'region' => 'required|string|size:2',
        ]);
        $data['realm']  = strtolower($data['realm']);
        $data['region'] = strtoupper($data['region']);
        $player = Player::create($data);
        return response()->json(['player' => $player], 201);
    }

    public function deletePlayer(int $id): JsonResponse
    {
        Player::findOrFail($id)->delete();
        return response()->json(['message' => 'Player removed']);
    }

    public function syncAll(SyncService $syncService): JsonResponse
    {
        $results = $syncService->syncAll();

        return response()->json([
            'message' => 'Sync global concluído',
            'results' => $results,
        ]);
    }

    public function syncPlayer(int $id, SyncService $syncService): JsonResponse
    {
        $player  = \App\Modules\Players\Player::findOrFail($id);
        $syncLog = $syncService->syncPlayer($player);

        return response()->json([
            'message'  => "Sync concluído para {$player->name}",
            'status'   => $syncLog->status,
            'reports'  => $syncLog->reports_fetched,
            'errors'   => $syncLog->error_message,
        ]);
    }

    public function syncMythicPlus(int $id, SyncService $syncService): JsonResponse
    {
        $player = Player::findOrFail($id);
        try {
            $saved = $syncService->syncMythicPlusOnly($player);
            return response()->json(['message' => "M+ sync OK para {$player->name}", 'dungeons_saved' => $saved]);
        } catch (\Throwable $e) {
            return response()->json(['message' => "M+ sync falhou para {$player->name}", 'error' => $e->getMessage()], 500);
        }
    }

    public function syncMythicPlusAll(SyncService $syncService): JsonResponse
    {
        $players = Player::where('is_active', true)->get();
        $errors = [];
        $total_saved = 0;
        foreach ($players as $player) {
            try {
                $total_saved += $syncService->syncMythicPlusOnly($player);
            } catch (\Throwable $e) {
                $errors[] = "{$player->name}: " . $e->getMessage();
            }
        }
        return response()->json([
            'message'      => 'M+ sync global ' . (empty($errors) ? 'OK' : 'parcial'),
            'total'        => $players->count(),
            'dungeons_saved' => $total_saved,
            'errors'       => $errors,
        ]);
    }

    public function updateAvatar(int $id, Request $request): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:2048']);
        $player = Player::findOrFail($id);

        $file    = $request->file('image');
        $mime    = $file->getMimeType();
        $base64  = base64_encode(file_get_contents($file->getRealPath()));
        $player->avatar_url = "data:{$mime};base64,{$base64}";
        $player->save();

        return response()->json(['avatar_url' => $player->avatar_url]);
    }

    public function syncLogs(): JsonResponse
    {
        $logs = SyncLog::with('player')
            ->orderByDesc('synced_at')
            ->limit(50)
            ->get();

        return response()->json(['logs' => $logs]);
    }
}
