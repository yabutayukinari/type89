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
Route::view('/', 'front.index')->name('home');

Route::group(['prefix' => 'contact'], function () {
    Route::view('input', 'front.contact.input')->name('contact_input');
    Route::post('complete', [ContactController::class, 'complete'])->name('contact_complete');
});
