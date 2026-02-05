<?php

use App\Http\Controllers\Ltr\CitationController;
use App\Http\Controllers\Ltr\ComparisonController;
use App\Http\Controllers\Ltr\ExportController;
use App\Http\Controllers\Ltr\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('ltr')->name('ltr.')->group(function () {
    Route::get('/', [ComparisonController::class, 'index'])->name('index');
    Route::get('/table', [ComparisonController::class, 'table'])->name('table');
    Route::get('/comparison', [ComparisonController::class, 'show'])->name('comparison');

    Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::get('/reviews/current', [ReviewController::class, 'current'])->name('reviews.current');
    Route::get('/reviews/history', [ReviewController::class, 'history'])->name('reviews.history');

    Route::get('/exports/comparison.csv', [ExportController::class, 'comparisonCsv'])->name('exports.comparison.csv');
    Route::get('/exports/comparison.xlsx', [ExportController::class, 'comparisonXlsx'])->name('exports.comparison.xlsx');
    Route::get('/exports/wide.xlsx', [ExportController::class, 'comparisonWideXlsx'])->name('exports.wide.xlsx');

    Route::get('/citations/chunk/{chunk}', [CitationController::class, 'showById'])->name('citations.chunk.show');
});
