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
            // Normalize to backslashes for class name resolution
            $normalized = str_replace('/', DIRECTORY_SEPARATOR, $file);
            $appPath    = str_replace('/', DIRECTORY_SEPARATOR, app_path());
            $class = str_replace(
                [$appPath . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, '.php'],
                ['App\\', '\\', ''],
                $normalized
            );
            $this->app->register($class);
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
