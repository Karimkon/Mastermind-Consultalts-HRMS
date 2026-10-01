<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every move an application makes, and what the applicant was told about it.
 *
 * Three things need this. The applicant status page shows a trail rather than
 * a single word. The client flow chart needs to know which stages have
 * actually happened. And recording which channels went out is what stops the
 * same "you have been shortlisted" SMS being sent twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);

            // What the applicant was shown, kept as sent: the preset wording
            // may be edited later and the trail should not rewrite itself.
            $table->text('message')->nullable();
            $table->string('channels', 60)->nullable();   // e.g. "app,email,sms"

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['candidate_id', 'created_at']);
        });

        // Seed the trail with where each existing application already stands,
        // so a status page is not blank for the six people who have applied.
        DB::table('candidates')->orderBy('id')->each(function ($row) {
            DB::table('candidate_status_events')->insert([
                'candidate_id' => $row->id,
                'from_status'  => null,
                'to_status'    => $row->status,
                'message'      => 'Application received.',
                'channels'     => null,
                'created_at'   => $row->created_at ?? now(),
                'updated_at'   => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_status_events');
    }
};
