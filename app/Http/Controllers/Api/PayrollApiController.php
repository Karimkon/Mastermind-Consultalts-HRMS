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

    public function process(PayrollRun $payroll)
    {
        if (!request()->user()->hasRole(['super-admin','hr-admin','payroll-officer'])) abort(403);
        if (!in_array($payroll->status, ['draft','failed'])) {
            return response()->json(['message' => 'Already processed.'], 422);
        }
        app(PayrollService::class)->processRun($payroll);
        return response()->json(['data' => ['id' => $payroll->id, 'status' => $payroll->fresh()->status]]);
    }

    public function approve(PayrollRun $payroll)
    {
        if (!request()->user()->hasRole(['super-admin','hr-admin'])) abort(403);
        if ($payroll->status !== 'processed') {
            return response()->json(['message' => 'Must be processed before approval.'], 422);
        }
        $payroll->update([
            'status'      => 'approved',
            'approved_by' => request()->user()->id,
            'approved_at' => now(),
            'locked_at'   => now(),
            'locked_by'   => request()->user()->id,
        ]);
        return response()->json(['data' => ['status' => 'approved']]);
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
