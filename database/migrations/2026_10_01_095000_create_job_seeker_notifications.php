<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notices for job seekers.
 *
 * `notifications.user_id` is NOT NULL against `users`, and a job seeker is
 * deliberately not a user, so the existing bell cannot carry these. A small
 * separate table keeps the two populations apart: no job seeker row can ever
 * be read by an employee query, and no employee notice can leak to the
 * careers app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_seeker_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_seeker_id')->constrained()->cascadeOnDelete();
            $table->string('type', 60);
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->string('action_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['job_seeker_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_seeker_notifications');
    }
};
