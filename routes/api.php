<?php

use App\Http\Controllers\Api\OpenCodeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:30,1'])
    ->prefix('opencode')
    ->group(function (): void {
        Route::get('/models', [OpenCodeController::class, 'models'])->name('api.opencode.models');
        Route::post('/process', [OpenCodeController::class, 'store'])->name('api.opencode.process');
        Route::get('/result/{key}', [OpenCodeController::class, 'show'])->name('api.opencode.result');
    });
