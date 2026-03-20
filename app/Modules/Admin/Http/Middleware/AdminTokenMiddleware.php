<?php
namespace App\Modules\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();
        if (!$bearer || Cache::get("admin_token:{$bearer}") !== true) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        return $next($request);
    }
}
