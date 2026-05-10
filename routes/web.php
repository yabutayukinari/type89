<?php declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', static fn () => Auth::check()
    ? redirect()->route('filament.admin.pages.dashboard')
    : redirect()->route('filament.admin.auth.login'));
