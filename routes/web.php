<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GolfController;


// トップページ (都道府県選択)
Route::get('/', [GolfController::class, 'index'])->name('golf.index');

// ゴルフ場検索 (都道府県選択後)
Route::get('/search', [GolfController::class, 'search'])->name('golf.search');