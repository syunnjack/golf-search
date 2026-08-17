<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GolfController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SitemapController;

Route::get('/', [GolfController::class, 'index'])->name('golf.index');
Route::get('/search', [GolfController::class, 'search'])->name('golf.search');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', function () {
    $body = "User-agent: *\nDisallow:\n\nSitemap: ".route('sitemap')."\n";

    return response($body, 200, ['Content-Type' => 'text/plain']);
});
Route::view('/about', 'about')->name('about');

Route::post('/reviews', [ReviewController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('reviews.store');
