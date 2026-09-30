<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files exchanged on an improvement plan.
 *
 * A PIP is a conversation with documents in it: HR sends the manual the person
 * is being asked to follow, the person sends back the updated version, and both
 * sit against the objective they belong to rather than in an undifferentiated
 * pile.
 *
 * `objective_index` points at a position in `pips.objectives`, which is a JSON
 * array rather than a table - null means the file belongs to the plan as a
 * whole, which is what a general document or a closing pack is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pip_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pip_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('objective_index')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('note')->nullable();          // the reply that came with it
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->index(['pip_id', 'objective_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pip_attachments');
    }
};
