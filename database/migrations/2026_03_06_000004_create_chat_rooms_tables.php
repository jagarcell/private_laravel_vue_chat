<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Logic:
     * 1) Create `chat_rooms` with room metadata and creator foreign key.
     * 2) Create `chat_room_user` pivot for room participants.
     * 3) Add participant uniqueness constraint per room.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('chat_rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->timestamps();

            $table->index(['created_by_user_id'], 'chat_rooms_created_by_user_id_idx');
            $table->index(['created_at'], 'chat_rooms_created_at_idx');
        });

        Schema::create('chat_room_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['chat_room_id', 'user_id'], 'chat_room_user_room_user_unique');
            $table->index(['user_id'], 'chat_room_user_user_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Logic:
     * 1) Drop pivot table before parent table.
     * 2) Drop `chat_rooms`.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_room_user');
        Schema::dropIfExists('chat_rooms');
    }
};
