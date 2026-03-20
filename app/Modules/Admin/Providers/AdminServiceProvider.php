<?php
namespace App\Modules\Admin\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->namespace('App\Modules\Admin\Http\Controllers')
            ->group(__DIR__ . '/../routes/api.php');
    }
}
