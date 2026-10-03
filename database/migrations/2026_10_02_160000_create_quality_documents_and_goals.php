<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quality Management - Phase 3: controlled documents and quality goals.
 *
 * Documents move Initiator -> Editor -> Approver, each stage with its own
 * dates, and once approved they are PUBLISHED to the whole company (every
 * logged-in Mastermind employee can read them). Quality goals mirror the
 * performance Goal Setting flow: an initiator assigns a dated goal, attaches
 * files, and the assignee actions it - with a notification in the bell and
 * the mobile app.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('quality_documents', function (Blueprint $t) {
            $t->id();
            $t->string('doc_number')->unique();          // QD-0001
            $t->string('title');
            $t->string('category')->default('policy');   // policy, procedure, work_instruction, form, manual, record
            $t->text('description')->nullable();
            // draft -> in_review (with editor) -> pending_approval (with approver) -> approved -> published ; or rejected
            $t->string('status')->default('draft');
            $t->foreignId('initiator_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();   // responsible for editing
            $t->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete(); // approves
            $t->date('start_date')->nullable();          // effective from
            $t->date('end_date')->nullable();            // review-by / expires
            $t->timestamp('published_at')->nullable();
            $t->unsignedInteger('current_version')->default(1);
            $t->timestamps();
            $t->index(['status', 'category']);
        });

        // Each uploaded file is a version of the document (latest = current_version).
        Schema::create('quality_document_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quality_document_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->string('path');                          // on the private 'local' disk
            $t->string('original_name');
            $t->string('mime')->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        // The workflow trail - who did what, when: gives documents their timeline.
        Schema::create('quality_document_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quality_document_id')->constrained()->cascadeOnDelete();
            $t->string('action');                        // created, forwarded_to_editor, edited, submitted_for_approval, approved, rejected, published, archived
            $t->foreignId('by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('note')->nullable();
            $t->timestamps();
        });

        Schema::create('quality_goals', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();           // QG-0001
            $t->string('title');
            $t->text('description')->nullable();
            $t->foreignId('initiator_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            // assigned -> in_progress -> completed ; or cancelled. overdue is derived from end_date.
            $t->string('status')->default('assigned');
            $t->timestamp('completed_at')->nullable();
            $t->text('progress_notes')->nullable();
            $t->timestamps();
            $t->index(['assignee_id', 'status']);
        });

        Schema::create('quality_goal_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quality_goal_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('original_name');
            $t->string('mime')->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_goal_files');
        Schema::dropIfExists('quality_goals');
        Schema::dropIfExists('quality_document_events');
        Schema::dropIfExists('quality_document_files');
        Schema::dropIfExists('quality_documents');
    }
};
