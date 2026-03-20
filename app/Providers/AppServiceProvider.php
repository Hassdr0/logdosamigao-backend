<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $modules = glob(app_path('Modules/*/Providers/*ServiceProvider.php'));
        foreach ($modules as $file) {
            $class = str_replace(
                [app_path() . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, '.php'],
                ['App\\', '\\', ''],
                $file
            );
            if (class_exists($class)) {
                $this->app->register($class);
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
