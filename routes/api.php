<?php

use App\Http\Controllers\Api\ChatRequestController;
use App\Http\Controllers\Api\ChatMessageController;
use App\Http\Controllers\Api\ChatRoomController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('api.users.index');
    Route::post('/chat-requests', [ChatRequestController::class, 'send'])->name('api.chat-requests.send');
    Route::post('/chat-requests/respond', [ChatRequestController::class, 'respond'])->name('api.chat-requests.respond');
    Route::post('/chat-requests/close', [ChatRequestController::class, 'close'])->name('api.chat-requests.close');
    Route::post('/chat-message/send', [ChatRequestController::class, 'sendMessage'])->name('api.chat-message.send');
    Route::get('/chat-messages/unread-counts', [ChatMessageController::class, 'unreadCounts'])->name('api.chat-messages.unread-counts');
    Route::get('/chat-messages/conversation/{user}', [ChatMessageController::class, 'conversation'])->name('api.chat-messages.conversation');
    Route::post('/chat-messages/conversation/{user}/read', [ChatMessageController::class, 'markRead'])->name('api.chat-messages.mark-read');
    Route::get('/chat-rooms', [ChatRoomController::class, 'index'])->name('api.chat-rooms.index');
    Route::post('/chat-rooms', [ChatRoomController::class, 'store'])->name('api.chat-rooms.store');
    Route::get('/chat-rooms/invites', [ChatRoomController::class, 'invites'])->name('api.chat-rooms.invites');
    Route::post('/chat-rooms/invites/{inviteId}/respond', [ChatRoomController::class, 'respondInvite'])->name('api.chat-rooms.invites.respond');
    Route::post('/chat-rooms/{chatRoomId}/close', [ChatRoomController::class, 'close'])->name('api.chat-rooms.close');
    Route::post('/chat-rooms/{chatRoomId}/leave', [ChatRoomController::class, 'leave'])->name('api.chat-rooms.leave');
});
