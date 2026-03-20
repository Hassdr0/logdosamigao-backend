<?php
namespace App\Modules\Raids\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Raids\RaidService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RaidController extends Controller
{
    public function __construct(private RaidService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['raids' => $this->service->getAll($request->only(['difficulty', 'instance']))]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['raid' => $this->service->findById($id)]);
    }
}
