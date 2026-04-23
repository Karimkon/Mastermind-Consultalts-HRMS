<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->boolean('client_approval_required')->default(false)->after('document_path');
            $table->enum('client_approval_status', ['pending', 'approved', 'rejected'])->nullable()->after('client_approval_required');
            $table->foreignId('client_approved_by')->nullable()->constrained('clients')->nullOnDelete()->after('client_approval_status');
            $table->timestamp('client_actioned_at')->nullable()->after('client_approved_by');
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->enum('client_shortlist_status', ['pending', 'approved', 'rejected'])->nullable()->after('notes');
            $table->foreignId('client_shortlisted_by')->nullable()->constrained('clients')->nullOnDelete()->after('client_shortlist_status');
            $table->text('client_shortlist_notes')->nullable()->after('client_shortlisted_by');
            $table->timestamp('client_actioned_at')->nullable()->after('client_shortlist_notes');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropForeign(['client_approved_by']);
            $table->dropColumn(['client_approval_required', 'client_approval_status', 'client_approved_by', 'client_actioned_at']);
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropForeign(['client_shortlisted_by']);
            $table->dropColumn(['client_shortlist_status', 'client_shortlisted_by', 'client_shortlist_notes', 'client_actioned_at']);
        });
    }
};
