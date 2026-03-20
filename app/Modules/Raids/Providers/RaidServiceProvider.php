<?php
namespace App\Modules\Raids\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\Raids\Raid;
use App\Modules\Raids\RaidService;

class RaidServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->namespace('App\Modules\Raids\Http\Controllers')
            ->group(__DIR__ . '/../routes/api.php');
    }

    public function register(): void
    {
        $this->app->bind(RaidService::class, fn() => new RaidService(new Raid()));
    }
}
