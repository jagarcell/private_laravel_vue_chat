<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
        * Logic:
        * 1) Create persisted table for direct user-to-user messages.
        * 2) Add sender/recipient foreign keys and read-tracking column.
        * 3) Add participant and unread-oriented indexes for query performance.
        *
     * @return void
     */
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['from_user_id', 'to_user_id', 'created_at'], 'chat_messages_participants_created_at_idx');
            $table->index(['to_user_id', 'read_at'], 'chat_messages_to_user_read_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
        * Logic:
        * 1) Drop chat messages table to rollback schema changes.
        *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
