<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{PayrollRun, Payslip, Employee, Client};
use App\Services\Payroll\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PayrollApiController extends Controller
{
    public function index()
    {
        $runs = PayrollRun::latest()->paginate(20);
        return response()->json([
            'data' => $runs->through(fn($r) => [
                'id'          => $r->id,
                'title'       => $r->title,
                'month'       => $r->month,
                'year'        => $r->year,
                'status'      => $r->status,
                'employee_count'=> $r->payslips()->count(),
                'total_gross' => $r->payslips()->sum('gross_salary'),
                'total_net'   => $r->payslips()->sum('net_salary'),
                'created_at'  => $r->created_at?->format('Y-m-d'),
            ]),
        ]);
    }

    public function show(PayrollRun $payroll)
    {
        $payroll->load('payslips.employee.user');
        return response()->json([
            'data' => [
                'id'      => $payroll->id,
                'title'   => $payroll->title,
                'month'   => $payroll->month,
                'year'    => $payroll->year,
                'status'  => $payroll->status,
                'totals'  => [
                    'employees' => $payroll->payslips->count(),
                    'gross'     => $payroll->payslips->sum('gross_salary'),
                    'net'       => $payroll->payslips->sum('net_salary'),
                    'tax'       => $payroll->payslips->sum('tax_amount'),
                    'deductions'=> $payroll->payslips->sum('total_deductions'),
                ],
                'payslips' => $payroll->payslips->map(fn($p) => [
                    'id'          => $p->id,
                    'employee_id' => $p->employee_id,
                    'employee'    => $p->employee?->full_name,
                    'gross_pay'   => $p->gross_salary,
                    'net_pay'     => $p->net_salary,
                    'tax'         => $p->tax_amount,
                ]),
            ],
        ]);
    }

    /**
     * Tell the next stage of the chain that the run is now theirs.
     *
     * Mirrors PayrollController::handOver — a control, or a courtesy, that exists
     * on one client and not the other is one that cannot be relied on.
     */
    private function handOver(PayrollRun $payroll, string $stage): void
    {
        try {
            app(\App\Services\NotificationService::class)->payrollAwaitingStage($payroll, $stage);
            \App\Services\QueueRunner::kick();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function process(PayrollRun $payroll)
    {
        if (!request()->user()->hasRole(['super-admin','hr-admin','payroll-officer'])) abort(403);
        if ($payroll->isImported()) {
            return response()->json(['message' => 'This run was imported and records what was actually paid. The system cannot reproduce its figures, so it cannot be re-processed — create a new run instead.'], 422);
        }
        if (!in_array($payroll->status, ['draft','failed'])) {
            return response()->json(['message' => 'Already processed.'], 422);
        }
        app(PayrollService::class)->processRun($payroll);
        $this->handOver($payroll, 'hr');
        return response()->json(['data' => ['id' => $payroll->id, 'status' => $payroll->fresh()->status]]);
    }

    /**
     * Stage 1 of 3 — HR approval.
     *
     * The three stages exist because paying people is the one thing in this system
     * that moves money out of the company, and no single person may do it alone.
     * HR confirms the figures are right, Finance confirms the company can pay them,
     * and the MD releases the money. Three roles, three records, in that order.
     *
     * This endpoint used to be a single `approve` that took a run straight from
     * `processed` to `approved` and locked it, on the say-so of one hr-admin. It
     * skipped Finance and the MD entirely, left `hr_approved_at`,
     * `finance_approved_at` and `md_approved_at` all null, and produced a locked
     * payroll with no approval trail behind it. The web has always enforced the
     * chain; the phone was a way around it.
     */
    public function hrApprove(PayrollRun $payroll)
    {
        if (!request()->user()->hasAnyRole(['super-admin', 'hr-admin'])) {
            abort(403, 'Only HR can give the first approval.');
        }

        if ($payroll->status !== 'processed') {
            return response()->json([
                'message' => 'Payroll must be processed before HR approval.',
            ], 422);
        }

        $payroll->update([
            'status'          => 'hr_approved',
            'hr_approved_by'  => request()->user()->id,
            'hr_approved_at'  => now(),
        ]);

        $this->handOver($payroll, 'finance');

        return response()->json(['data' => ['status' => 'hr_approved']]);
    }

    /** Stage 2 of 3 — Finance approval. A different role from HR, deliberately. */
    public function financeApprove(PayrollRun $payroll)
    {
        if (!request()->user()->hasAnyRole(['super-admin', 'payroll-officer'])) {
            abort(403, 'Only Finance can give the second approval.');
        }

        if ($payroll->status !== 'hr_approved') {
            return response()->json([
                'message' => 'Payroll must be HR-approved before Finance approval.',
            ], 422);
        }

        $payroll->update([
            'status'               => 'finance_approved',
            'finance_approved_by'  => request()->user()->id,
            'finance_approved_at'  => now(),
        ]);

        $this->handOver($payroll, 'md');

        return response()->json(['data' => ['status' => 'finance_approved']]);
    }

    /**
     * Stage 3 of 3 — MD final approval, which locks the run.
     *
     * Locking happens here and only here, exactly as on the web: a run is locked
     * because the MD released it, not because somebody approved something.
     *
     * Withholding individual payslips is intentionally NOT offered on the phone.
     * The web does it by selecting which payslips to release, and a decision to
     * withhold somebody's pay should be taken in front of the full list rather
     * than on a handset.
     */
    public function approve(PayrollRun $payroll)
    {
        if (!request()->user()->hasAnyRole(['super-admin', 'md'])) {
            abort(403, 'Only the MD can give final approval.');
        }

        if ($payroll->status !== 'finance_approved') {
            return response()->json([
                'message' => 'Payroll must be Finance-approved before MD approval.',
            ], 422);
        }

        $payroll->update([
            'status'         => 'md_approved',
            'md_approved_by' => request()->user()->id,
            'md_approved_at' => now(),
            'approved_by'    => request()->user()->id,
            'approved_at'    => now(),
            'locked_at'      => now(),
            'locked_by'      => request()->user()->id,
        ]);

        $this->handOver($payroll, 'payment');

        return response()->json(['data' => ['status' => 'md_approved']]);
    }

    public function payslips(PayrollRun $payroll)
    {
        $payslips = $payroll->payslips()->with('employee.user')->get();
        return response()->json([
            'data' => $payslips->map(fn($p) => $this->formatPayslip($p)),
        ]);
    }

    public function store(Request $request)
    {
        if (!$request->user()->hasRole(['super-admin','hr-admin','payroll-officer'])) abort(403);

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
            return response()->json(['message' => 'A payroll run for this period already exists.'], 422);
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
            'processed_by' => $request->user()->id,
        ]);

        return response()->json(['data' => ['id' => $run->id, 'title' => $run->title, 'status' => $run->status]], 201);
    }

    public function myPayslips(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) return response()->json(['data' => []]);

        $payslips = Payslip::with('payrollRun')
            ->where('employee_id', $employee->id)
            ->latest()->get()
            ->map(fn($p) => $this->formatPayslip($p));

        return response()->json(['data' => $payslips]);
    }

    public function downloadPayslipPdf(Request $request, Payslip $payslip)
    {
        $employee = $request->user()->employee;
        $isHr = $request->user()->hasRole(['super-admin','hr-admin','payroll-officer']);

        if (!$isHr && (!$employee || $employee->id !== $payslip->employee_id)) {
            abort(403);
        }

        $payslip->load(['payrollRun', 'employee.department', 'employee.designation']);
        $company = [
            'name'     => \App\Models\Setting::get('company_name', config('app.name')),
            'address'  => \App\Models\Setting::get('company_address', ''),
            'email'    => \App\Models\Setting::get('company_email', ''),
            'phone'    => \App\Models\Setting::get('company_phone', ''),
            'currency' => \App\Models\Setting::get('currency_symbol', 'UGX'),
        ];

        $pdf = Pdf::loadView('payroll.payslip-pdf', compact('payslip', 'company'))
            ->setPaper('a4');

        return $pdf->download("payslip-{$payslip->id}.pdf");
    }

    public function report(Request $request)
    {
        $month = $request->integer('month', now()->month);
        $year  = $request->integer('year',  now()->year);

        $runs = PayrollRun::with('payslips')
            ->where('month', $month)->where('year', $year)->get();

        $payslips = $runs->flatMap->payslips;

        return response()->json([
            'data' => [
                'month'      => $month,
                'year'       => $year,
                'run_count'  => $runs->count(),
                'total_gross'=> $payslips->sum('gross_salary'),
                'total_net'  => $payslips->sum('net_salary'),
                'total_tax'  => $payslips->sum('tax_amount'),
                'employee_count' => $payslips->count(),
                'runs'       => $runs->map(fn($r) => [
                    'id'     => $r->id,
                    'title'  => $r->title,
                    'status' => $r->status,
                    'count'  => $r->payslips->count(),
                    'gross'  => $r->payslips->sum('gross_salary'),
                    'net'    => $r->payslips->sum('net_salary'),
                ]),
            ],
        ]);
    }

    private function formatPayslip(Payslip $p): array
    {
        return [
            'id'              => $p->id,
            'employee_id'     => $p->employee_id,
            'employee'        => $p->employee?->full_name,
            'period'          => $p->payrollRun?->title,
            'month'           => $p->payrollRun?->month,
            'year'            => $p->payrollRun?->year,
            'gross_pay'       => $p->gross_salary,
            'net_pay'         => $p->net_salary,
            'basic_salary'    => $p->basic_salary,
            'tax'             => $p->tax_amount,
            'total_deductions'=> $p->total_deductions,
            'total_allowances'=> $p->total_allowances,
        ];
    }
}
