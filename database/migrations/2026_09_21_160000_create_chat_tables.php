<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff messaging: conversations, who is in them, and what was said.
 *
 * Built for this host rather than an ideal one. There is no websocket server
 * and no queue worker, so delivery is by polling and every write is synchronous.
 * That shapes the schema: `last_message_at` on the conversation and
 * `last_read_at` on the participant let a client ask "what changed" cheaply,
 * instead of counting messages on every poll across 900 staff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            // 'direct' is a pair; 'group' is anything larger and carries a name.
            $table->string('type', 20)->default('direct');
            $table->string('name')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Denormalised so a conversation list sorts without touching messages.
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview', 160)->nullable();

            $table->timestamps();

            $table->index('last_message_at');
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Unread count is derived from this, not stored, so it can never
            // drift from the messages themselves.
            $table->timestamp('last_read_at')->nullable();
            $table->boolean('muted')->default(false);

            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // text | image | audio | video | file
            $table->string('type', 12)->default('text');

            // Null for a media message with no caption.
            $table->text('body')->nullable();

            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime', 120)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();

            // Seconds. Lets a voice note show its length before it is fetched.
            $table->unsignedInteger('duration')->nullable();

            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // The one query the thread makes, and the one polling makes.
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
