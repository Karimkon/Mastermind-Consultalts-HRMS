<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Client, Employee, LeaveRequest, PayrollRun, AmSalaryPayment};
use Illuminate\Http\Request;

class AccountManagerApiController extends Controller
{
    private function managedClients()
    {
        $q = Client::with(['employees.department', 'employees.designation', 'employees.user']);
        if (!auth()->user()->hasAnyRole(['super-admin', 'hr-admin'])) {
            $q->where('account_manager_id', auth()->id());
        }
        return $q->get();
    }

    private function managedEmployeeIds(?int $clientId = null): array
    {
        $clients = $clientId
            ? $this->managedClients()->where('id', $clientId)
            : $this->managedClients();
        return $clients->flatMap(fn($c) => $c->employees->pluck('id'))->unique()->values()->toArray();
    }

    /* ── Clients list ── */
    public function clients()
    {
        $clients = $this->managedClients()->map(fn($c) => [
            'id'             => $c->id,
            'company_name'   => $c->company_name,
            'status'         => $c->status,
            'employee_count' => $c->employees->count(),
        ]);
        return response()->json($clients);
    }

    /* ── Employees ── */
    public function employees(Request $request)
    {
        $clientId = $request->client_id ? (int) $request->client_id : null;
        $empIds   = $this->managedEmployeeIds($clientId);
        $clients  = $this->managedClients();

        $q = Employee::with(['department', 'designation', 'user', 'salary'])
            ->whereIn('id', $empIds)
            ->when($request->search, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('first_name', 'like', "%{$request->search}%")
                   ->orWhere('last_name',  'like', "%{$request->search}%")
                   ->orWhere('emp_number', 'like', "%{$request->search}%");
            }))
            ->when($request->status, fn($q) => $q->where('status', $request->status));

        $paginated = $q->paginate(20);

        return response()->json([
            'data' => $paginated->map(fn($emp) => [
                'id'          => $emp->id,
                'emp_number'  => $emp->emp_number,
                'full_name'   => $emp->full_name,
                'email'       => $emp->user?->email ?? '',
                'phone'       => $emp->phone ?? '',
                'department'  => $emp->department?->name ?? '—',
                'designation' => $emp->designation?->name ?? '—',
                'status'      => $emp->status,
                'hire_date'   => $emp->hire_date,
                'avatar_url'  => $emp->avatar_url,
                'company'      => $clients->first(fn($c) => $c->employees->contains('id', $emp->id))?->company_name ?? '—',
                'basic_salary' => (float) ($emp->salary?->basic_salary ?? 0),
            ]),
            'total'        => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    /* ── Leaves ── */
    public function leaves(Request $request)
    {
        $clientId = $request->client_id ? (int) $request->client_id : null;
        $empIds   = $this->managedEmployeeIds($clientId);

        $paginated = LeaveRequest::with(['employee.department', 'leaveType'])
            ->whereIn('employee_id', $empIds)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => $paginated->map(fn($l) => [
                'id'               => $l->id,
                'employee_id'      => $l->employee_id,
                'employee'         => $l->employee->full_name,
                'avatar_url'       => $l->employee->avatar_url,
                'department'       => $l->employee->department?->name ?? '—',
                'leave_type'       => $l->leaveType?->name ?? '—',
                'from_date'        => $l->from_date?->format('Y-m-d'),
                'to_date'          => $l->to_date?->format('Y-m-d'),
                'days_count'       => $l->days_count,
                'reason'           => $l->reason,
                'status'           => $l->status,
                'replacement_name' => $l->replacement_name,
            ]),
            'total'        => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    /* ── Approve leave ── */
    public function approveLeave(LeaveRequest $leave)
    {
        abort_unless(in_array($leave->employee_id, $this->managedEmployeeIds()), 403);
        $leave->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        $leave->employee->update(['status' => 'on_leave']);
        return response()->json(['message' => 'Leave approved.', 'status' => 'approved']);
    }

    /* ── Payroll runs ── */
    public function payroll(Request $request)
    {
        $clientId  = $request->client_id ? (int) $request->client_id : null;
        $clientIds = $this->managedClients()->pluck('id')->toArray();

        $paginated = PayrollRun::with('client')
            ->where(function ($q) use ($clientId, $clientIds) {
                if ($clientId) {
                    $q->where('client_id', $clientId)->orWhereNull('client_id');
                } else {
                    $q->whereIn('client_id', $clientIds)->orWhereNull('client_id');
                }
            })
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $paginated->map(fn($r) => [
                'id'             => $r->id,
                'title'          => $r->title,
                'month'          => $r->month,
                'year'           => $r->year,
                'status'         => $r->status,
                'is_locked'      => $r->isLocked(),
                'client_name'    => $r->client?->company_name ?? '—',
                'payment_date'   => $r->payment_date?->format('Y-m-d'),
                'employee_count' => $r->payslips()->count(),
                'total_gross'    => $r->payslips()->sum('gross_salary'),
                'total_net'      => $r->payslips()->sum('net_salary'),
            ]),
            'total'        => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    /* ── Payroll payslips ── */
    public function payrollPayslips(PayrollRun $run)
    {
        $clientIds = $this->managedClients()->pluck('id')->toArray();
        abort_unless(is_null($run->client_id) || in_array($run->client_id, $clientIds), 403);

        $run->load(['payslips.employee.department', 'client']);
        $empIds = $this->managedEmployeeIds($run->client_id);

        $payslips = $run->payslips->whereIn('employee_id', $empIds)->values();

        return response()->json([
            'run' => [
                'id'           => $run->id,
                'title'        => $run->title,
                'month'        => $run->month,
                'year'         => $run->year,
                'status'       => $run->status,
                'is_locked'    => $run->isLocked(),
                'client_name'  => $run->client?->company_name ?? '—',
                'payment_date' => $run->payment_date?->format('Y-m-d'),
            ],
            'payslips' => $payslips->map(fn($p) => [
                'id'               => $p->id,
                'employee'         => $p->employee?->full_name ?? '—',
                'department'       => $p->employee?->department?->name ?? '—',
                'basic_salary'     => $p->basic_salary,
                'total_allowances' => $p->total_allowances,
                'total_deductions' => $p->total_deductions,
                'gross_salary'     => $p->gross_salary,
                'tax_amount'       => $p->tax_amount,
                'net_salary'       => $p->net_salary,
            ]),
            'totals' => [
                'gross' => $payslips->sum('gross_salary'),
                'net'   => $payslips->sum('net_salary'),
                'tax'   => $payslips->sum('tax_amount'),
                'count' => $payslips->count(),
            ],
        ]);
    }

    /* ── Reject leave ── */
    public function rejectLeave(Request $request, LeaveRequest $leave)
    {
        abort_unless(in_array($leave->employee_id, $this->managedEmployeeIds()), 403);
        $leave->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason ?? 'Rejected by Account Manager',
            'approved_by'      => auth()->id(),
            'approved_at'      => now(),
        ]);
        return response()->json(['message' => 'Leave rejected.', 'status' => 'rejected']);
    }

    /* ── Salary Payments ── */
    public function salaryPayments(Request $request)
    {
        $empIds   = $this->managedEmployeeIds();
        $clientId = $request->client_id ? (int) $request->client_id : null;

        $payments = AmSalaryPayment::with(['employee.department', 'client'])
            ->whereIn('employee_id', $empIds)
            ->when($clientId, fn($q) => $q->where('client_id', $clientId))
            ->when($request->month, fn($q) => $q->where('period_month', (int) $request->month))
            ->when($request->year,  fn($q) => $q->where('period_year',  (int) $request->year))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data'  => $payments->map(fn($p) => $this->paymentResource($p)),
            'total' => $payments->total(),
            'page'  => $payments->currentPage(),
            'last_page' => $payments->lastPage(),
            'summary' => [
                'total_gross' => AmSalaryPayment::whereIn('employee_id', $empIds)->sum('gross_salary'),
                'total_net'   => AmSalaryPayment::whereIn('employee_id', $empIds)->sum('net_salary'),
                'count'       => AmSalaryPayment::whereIn('employee_id', $empIds)->count(),
            ],
        ]);
    }

    public function storeSalaryPayment(Request $request)
    {
        $empIds = $this->managedEmployeeIds();
        $request->validate([
            'employee_id'      => 'required|integer|in:' . implode(',', $empIds),
            'client_id'        => 'required|integer',
            'period_month'     => 'required|integer|min:1|max:12',
            'period_year'      => 'required|integer|min:2020|max:2100',
            'basic_salary'     => 'required|numeric|min:0',
            'allowances'       => 'nullable|numeric|min:0',
            'paye_tax'         => 'nullable|numeric|min:0',
            'nssf'             => 'nullable|numeric|min:0',
            'other_deductions' => 'nullable|numeric|min:0',
            'payment_method'   => 'nullable|in:bank_transfer,mobile_money,cash,cheque',
            'payment_reference'=> 'nullable|string|max:255',
            'payment_date'     => 'nullable|date',
            'notes'            => 'nullable|string|max:1000',
        ]);

        $basic    = (float) $request->basic_salary;
        $allow    = (float) ($request->allowances ?? 0);
        $paye     = (float) ($request->paye_tax ?? 0);
        $nssf     = (float) ($request->nssf ?? 0);
        $other    = (float) ($request->other_deductions ?? 0);
        $gross    = $basic + $allow;
        $totalDed = $paye + $nssf + $other;

        $payment = AmSalaryPayment::updateOrCreate(
            [
                'employee_id'  => $request->employee_id,
                'period_month' => $request->period_month,
                'period_year'  => $request->period_year,
            ],
            [
                'client_id'          => $request->client_id,
                'account_manager_id' => auth()->id(),
                'basic_salary'       => $basic,
                'allowances'         => $allow,
                'gross_salary'       => $gross,
                'paye_tax'           => $paye,
                'nssf'               => $nssf,
                'other_deductions'   => $other,
                'total_deductions'   => $totalDed,
                'net_salary'         => $gross - $totalDed,
                'payment_method'     => $request->payment_method,
                'payment_reference'  => $request->payment_reference,
                'payment_date'       => $request->payment_date ?: today()->toDateString(),
                'notes'              => $request->notes,
            ]
        );

        $payment->load(['employee.department', 'client']);
        return response()->json(['message' => 'Payment recorded.', 'payment' => $this->paymentResource($payment)], 201);
    }

    public function deleteSalaryPayment(AmSalaryPayment $payment)
    {
        abort_unless(in_array($payment->employee_id, $this->managedEmployeeIds()), 403);
        $payment->delete();
        return response()->json(['message' => 'Payment deleted.']);
    }

    private function paymentResource(AmSalaryPayment $p): array
    {
        return [
            'id'               => $p->id,
            'employee_id'      => $p->employee_id,
            'employee_name'    => $p->employee?->full_name ?? '—',
            'emp_number'       => $p->employee?->emp_number ?? '—',
            'department'       => $p->employee?->department?->name ?? '—',
            'client_id'        => $p->client_id,
            'client_name'      => $p->client?->company_name ?? '—',
            'period_month'     => $p->period_month,
            'period_year'      => $p->period_year,
            'period_label'     => $p->period_label,
            'basic_salary'     => $p->basic_salary,
            'allowances'       => $p->allowances,
            'gross_salary'     => $p->gross_salary,
            'paye_tax'         => $p->paye_tax,
            'nssf'             => $p->nssf,
            'other_deductions' => $p->other_deductions,
            'total_deductions' => $p->total_deductions,
            'net_salary'       => $p->net_salary,
            'payment_method'   => $p->payment_method,
            'payment_reference'=> $p->payment_reference,
            'payment_date'     => $p->payment_date?->format('Y-m-d'),
            'notes'            => $p->notes,
        ];
    }
}
