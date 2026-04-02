<?php
namespace App\Modules\Players\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Players\PlayerService;
use Illuminate\Http\JsonResponse;

class PlayerController extends Controller
{
    public function __construct(private PlayerService $service) {}

    public function index(): JsonResponse
    {
        return response()->json(['players' => $this->service->getAll()]);
    }

    public function show(string $realm, string $name): JsonResponse
    {
        $player = $this->service->findByRealmAndName($realm, $name);
        return response()->json(['player' => $player->load('performances.raid', 'dungeonRuns')]);
    }
}
