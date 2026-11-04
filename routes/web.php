<?php

use App\Http\Controllers\LandingController;
use App\Http\Controllers\Studio\StudioAuthController;
use App\Http\Controllers\Studio\StudioController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/watch/{id}', [LandingController::class, 'watch'])
    ->where('id', '[0-9]+')
    ->name('watch');
Route::get('/shorts/{id}', [LandingController::class, 'short'])
    ->where('id', '[0-9]+')
    ->name('short');
Route::get('/.well-known/assetlinks.json', [LandingController::class, 'wellKnownAssetLinks']);
Route::get('/.well-known/apple-app-site-association', [LandingController::class, 'wellKnownAppleAppSite']);
Route::get('/ref/{code}', [LandingController::class, 'referral'])
    ->where('code', '[A-Za-z0-9]+')
    ->name('referral');
Route::get('/privacy', fn () => redirect('/privacy.html'))->name('privacy');
Route::get('/delete-account', fn () => redirect('/delete-account.html'))->name('delete-account');

Route::prefix('studio')->name('studio.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [StudioAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [StudioAuthController::class, 'login'])->name('login.store');
        Route::get('/register', [StudioAuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [StudioAuthController::class, 'register'])->name('register.store');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [StudioAuthController::class, 'logout'])->name('logout');
        Route::get('/', [StudioController::class, 'index'])->name('index');
        Route::get('/upload', [StudioController::class, 'create'])->name('upload');
        Route::post('/upload', [StudioController::class, 'store'])->name('upload.store');
        Route::post('/upload/initiate', [StudioController::class, 'initiate'])->name('upload.initiate');
        Route::post('/upload/chunk', [StudioController::class, 'chunk'])->name('upload.chunk');
        Route::post('/upload/finalize', [StudioController::class, 'finalize'])->name('upload.finalize');
        Route::delete('/videos/{video}', [StudioController::class, 'destroy'])->name('videos.destroy');
    });
});
