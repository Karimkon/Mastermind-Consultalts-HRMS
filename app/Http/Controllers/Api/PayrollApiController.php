<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{PayrollRun, Payslip, Employee};
use App\Services\PayrollService;
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
                'total_gross' => $r->payslips()->sum('gross_pay'),
                'total_net'   => $r->payslips()->sum('net_pay'),
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
                    'gross'     => $payroll->payslips->sum('gross_pay'),
                    'net'       => $payroll->payslips->sum('net_pay'),
                    'tax'       => $payroll->payslips->sum('tax'),
                    'deductions'=> $payroll->payslips->sum('total_deductions'),
                ],
                'payslips' => $payroll->payslips->map(fn($p) => [
                    'id'          => $p->id,
                    'employee_id' => $p->employee_id,
                    'employee'    => $p->employee?->full_name,
                    'gross_pay'   => $p->gross_pay,
                    'net_pay'     => $p->net_pay,
                    'tax'         => $p->tax,
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

    private function formatPayslip(Payslip $p): array
    {
        return [
            'id'              => $p->id,
            'employee_id'     => $p->employee_id,
            'employee'        => $p->employee?->full_name,
            'period'          => $p->payrollRun?->title,
            'month'           => $p->payrollRun?->month,
            'year'            => $p->payrollRun?->year,
            'gross_pay'       => $p->gross_pay,
            'net_pay'         => $p->net_pay,
            'basic_salary'    => $p->basic_salary,
            'tax'             => $p->tax,
            'total_deductions'=> $p->total_deductions,
            'total_allowances'=> $p->total_allowances,
        ];
    }
}
