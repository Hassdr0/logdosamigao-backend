<?php
namespace App\Modules\Performances\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Performances\PerformanceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RankingController extends Controller
{
    public function __construct(private PerformanceService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['difficulty', 'raid']);
        return response()->json([
            'rankings'   => $this->service->getRankings($filters),
            'highlights' => $this->service->getHighlights($filters),
        ]);
    }
}
