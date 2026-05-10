<?php declare(strict_types=1);

use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', static fn () => response()->json(['status' => 'ok']))->name('api.health');

Route::post('/login', [AuthController::class, 'login'])->name('api.login');
Route::middleware('auth:web')->group(static function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');
});

Route::prefix('admin')->name('api.admin.')->group(static function (): void {
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login');
    Route::middleware('auth:admin')->group(static function (): void {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AdminAuthController::class, 'me'])->name('me');
    });
});
