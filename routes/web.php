<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
Route::get('/login', fn () => view('app'))->name('login');


Route::post('/login', [LoginController::class, 'login'])->name('login.perform');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout.perform');

// SPA Vue
// SPA Vue (ne doit PAS capturer /api)
Route::get('/{any}', fn () => view('app'))
    ->where('any', '^(?!api).*$');
