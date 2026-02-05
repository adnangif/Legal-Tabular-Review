<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\Auth\TokenController;
use App\Http\Controllers\Ltr\CitationController;
use App\Http\Controllers\Ltr\ExportController;
use App\Http\Controllers\Ltr\ReviewController;
use App\Http\Controllers\Ltr\ComparisonController;
use App\Http\Controllers\Ltr\DocumentChunkController;
use App\Http\Controllers\Ltr\RunController;

Route::prefix('v1')->group(function () {

    // Auth (public)
    Route::post('/auth/token', [TokenController::class, 'issue']);

    // Auth (protected)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [TokenController::class, 'revoke']);

        Route::get('/auth/me', function (Request $request) {
            return response()->json([
                'id' => $request->user()->id,
                'email' => $request->user()->email,
                'name' => $request->user()->name,
            ]);
        });

        // LTR (protected)
        Route::prefix('ltr')->group(function () {

            // Runs
            Route::post('/runs/execute', [RunController::class, 'execute']);
            Route::get('/runs', [RunController::class, 'index']);
            Route::get('/runs/{run}', [RunController::class, 'show']);
            Route::get('/runs/{run}/results', [RunController::class, 'results']);

            // Comparison
            Route::get('/comparison', [ComparisonController::class, 'show']);

            // Reviews
            Route::post('/reviews', [ReviewController::class, 'store']);
            Route::get('/reviews', [ReviewController::class, 'index']);
            Route::get('/reviews/current', [ReviewController::class, 'current']);
            Route::get('/reviews/history', [ReviewController::class, 'history']);

            // Citations
            Route::get('/citations/chunks/{chunk}', [CitationController::class, 'showById']);
            Route::get('/citations/chunk', [CitationController::class, 'showByUid']);

            // Documents
            Route::get('/documents/{document}/chunks', [DocumentChunkController::class, 'index']);

            // Exports
            Route::get('/exports/comparison.csv', [ExportController::class, 'comparisonCsv']);
            Route::get('/exports/comparison.xlsx', [ExportController::class, 'comparisonXlsx']);
            Route::get('/exports/comparison-wide.xlsx', [ExportController::class, 'comparisonWideXlsx']);
        });
    });
});
