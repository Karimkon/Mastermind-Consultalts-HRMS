<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts for people outside the company who want to apply from the app.
 *
 * Deliberately NOT rows in `users`. Everything in this system keys off
 * `users` - payroll, leave, attendance, the Spatie roles, the employee
 * record - and a stranger who downloads the app to apply for a cleaning job
 * has no business existing in that table. Separate table, separate guard,
 * separate token ability; nothing about a job seeker can reach the HR side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_seekers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->string('location', 120)->nullable();
            $table->string('education_level', 100)->nullable();
            $table->unsignedSmallInteger('experience_years')->nullable();

            // How they want to hear about new jobs in their categories.
            $table->boolean('notify_email')->default(true);
            $table->boolean('notify_sms')->default(true);

            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // The categories a seeker asked to hear about. They choose at
        // registration and can change them afterwards, so this is a plain
        // pivot with nothing but the pair.
        Schema::create('job_category_job_seeker', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_seeker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['job_seeker_id', 'job_category_id'], 'seeker_category_unique');
        });

        // An application can now belong to an account. Still nullable: the
        // public careers page takes applications from people with no account
        // at all, and that must keep working.
        Schema::table('candidates', function (Blueprint $table) {
            $table->foreignId('job_seeker_id')->nullable()->after('job_posting_id')
                ->constrained('job_seekers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_seeker_id');
        });

        Schema::dropIfExists('job_category_job_seeker');
        Schema::dropIfExists('job_seekers');
    }
};
