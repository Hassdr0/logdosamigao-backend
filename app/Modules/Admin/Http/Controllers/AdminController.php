<?php
namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Players\Player;
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

    public function syncAll(): JsonResponse
    {
        // Implementado no Plano 2 (WCL Sync)
        return response()->json(['message' => 'Sync enqueued', 'status' => 'pending']);
    }

    public function syncPlayer(int $id): JsonResponse
    {
        $player = Player::findOrFail($id);
        // Implementado no Plano 2 (WCL Sync)
        return response()->json(['message' => "Sync enqueued for {$player->name}", 'status' => 'pending']);
    }
}
