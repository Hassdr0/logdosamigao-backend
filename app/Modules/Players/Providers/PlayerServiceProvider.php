<?php
namespace App\Modules\Players\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\Players\Player;
use App\Modules\Players\PlayerService;

class PlayerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->namespace('App\Modules\Players\Http\Controllers')
            ->group(__DIR__ . '/../routes/api.php');
    }

    public function register(): void
    {
        $this->app->bind(PlayerService::class, fn() => new PlayerService(new Player()));
    }
}
