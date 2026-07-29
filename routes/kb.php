<?php

use App\Http\Controllers\KbController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('kb')->name('kb.')->group(function () {
    Route::get('foods', [KbController::class, 'foods'])->name('foods.index');
    Route::get('foods/{food}', [KbController::class, 'food'])->name('foods.show');
    Route::get('exercises', [KbController::class, 'exercises'])->name('exercises.index');
    Route::get('exercises/{exercise}', [KbController::class, 'exercise'])->name('exercises.show');
    Route::get('diseases', [KbController::class, 'diseases'])->name('diseases.index');
    Route::get('diseases/{disease}', [KbController::class, 'disease'])->name('diseases.show');
    Route::get('articles', [KbController::class, 'articles'])->name('articles.index');
    Route::get('articles/{slug}', [KbController::class, 'article'])->name('articles.show');
    Route::get('faqs', [KbController::class, 'faqs'])->name('faqs.index');
});
