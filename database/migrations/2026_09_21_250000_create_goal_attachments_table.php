<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evidence against a goal.
 *
 * A goal is scored at the end of a cycle on whether it was met, and "met" is an
 * assertion until something backs it. This is where the employee puts the report,
 * certificate or signed-off deliverable, and where a manager puts the brief the
 * goal was set from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goal_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('note')->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->index('employee_goal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_attachments');
    }
};
