<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** MNO prefixes (local part, leading 0 stripped) — mirrors App\Models\Employee. */
    private const MTN    = ['76', '77', '78', '79', '39', '31'];
    private const AIRTEL = ['70', '74', '75', '20'];

    public function up(): void
    {
        // ── 1. Collapse the four historical spellings of payment_mode ──────
        // "Bank" (439 rows) and "bank_transfer" (2) never matched the
        // case-sensitive filters in the KCB export classes, so the EFT file
        // came out with headings and no rows.
        DB::table('employees')
            ->whereIn(DB::raw('LOWER(payment_mode)'), ['bank', 'bank_transfer', 'eft', 'bank account'])
            ->update(['payment_mode' => 'bank']);

        DB::table('employees')
            ->where(fn ($q) => $q->whereNull('payment_mode')->orWhere('payment_mode', ''))
            ->update(['payment_mode' => 'bank']);

        DB::table('employees')->whereIn(DB::raw('LOWER(payment_mode)'), ['cash'])->update(['payment_mode' => 'cash']);
        DB::table('employees')->whereIn(DB::raw('LOWER(payment_mode)'), ['cheque', 'check'])->update(['payment_mode' => 'cheque']);

        // ── 2. Resolve legacy "mobile_money" into the actual network ───────
        DB::table('employees')
            ->whereIn(DB::raw('LOWER(payment_mode)'), ['mobile_money', 'momo', 'mobile'])
            ->orderBy('id')
            ->select('id', 'mobile_money_number', 'phone')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $mno = $this->detectMno($row->mobile_money_number ?: $row->phone);
                    if ($mno) {
                        DB::table('employees')->where('id', $row->id)->update(['payment_mode' => $mno]);
                    }
                    // No usable number → leave as 'mobile_money'; the payment
                    // readiness screen reports these so HR can fix the number.
                }
            });

        // ── 3. Store NSSF on the payslip itself ────────────────────────────
        // Previously the only record of NSSF was a needle-in-a-haystack search
        // through the component_details JSON, and the employer's 10% share was
        // not recorded at all.
        Schema::table('payslips', function (Blueprint $table) {
            $table->decimal('employee_nssf', 12, 2)->default(0)->after('tax_amount');
            $table->decimal('employer_nssf', 12, 2)->default(0)->after('employee_nssf');
        });

        // Backfill the employee share from the existing JSON so historical
        // payslips keep showing the right figures.
        DB::table('payslips')->orderBy('id')->select('id', 'component_details')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $details = json_decode($row->component_details ?? '[]', true) ?: [];
                    $employee = 0;
                    foreach ($details as $d) {
                        if (($d['code'] ?? '') === 'NSSF_EMP') { $employee = (float) ($d['amount'] ?? 0); break; }
                    }
                    if ($employee > 0) {
                        DB::table('payslips')->where('id', $row->id)->update([
                            'employee_nssf' => $employee,
                            'employer_nssf' => round($employee * 2, 0),  // 10% employer = 2 × the 5% employee share
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['employee_nssf', 'employer_nssf']);
        });
        // payment_mode normalisation is deliberately not reversed — the old
        // mixed-case values were the bug.
    }

    private function detectMno(?string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $raw);
        $digits = ltrim($digits, '0');
        if (str_starts_with($digits, '256')) $digits = substr($digits, 3);
        if (strlen($digits) !== 9) return null;

        $prefix = substr($digits, 0, 2);
        if (in_array($prefix, self::MTN, true))    return 'mtn';
        if (in_array($prefix, self::AIRTEL, true)) return 'airtel';
        return null;
    }
};
