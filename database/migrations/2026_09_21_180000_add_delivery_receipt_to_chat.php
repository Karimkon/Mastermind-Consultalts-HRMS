<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery receipts.
 *
 * `last_read_at` already said when somebody opened a thread, which is enough
 * for an unread count but not for a receipt: it cannot tell a message that has
 * reached the other person's screen from one still sitting on the server while
 * they are signed out.
 *
 * There is no websocket here, so "delivered" means what it can honestly mean on
 * a polling client: their browser asked for messages at this moment, so
 * anything sent before it is in their hands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->timestamp('last_delivered_at')->nullable()->after('last_read_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropColumn('last_delivered_at');
        });
    }
};
