<?php declare(strict_types=1);

use App\Http\Controllers\Front\ContactController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::group(['prefix' => 'contact'], function(){
    Route::get('input', [ContactController::class, 'input'])->name('contact_input');
    Route::get('return', [ContactController::class, 'returnInput'])->name('contact_return');
    Route::post('confirm', [ContactController::class, 'confirm'])->name('contact_confirm');
    Route::post('complete', [ContactController::class, 'complete'])->name('contact_complete');
});
