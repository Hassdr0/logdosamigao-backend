<?php
namespace App\Modules\Dungeons\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class DungeonServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::namespace('App\Modules\Dungeons\Http')
            ->prefix('api')
            ->middleware('api')
            ->group(__DIR__ . '/../routes/api.php');
    }
}
