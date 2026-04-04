<?php
namespace App\Modules\Sync\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\Sync\WarcraftLogsService;
use App\Modules\Sync\BlizzardService;
use App\Modules\Sync\RaiderIOService;
use App\Modules\Sync\SyncService;

class SyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WarcraftLogsService::class, fn() => new WarcraftLogsService());
        $this->app->singleton(BlizzardService::class,     fn() => new BlizzardService());
        $this->app->singleton(RaiderIOService::class,     fn() => new RaiderIOService());

        $this->app->singleton(SyncService::class, function ($app) {
            return new SyncService(
                $app->make(WarcraftLogsService::class),
                $app->make(BlizzardService::class),
                $app->make(RaiderIOService::class),
            );
        });
    }

    public function boot(): void {}
}
