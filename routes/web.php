<?php

use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/privacy', fn () => redirect('/privacy.html'))->name('privacy');
Route::get('/delete-account', fn () => redirect('/delete-account.html'))->name('delete-account');
