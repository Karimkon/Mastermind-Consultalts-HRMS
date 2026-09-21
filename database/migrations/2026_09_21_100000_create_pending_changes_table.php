<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Changes an account manager has asked for, but which have not happened yet.
 *
 * An account manager edits the records of people they place at a client. Those
 * edits move money — a bank account number, a payment mode, a salary — so they
 * are proposed here and written only when HR approves them. Until then the
 * record on file is unchanged and the system behaves as though the edit does
 * not exist.
 *
 * `original` is the state of those same fields at the moment the change was
 * asked for. It is kept so the reviewer can be shown before and after, and so
 * approval can detect that somebody else has edited the record in the meantime
 * rather than silently overwriting them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_changes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();

            // What is being asked for: 'update', 'create', 'delete', or a named
            // action for things that are not a plain model write.
            $table->string('action', 40)->default('update');
            $table->string('model_type', 100);
            $table->unsignedBigInteger('model_id')->nullable();

            // Who or what the change is about, in words, so a reviewer does not
            // have to resolve an id to know what they are approving.
            $table->string('label')->nullable();

            $table->longText('payload');     // the proposed values
            $table->longText('original_values');    // those fields as they stood

            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            // Lets HR filter a queue by client, and an account manager see only
            // their own.
            $table->unsignedBigInteger('client_id')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['model_type', 'model_id']);
            $table->index('requested_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_changes');
    }
};
