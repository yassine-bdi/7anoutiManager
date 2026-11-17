<?php

use App\Http\Controllers\Auth\DashboardAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [DashboardAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [DashboardAuthController::class, 'attemptLogin'])
    ->middleware('throttle:dashboard-login')
    ->name('login.attempt');
Route::post('/logout', [DashboardAuthController::class, 'logout'])->name('logout');

Route::middleware('dashboard.auth')->group(function () {
    Route::view('/', 'app')->name('home');
});
