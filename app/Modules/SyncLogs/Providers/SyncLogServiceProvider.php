<?php
namespace App\Modules\SyncLogs\Providers;

use Illuminate\Support\ServiceProvider;

class SyncLogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app['router']->get('api/sync-logs', function (\Illuminate\Http\Request $request) {
            $query = \App\Modules\SyncLogs\SyncLog::query();
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            $limit = (int) $request->get('limit', 10);
            return response()->json(
                $query->orderByDesc('synced_at')->limit($limit)->get()
            );
        });
    }
}
