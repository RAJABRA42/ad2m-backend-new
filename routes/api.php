<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\MissionController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\DashboardController;

use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::middleware([EnsureFrontendRequestsAreStateful::class, 'auth:sanctum'])->group(function () {

    Route::get('/user', fn (Request $request) => $request->user()->load('roles'));

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/missions', [MissionController::class, 'index']);
    Route::get('/missions/{mission}', [MissionController::class, 'show']);
    Route::post('/missions', [MissionController::class, 'store']);
    Route::put('/missions/{mission}', [MissionController::class, 'update']);
    Route::delete('/missions/{mission}', [MissionController::class, 'destroy']);

    Route::prefix('/missions/{mission}')->group(function () {
        Route::post('/soumettre', [WorkflowController::class, 'soumettre']);
        Route::post('/valider-ch', [WorkflowController::class, 'validerCH']);
        Route::post('/valider-raf', [WorkflowController::class, 'validerRAF']);
        Route::post('/valider-cp', [WorkflowController::class, 'validerCP']);
        Route::post('/payer', [WorkflowController::class, 'payer']);
        Route::post('/commencer', [WorkflowController::class, 'commencer']);
        Route::post('/cloturer', [WorkflowController::class, 'cloturer']);
        Route::post('/rejeter', [WorkflowController::class, 'rejeter']);
    });

});
