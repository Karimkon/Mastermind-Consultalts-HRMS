<?php
namespace App\Http\Controllers;

use App\Exports\{BankPaymentExport, KcbEftExport, KcbMtnExport, KcbAirtelExport};
use App\Imports\PayrollManualDaysImport;
use App\Mail\PayslipMail;
use App\Models\{PayrollRun, Payslip, PayrollManualDays, PayrollComment, Employee, SalaryGrade, SalaryComponent, EmployeeSalary, Client};
use App\Services\Payroll\PayrollService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class PayrollController extends Controller
{
    /** MD role can only view payroll and give final approval — block everything else. */
    private function denyMd(): void
    {
        abort_if(auth()->user()->hasRole('md') && !auth()->user()->hasRole('super-admin'), 403, 'MD accounts can only view payroll and give final approval.');
    }

    public function index(Request $request)
    {
        $clients = Client::orderBy('company_name')->get();

        $runs = PayrollRun::with(['processor', 'client'])
            ->withCount('payslips')
            ->when($request->year,      fn($q) => $q->where('year', $request->year))
            ->when($request->client_id, fn($q) => $q->where('client_id', $request->client_id))
            ->when($request->status,    fn($q) => $q->where('status', $request->status))
            ->orderByDesc('year')->orderByDesc('month')
            ->paginate(20);

        return view('payroll.index', compact('runs', 'clients'));
    }

    public function create()
    {
        $this->denyMd();
        $clients = Client::where('status', 'active')->orderBy('company_name')->get();
        return view('payroll.create', compact('clients'));
    }

    public function store(Request $request)
    {
        $this->denyMd();
        $request->validate([
            'month'     => 'required|integer|min:1|max:12',
            'year'      => 'required|integer|min:2020',
            'client_id' => 'nullable|exists:clients,id',
        ]);

        $exists = PayrollRun::where('month', $request->month)
            ->where('year', $request->year)
            ->where('client_id', $request->client_id ?: null)
            ->exists();

        if ($exists) {
            return back()->with('error', 'A payroll run for this period and client already exists.');
        }

        $clientName = $request->client_id
            ? Client::find($request->client_id)->company_name . ' — '
            : '';
        $title = $clientName . date('F', mktime(0, 0, 0, $request->month, 1)) . ' ' . $request->year . ' Payroll';

        $run = PayrollRun::create([
            'title'        => $title,
            'month'        => $request->month,
            'year'         => $request->year,
            'client_id'    => $request->client_id ?: null,
            'status'       => 'draft',
            'processed_by' => auth()->id(),
            'notes'        => $request->notes,
        ]);

        return redirect()->route('payroll.show', $run)->with('success', 'Payroll run created.');
    }

    public function show(PayrollRun $payroll)
    {
        $payroll->load(['payslips.employee.department', 'client', 'processor', 'approver', 'locker']);
        $totals = [
            'count'      => $payroll->payslips->count(),
            'gross'      => $payroll->payslips->sum('gross_salary'),
            'net'        => $payroll->payslips->sum('net_salary'),
            'tax'        => $payroll->payslips->sum('tax_amount'),
            'deductions' => $payroll->payslips->sum('total_deductions'),
            'nssf'          => $payroll->payslips->sum(fn ($slip) => $slip->employeeNssf()),
            'nssf_employer' => $payroll->payslips->sum(fn ($slip) => $slip->employerNssf()),
            'nssf_total'    => $payroll->payslips->sum(fn ($slip) => $slip->totalNssf()),
            'zero_net'      => $payroll->payslips->where('net_salary', '<=', 0)->count(),
            'unpayable'     => $payroll->payslips
                ->filter(fn ($slip) => (float) $slip->net_salary > 0
                    && $slip->employee && $slip->employee->payoutIssue() !== null)
                ->count(),
        ];
        return view('payroll.show', ['payroll' => $payroll, 'totals' => $totals]);
    }

    /**
     * Pre-flight check before the bank files are downloaded.
     * Finance needs to see who cannot be paid — and why — instead of getting a
     * short file and discovering the gap at the bank.
     */
    public function paymentReadiness(PayrollRun $payroll)
    {
        $payroll->load(['payslips.employee', 'client']);

        $rows = $payroll->payslips
            ->filter(fn ($slip) => $slip->employee !== null)
            ->map(fn ($slip) => [
                'slip'      => $slip,
                'employee'  => $slip->employee,
                'channel'   => $slip->employee->paymentChannel(),
                'issue'     => $slip->employee->payoutIssue(),
                'zero_pay'  => (float) $slip->net_salary <= 0,
            ])
            ->sortBy(fn ($r) => [$r['issue'] === null ? 1 : 0, $r['employee']->full_name])
            ->values();

        $byChannel = $rows->groupBy('channel')->map(fn ($g) => [
            'count'     => $g->count(),
            'ready'     => $g->where('issue', null)->where('zero_pay', false)->count(),
            'blocked'   => $g->filter(fn ($r) => $r['issue'] !== null)->count(),
            'zero_pay'  => $g->where('zero_pay', true)->count(),
            'amount'    => $g->filter(fn ($r) => $r['issue'] === null)->sum(fn ($r) => (float) $r['slip']->net_salary),
        ]);

        return view('payroll.payment-readiness', [
            'payroll'    => $payroll,
            'rows'       => $rows,
            'byChannel'  => $byChannel,
            // The debit account is the first column of every KCB file; the bank
            // rejects the upload when it is blank.
            'kcbAccount' => \App\Models\Setting::get('kcb_account_number', ''),
        ]);
    }

    public function edit(PayrollRun $payroll)
    {
        $this->denyMd();
        $clients = Client::where('status', 'active')->orderBy('company_name')->get();
        return view('payroll.edit', ['run' => $payroll, 'clients' => $clients]);
    }

    public function update(Request $request, PayrollRun $payroll)
    {
        $this->denyMd();
        if ($payroll->isLocked()) {
            return back()->with('error', 'This payroll is locked. Only a Super Admin can unlock it.');
        }
        $payroll->update($request->only('month', 'year', 'status', 'client_id', 'notes'));
        return redirect()->route('payroll.show', $payroll)->with('success', 'Updated.');
    }

    public function destroy(PayrollRun $payroll)
    {
        $this->denyMd();
        if ($payroll->isLocked()) {
            return back()->with('error', 'Cannot delete a locked payroll run.');
        }
        $payroll->delete();
        return redirect()->route('payroll.index')->with('success', 'Deleted.');
    }

    /**
     * Stage 1 — Account Manager runs/processes payroll then submits to HR
     */
    public function process(PayrollRun $payroll)
    {
        $this->denyMd();
        if ($payroll->isLocked()) return back()->with('error', 'This payroll is locked.');
        if (in_array($payroll->status, ['hr_approved','finance_approved','md_approved','approved','paid'])) {
            return back()->with('error', 'Payroll is already in approval workflow.');
        }
        app(PayrollService::class)->processRun($payroll);
        $payroll->update([
            'status'       => 'processed',
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);
        return back()->with('success', 'Payroll processed and submitted to HR for approval.');
    }

    /**
     * Stage 2a — HR Review page: select which employees to include
     */
    public function hrReview(PayrollRun $payroll)
    {
        $this->denyMd();
        abort_unless(auth()->user()->hasAnyRole(['super-admin','hr-admin']), 403);
        if ($payroll->status !== 'processed') {
            return back()->with('error', 'Payroll must be submitted to HR before review.');
        }
        $payslips = $payroll->payslips()->with('employee.department')->orderBy('employee_id')->get();
        return view('payroll.hr-review', compact('payroll', 'payslips'));
    }

    /**
     * Stage 2b — HR submits review: mark withheld employees, approve to Finance
     */
    public function hrApprove(PayrollRun $payroll)
    {
        $this->denyMd();
        abort_unless(auth()->user()->hasAnyRole(['super-admin','hr-admin']), 403);
        if ($payroll->status !== 'processed') {
            return back()->with('error', 'Payroll must be submitted to HR before approving.');
        }

        $selectedIds    = request()->input('selected_payslips', []);
        $withheldReason = request()->input('withheld_reason', 'Withheld by HR review.');

        // Mark each payslip as pending (selected) or withheld (deselected)
        $withheld = 0;
        foreach ($payroll->payslips as $slip) {
            if (in_array((string)$slip->id, $selectedIds)) {
                $slip->update(['payment_status' => 'pending', 'withheld_reason' => null, 'withheld_stage' => null]);
            } else {
                $slip->update(['payment_status' => 'withheld', 'withheld_reason' => $withheldReason, 'withheld_stage' => 'hr']);
                $withheld++;
            }
        }

        $payroll->update([
            'status'         => 'hr_approved',
            'hr_approved_by' => auth()->id(),
            'hr_approved_at' => now(),
        ]);

        $msg = 'HR approved. Payroll submitted to Finance.';
        if ($withheld) $msg .= " {$withheld} employee(s) withheld from payment.";
        return redirect()->route('payroll.show', $payroll)->with('success', $msg);
    }

    /**
     * Stage 3a — Finance review page (select/deselect employees)
     */
    public function financeReview(PayrollRun $payroll)
    {
        $this->denyMd();
        abort_unless(auth()->user()->hasAnyRole(['super-admin','payroll-officer']), 403);
        if ($payroll->status !== 'hr_approved') {
            return back()->with('error', 'Payroll must be HR-approved before Finance review.');
        }
        $payslips = $payroll->payslips()->with('employee.department')->orderBy('employee_id')->get();
        return view('payroll.finance-review', compact('payroll', 'payslips'));
    }

    /**
     * Stage 3b — Finance / Payroll Officer approves with selection
     */
    public function financeApprove(PayrollRun $payroll)
    {
        $this->denyMd();
        abort_unless(auth()->user()->hasAnyRole(['super-admin','payroll-officer']), 403);
        if ($payroll->status !== 'hr_approved') {
            return back()->with('error', 'Payroll must be HR-approved before Finance can approve.');
        }

        $selectedIds    = request()->input('selected_payslips', []);
        $withheldReason = request()->input('withheld_reason', 'Withheld at Finance review.');
        $withheld = 0;
        foreach ($payroll->payslips as $slip) {
            if (in_array((string)$slip->id, $selectedIds)) {
                $slip->update(['payment_status' => 'pending', 'withheld_reason' => null, 'withheld_stage' => null]);
            } else {
                $slip->update([
                    'payment_status' => 'withheld',
                    'withheld_reason' => $slip->withheld_reason ?: $withheldReason,
                    'withheld_stage'  => $slip->withheld_stage  ?: 'finance',
                ]);
                $withheld++;
            }
        }

        $payroll->update([
            'status'              => 'finance_approved',
            'finance_approved_by' => auth()->id(),
            'finance_approved_at' => now(),
        ]);

        $msg = 'Finance approved. Payroll submitted to MD for final approval.';
        if ($withheld) $msg .= " {$withheld} employee(s) withheld.";
        return redirect()->route('payroll.show', $payroll)->with('success', $msg);
    }

    /**
     * Stage 4a — MD review page (select/deselect employees)
     */
    public function mdReview(PayrollRun $payroll)
    {
        abort_unless(auth()->user()->hasAnyRole(['super-admin','md']), 403);
        if ($payroll->status !== 'finance_approved') {
            return back()->with('error', 'Payroll must be Finance-approved before MD review.');
        }
        $payslips = $payroll->payslips()->with('employee.department')->orderBy('employee_id')->get();
        return view('payroll.md-review', compact('payroll', 'payslips'));
    }

    /**
     * Stage 4b — MD final approval with selection — auto-locks payroll
     */
    public function approve(PayrollRun $payroll)
    {
        abort_unless(auth()->user()->hasAnyRole(['super-admin', 'md']), 403, 'Only the MD can give final approval.');
        if ($payroll->status !== 'finance_approved') {
            return back()->with('error', 'Payroll must be Finance-approved before MD approval.');
        }

        $selectedIds    = request()->input('selected_payslips', []);
        $withheldReason = request()->input('withheld_reason', 'Withheld at MD review.');
        $withheld = 0;
        foreach ($payroll->payslips as $slip) {
            if (in_array((string)$slip->id, $selectedIds)) {
                $slip->update(['payment_status' => 'pending', 'withheld_reason' => null, 'withheld_stage' => null]);
            } else {
                $slip->update([
                    'payment_status' => 'withheld',
                    'withheld_reason' => $slip->withheld_reason ?: $withheldReason,
                    'withheld_stage'  => $slip->withheld_stage  ?: 'md',
                ]);
                $withheld++;
            }
        }

        $payroll->update([
            'status'        => 'md_approved',
            'md_approved_by'=> auth()->id(),
            'md_approved_at'=> now(),
            'approved_by'   => auth()->id(),
            'approved_at'   => now(),
            'locked_at'     => now(),
            'locked_by'     => auth()->id(),
        ]);

        $msg = 'MD approved. Payroll is now locked. Finance can proceed to Mark as Paid.';
        if ($withheld) $msg .= " {$withheld} employee(s) withheld.";
        return redirect()->route('payroll.show', $payroll)->with('success', $msg);
    }

    /**
     * Send payroll back to previous stage with a reason comment.
     * Each role can only send back from the stage they approve.
     */
    public function sendBack(Request $request, PayrollRun $payroll)
    {
        $request->validate(['comment' => 'required|string|max:1000']);
        $user = auth()->user();

        // Map: current status → status it reverts to, and who is allowed
        $revertMap = [
            'processed'        => ['to' => 'draft',            'roles' => ['super-admin','hr-admin']],
            'hr_approved'      => ['to' => 'processed',        'roles' => ['super-admin','payroll-officer']],
            'finance_approved' => ['to' => 'hr_approved',      'roles' => ['super-admin','md']],
            'md_approved'      => ['to' => 'finance_approved', 'roles' => ['super-admin']],
        ];

        if (!isset($revertMap[$payroll->status])) {
            return back()->with('error', 'This payroll cannot be sent back from its current status.');
        }

        $map = $revertMap[$payroll->status];
        abort_unless($user->hasAnyRole($map['roles']), 403, 'You are not authorised to send back at this stage.');

        $fromStatus = $payroll->status;
        $toStatus   = $map['to'];

        // Record the comment
        PayrollComment::create([
            'payroll_run_id' => $payroll->id,
            'user_id'        => $user->id,
            'action'         => 'sent_back',
            'from_status'    => $fromStatus,
            'to_status'      => $toStatus,
            'comment'        => $request->comment,
        ]);

        // Clear the approval fields for that stage
        $clearFields = match($fromStatus) {
            'hr_approved'      => ['hr_approved_by' => null, 'hr_approved_at' => null],
            'finance_approved' => ['finance_approved_by' => null, 'finance_approved_at' => null],
            'md_approved'      => ['md_approved_by' => null, 'md_approved_at' => null, 'locked_at' => null, 'locked_by' => null],
            default            => [],
        };

        $payroll->update(array_merge(['status' => $toStatus], $clearFields));

        $stageLabels = [
            'draft'            => 'Draft (Account Manager)',
            'processed'        => 'HR Review',
            'hr_approved'      => 'Finance Review',
            'finance_approved' => 'MD Review',
        ];

        return back()->with('success',
            "Payroll sent back to {$stageLabels[$toStatus]}. Reason recorded: \"{$request->comment}\""
        );
    }

    /**
     * Mark as Paid — simple one-click after MD approval.
     * HR already filtered employees during review; just mark all pending as paid.
     */
    public function markPaid(PayrollRun $payroll)
    {
        $this->denyMd();
        abort_unless(auth()->user()->hasAnyRole(['super-admin','payroll-officer']), 403);
        if (!in_array($payroll->status, ['md_approved','approved'])) {
            return back()->with('error', 'Payroll must be MD-approved before marking as paid.');
        }

        // Mark only pending payslips as paid (withheld ones stay withheld)
        $paid = $payroll->payslips()->where('payment_status','pending')->count();
        $payroll->payslips()->where('payment_status','pending')->update(['payment_status' => 'paid']);

        $payroll->update([
            'status'       => 'paid',
            'payment_date' => now()->toDateString(),
            'paid_by'      => auth()->id(),
            'paid_at'      => now(),
        ]);

        $ns = app(NotificationService::class);
        $payroll->payslips()->where('payment_status','paid')->with(['employee.user','payrollRun'])->each(
            fn($slip) => $ns->payrollProcessed($slip)
        );

        // Those notifications are queued; start draining now rather than waiting
        // on cron, so employees hear about the payment promptly.
        \App\Services\QueueRunner::kick();

        $withheld = $payroll->payslips()->where('payment_status','withheld')->count();
        $msg = "{$paid} employees marked as paid. Payment notification emails are being sent now.";
        if ($withheld) $msg .= " {$withheld} withheld employees were not paid or notified.";
        return back()->with('success', $msg);
    }

    /**
     * Lock payroll manually.
     *
     * Only permitted once the MD has approved. Every workflow button on the run is
     * hidden while it is locked, so locking a run that is still mid-approval strands
     * it: nobody but a Super Admin can move it again. MD approval locks the run
     * automatically anyway, which is the only point at which freezing it is correct.
     */
    public function lock(PayrollRun $payroll)
    {
        $this->denyMd();
        if ($payroll->isLocked()) return back()->with('error', 'Already locked.');

        if (!in_array($payroll->status, ['md_approved', 'approved', 'paid'])) {
            return back()->with('error',
                'A payroll run can only be locked after MD approval — locking it now would block the remaining approvals. It locks itself automatically once the MD approves.');
        }

        $payroll->update(['locked_at' => now(), 'locked_by' => auth()->id()]);
        return back()->with('success', 'Payroll run locked successfully.');
    }

    /** Unlock payroll — Super Admin only */
    public function unlock(PayrollRun $payroll)
    {
        abort_unless(auth()->user()->hasRole('super-admin'), 403, 'Only a Super Admin can unlock payroll runs.');
        $payroll->update(['locked_at' => null, 'locked_by' => null]);
        return back()->with('success', 'Payroll run unlocked. Changes are now allowed.');
    }

    /** Export full payroll summary PDF — available after MD approval */
    public function exportPdf(PayrollRun $payroll)
    {
        abort_unless(auth()->user()->can('reports.export'), 403);
        if (!in_array($payroll->status, ['md_approved','approved','paid'])) {
            return back()->with('error', 'Payroll must be MD-approved before downloading.');
        }
        $payroll->load(['payslips.employee.department', 'client', 'processor', 'hrApprover', 'financeApprover', 'mdApprover']);
        $totals = [
            'count'      => $payroll->payslips->count(),
            'gross'      => $payroll->payslips->sum('gross_salary'),
            'net'        => $payroll->payslips->sum('net_salary'),
            'tax'        => $payroll->payslips->sum('tax_amount'),
            'deductions'    => $payroll->payslips->sum('total_deductions'),
            'nssf'          => $payroll->payslips->sum(fn ($slip) => $slip->employeeNssf()),
            'nssf_employer' => $payroll->payslips->sum(fn ($slip) => $slip->employerNssf()),
            'nssf_total'    => $payroll->payslips->sum(fn ($slip) => $slip->totalNssf()),
        ];
        $company = [
            'name'     => \App\Models\Setting::get('company_name', 'Mastermind Consult Ltd'),
            'email'    => \App\Models\Setting::get('company_email', ''),
            'currency' => \App\Models\Setting::get('currency_symbol', 'UGX'),
        ];
        $pdf = Pdf::loadView('payroll.summary-pdf', compact('payroll','totals','company'))->setPaper('a4','landscape');
        return $pdf->download("payroll-{$payroll->year}-{$payroll->month}-summary.pdf");
    }

    /**
     * Export full payroll Excel — employee details, PAYE and both NSSF shares.
     *
     * Available from the Finance review stage onward: Finance has to reconcile the
     * figures in order to approve, so gating this on MD approval left them with
     * only the KCB bank files, which carry no employee or NSSF data.
     */
    public function exportExcel(PayrollRun $payroll)
    {
        abort_unless(auth()->user()->can('reports.export'), 403);
        if (!in_array($payroll->status, ['hr_approved','finance_approved','md_approved','approved','paid'])) {
            return back()->with('error', 'Payroll must be at least HR-approved before downloading.');
        }
        $filename = 'payroll-'.$payroll->year.'-'.str_pad($payroll->month,2,'0',STR_PAD_LEFT).'.xlsx';
        return Excel::download(new \App\Exports\PayrollRunExport($payroll), $filename);
    }

    /** Monthly NSSF contribution schedule (5% employee + 10% employer) for filing. */
    public function exportNssf(PayrollRun $payroll)
    {
        abort_unless(auth()->user()->can('reports.export'), 403);
        if (!in_array($payroll->status, ['processed','hr_approved','finance_approved','md_approved','approved','paid'])) {
            return back()->with('error', 'Payroll must be processed before the NSSF schedule can be produced.');
        }
        $filename = 'nssf-schedule-'.$payroll->year.'-'.str_pad($payroll->month,2,'0',STR_PAD_LEFT).'.xlsx';
        return Excel::download(new \App\Exports\NssfScheduleExport($payroll), $filename);
    }

    // ─── Manual Days Import ───────────────────────────────────────────

    /** Download the template CSV for manual days upload */
    public function manualDaysTemplate(PayrollRun $payroll)
    {
        $this->denyMd();
        $employees = Employee::whereHas('clients', fn($q) => $q->where('clients.id', $payroll->client_id))
            ->orWhere(fn($q) => !$payroll->client_id ? $q : $q->whereRaw('1=0'))
            ->orderBy('last_name')->get();

        $rows = [['emp_number', 'full_name', 'days_worked', 'notes']];
        foreach ($employees as $emp) {
            $rows[] = [$emp->emp_number, trim($emp->last_name . ' ' . $emp->first_name), 0, ''];
        }

        $filename = "manual_days_{$payroll->year}_{$payroll->month}.csv";
        $handle   = fopen('php://temp', 'r+');
        foreach ($rows as $row) fputcsv($handle, $row);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /** Upload CSV/Excel with manual days worked per employee */
    public function importManualDays(Request $request, PayrollRun $payroll)
    {
        $this->denyMd();
        $request->validate(['file' => 'required|file|mimes:csv,xlsx,xls']);

        // Delete existing manual days for this run first
        PayrollManualDays::where('payroll_run_id', $payroll->id)->delete();

        Excel::import(new PayrollManualDaysImport($payroll->id), $request->file('file'));

        $count = PayrollManualDays::where('payroll_run_id', $payroll->id)->count();
        return back()->with('success', "Manual days imported for {$count} employees. Run 'Process' to recalculate.");
    }

    // ─── KCB Bulk Payment Exports ─────────────────────────────────────

    private function requiresApproval(PayrollRun $payroll): bool
    {
        return !in_array($payroll->status, ['md_approved', 'approved', 'paid']);
    }

    public function bankExport(PayrollRun $payroll)
    {
        if (!in_array($payroll->status, ['processed', 'hr_approved', 'finance_approved', 'md_approved', 'approved', 'paid'])) {
            return back()->with('error', 'Payroll must be processed before exporting bank schedule.');
        }
        $filename = 'bank_payment_' . $payroll->year . '_' . str_pad($payroll->month, 2, '0', STR_PAD_LEFT) . '.csv';
        return Excel::download(new BankPaymentExport($payroll), $filename, \Maatwebsite\Excel\Excel::CSV);
    }

    public function kcbEft(PayrollRun $payroll)
    {
        if (!in_array($payroll->status, ['processed', 'hr_approved', 'finance_approved', 'md_approved', 'approved', 'paid'])) {
            return back()->with('error', 'Payroll must be processed before exporting.');
        }
        $slug = $payroll->year . '_' . str_pad($payroll->month, 2, '0', STR_PAD_LEFT);
        return Excel::download(new KcbEftExport($payroll), "KCB_EFT_{$slug}.xlsx");
    }

    public function kcbMtn(PayrollRun $payroll)
    {
        if (!in_array($payroll->status, ['processed', 'hr_approved', 'finance_approved', 'md_approved', 'approved', 'paid'])) {
            return back()->with('error', 'Payroll must be processed before exporting.');
        }
        $slug = $payroll->year . '_' . str_pad($payroll->month, 2, '0', STR_PAD_LEFT);
        return Excel::download(new KcbMtnExport($payroll), "KCB_MTN_{$slug}.xlsx");
    }

    public function kcbAirtel(PayrollRun $payroll)
    {
        if (!in_array($payroll->status, ['processed', 'hr_approved', 'finance_approved', 'md_approved', 'approved', 'paid'])) {
            return back()->with('error', 'Payroll must be processed before exporting.');
        }
        $slug = $payroll->year . '_' . str_pad($payroll->month, 2, '0', STR_PAD_LEFT);
        return Excel::download(new KcbAirtelExport($payroll), "KCB_Airtel_{$slug}.xlsx");
    }

    public function payslips(PayrollRun $payroll)
    {
        $payslips    = $payroll->payslips()->with('employee')->paginate(30);
        $payroll_run = $payroll;
        return view('payroll.payslips', compact('payslips', 'payroll_run'));
    }

    public function payslipPdf(PayrollRun $payroll, Employee $employee)
    {
        $payslip     = Payslip::where('payroll_run_id', $payroll->id)->where('employee_id', $employee->id)->firstOrFail();
        $payslip->load('employee.department', 'employee.designation', 'employee.user');
        $payroll->load('client');
        $payroll_run = $payroll;
        $company     = [
            'name'     => \App\Models\Setting::get('company_name', 'Mastermind Consult Ltd'),
            'email'    => \App\Models\Setting::get('company_email', ''),
            'phone'    => \App\Models\Setting::get('company_phone', '+256 393 215 289'),
            'currency' => \App\Models\Setting::get('currency_symbol', 'UGX'),
        ];
        $logoPath = public_path('images/logo.png');
        $logo     = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;

        $avatar = null;
        $avatarPath = $payslip->employee->user?->avatar;
        if ($avatarPath) {
            $fullPath = storage_path('app/public/' . $avatarPath);
            if (file_exists($fullPath)) {
                $mime   = mime_content_type($fullPath);
                $avatar = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullPath));
            }
        }

        $pdf = Pdf::loadView('payroll.payslip-pdf', compact('payslip', 'payroll_run', 'company', 'logo', 'avatar'))->setPaper('a4');
        return $pdf->download("payslip-{$employee->emp_number}-{$payroll->month}-{$payroll->year}.pdf");
    }

    /** Email a single payslip PDF to the employee */
    public function emailPayslip(PayrollRun $payroll, Employee $employee)
    {
        abort_unless(auth()->user()->hasAnyRole(['super-admin','hr-admin','payroll-officer','account-manager']), 403);

        $payslip = Payslip::where('payroll_run_id', $payroll->id)
            ->where('employee_id', $employee->id)->firstOrFail();
        $payslip->load('employee.department', 'employee.designation', 'employee.user');

        $email = $payslip->employee->user?->email;
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->with('error', "Employee {$employee->full_name} has no valid email address on file.");
        }

        // Sent inline so the sender sees a real success/failure, not a silent queue.
        try {
            Mail::to($email)->send(new PayslipMail($payslip, $payroll));
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', "Could not email {$payslip->employee->full_name}: {$e->getMessage()}");
        }

        return back()->with('success', "Payslip emailed to {$payslip->employee->full_name} ({$email}).");
    }

    /**
     * Bulk email all payslips for a payroll run.
     *
     * Queued rather than sent inline: a run of several hundred employees means as
     * many PDF renders and SMTP round-trips, which would run past the request
     * timeout and leave Finance unsure how many actually went out.
     */
    public function emailAllPayslips(PayrollRun $payroll)
    {
        abort_unless(auth()->user()->hasAnyRole(['super-admin','hr-admin','payroll-officer','account-manager']), 403);

        $payslips = $payroll->payslips()->with(['employee.user','employee.department','employee.designation'])->get();

        $queued = 0; $skipped = 0;
        foreach ($payslips as $payslip) {
            $email = $payslip->employee?->user?->email;
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $skipped++; continue; }
            Mail::to($email)->queue(new PayslipMail($payslip, $payroll));
            $queued++;
        }

        if ($queued) \App\Services\QueueRunner::kick();

        $msg = "{$queued} payslip(s) queued for emailing — they will go out over the next few minutes.";
        if ($skipped) $msg .= " {$skipped} skipped (no valid email address on file).";

        return back()->with('success', $msg);
    }

    // ─── Salary management ───────────────────────────────────────────

    public function salaryIndex(Request $request)
    {
        $this->denyMd();
        $salaries  = EmployeeSalary::with('employee.department')->paginate(25);
        $employees = Employee::where('status', 'active')->with('user')->get();
        return view('payroll.salary.index', compact('salaries', 'employees'));
    }

    public function salaryCreate()
    {
        $this->denyMd();
        $employees  = Employee::where('status', 'active')->with('user')->get();
        $components = SalaryComponent::all();
        $grades     = SalaryGrade::all();
        return view('payroll.salary.create', compact('employees', 'components', 'grades'));
    }

    public function salaryStore(Request $request)
    {
        $this->denyMd();
        $request->validate(['employee_id' => 'required', 'basic_salary' => 'required|numeric', 'effective_from' => 'required|date']);
        EmployeeSalary::updateOrCreate(
            ['employee_id' => $request->employee_id, 'is_current' => true],
            ['basic_salary' => $request->basic_salary, 'salary_type' => $request->salary_type ?? 'monthly', 'components' => $request->components ?? [], 'effective_from' => $request->effective_from, 'is_current' => true]
        );
        return redirect()->route('salary.index')->with('success', 'Salary assigned.');
    }

    public function salaryShow(EmployeeSalary $salary)
    {
        $salary->load('employee');
        return view('payroll.salary.show', compact('salary'));
    }

    public function salaryEdit(EmployeeSalary $salary)
    {
        $this->denyMd();
        $components = SalaryComponent::all();
        return view('payroll.salary.edit', compact('salary', 'components'));
    }

    public function salaryUpdate(Request $request, EmployeeSalary $salary)
    {
        $this->denyMd();
        $salary->update($request->only('basic_salary', 'salary_type', 'components', 'effective_from'));
        return redirect()->route('salary.show', $salary)->with('success', 'Salary updated.');
    }

    public function gradesIndex()
    {
        $grades = SalaryGrade::paginate(20);
        return view('payroll.salary.grades', compact('grades'));
    }

    public function gradesStore(Request $request)
    {
        $this->denyMd();
        $request->validate([
            'grade'     => 'required|string|max:20',
            'label'     => 'nullable|string|max:255',
            'basic_min' => 'required|numeric|min:0',
            // A band whose ceiling sits below its floor cannot hold anybody.
            'basic_max' => 'required|numeric|gte:basic_min',
        ]);

        SalaryGrade::create($request->only('grade', 'label', 'basic_min', 'basic_max') + ['label' => '']);
        return back()->with('success', 'Grade created.');
    }

    public function componentsIndex()
    {
        $components = SalaryComponent::paginate(20);
        return view('payroll.salary.components', compact('components'));
    }

    public function componentsStore(Request $request)
    {
        $this->denyMd();

        // `code` was missing from both the validation and the create. The form has
        // always posted one, `salary_components.code` is NOT NULL with no default,
        // and $request->only() silently dropped it — so every attempt to add a
        // component died on the insert.
        //
        // It is not cosmetic. PayrollService recognises the statutory components by
        // code and by nothing else: `NSSF_EMP` is the employee's 5%, `NSSF_CO` the
        // employer's 10%. A component without one is just another allowance.
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:30|unique:salary_components,code',
            'type' => 'required|in:allowance,deduction',
            'is_taxable' => 'nullable|boolean',
            'is_fixed' => 'nullable|boolean',
            'amount' => 'nullable|numeric|min:0',
            'percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        SalaryComponent::create([
            'name' => $data['name'],
            'code' => mb_strtoupper($data['code']),
            'type' => $data['type'],
            'is_taxable' => $request->boolean('is_taxable'),
            'is_fixed' => $request->boolean('is_fixed'),
            'amount' => $data['amount'] ?? 0,
            'percentage' => $data['percentage'] ?? 0,
            'is_active' => true,
        ]);

        return back()->with('success', 'Component created.');
    }
}
