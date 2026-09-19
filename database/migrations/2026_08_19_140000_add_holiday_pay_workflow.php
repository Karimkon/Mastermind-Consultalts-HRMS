<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Public holiday pay, decided in the system rather than assumed.
 *
 * Payroll used to add every public holiday to everyone's worked days
 * automatically. Management wants it approved instead, and wants someone who
 * actually works a holiday paid double. That needs two records:
 *
 *   holiday_pay_approvals — per holiday, per client: do we pay this one at all?
 *   holiday_work          — per holiday, per employee: did this person work it?
 *
 * Also seeds Archbishop Janani Luwum Day (16 February), a gazetted Ugandan
 * public holiday that was missing from the calendar entirely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holiday_pay_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_holiday_id')->constrained()->cascadeOnDelete();
            // Null client = a decision covering everyone (head office staff).
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');   // pending|approved|rejected
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['public_holiday_id', 'client_id'], 'holiday_client_unique');
            $table->index('status');
        });

        Schema::create('holiday_work', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_holiday_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->boolean('worked')->default(true);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['public_holiday_id', 'employee_id'], 'holiday_employee_unique');
        });

        // ── Janani Luwum Day, 16 February, every year already on the calendar ──
        $years = DB::table('public_holidays')->distinct()->pluck('year');
        $rows  = [];
        foreach ($years as $year) {
            $exists = DB::table('public_holidays')
                ->where('year', $year)->where('name', 'Archbishop Janani Luwum Day')->exists();
            if ($exists) continue;

            $rows[] = [
                'date'       => sprintf('%04d-02-16', $year),
                'name'       => 'Archbishop Janani Luwum Day',
                'type'       => 'national',
                'country'    => 'UG',
                'is_paid'    => true,
                'year'       => $year,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($rows) DB::table('public_holidays')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_work');
        Schema::dropIfExists('holiday_pay_approvals');
        DB::table('public_holidays')->where('name', 'Archbishop Janani Luwum Day')->delete();
    }
};
