<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Papers for a meeting.
 *
 * An agenda is of limited use without the document it is about, and people were
 * emailing those round separately — so the version in the room depended on who
 * had read which message.
 *
 * Files live on the PRIVATE disk and are served by a controller that checks the
 * caller is the organizer or an invited participant. A meeting pack is not
 * public, and a guessable URL under public/ would be.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('meeting_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_files');
    }
};
