<?php
namespace App\Modules\Sync\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\Sync\WarcraftLogsService;
use App\Modules\Sync\SyncService;

class SyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WarcraftLogsService::class, function () {
            return new WarcraftLogsService();
        });

        $this->app->singleton(SyncService::class, function ($app) {
            return new SyncService($app->make(WarcraftLogsService::class));
        });
    }

    public function boot(): void {}
}
