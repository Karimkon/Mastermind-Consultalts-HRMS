<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('organizer_id');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('location', 200)->nullable();
            $table->string('meeting_url')->nullable();
            $table->enum('type', ['team', 'one_on_one', 'board', 'client', 'training', 'other'])->default('team');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->text('agenda')->nullable();
            $table->text('minutes')->nullable();
            $table->timestamps();
        });

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_id');
            $table->unsignedBigInteger('employee_id');
            $table->enum('rsvp', ['pending', 'accepted', 'declined', 'tentative'])->default('pending');
            $table->boolean('attended')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'employee_id']);
            $table->foreign('meeting_id')->references('id')->on('meetings')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meetings');
    }
};
