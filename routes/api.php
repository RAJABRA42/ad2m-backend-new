<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\WorkflowController;


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user()->load('roles'));

    Route::get('/missions', [MissionController::class, 'index']);
    Route::get('/missions/{mission}', [MissionController::class, 'show']);
    Route::post('/missions', [MissionController::class, 'store']);
    Route::put('/missions/{mission}', [MissionController::class, 'update']);
    Route::delete('/missions/{mission}', [MissionController::class, 'destroy']);

    Route::post('/missions/{mission}/soumettre', [WorkflowController::class, 'soumettre']);
    Route::post('/missions/{mission}/valider-ch', [WorkflowController::class, 'validerCH']);
    Route::post('/missions/{mission}/valider-raf', [WorkflowController::class, 'validerRAF']);
    Route::post('/missions/{mission}/valider-cp', [WorkflowController::class, 'validerCP']);
    Route::post('/missions/{mission}/payer', [WorkflowController::class, 'payer']);
    Route::post('/missions/{mission}/commencer', [WorkflowController::class, 'commencer']);
    Route::post('/missions/{mission}/cloturer', [WorkflowController::class, 'cloturer']);
    Route::post('/missions/{mission}/rejeter', [WorkflowController::class, 'rejeter']);
});
