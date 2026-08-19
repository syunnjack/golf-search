<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GolfController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SitemapController;

Route::get('/', [GolfController::class, 'index'])->name('golf.index');
Route::get('/search', [GolfController::class, 'search'])->name('golf.search');
Route::get('/golf/{prefectureSlug}', [GolfController::class, 'prefecture'])
    ->whereAlpha('prefectureSlug')
    ->name('golf.prefecture');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::view('/about', 'about')->name('about');

Route::post('/reviews', [ReviewController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('reviews.store');
