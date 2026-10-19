<?php

use App\Http\Controllers\LandingController;
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
