<?php
namespace App\Modules\Performances\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\Performances\Performance;
use App\Modules\Performances\PerformanceService;

class PerformanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->namespace('App\Modules\Performances\Http\Controllers')
            ->group(__DIR__ . '/../routes/api.php');
    }

    public function register(): void
    {
        $this->app->bind(PerformanceService::class, fn() => new PerformanceService(new Performance()));
    }
}
