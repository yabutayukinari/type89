<?php declare(strict_types=1);

use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\AuctionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BidController;
use App\Http\Controllers\Api\BroadcastTestController;
use Illuminate\Support\Facades\Route;

Route::get('/health', static fn () => response()->json(['status' => 'ok']))->name('api.health');

Route::post('/broadcast-test', BroadcastTestController::class)->name('api.broadcast-test');

Route::get('/auctions', [AuctionController::class, 'index'])->name('api.auctions.index');
Route::get('/auctions/{auction}', [AuctionController::class, 'show'])->name('api.auctions.show');
Route::middleware('auth:web')->group(static function (): void {
    Route::post('/auctions', [AuctionController::class, 'store'])->name('api.auctions.store');
    Route::post('/auctions/{auction}/bids', [BidController::class, 'store'])->name('api.auctions.bids.store');
});

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
