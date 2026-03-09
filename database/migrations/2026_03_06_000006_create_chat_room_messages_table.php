<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Logic:
     * 1) Create persisted table for room-scoped chat messages.
     * 2) Add room/sender foreign keys and message payload column.
     * 3) Add room+created_at index for ordered room history reads.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('chat_room_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->timestamps();

            $table->index(['chat_room_id', 'created_at'], 'chat_room_messages_room_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Logic:
     * 1) Drop room message table to rollback schema changes.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_room_messages');
    }
};
