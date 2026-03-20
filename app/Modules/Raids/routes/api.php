<?php
Route::get('raids', 'RaidController@index')->name('raids.index');
Route::get('raids/{id}', 'RaidController@show')->name('raids.show');
