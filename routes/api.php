<?php

use App\Http\Controllers\Api\ChatRequestController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('api.users.index');
    Route::post('/chat-requests', [ChatRequestController::class, 'send'])->name('api.chat-requests.send');
    Route::post('/chat-requests/respond', [ChatRequestController::class, 'respond'])->name('api.chat-requests.respond');
    Route::post('/chat-requests/close', [ChatRequestController::class, 'close'])->name('api.chat-requests.close');
});
