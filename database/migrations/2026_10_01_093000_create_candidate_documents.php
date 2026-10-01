<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The papers an application arrives with.
 *
 * `candidates.resume_path` holds one file and that is all it can ever hold.
 * The brief asks for a CV, academic papers, a passport photo, reference
 * letters, an LC letter and a police letter - several of some kinds - so each
 * file becomes a row. The existing single CV is copied across so an
 * application opened tomorrow shows its CV in the same list as the rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();

            // Kept as a string rather than an enum: the list of papers Uganda
            // asks for changes, and widening an enum on a live table is a
            // migration nobody wants to write twice.
            $table->string('type', 40);
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->timestamps();

            $table->index(['candidate_id', 'type']);
        });

        // Bring the CVs that already exist into the list.
        DB::table('candidates')->whereNotNull('resume_path')->orderBy('id')
            ->each(function ($row) {
                DB::table('candidate_documents')->insert([
                    'candidate_id'  => $row->id,
                    'type'          => 'cv',
                    'path'          => $row->resume_path,
                    'original_name' => basename($row->resume_path),
                    'created_at'    => $row->created_at ?? now(),
                    'updated_at'    => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_documents');
    }
};
