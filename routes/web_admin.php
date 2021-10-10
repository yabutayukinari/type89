<?php declare(strict_types=1);

use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Admin Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// ユーザー管理
Route::get('/users', [UserController::class, 'index'])->name('admin_user_index');
Route::get('/users/{user}', [UserController::class, 'show'])->name('admin_user_show');
Route::post('/users/{user}', [UserController::class, 'update'])->name('admin_user_update');
