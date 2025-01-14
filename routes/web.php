<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\TikTokController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/tiktok', [SocialAuthController::class, 'redirectToTikTok']);
Route::get('/callback/tiktok', [SocialAuthController::class, 'handleTikTokCallback']);

Route::get('/tiktok/login', [TikTokController::class, 'login']);
Route::get('/tiktok/callback', [TikTokController::class, 'handleCallback'])->name('tiktok.callback');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';