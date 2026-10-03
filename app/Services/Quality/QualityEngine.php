<?php

namespace App\Services\Quality;

use App\Models\Employee;
use App\Models\Notification;
use App\Models\Payslip;
use App\Models\QualityCheck;
use App\Models\QualityCheckResult;
use App\Models\QualityCheckRun;
use App\Models\QualityNonconformity;
use App\Models\QualityScoreSnapshot;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The Quality Engine.
 *
 * This is the control layer the module is built around: it reads the live HR
 * data the rest of the system already owns and measures it against the active
 * quality checks. It never changes HR data. Each check reports how many
 * records it scanned and which ones failed; from that the engine produces a
 * weighted quality score per HR function, writes one result row per failure
 * (passes are counted, not stored, so a 1,200-employee scan stays light), and
 * raises - or refreshes - a single non-conformity per failing check so the
 * backlog stays meaningful instead of drowning in one issue per employee.
 *
 * Adding a check is two steps: insert a quality_checks row with an engine_key,
 * and add a matching case to resolve(). Nothing else needs to change.
 */
class QualityEngine
{
    /** Severities at or above which a failing check raises a non-conformity. */
    private const NC_SEVERITIES = ['high', 'critical'];

    /**
     * Run every active check (optionally limited to one HR function) and return
     * the completed run record.
     */
    public function run(?string $scope = null, ?int $userId = null, string $trigger = 'manual'): QualityCheckRun
    {
        $checks = QualityCheck::where('is_active', true)
            ->when($scope && $scope !== 'all', fn ($q) => $q->where('hr_function', $scope))
            ->orderBy('hr_function')
            ->get();

        $run = QualityCheckRun::create([
            'triggered_by' => $userId,
            'trigger' => $trigger,
            'scope' => $scope ?: 'all',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $scanned = 0;
        $passed = 0;
        $failed = 0;
        $warnings = 0;
        $ncRaised = 0;

        // Accumulate per-function weighted pass rates for the scorecard.
        $byFunction = [];

        foreach ($checks as $check) {
            try {
                $outcome = $this->resolve($check);
            } catch (\Throwable $e) {
                // A broken check must never sink the whole run.
                report($e);
                continue;
            }

            $checkScanned = (int) ($outcome['scanned'] ?? 0);
            $failures = $outcome['failures'] ?? [];
            $warns = $outcome['warnings'] ?? [];
            $checkFailed = count($failures);
            $checkWarn = count($warns);

            $scanned += $checkScanned;
            $failed += $checkFailed;
            $warnings += $checkWarn;
            $passed += max(0, $checkScanned - $checkFailed - $checkWarn);

            // Persist failures and warnings as result rows (passes are counted only).
            foreach ($failures as $f) {
                $this->writeResult($run, $check, 'fail', $f);
            }
            foreach ($warns as $w) {
                $this->writeResult($run, $check, 'warning', $w);
            }

            // One pass row per check, so a clean function still shows evidence it ran.
            if ($checkFailed === 0 && $checkWarn === 0) {
                QualityCheckResult::create([
                    'quality_check_run_id' => $run->id,
                    'quality_check_id' => $check->id,
                    'status' => 'pass',
                    'subject_label' => null,
                    'message' => $checkScanned . ' record(s) checked, all conform.',
                ]);
            }

            // Pass rate for the scorecard: warnings count as half a pass.
            $rate = $checkScanned > 0
                ? (($checkScanned - $checkFailed - ($checkWarn * 0.5)) / $checkScanned)
                : 1.0;
            $fn = $check->hr_function;
            $byFunction[$fn] ??= ['weight' => 0, 'weighted' => 0, 'checks' => 0, 'failed' => 0];
            $byFunction[$fn]['weight'] += $check->weight;
            $byFunction[$fn]['weighted'] += $check->weight * $rate;
            $byFunction[$fn]['checks']++;
            $byFunction[$fn]['failed'] += $checkFailed;

            // Raise / refresh a non-conformity when a serious check fails.
            if ($checkFailed > 0 && $check->auto_raise_nc && in_array($check->severity, self::NC_SEVERITIES, true)) {
                if ($this->raiseNonconformity($check, $failures, $userId)) {
                    $ncRaised++;
                }
            }

            $check->forceFill(['last_run_at' => now()])->saveQuietly();
        }

        // Fold per-function weighted rates into percentage scores.
        $summary = [];
        $overallWeight = 0;
        $overallWeighted = 0;
        foreach ($byFunction as $fn => $d) {
            $score = $d['weight'] > 0 ? round($d['weighted'] / $d['weight'] * 100, 2) : 100.0;
            $summary[$fn] = [
                'score' => $score,
                'checks' => $d['checks'],
                'failed' => $d['failed'],
            ];
            $overallWeight += $d['weight'];
            $overallWeighted += $d['weighted'];
        }
        $overall = $overallWeight > 0 ? round($overallWeighted / $overallWeight * 100, 2) : 100.0;

        $run->update([
            'status' => 'completed',
            'checks_run' => $checks->count(),
            'subjects_scanned' => $scanned,
            'passed' => $passed,
            'failed' => $failed,
            'warnings' => $warnings,
            'nonconformities_raised' => $ncRaised,
            'score' => $overall,
            'summary' => $summary,
            'completed_at' => now(),
        ]);

        // Lay down today's point on the quality trend for the Reports screen.
        $this->writeSnapshots($run, $summary, $overall);

        return $run->fresh();
    }

    /**
     * Upsert one snapshot per scope for today: overall, each HR function, and
     * each department. Re-running a scan on the same day updates in place.
     */
    private function writeSnapshots(QualityCheckRun $run, array $summary, float $overall): void
    {
        $today = Carbon::today()->toDateString();
        $openNc = QualityNonconformity::whereIn('status', QualityNonconformity::OPEN_STATES)->count();

        $save = function (string $scope, ?string $key, ?string $label, float $score, int $open, int $failed) use ($today, $run) {
            QualityScoreSnapshot::updateOrCreate(
                ['snapshot_date' => $today, 'scope' => $scope, 'scope_key' => $key],
                ['label' => $label, 'score' => $score, 'open_nc' => $open, 'failed' => $failed, 'quality_check_run_id' => $run->id]
            );
        };

        $save('overall', null, 'Overall', $overall, $openNc, (int) $run->failed);

        foreach ($summary as $fn => $d) {
            $save('function', $fn, ucwords(str_replace('_', ' ', $fn)), (float) ($d['score'] ?? 0), 0, (int) ($d['failed'] ?? 0));
        }

        foreach ($this->departmentScores($run) as $d) {
            $save('department', (string) $d['key'], $d['name'], (float) $d['score'], 0, (int) $d['flagged']);
        }
    }

    /**
     * Department quality for a run: share of each department's active employees
     * with no failing employee-level result. Shared by snapshots and reports.
     *
     * @return array<int,array{key:int|string,name:string,total:int,flagged:int,score:float}>
     */
    public function departmentScores(QualityCheckRun $run): array
    {
        $active = DB::table('employees')
            ->whereNull('deleted_at')->where('status', 'active')
            ->select('department_id', DB::raw('COUNT(*) as total'))
            ->groupBy('department_id')->pluck('total', 'department_id');

        $failing = DB::table('quality_check_results')
            ->join('employees', 'quality_check_results.subject_id', '=', 'employees.id')
            ->where('quality_check_results.quality_check_run_id', $run->id)
            ->where('quality_check_results.status', 'fail')
            ->where('quality_check_results.subject_type', Employee::class)
            ->whereNull('employees.deleted_at')->where('employees.status', 'active')
            ->select('employees.department_id', DB::raw('COUNT(DISTINCT employees.id) as bad'))
            ->groupBy('employees.department_id')->pluck('bad', 'department_id');

        $names = DB::table('departments')->pluck('name', 'id');

        $out = [];
        foreach ($active as $deptId => $total) {
            if (! $total) continue;
            $bad = (int) ($failing[$deptId] ?? 0);
            $out[] = [
                'key' => $deptId ?: 0,
                'name' => $deptId ? ($names[$deptId] ?? 'Dept #' . $deptId) : 'Unassigned',
                'total' => (int) $total,
                'flagged' => $bad,
                'score' => round((1 - $bad / $total) * 100, 1),
            ];
        }
        usort($out, fn ($a, $b) => $a['score'] <=> $b['score']);
        return $out;
    }

    private function writeResult(QualityCheckRun $run, QualityCheck $check, string $status, array $f): void
    {
        QualityCheckResult::create([
            'quality_check_run_id' => $run->id,
            'quality_check_id' => $check->id,
            'status' => $status,
            'subject_type' => $f['subject_type'] ?? null,
            'subject_id' => $f['subject_id'] ?? null,
            'subject_label' => $f['label'] ?? null,
            'message' => $f['message'] ?? null,
            'context' => $f['context'] ?? null,
        ]);
    }

    /**
     * Raise one non-conformity for a failing check, or refresh the open one if
     * it already exists, so the same issue is never logged twice.
     */
    private function raiseNonconformity(QualityCheck $check, array $failures, ?int $userId): bool
    {
        $count = count($failures);
        $sample = collect($failures)->take(8)->pluck('label')->filter()->values()->all();

        $existing = QualityNonconformity::where('quality_check_id', $check->id)
            ->whereIn('status', QualityNonconformity::OPEN_STATES)
            ->first();

        $description = $check->name . ' failed for ' . $count . ' record(s). '
            . ($check->description ? $check->description . ' ' : '')
            . ($sample ? 'Affected: ' . implode('; ', $sample) . ($count > count($sample) ? ' ...' : '') : '');

        if ($existing) {
            $existing->update([
                'description' => $description,
                'severity' => $check->severity,
                'detected_at' => now(),
            ]);
            return false; // refreshed, not newly raised
        }

        $nc = QualityNonconformity::create([
            'reference' => QualityNonconformity::nextReference(),
            'quality_check_id' => $check->id,
            'hr_function' => $check->hr_function,
            'title' => $check->name,
            'description' => $description,
            'severity' => $check->severity,
            'source' => 'auto',
            'status' => 'open',
            'raised_by' => $userId,
            'detected_at' => now(),
        ]);

        $this->alert($nc);

        return true;
    }

    /** Recipients of quality alerts, resolved once per run. */
    private ?array $alertRecipients = null;

    /**
     * Drop a quality alert into the notification bell for the people who own
     * quality. Never allowed to break a scan.
     */
    private function alert(QualityNonconformity $nc): void
    {
        try {
            if ($this->alertRecipients === null) {
                $this->alertRecipients = User::role(['super-admin', 'hr-admin', 'quality-manager'])
                    ->pluck('id')->all();
            }
            if (! $this->alertRecipients) return;

            $url = route('quality.nonconformities.show', $nc->id);
            foreach ($this->alertRecipients as $uid) {
                Notification::create([
                    'user_id' => $uid,
                    'type' => 'quality_alert',
                    'title' => ucfirst($nc->severity) . ' quality issue: ' . $nc->title,
                    'body' => $nc->description,
                    'data' => ['url' => $url, 'reference' => $nc->reference, 'severity' => $nc->severity],
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Map an engine_key to its routine. */
    private function resolve(QualityCheck $check): array
    {
        return match ($check->engine_key) {
            'employee.missing_nssf' => $this->employeeMissing('nssf_number', 'NSSF number'),
            'employee.missing_tin' => $this->employeeMissing('tin_number', 'TIN'),
            'employee.missing_hire_date' => $this->employeeNullColumn('hire_date', 'hire date'),
            'employee.missing_department' => $this->employeeNullColumn('department_id', 'department'),
            'employee.missing_designation' => $this->employeeNullColumn('designation_id', 'designation'),
            'employee.missing_bank' => $this->employeeMissingBank(),
            'employee.missing_contact' => $this->employeeMissingContact(),
            'employee.missing_emergency_contact' => $this->employeeMissingEmergency(),
            'employee.probation_overdue' => $this->employeeProbationOverdue(),
            'payroll.negative_net' => $this->payslipCompare('net_salary', '<', 0, 'Net pay is negative', true),
            'payroll.net_exceeds_gross' => $this->payslipNetExceedsGross(),
            'payroll.zero_gross' => $this->payslipZeroGross(),
            'payroll.inactive_employee_paid' => $this->payslipInactivePaid(),
            'compliance.missing_national_id' => $this->employeeMissing('national_id', 'National ID (NIN)'),
            'compliance.expatriate_no_passport' => $this->expatriateNoPassport(),
            'compliance.contract_expired' => $this->contractExpired(),
            'compliance.contract_expiring' => $this->contractExpiring(),
            default => ['scanned' => 0, 'failures' => []],
        };
    }

    // ----- Compliance checks -------------------------------------------------

    private function expatriateNoPassport(): array
    {
        $base = $this->activeEmployees()->where('is_expatriate', 1)
            ->where(function ($q) {
                $q->whereNull('passport_number')->orWhere('passport_number', '');
            });
        $scanned = (clone $this->activeEmployees())->where('is_expatriate', 1)->count();
        $rows = $base->select('id', 'first_name', 'last_name', 'emp_number')->get();

        return $this->asFailures($rows, fn ($r) => 'Expatriate with no passport number on file', max($scanned, 1));
    }

    private function contractExpired(): array
    {
        $base = $this->activeEmployees()
            ->where('contract_applicable', 1)
            ->whereNotNull('contract_end_date')
            ->whereDate('contract_end_date', '<', Carbon::today());
        $scanned = (clone $this->activeEmployees())->where('contract_applicable', 1)->count();
        $rows = $base->select('id', 'first_name', 'last_name', 'emp_number', 'contract_end_date')->get();

        return $this->asFailures(
            $rows,
            fn ($r) => 'Contract expired ' . Carbon::parse($r->contract_end_date)->format('d M Y') . ' - still active',
            max($scanned, count($rows))
        );
    }

    private function contractExpiring(): array
    {
        $base = $this->activeEmployees()
            ->where('contract_applicable', 1)
            ->whereNotNull('contract_end_date')
            ->whereDate('contract_end_date', '>=', Carbon::today())
            ->whereDate('contract_end_date', '<=', Carbon::today()->addDays(30));
        $scanned = (clone $this->activeEmployees())->where('contract_applicable', 1)->count();
        $rows = $base->select('id', 'first_name', 'last_name', 'emp_number', 'contract_end_date')->get();

        // Expiring soon is a warning, not a breach - it needs attention, not a fault.
        $warnings = [];
        foreach ($rows as $r) {
            $warnings[] = [
                'subject_type' => Employee::class,
                'subject_id' => $r->id,
                'label' => $this->employeeLabel($r),
                'message' => 'Contract expires ' . Carbon::parse($r->contract_end_date)->format('d M Y'),
            ];
        }
        return ['scanned' => max($scanned, count($warnings)), 'failures' => [], 'warnings' => $warnings];
    }

    // ----- Employee Central checks -------------------------------------------

    /** Base query for live, employed people. */
    private function activeEmployees()
    {
        return DB::table('employees')
            ->whereNull('deleted_at')
            ->where('status', 'active');
    }

    private function employeeLabel($row): string
    {
        $name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
        return ($name ?: 'Employee #' . $row->id) . ($row->emp_number ? ' (' . $row->emp_number . ')' : '');
    }

    /** Active employees whose text column is null or blank. */
    private function employeeMissing(string $column, string $human): array
    {
        $scanned = (clone $this->activeEmployees())->count();
        $rows = $this->activeEmployees()
            ->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            })
            ->select('id', 'first_name', 'last_name', 'emp_number')
            ->get();

        return $this->asFailures($rows, fn ($r) => 'Missing ' . $human, $scanned);
    }

    /** Active employees whose column is strictly null (ids, dates). */
    private function employeeNullColumn(string $column, string $human): array
    {
        $scanned = (clone $this->activeEmployees())->count();
        $rows = $this->activeEmployees()
            ->whereNull($column)
            ->select('id', 'first_name', 'last_name', 'emp_number')
            ->get();

        return $this->asFailures($rows, fn ($r) => 'Missing ' . $human, $scanned);
    }

    private function employeeMissingBank(): array
    {
        $scanned = (clone $this->activeEmployees())->where('payment_mode', 'bank')->count();
        $rows = $this->activeEmployees()
            ->where('payment_mode', 'bank')
            ->where(function ($q) {
                $q->whereNull('bank_account')->orWhere('bank_account', '')
                  ->orWhereNull('bank_name')->orWhere('bank_name', '');
            })
            ->select('id', 'first_name', 'last_name', 'emp_number')
            ->get();

        return $this->asFailures($rows, fn ($r) => 'Paid by bank but bank details incomplete', $scanned);
    }

    private function employeeMissingContact(): array
    {
        $scanned = (clone $this->activeEmployees())->count();
        $rows = $this->activeEmployees()
            ->where(function ($q) {
                $q->whereNull('phone')->orWhere('phone', '');
            })
            ->where(function ($q) {
                $q->whereNull('personal_email')->orWhere('personal_email', '');
            })
            ->select('id', 'first_name', 'last_name', 'emp_number')
            ->get();

        return $this->asFailures($rows, fn ($r) => 'No phone and no personal email on record', $scanned);
    }

    private function employeeMissingEmergency(): array
    {
        $scanned = (clone $this->activeEmployees())->count();
        $rows = $this->activeEmployees()
            ->where(function ($q) {
                $q->whereNull('emergency_contact_phone')->orWhere('emergency_contact_phone', '');
            })
            ->where(function ($q) {
                $q->whereNull('next_of_kin_phone')->orWhere('next_of_kin_phone', '');
            })
            ->select('id', 'first_name', 'last_name', 'emp_number')
            ->get();

        return $this->asFailures($rows, fn ($r) => 'No emergency or next-of-kin phone', $scanned);
    }

    private function employeeProbationOverdue(): array
    {
        $base = $this->activeEmployees()
            ->whereNull('end_date')
            ->whereNotNull('probation_end_date')
            ->whereDate('probation_end_date', '<', Carbon::today())
            ->where(function ($q) {
                $q->whereNull('probation_status')
                  ->orWhereNotIn('probation_status', ['confirmed', 'passed']);
            });
        $scanned = (clone $base)->count();
        $rows = $base->select('id', 'first_name', 'last_name', 'emp_number', 'probation_end_date')->get();

        return $this->asFailures(
            $rows,
            fn ($r) => 'Probation ended ' . Carbon::parse($r->probation_end_date)->format('d M Y') . ' but not confirmed',
            // scanned here is only the overdue set; treat denominator as the whole active base so the score reflects reality
            (clone $this->activeEmployees())->count()
        );
    }

    /**
     * Turn a row set into the failure structure, tagging each with the Employee
     * class so the result links back to the record.
     */
    private function asFailures($rows, callable $message, int $scanned): array
    {
        $failures = [];
        foreach ($rows as $r) {
            $failures[] = [
                'subject_type' => Employee::class,
                'subject_id' => $r->id,
                'label' => $this->employeeLabel($r),
                'message' => $message($r),
            ];
        }
        return ['scanned' => max($scanned, count($failures)), 'failures' => $failures];
    }

    // ----- Payroll checks ----------------------------------------------------

    /** Payslips in runs that have left draft - the ones that count. */
    private function livePayslips()
    {
        return DB::table('payslips')
            ->join('payroll_runs', 'payslips.payroll_run_id', '=', 'payroll_runs.id')
            ->leftJoin('employees', 'payslips.employee_id', '=', 'employees.id')
            ->where('payroll_runs.status', '!=', 'draft');
    }

    private function payslipLabel($row): string
    {
        $name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')) ?: 'Employee #' . $row->employee_id;
        return $name . ' - payslip #' . $row->id;
    }

    private function payslipFailures($rows, callable $message, int $scanned): array
    {
        $failures = [];
        foreach ($rows as $r) {
            $failures[] = [
                'subject_type' => Payslip::class,
                'subject_id' => $r->id,
                'label' => $this->payslipLabel($r),
                'message' => $message($r),
            ];
        }
        return ['scanned' => max($scanned, count($failures)), 'failures' => $failures];
    }

    private function payslipCompare(string $column, string $op, $value, string $message): array
    {
        $scanned = (clone $this->livePayslips())->count();
        $rows = $this->livePayslips()
            ->where('payslips.' . $column, $op, $value)
            ->select('payslips.id', 'payslips.employee_id', 'employees.first_name', 'employees.last_name')
            ->get();

        return $this->payslipFailures($rows, fn ($r) => $message, $scanned);
    }

    private function payslipNetExceedsGross(): array
    {
        $scanned = (clone $this->livePayslips())->count();
        $rows = $this->livePayslips()
            ->whereColumn('payslips.net_salary', '>', 'payslips.gross_salary')
            ->select('payslips.id', 'payslips.employee_id', 'payslips.net_salary', 'payslips.gross_salary',
                     'employees.first_name', 'employees.last_name')
            ->get();

        return $this->payslipFailures(
            $rows,
            fn ($r) => 'Net (' . number_format((float) $r->net_salary) . ') exceeds gross (' . number_format((float) $r->gross_salary) . ')',
            $scanned
        );
    }

    private function payslipZeroGross(): array
    {
        $scanned = (clone $this->livePayslips())->count();
        $rows = $this->livePayslips()
            ->where('payslips.gross_salary', '<=', 0)
            ->select('payslips.id', 'payslips.employee_id', 'employees.first_name', 'employees.last_name')
            ->get();

        // Zero gross is a warning, not a hard fail - a run can legitimately prorate to nil.
        $warnings = [];
        foreach ($rows as $r) {
            $warnings[] = [
                'subject_type' => Payslip::class,
                'subject_id' => $r->id,
                'label' => $this->payslipLabel($r),
                'message' => 'Gross pay is zero - verify this is intended',
            ];
        }
        return ['scanned' => max($scanned, count($warnings)), 'failures' => [], 'warnings' => $warnings];
    }

    private function payslipInactivePaid(): array
    {
        $scanned = (clone $this->livePayslips())->count();
        $rows = $this->livePayslips()
            ->where(function ($q) {
                $q->where('employees.status', '!=', 'active')
                  ->orWhereNull('employees.id')
                  ->orWhereNotNull('employees.deleted_at');
            })
            ->select('payslips.id', 'payslips.employee_id', 'employees.first_name', 'employees.last_name', 'employees.status')
            ->get();

        return $this->payslipFailures(
            $rows,
            fn ($r) => 'Payslip for a non-active employee (status: ' . ($r->status ?? 'missing') . ')',
            $scanned
        );
    }
}
