<?php
use App\Modules\Admin\Http\Middleware\AdminTokenMiddleware;

Route::post('admin/login', 'AdminController@login')
    ->middleware('throttle:5,1')
    ->name('admin.login');

Route::middleware(AdminTokenMiddleware::class)->group(function () {
    Route::get('admin/players',         'AdminController@listPlayers');
    Route::post('admin/players',        'AdminController@createPlayer');
    Route::delete('admin/players/{id}', 'AdminController@deletePlayer');
    Route::post('admin/sync',           'AdminController@syncAll');
    Route::post('admin/sync/{id}',      'AdminController@syncPlayer');
});
