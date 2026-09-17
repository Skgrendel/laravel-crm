<?php

use Addons\Zadarma\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings/zadarma')->group(function () {
    Route::controller(SettingsController::class)->group(function () {
        Route::get('', 'index')->name('admin.settings.zadarma.index');

        Route::put('', 'update')->name('admin.settings.zadarma.update');

        Route::post('test-connection', 'testConnection')->name('admin.settings.zadarma.test_connection');
    });
});
