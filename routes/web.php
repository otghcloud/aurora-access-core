<?php

use Illuminate\Support\Facades\Route;
use OTGH\AccessControl\Core\Http\Controllers\AccessController;
use OTGH\AccessControl\Core\Http\Controllers\Admin\Access\AccessAreaController;
use OTGH\AccessControl\Core\Http\Controllers\Admin\Access\AccessCardController;
use OTGH\AccessControl\Core\Http\Controllers\Admin\Access\AccessEventController;
use OTGH\AccessControl\Core\Http\Controllers\Admin\Access\AccessUserController;
use OTGH\AccessControl\Core\Http\Controllers\Admin\Access\AreaPermissionController;
use OTGH\AccessControl\Core\Http\Controllers\Admin\DashboardController;
use OTGH\AccessControl\Core\Http\Controllers\Admin\Hardware\AccessAdapterBindingController;
use OTGH\AccessControl\Core\Http\Controllers\Admin\Hardware\AccessLockController;
use OTGH\AccessControl\Core\Http\Controllers\Admin\Hardware\AccessReaderController;
use OTGH\AccessControl\Core\Http\Controllers\Admin\Hardware\AccessSensorController;
use OTGH\AccessControl\Core\Http\Controllers\Admin\Hardware\AccessSourceController;
use OTGH\AccessControl\Core\Http\Controllers\Admin\Hardware\AccessSwitchController;
use OTGH\AccessControl\Core\Http\Controllers\Admin\Health\HealthController;
use OTGH\AccessControl\Core\Http\Controllers\Admin\Management\SystemUserController;
use OTGH\AccessControl\Core\Http\Controllers\Admin\System\ConfigurationController;
use OTGH\AccessControl\Core\Http\Controllers\Admin\System\EnvironmentController;
use OTGH\AccessControl\Core\Http\Controllers\Auth\LoginController;

Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to the Access Control API. Use the /validate endpoint to validate access cards.',
    ]);
});

Route::post('/validate', [AccessController::class, 'validateCard']);
Route::post('/doorbell', [AccessController::class, 'doorbellPressed']);

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::prefix('access')->name('access-')->group(function () {
        Route::post('areas/{area}/lock', [AccessAreaController::class, 'lock'])->name('areas.lock');
        Route::post('areas/{area}/unlock', [AccessAreaController::class, 'unlock'])->name('areas.unlock');
        Route::post('areas/{area}/autolock', [AccessAreaController::class, 'updateAutolock'])->name('areas.autolock');
        Route::get('areas/{area}/bindings', [AccessAreaController::class, 'bindings'])->name('areas.bindings');
        Route::resource('areas', AccessAreaController::class)->except(['show']);
        Route::resource('area-permissions', AreaPermissionController::class)->except(['show']);
        Route::resource('cards', AccessCardController::class);
        Route::resource('users', AccessUserController::class)->except(['show']);

        Route::get('events', [AccessEventController::class, 'index'])->name('events.index');
        Route::get('events/{event}', [AccessEventController::class, 'show'])->name('events.show');
    });

    Route::prefix('hardware')->name('access-')->group(function () {
        Route::get('locks/{lock}/bindings', [AccessLockController::class, 'editBindings'])->name('locks.bindings.edit');
        Route::put('locks/{lock}/bindings', [AccessLockController::class, 'updateBindings'])->name('locks.bindings.update');
        Route::get('readers/{reader}/lock-bindings', [AccessReaderController::class, 'editLockBindings'])->name('readers.lock-bindings.edit');
        Route::get('readers/{reader}/bindings', [AccessReaderController::class, 'editBindings'])->name('readers.bindings.edit');
        Route::put('readers/{reader}/bindings', [AccessReaderController::class, 'updateBindings'])->name('readers.bindings.update');
        Route::resource('bindings', AccessAdapterBindingController::class)->except(['show']);
        Route::resource('locks', AccessLockController::class);
        Route::resource('readers', AccessReaderController::class);
        Route::get('readers/{reader}/status', [AccessReaderController::class, 'status'])->name('readers.status');
        Route::post('readers/{reader}/toggle-lock', [AccessReaderController::class, 'toggleLock'])->name('readers.toggle-lock');
        Route::post('readers/{reader}/toggle-autolock', [AccessReaderController::class, 'toggleAutolock'])->name('readers.toggle-autolock');
        Route::resource('switches', AccessSwitchController::class)->except(['show']);
        Route::resource('sensors', AccessSensorController::class);
        Route::resource('sources', AccessSourceController::class)->except(['show']);
        Route::post('sources/{accessSource}/test', [AccessSourceController::class, 'testConnection'])->name('sources.test');
    });

    Route::prefix('health')->group(function () {
        Route::get('overview', HealthController::class)->name('health');
    });

    Route::prefix('management')->name('system-')->group(function () {
        Route::resource('users', SystemUserController::class)->except(['show']);
        Route::post('users/{user}/tokens', [SystemUserController::class, 'storeToken'])->name('users.tokens.store');
        Route::delete('users/{user}/tokens/{token}', [SystemUserController::class, 'destroyToken'])->name('users.tokens.destroy');
    });

    Route::prefix('system')->name('system.')->group(function () {
        Route::get('configuration', ConfigurationController::class)->name('configuration');
        Route::post('configuration', [ConfigurationController::class, 'update'])->name('configuration.update');
        Route::get('environment', EnvironmentController::class)->name('environment');
    });
});
