<?php
namespace App\Http\Controllers;

use App\Exports\AmEmployeesExport;
use App\Models\{Client, Employee, EmployeeSalary, LeaveRequest, AttendanceLog, EmployeeDocument, PayrollRun, AmSalaryPayment};
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AccountManagerController extends Controller
{
    /**
     * Get clients managed by the current account manager.
     * Super-admin / hr-admin see all clients.
     */
    private function managedClients()
    {
        $query = Client::with(['employees.department', 'employees.user']);
        if (!auth()->user()->hasAnyRole(['super-admin', 'hr-admin'])) {
            $query->where('account_manager_id', auth()->id());
        }
        return $query->get();
    }

    /** Resolve a single managed client by ID (guards against accessing other AM's clients). */
    private function resolveClient(?int $clientId): ?Client
    {
        if (!$clientId) return null;
        $clients = $this->managedClients();
        return $clients->firstWhere('id', $clientId);
    }

    private function managedEmployeeIds(?int $clientId = null): array
    {
        $clients = $clientId
            ? $this->managedClients()->where('id', $clientId)
            : $this->managedClients();
        return $clients->flatMap(fn($c) => $c->employees->pluck('id'))->unique()->values()->toArray();
    }

    /* ─────────────────────────── Dashboard ─────────────────────────── */

    public function dashboard()
    {
        $clients = $this->managedClients();

        // Per-client stats
        $clientStats = $clients->map(function (Client $client) {
            $empIds = $client->employees->pluck('id')->toArray();
            return [
                'client'        => $client,
                'total'         => count($empIds),
                'present_today' => $empIds
                    ? AttendanceLog::whereIn('employee_id', $empIds)->whereDate('date', today())->where('status', 'present')->count()
                    : 0,
                'on_leave'      => $empIds
                    ? Employee::whereIn('id', $empIds)->where('status', 'on_leave')->count()
                    : 0,
                'pending_leaves'=> $empIds
                    ? LeaveRequest::whereIn('employee_id', $empIds)->where('status', 'pending')->count()
                    : 0,
            ];
        });

        // Aggregate totals
        $totalEmp      = $clientStats->sum('total');
        $todayPresent  = $clientStats->sum('present_today');
        $onLeave       = $clientStats->sum('on_leave');
        $pendingLeaves = $clientStats->sum('pending_leaves');

        return view('account-manager.dashboard', compact(
            'clients', 'clientStats', 'totalEmp', 'onLeave', 'pendingLeaves', 'todayPresent'
        ));
    }

    /* ─────────────────────────── Employees ─────────────────────────── */

    public function employees(Request $request)
    {
        $clients  = $this->managedClients();
        $clientId = $request->client_id ? (int)$request->client_id : null;

        // Validate the requested client belongs to this AM
        if ($clientId && !$clients->contains('id', $clientId)) {
            $clientId = null;
        }

        $empIds = $this->managedEmployeeIds($clientId);

        $employees = Employee::with(['department', 'designation', 'user'])
            ->whereIn('id', $empIds)
            ->when($request->search, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('first_name', 'like', "%{$request->search}%")
                   ->orWhere('last_name',  'like', "%{$request->search}%")
                   ->orWhere('emp_number', 'like', "%{$request->search}%");
            }))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->paginate(25);

        $activeClient = $clientId ? $clients->firstWhere('id', $clientId) : null;

        return view('account-manager.employees', compact('employees', 'clients', 'activeClient', 'clientId'));
    }

    public function showEmployee(Employee $employee)
    {
        abort_unless(in_array($employee->id, $this->managedEmployeeIds()), 403);

        $employee->load(['department', 'designation', 'user', 'documents',
                         'leaveRequests.leaveType', 'attendanceLogs', 'salary']);
        $recentAttendance = $employee->attendanceLogs()->orderByDesc('date')->limit(14)->get();
        $leaveBalance     = $employee->leaveBalances()->with('leaveType')->get();
        $clients          = $this->managedClients();
        $employeeClient   = $clients->first(fn($c) => $c->employees->contains('id', $employee->id));

        return view('account-manager.employee-detail', compact(
            'employee', 'recentAttendance', 'leaveBalance', 'employeeClient'
        ));
    }

    public function updateEmployee(Request $request, Employee $employee)
    {
        abort_unless(in_array($employee->id, $this->managedEmployeeIds()), 403);

        $request->validate([
            'phone'        => 'nullable|string|max:20',
            'address'      => 'nullable|string|max:500',
            'status'       => 'in:active,on_leave,suspended',
            'basic_salary' => 'nullable|numeric|min:0',
        ]);

        $employee->update($request->only(
            'phone', 'address', 'city', 'status',
            'emergency_contact_name', 'emergency_contact_phone',
            'next_of_kin_name', 'next_of_kin_relation', 'next_of_kin_phone', 'next_of_kin_email'
        ));

        if ($request->filled('basic_salary')) {
            $existing = $employee->salary;
            if ($existing) {
                $existing->update(['basic_salary' => $request->basic_salary]);
            } else {
                EmployeeSalary::create([
                    'employee_id'    => $employee->id,
                    'basic_salary'   => $request->basic_salary,
                    'effective_from' => today(),
                    'is_current'     => true,
                    'created_by'     => auth()->id(),
                ]);
            }
        }

        return back()->with('success', 'Employee updated.');
    }

    /* ─────────────────────────── Export / Import ─────────────────────────── */

    public function exportEmployees(Request $request)
    {
        $clientId = $request->client_id ? (int)$request->client_id : null;
        $empIds   = $this->managedEmployeeIds($clientId);

        $employees = Employee::with(['department', 'designation', 'user', 'salary'])
            ->whereIn('id', $empIds)->get();

        $clientLabel = $clientId
            ? ($this->resolveClient($clientId)?->company_name ?? 'client')
            : 'all-companies';

        $filename = 'employees-' . str_replace(' ', '-', strtolower($clientLabel)) . '-' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($employees) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Emp Number', 'First Name', 'Last Name', 'Email', 'Phone',
                'Department', 'Designation', 'Status', 'Hire Date',
                'Basic Salary', 'Address', 'City',
                'Emergency Contact', 'Emergency Phone',
            ]);
            foreach ($employees as $emp) {
                fputcsv($handle, [
                    $emp->emp_number,
                    $emp->first_name,
                    $emp->last_name,
                    $emp->user?->email ?? '',
                    $emp->phone ?? '',
                    $emp->department?->name ?? '',
                    $emp->designation?->name ?? '',
                    $emp->status,
                    $emp->hire_date,
                    $emp->salary?->basic_salary ?? '',
                    $emp->address ?? '',
                    $emp->city ?? '',
                    $emp->emergency_contact_name ?? '',
                    $emp->emergency_contact_phone ?? '',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportEmployeesExcel(Request $request)
    {
        $clientId = $request->client_id ? (int)$request->client_id : null;
        $empIds   = $this->managedEmployeeIds($clientId);

        $employees = Employee::with(['department', 'designation', 'user', 'salary'])
            ->whereIn('id', $empIds)->get();

        $clientLabel = $clientId
            ? ($this->resolveClient($clientId)?->company_name ?? 'client')
            : 'all-companies';

        $filename = 'employees-' . str_replace(' ', '-', strtolower($clientLabel)) . '-' . date('Y-m-d') . '.xlsx';

        return Excel::download(new AmEmployeesExport($employees), $filename);
    }

    public function importTemplate()
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="employee-import-template.csv"',
        ];
        $callback = function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Emp Number', 'Phone', 'Address', 'City', 'Status',
                              'Emergency Contact', 'Emergency Phone']);
            fputcsv($handle, ['EMP001', '+256 700 000 001', '123 Main St', 'Kampala', 'active',
                              'Jane Doe', '+256 700 000 002']);
            fclose($handle);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function importEmployees(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:2048']);

        $empIds  = $this->managedEmployeeIds();
        $handle  = fopen($request->file('csv_file')->getRealPath(), 'r');
        $headers = fgetcsv($handle); // skip header row
        $updated = 0;
        $skipped = 0;
        $errors  = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) continue;
            [$empNumber, $phone, $address, $city, $status, $ecName, $ecPhone] = array_pad($row, 7, null);

            $emp = Employee::whereIn('id', $empIds)->where('emp_number', trim($empNumber))->first();
            if (!$emp) {
                $skipped++;
                $errors[] = "Row skipped: emp_number '{$empNumber}' not in your managed employees.";
                continue;
            }

            $updateData = array_filter([
                'phone'                  => $phone   ? trim($phone)   : null,
                'address'                => $address ? trim($address) : null,
                'city'                   => $city    ? trim($city)    : null,
                'emergency_contact_name' => $ecName  ? trim($ecName)  : null,
                'emergency_contact_phone'=> $ecPhone ? trim($ecPhone) : null,
            ], fn($v) => $v !== null);

            if ($status && in_array(trim($status), ['active', 'on_leave', 'suspended'])) {
                $updateData['status'] = trim($status);
            }

            $emp->update($updateData);
            $updated++;
        }
        fclose($handle);

        $message = "Import complete: {$updated} updated, {$skipped} skipped.";
        if ($errors) $message .= ' Errors: ' . implode(' | ', array_slice($errors, 0, 3));

        return back()->with($skipped > $updated ? 'error' : 'success', $message);
    }

    /* ─────────────────────────── Leaves ─────────────────────────── */

    public function leaves(Request $request)
    {
        $clients  = $this->managedClients();
        $clientId = $request->client_id ? (int)$request->client_id : null;

        if ($clientId && !$clients->contains('id', $clientId)) {
            $clientId = null;
        }

        $empIds = $this->managedEmployeeIds($clientId);

        $leaves = LeaveRequest::with(['employee.department', 'leaveType'])
            ->whereIn('employee_id', $empIds)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(25);

        $activeClient = $clientId ? $clients->firstWhere('id', $clientId) : null;

        return view('account-manager.leaves', compact('leaves', 'clients', 'activeClient', 'clientId'));
    }

    public function approveLeave(LeaveRequest $leave)
    {
        abort_unless(in_array($leave->employee_id, $this->managedEmployeeIds()), 403);
        $leave->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        $leave->employee->update(['status' => 'on_leave']);
        return back()->with('success', 'Leave approved.');
    }

    public function rejectLeave(Request $request, LeaveRequest $leave)
    {
        abort_unless(in_array($leave->employee_id, $this->managedEmployeeIds()), 403);
        $leave->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason,
            'approved_by'      => auth()->id(),
            'approved_at'      => now(),
        ]);
        return back()->with('success', 'Leave rejected.');
    }

    /* ─────────────────────────── Payroll ─────────────────────────── */

    public function payroll(Request $request)
    {
        $clients  = $this->managedClients();
        $clientId = $request->client_id ? (int)$request->client_id : null;

        if ($clientId && !$clients->contains('id', $clientId)) {
            $clientId = null;
        }

        $clientIds = $clients->pluck('id')->toArray();

        // Show runs for this AM's clients AND global runs (client_id NULL) that include their employees
        $runs = PayrollRun::with('client')
            ->where(function ($q) use ($clientId, $clientIds) {
                if ($clientId) {
                    $q->where('client_id', $clientId)->orWhereNull('client_id');
                } else {
                    $q->whereIn('client_id', $clientIds)->orWhereNull('client_id');
                }
            })
            ->latest()
            ->paginate(20);

        $activeClient = $clientId ? $clients->firstWhere('id', $clientId) : null;

        return view('account-manager.payroll', compact('runs', 'clients', 'activeClient', 'clientId'));
    }

    public function payrollMarkPaid(Request $request, PayrollRun $run)
    {
        $clientIds = $this->managedClients()->pluck('id')->toArray();
        abort_unless(is_null($run->client_id) || in_array($run->client_id, $clientIds), 403);

        if (!in_array($run->status, ['approved', 'processed'])) {
            return back()->with('error', 'Only approved or processed payroll runs can be marked as paid.');
        }

        $request->validate([
            'payment_method'    => 'required|in:bank_transfer,mobile_money,cash,cheque',
            'payment_reference' => 'nullable|string|max:255',
            'payment_date'      => 'nullable|date',
        ]);

        $run->update([
            'status'             => 'paid',
            'payment_method'     => $request->payment_method,
            'payment_reference'  => $request->payment_reference,
            'payment_date'       => $request->payment_date ?: today(),
            'paid_by'            => auth()->id(),
            'paid_at'            => now(),
        ]);

        return back()->with('success', 'Payroll marked as paid. Reference: ' . ($request->payment_reference ?: 'N/A'));
    }

    public function payrollShow(PayrollRun $run)
    {
        $clientIds = $this->managedClients()->pluck('id')->toArray();
        // Allow global runs (null client_id) and client-specific runs
        abort_unless(is_null($run->client_id) || in_array($run->client_id, $clientIds), 403);

        $run->load(['payslips.employee.department', 'client']);
        // For global runs, filter to only AM's employees
        $empIds   = $this->managedEmployeeIds($run->client_id);
        $payslips = $run->payslips->whereIn('employee_id', $empIds)->values();

        return view('account-manager.payroll-show', compact('run', 'payslips'));
    }

    /* ─────────────────────────── Client Settings ─────────────────────────── */

    public function clientSettings(Client $client)
    {
        abort_unless(
            auth()->user()->hasAnyRole(['super-admin', 'hr-admin']) ||
            $client->account_manager_id === auth()->id(),
            403
        );
        return view('account-manager.client-settings', compact('client'));
    }

    public function updateClientSettings(Request $request, Client $client)
    {
        abort_unless(
            auth()->user()->hasAnyRole(['super-admin', 'hr-admin']) ||
            $client->account_manager_id === auth()->id(),
            403
        );
        $request->validate([
            'payment_day'       => 'nullable|integer|min:1|max:31',
            'work_site_address' => 'nullable|string|max:255',
            'work_site_lat'     => 'nullable|numeric|between:-90,90',
            'work_site_lng'     => 'nullable|numeric|between:-180,180',
            'geo_fence_radius'  => 'nullable|integer|min:10|max:5000',
        ]);
        $client->update($request->only(
            'payment_day', 'work_site_address', 'work_site_lat', 'work_site_lng', 'geo_fence_radius'
        ));
        return back()->with('success', 'Client settings updated.');
    }

    // ── Salary Payments ──────────────────────────────────────────────────────

    public function salaryPayments(Request $request)
    {
        $clients  = $this->managedClients();
        $empIds   = $this->managedEmployeeIds();
        $clientId = $request->client_id ? (int) $request->client_id : null;
        $month    = $request->month ? (int) $request->month : null;
        $year     = $request->year  ? (int) $request->year  : null;

        $payments = AmSalaryPayment::with(['employee.department', 'client'])
            ->whereIn('employee_id', $empIds)
            ->when($clientId, fn($q) => $q->where('client_id', $clientId))
            ->when($month,    fn($q) => $q->where('period_month', $month))
            ->when($year,     fn($q) => $q->where('period_year', $year))
            ->latest()
            ->paginate(30);

        $employees = Employee::with('department')
            ->whereIn('id', $empIds)->where('status', 'active')->get();

        return view('account-manager.salary-payments', compact('payments', 'clients', 'employees', 'clientId', 'month', 'year'));
    }

    public function createSalaryPayment()
    {
        $clients   = $this->managedClients();
        $empIds    = $this->managedEmployeeIds();
        $employees = Employee::with(['department', 'designation', 'salary'])
            ->whereIn('id', $empIds)->where('status', 'active')->orderBy('first_name')->get();

        $employeeSalaries = $employees->mapWithKeys(fn($e) => [
            $e->id => (float) ($e->salary?->basic_salary ?? 0),
        ]);

        return view('account-manager.salary-payment-form', compact('clients', 'employees', 'employeeSalaries'));
    }

    public function storeSalaryPayment(Request $request)
    {
        $empIds = $this->managedEmployeeIds();
        $request->validate([
            'employee_id'     => 'required|integer|in:' . implode(',', $empIds),
            'client_id'       => 'required|integer',
            'period_month'    => 'required|integer|min:1|max:12',
            'period_year'     => 'required|integer|min:2020|max:2100',
            'basic_salary'    => 'required|numeric|min:0',
            'allowances'      => 'nullable|numeric|min:0',
            'paye_tax'        => 'nullable|numeric|min:0',
            'nssf'            => 'nullable|numeric|min:0',
            'other_deductions'=> 'nullable|numeric|min:0',
            'payment_method'  => 'nullable|in:bank_transfer,mobile_money,cash,cheque',
            'payment_reference'=> 'nullable|string|max:255',
            'payment_date'    => 'nullable|date',
            'notes'           => 'nullable|string|max:1000',
        ]);

        $basic      = (float) $request->basic_salary;
        $allowances = (float) ($request->allowances ?? 0);
        $paye       = (float) ($request->paye_tax ?? 0);
        $nssf       = (float) ($request->nssf ?? 0);
        $other      = (float) ($request->other_deductions ?? 0);
        $gross      = $basic + $allowances;
        $totalDed   = $paye + $nssf + $other;
        $net        = $gross - $totalDed;

        AmSalaryPayment::updateOrCreate(
            [
                'employee_id'  => $request->employee_id,
                'period_month' => $request->period_month,
                'period_year'  => $request->period_year,
            ],
            [
                'client_id'          => $request->client_id,
                'account_manager_id' => auth()->id(),
                'basic_salary'       => $basic,
                'allowances'         => $allowances,
                'gross_salary'       => $gross,
                'paye_tax'           => $paye,
                'nssf'               => $nssf,
                'other_deductions'   => $other,
                'total_deductions'   => $totalDed,
                'net_salary'         => $net,
                'payment_method'     => $request->payment_method,
                'payment_reference'  => $request->payment_reference,
                'payment_date'       => $request->payment_date ?: today(),
                'notes'              => $request->notes,
            ]
        );

        return redirect()->route('account-manager.salary-payments')
            ->with('success', 'Salary payment recorded successfully.');
    }

    public function editSalaryPayment(AmSalaryPayment $payment)
    {
        $empIds = $this->managedEmployeeIds();
        abort_unless(in_array($payment->employee_id, $empIds), 403);

        $clients   = $this->managedClients();
        $employees = Employee::with(['department', 'designation'])
            ->whereIn('id', $empIds)->where('status', 'active')->orderBy('first_name')->get();

        return view('account-manager.salary-payment-form', compact('clients', 'employees', 'payment'));
    }

    public function updateSalaryPayment(Request $request, AmSalaryPayment $payment)
    {
        $empIds = $this->managedEmployeeIds();
        abort_unless(in_array($payment->employee_id, $empIds), 403);

        $request->validate([
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

        $payment->update([
            'basic_salary'     => $basic,
            'allowances'       => $allow,
            'gross_salary'     => $gross,
            'paye_tax'         => $paye,
            'nssf'             => $nssf,
            'other_deductions' => $other,
            'total_deductions' => $totalDed,
            'net_salary'       => $gross - $totalDed,
            'payment_method'   => $request->payment_method,
            'payment_reference'=> $request->payment_reference,
            'payment_date'     => $request->payment_date ?: $payment->payment_date,
            'notes'            => $request->notes,
        ]);

        return redirect()->route('account-manager.salary-payments')
            ->with('success', 'Payment updated successfully.');
    }

    public function deleteSalaryPayment(AmSalaryPayment $payment)
    {
        $empIds = $this->managedEmployeeIds();
        abort_unless(in_array($payment->employee_id, $empIds), 403);
        $payment->delete();
        return back()->with('success', 'Payment record deleted.');
    }
}
