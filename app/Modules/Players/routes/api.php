<?php
Route::get('players', 'PlayerController@index')->name('players.index');
Route::get('players/{realm}/{name}', 'PlayerController@show')->name('players.show');
