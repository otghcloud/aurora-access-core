<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\Admin\Access\AccessAreaController;
use App\Http\Controllers\Admin\Access\AccessCardController;
use App\Http\Controllers\Admin\Access\AccessEventController;
use App\Http\Controllers\Admin\Access\AccessUserController;
use App\Http\Controllers\Admin\Access\AreaPermissionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Hardware\AccessAdapterBindingController;
use App\Http\Controllers\Admin\Hardware\AccessLockController;
use App\Http\Controllers\Admin\Hardware\AccessReaderController;
use App\Http\Controllers\Admin\Hardware\AccessSensorController;
use App\Http\Controllers\Admin\Hardware\AccessSourceController;
use App\Http\Controllers\Admin\Hardware\AccessSwitchController;
use App\Http\Controllers\Admin\Health\HealthController;
use App\Http\Controllers\Admin\Management\SystemUserController;
use App\Http\Controllers\Admin\System\ConfigurationController;
use App\Http\Controllers\Admin\System\EnvironmentController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

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
