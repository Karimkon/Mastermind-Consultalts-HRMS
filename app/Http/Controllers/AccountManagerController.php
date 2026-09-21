<?php
namespace App\Http\Controllers;

use App\Exports\AmEmployeesExport;
use App\Services\ChangeRequestService;
use App\Models\{Client, Department, Designation, Employee, EmployeeSalary, LeaveRequest, AttendanceLog, EmployeeDocument, PayrollRun, AmSalaryPayment, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            ->when($request->placement, fn($q) => $q->where('department_id', (int) $request->placement));

        // Only the placements these people are actually at. Offering all 48
        // departments would mostly be sites this account manager does not cover,
        // and every one of those choices returns nothing.
        $placements = Department::whereIn(
            'id',
            Employee::whereIn('id', $empIds)->whereNotNull('department_id')->distinct()->pluck('department_id')
        )->orderBy('name')->get();

        // How many to show. Paging 439 people twenty-five at a time is eighteen
        // clicks to reach the end of one client, which is why this was asked for.
        //
        // "All" is a real option rather than a very large number: on the biggest
        // client that is 439 rows, and somebody who asks for all of them usually
        // wants to search the page or print it.
        $perPage = $this->employeesPerPage($request, (clone $employees)->count());

        $employees = $employees->paginate($perPage);

        $activeClient = $clientId ? $clients->firstWhere('id', $clientId) : null;

        return view('account-manager.employees',
            compact('employees', 'clients', 'activeClient', 'clientId', 'placements'));
    }

    /**
     * The page size to use, from ?per_page, bounded to what is on offer.
     *
     * Anything unrecognised falls back to 25 rather than being trusted: a page
     * size arrives in a query string, and `?per_page=100000` on a table this size
     * is a way to make the server do a lot of work on request.
     */
    private function employeesPerPage(Request $request, int $total): int
    {
        $requested = $request->query('per_page');

        if ($requested === 'all') {
            // Never zero — the paginator rejects it — and never unbounded.
            return max(1, min($total, 2000));
        }

        $allowed = [25, 50, 100, 200];

        return in_array((int) $requested, $allowed, true) ? (int) $requested : 25;
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
            'first_name'    => 'required|string|max:100',
            'last_name'     => 'required|string|max:100',
            'status'        => 'in:active,on_leave,suspended,terminated',
            'basic_salary'  => 'nullable|numeric|min:0',
            'date_of_birth' => 'nullable|date',
            'hire_date'     => 'nullable|date',
            'contract_end_date' => 'nullable|date',
            'payment_mode'  => 'nullable|in:bank,mtn,airtel,cash,cheque',
        ]);

        $data = $request->only(
            'title', 'first_name', 'middle_name', 'last_name',
            'date_of_birth', 'gender', 'marital_status', 'nationality', 'religion', 'mother_tongue',
            'phone', 'personal_email', 'address', 'city', 'country',
            'emergency_contact_name', 'emergency_contact_phone',
            'next_of_kin_name', 'next_of_kin_relation', 'next_of_kin_phone', 'next_of_kin_email',
            'status', 'employment_type', 'hire_date', 'contract_end_date', 'work_location',
            'nssf_number', 'tin_number', 'national_id', 'passport_number', 'ifms_supplier_no', 'pension_no',
            'bank_name', 'bank_account', 'bank_branch', 'mobile_money_number',
        );

        // Checkboxes send nothing when unticked, so they are read explicitly
        // rather than taken from only().
        foreach (['charge_paye', 'charge_nssf', 'nssf_paid_by_employer', 'charge_lst', 'tax_paid_by_employer'] as $flag) {
            $data[$flag] = $request->boolean($flag) ? 1 : 0;
        }

        // Only rewrite the payment mode when the form actually submitted one.
        // normalisePaymentMode() falls back to 'bank' for an empty value, so
        // writing it unconditionally would silently switch a mobile-money
        // employee to bank on any save that did not include the field.
        if ($request->filled('payment_mode')) {
            $data['payment_mode'] = Employee::normalisePaymentMode(
                $request->input('payment_mode'),
                $request->input('mobile_money_number') ?: $request->input('phone')
            );
        }

        // An account manager proposes; HR decides. Nothing on the record moves
        // until somebody with HR authority approves it, because these fields
        // move money - a bank account number, a payment mode, a salary.
        if ($this->mustBeApproved()) {
            $pending = app(ChangeRequestService::class)->capture(
                $employee,
                $data,
                $employee->full_name.' ('.$employee->emp_number.')',
                $this->clientIdFor($employee)
            );

            // The salary lives on its own record, so it is proposed separately.
            $salaryPending = $this->captureSalaryChange($request, $employee);

            if (! $pending && ! $salaryPending) {
                return back()->with('success', 'Nothing was changed.');
            }

            return back()->with('success',
                'Sent to HR for approval. Nothing on this record changes until they approve it.');
        }

        $employee->update($data);
        $this->applySalaryChange($request, $employee);

        return back()->with('success', 'Employee profile updated successfully.');
    }

    /**
     * Whether the person signed in has to ask.
     *
     * HR and administrators are the approvers, so they still write directly -
     * routing their own edits into a queue only they can clear would leave
     * nobody able to act.
     */
    private function mustBeApproved(): bool
    {
        return ! auth()->user()->hasAnyRole(['super-admin', 'hr-admin']);
    }

    /** Which client this person is placed with, for the reviewer's queue. */
    private function clientIdFor(Employee $employee): ?int
    {
        return \Illuminate\Support\Facades\DB::table('client_employee_assignments')
            ->where('employee_id', $employee->id)->value('client_id');
    }

    private function captureSalaryChange(Request $request, Employee $employee): ?\App\Models\PendingChange
    {
        if (! $request->filled('basic_salary')) {
            return null;
        }

        $salary = $employee->salary;

        if (! $salary) {
            // There is nothing to compare against, so the proposal carries the
            // whole row and approval creates it.
            $salary = new EmployeeSalary(['employee_id' => $employee->id]);
        }

        return app(ChangeRequestService::class)->capture(
            $salary,
            [
                'basic_salary' => $request->basic_salary,
                'salary_type'  => $request->input('salary_type', 'monthly'),
            ],
            'Salary for '.$employee->full_name.' ('.$employee->emp_number.')',
            $this->clientIdFor($employee)
        );
    }

    private function applySalaryChange(Request $request, Employee $employee): void
    {
        if (! $request->filled('basic_salary')) {
            return;
        }

        $existing = $employee->salary;
        $salaryType = $request->input('salary_type', 'monthly');

        if ($existing) {
            $existing->update(['basic_salary' => $request->basic_salary, 'salary_type' => $salaryType]);
            return;
        }

        EmployeeSalary::create([
            'employee_id'    => $employee->id,
            'basic_salary'   => $request->basic_salary,
            'salary_type'    => $salaryType,
            'effective_from' => today(),
            'is_current'     => true,
            'created_by'     => auth()->id(),
        ]);
    }

    /* ─────────────────────────── Export / Import ─────────────────────────── */

    public function exportEmployees(Request $request)
    {
        $clientId  = $request->client_id ? (int)$request->client_id : null;
        $empIds    = $this->managedEmployeeIds($clientId);
        $employees = Employee::with(['department', 'designation', 'user', 'salary', 'clients'])
            ->whereIn('id', $empIds)->get();

        $clientLabel = $clientId
            ? ($this->resolveClient($clientId)?->company_name ?? 'client')
            : 'all-companies';
        $filename = 'employees-' . str_replace(' ', '-', strtolower($clientLabel)) . '-' . date('Y-m-d') . '.csv';

        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""];

        $callback = function () use ($employees) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Emp Number','Payroll Number','Title','First Name','Middle Name','Last Name',
                'Company / Client','Department','Designation','Sub Department','Position',
                'Organisation Unit','Work Location','Employee Category',
                'Employment Type','Status','Hire Date','End Date','Contract Start','Contract End','Salary Grade',
                'NSSF Number','TIN Number','National ID','Passport Number','IFMS Supplier No','Pension No',
                'Date of Birth','Gender','Marital Status','Nationality','Religion','Mother Tongue',
                'Children Count','Dependents Count',
                'Phone','Personal Email','Address','City','Country',
                'Emergency Contact Name','Emergency Phone',
                'Next of Kin Name','NOK Relation','NOK Phone','NOK Email',
                'Payment Mode','Bank Name','Bank Account','Bank Branch','Mobile Money Number',
                'Basic Salary (UGX)','Salary Type',
                'Charge PAYE','Charge NSSF','NSSF Paid by Employer','Charge LST','Tax Paid by Employer',
                'Is Blacklisted','Blacklist Reason','On Hold','Hold Reason',
            ]);
            foreach ($employees as $emp) {
                $client = $emp->clients->first();
                fputcsv($handle, [
                    $emp->emp_number, $emp->payroll_number ?? '', $emp->title ?? '',
                    $emp->first_name, $emp->middle_name ?? '', $emp->last_name,
                    $client?->company_name ?? '',
                    $emp->department?->name ?? '', $emp->designation?->title ?? '',
                    $emp->sub_department ?? '', $emp->position_name ?? '',
                    $emp->organization_unit ?? '', $emp->work_location ?? '', $emp->employee_category ?? '',
                    ucfirst(str_replace('_',' ',$emp->employment_type ?? '')),
                    ucfirst($emp->status ?? ''),
                    $emp->hire_date ? \Carbon\Carbon::parse($emp->hire_date)->format('d M Y') : '',
                    $emp->end_date ? \Carbon\Carbon::parse($emp->end_date)->format('d M Y') : '',
                    $emp->contract_start_date ? \Carbon\Carbon::parse($emp->contract_start_date)->format('d M Y') : '',
                    $emp->contract_end_date ? \Carbon\Carbon::parse($emp->contract_end_date)->format('d M Y') : '',
                    $emp->salary_grade ?? '',
                    $emp->nssf_number ?? '', $emp->tin_number ?? '', $emp->national_id ?? '',
                    $emp->passport_number ?? '', $emp->ifms_supplier_no ?? '', $emp->pension_no ?? '',
                    $emp->date_of_birth ? \Carbon\Carbon::parse($emp->date_of_birth)->format('d M Y') : '',
                    ucfirst($emp->gender ?? ''),
                    ucfirst(str_replace('_',' ',$emp->marital_status ?? '')),
                    $emp->nationality ?? '', $emp->religion ?? '', $emp->mother_tongue ?? '',
                    $emp->children_count ?? '', $emp->dependents_count ?? '',
                    $emp->phone ?? '', $emp->personal_email ?? $emp->user?->email ?? '',
                    $emp->address ?? '', $emp->city ?? '', $emp->country ?? '',
                    $emp->emergency_contact_name ?? '', $emp->emergency_contact_phone ?? '',
                    $emp->next_of_kin_name ?? '', $emp->next_of_kin_relation ?? '',
                    $emp->next_of_kin_phone ?? '', $emp->next_of_kin_email ?? '',
                    $emp->paymentChannelLabel(),
                    $emp->bank_name ?? '', $emp->bank_account ?? '',
                    $emp->bank_branch ?? '', $emp->mobile_money_number ?? '',
                    $emp->salary?->basic_salary ?? '', ucfirst($emp->salary?->salary_type ?? ''),
                    $emp->charge_paye ? 'Yes':'No', $emp->charge_nssf ? 'Yes':'No',
                    $emp->nssf_paid_by_employer ? 'Yes':'No', $emp->charge_lst ? 'Yes':'No',
                    $emp->tax_paid_by_employer ? 'Yes':'No',
                    $emp->is_blacklisted ? 'Yes':'No', $emp->blacklist_reason ?? '',
                    $emp->on_hold ? 'Yes':'No', $emp->hold_reason ?? '',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportEmployeesExcel(Request $request)
    {
        $clientId  = $request->client_id ? (int)$request->client_id : null;
        $empIds    = $this->managedEmployeeIds($clientId);
        $employees = Employee::with(['department', 'designation', 'user', 'salary', 'clients'])
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

        $empType  = $request->emp_type;   // all, casual, contract
        $statusF  = $request->status_f;   // draft, processed, hr_approved, finance_approved, md_approved, paid

        $runs = PayrollRun::with('client')
            ->where(function ($q) use ($clientId, $clientIds) {
                if ($clientId) {
                    $q->where('client_id', $clientId)->orWhereNull('client_id');
                } else {
                    $q->whereIn('client_id', $clientIds)->orWhereNull('client_id');
                }
            })
            ->when($empType && $empType !== 'all',  fn($q) => $q->where('employment_type', $empType))
            ->when($statusF,                         fn($q) => $q->where('status', $statusF))
            ->latest()
            ->paginate(30);

        $activeClient = $clientId ? $clients->firstWhere('id', $clientId) : null;

        // Aggregate stats for current filter
        $allRuns = PayrollRun::whereIn('client_id', $clientIds)
            ->when($clientId, fn($q) => $q->where('client_id', $clientId))
            ->when($empType && $empType !== 'all', fn($q) => $q->where('employment_type', $empType))
            ->when($statusF, fn($q) => $q->where('status', $statusF));

        $stats = [
            'total_runs'   => (clone $allRuns)->count(),
            'casual_runs'  => PayrollRun::whereIn('client_id', $clientIds)->where('employment_type', 'casual')->count(),
            'contract_runs'=> PayrollRun::whereIn('client_id', $clientIds)->where('employment_type', 'contract')->count(),
            'pending_runs' => PayrollRun::whereIn('client_id', $clientIds)->whereIn('status', ['draft','processed','hr_approved','finance_approved'])->count(),
        ];

        return view('account-manager.payroll', compact('runs', 'clients', 'activeClient', 'clientId', 'empType', 'statusF', 'stats'));
    }

    public function payrollMarkPaid(Request $request, PayrollRun $run)
    {
        $clientIds = $this->managedClients()->pluck('id')->toArray();
        abort_unless(is_null($run->client_id) || in_array($run->client_id, $clientIds), 403);

        if (!in_array($run->status, ['approved', 'processed', 'md_approved'])) {
            return back()->with('error', 'Payroll must be approved before marking as paid.');
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
        abort_unless(is_null($run->client_id) || in_array($run->client_id, $clientIds), 403);

        $run->load(['payslips.employee.department', 'client']);
        $empIds   = $this->managedEmployeeIds($run->client_id);
        $payslips = $run->payslips->whereIn('employee_id', $empIds)->values();

        // Count eligible employees for this run (not yet processed)
        $eligibleCount = \App\Models\Employee::whereIn('status', ['active', 'on_leave'])
            ->where('is_blacklisted', false)
            ->where('on_hold', false)
            ->whereHas('clients', fn($q) => $q->where('clients.id', $run->client_id))
            ->count();

        $manualDaysCount = \App\Models\PayrollManualDays::where('payroll_run_id', $run->id)->count();

        return view('account-manager.payroll-show', compact('run', 'payslips', 'eligibleCount', 'manualDaysCount'));
    }

    public function processPayroll(PayrollRun $run)
    {
        $clientIds = $this->managedClients()->pluck('id')->toArray();
        abort_unless(is_null($run->client_id) || in_array($run->client_id, $clientIds), 403);

        if ($run->isLocked()) {
            return back()->with('error', 'This payroll run is locked.');
        }
        if (!in_array($run->status, ['draft', 'processing'])) {
            return back()->with('error', 'Payroll is already in the approval workflow.');
        }

        app(\App\Services\Payroll\PayrollService::class)->processRun($run);
        $run->update([
            'status'       => 'processed',
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        $count = $run->payslips()->count();
        return back()->with('success', "Payroll processed for {$count} employees and submitted to HR for review.");
    }

    public function manualDaysTemplate(PayrollRun $run)
    {
        $clientIds = $this->managedClients()->pluck('id')->toArray();
        abort_unless(is_null($run->client_id) || in_array($run->client_id, $clientIds), 403);

        $employees = \App\Models\Employee::whereHas('clients', fn($q) => $q->where('clients.id', $run->client_id))
            ->where('status', 'active')
            ->orderBy('last_name')->get();

        $rows = [['emp_number', 'full_name', 'days_worked', 'notes']];
        foreach ($employees as $emp) {
            // Pre-fill existing manual days if any
            $existing = \App\Models\PayrollManualDays::where('payroll_run_id', $run->id)
                ->where('employee_id', $emp->id)->first();
            $rows[] = [$emp->emp_number, trim($emp->last_name . ' ' . $emp->first_name), $existing?->days_worked ?? 0, ''];
        }

        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) fputcsv($handle, $row);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = "manual_days_{$run->year}_{$run->month}.csv";
        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function importManualDays(\Illuminate\Http\Request $request, PayrollRun $run)
    {
        $clientIds = $this->managedClients()->pluck('id')->toArray();
        abort_unless(is_null($run->client_id) || in_array($run->client_id, $clientIds), 403);

        $request->validate(['file' => 'required|file|mimes:csv,xlsx,xls']);
        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\PayrollManualDaysImport($run->id), $request->file('file'));

        $count = \App\Models\PayrollManualDays::where('payroll_run_id', $run->id)->count();
        return back()->with('success', "Manual days imported for {$count} employees.");
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

    /**
     * Add a premises to a client.
     *
     * A client can be several places. Roofings runs Lubowa and Industrial Area
     * about ten kilometres apart, and the single work_site_lat/lng pair could
     * only ever describe one of them -- so whichever were set, the other
     * workforce would be refused at their own gate.
     */
    public function storeClientSite(Request $request, Client $client)
    {
        $this->authoriseClientSettings($client);

        $data = $request->validate([
            'name'             => 'required|string|max:100',
            'address'          => 'nullable|string|max:255',
            'lat'              => 'required|numeric|between:-90,90',
            'lng'              => 'required|numeric|between:-180,180',
            // Twenty-five metres is already close to a phone's own GPS error, so
            // anything tighter would refuse people genuinely standing there.
            'geo_fence_radius' => 'required|integer|min:25|max:5000',
        ]);

        $client->sites()->create($data + ['is_active' => true]);

        return back()->with('success', $data['name'] . ' added. Staff can now clock in there.');
    }

    /**
     * Retire a site, or bring it back. Never delete one.
     *
     * A site that has been clocked into is part of the history of somebody's
     * attendance, and removing it would leave those records pointing at nothing.
     */
    public function retireClientSite(Client $client, \App\Models\ClientSite $site)
    {
        $this->authoriseClientSettings($client);
        abort_unless($site->client_id === $client->id, 404);

        $site->update(['is_active' => ! $site->is_active]);

        return back()->with('success', $site->name . ($site->is_active
            ? ' is active again.'
            : ' retired. Staff can no longer clock in there.'));
    }

    private function authoriseClientSettings(Client $client): void
    {
        abort_unless(
            auth()->user()->hasAnyRole(['super-admin', 'hr-admin']) ||
            $client->account_manager_id === auth()->id(),
            403,
        );
    }

    public function updateClientSettings(Request $request, Client $client)
    {
        abort_unless(
            auth()->user()->hasAnyRole(['super-admin', 'hr-admin']) ||
            $client->account_manager_id === auth()->id(),
            403
        );
        $request->validate([
            'payment_day'             => 'nullable|integer|min:1|max:31',
            'work_site_address'       => 'nullable|string|max:255',
            'work_site_lat'           => 'nullable|numeric|between:-90,90',
            'work_site_lng'           => 'nullable|numeric|between:-180,180',
            'geo_fence_radius'        => 'nullable|integer|min:10|max:5000',
            'payroll_type'            => 'nullable|in:daily,hourly,monthly,mixed',
            'gpa_wmc_rate'            => 'nullable|numeric|min:0|max:20',
            'billing_rate_multiplier' => 'nullable|numeric|min:1|max:5',
        ]);
        $client->update(array_merge(
            $request->only('payment_day', 'work_site_address', 'work_site_lat', 'work_site_lng',
                           'geo_fence_radius', 'payroll_type', 'gpa_wmc_rate', 'billing_rate_multiplier'),
            ['gross_up_paye' => $request->boolean('gross_up_paye')]
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

    // ── Create Payroll Run ────────────────────────────────────────────────────

    public function storePayrollRun(Request $request)
    {
        $clients = $this->managedClients();
        $request->validate([
            'client_id'       => 'required|integer',
            'month'           => 'required|integer|between:1,12',
            'year'            => 'required|integer|min:2020|max:2100',
            'employment_type' => 'required|in:casual,contract,mixed',
        ]);

        abort_unless($clients->contains('id', (int)$request->client_id), 403);

        $exists = PayrollRun::where('client_id', $request->client_id)
            ->where('month', $request->month)
            ->where('year', $request->year)
            ->where('employment_type', $request->employment_type)
            ->exists();

        if ($exists) {
            return back()->with('error', ucfirst($request->employment_type) . ' payroll for this client and period already exists.');
        }

        $client    = $clients->firstWhere('id', (int)$request->client_id);
        $monthName = date('F', mktime(0, 0, 0, $request->month, 1));
        $typeLabel = ucfirst($request->employment_type);

        $run = PayrollRun::create([
            'title'           => "{$client->company_name} — {$monthName} {$request->year} {$typeLabel} Payroll",
            'month'           => $request->month,
            'year'            => $request->year,
            'status'          => 'draft',
            'client_id'       => $request->client_id,
            'employment_type' => $request->employment_type,
        ]);

        return redirect()->route('account-manager.payroll.show', $run)
            ->with('success', "Payroll run created for {$client->company_name}. Upload manual days if needed, then click \"Run & Submit to HR\".");
    }

    // ── Full Employee Bulk Import (create + update) ───────────────────────────

    public function fullImportTemplate()
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="employee-full-import-template.csv"',
        ];
        $callback = function () {
            $handle = fopen('php://output', 'w');
            // Columns are matched by heading, so they may be reordered or omitted.
            // Only first_name and last_name are required.
            fputcsv($handle, [
                'emp_number','first_name','last_name','middle_name','email',
                'employment_type','salary_type','rate',
                'department','designation',
                'hire_date','phone','national_id','nssf_number',
                'payment_mode','bank_name','bank_account','mobile_money_number',
                'charge_paye','charge_nssf','nssf_paid_by_employer',
            ]);
            // payment_mode must be one of: bank | mtn | airtel | cash | cheque
            fputcsv($handle, ['EMP001','John','Doe','','john.doe@example.com','casual','daily','16615','Kitchen','Cook','2026-01-15','+256700000001','CF10000001','1234567890','bank','Stanbic Bank','0123456789','','Yes','Yes','No']);
            fputcsv($handle, ['','Jane','Smith','Mary','','casual','hourly','1600','Housekeeping','Cleaner','2026-02-01','+256770000002','','','mtn','','','0770000002','Yes','Yes','No']);
            fputcsv($handle, ['','Peter','Amuriat','','','casual','daily','16615','Security','Guard','2026-02-01','+256750000004','','','airtel','','','0750000004','Yes','Yes','No']);
            fputcsv($handle, ['EMP100','Robert','Okello','','r.okello@example.com','contract','monthly','1500000','Management','Supervisor','2025-06-01','+256700000003','CF20000001','9876543210','bank','Centenary Bank','9876543210','','Yes','Yes','No']);
            fclose($handle);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function fullImportEmployees(Request $request)
    {
        $request->validate([
            'csv_file'  => 'required|file|mimes:csv,txt|max:5120',
            'client_id' => 'required|integer',
        ]);

        $clients  = $this->managedClients();
        $clientId = (int)$request->client_id;
        abort_unless($clients->contains('id', $clientId), 403);
        $client = $clients->firstWhere('id', $clientId);

        $departments  = Department::pluck('id', 'name')->toArray();
        $designations = Designation::pluck('id', 'title')->toArray();

        // Prefix for auto-generated emp_numbers
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $client->company_name), 0, 3));

        $handle    = fopen($request->file('csv_file')->getRealPath(), 'r');
        $headerRow = fgetcsv($handle);

        // Columns are matched by header name, not position, so a manager can drop
        // in an extra column, reorder them, or send a sheet with only the fields
        // they actually have without the rest of the row shifting out of place.
        $columns = [];
        foreach ($headerRow ?: [] as $i => $name) {
            $key = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', (string) $name), '_'));
            if ($key !== '') $columns[$key] = $i;
        }

        // Accept the friendlier spellings managers actually type.
        $aliases = [
            'employee_number' => 'emp_number',  'staff_number' => 'emp_number',
            'surname'         => 'last_name',   'given_name'   => 'first_name',
            'mobile_money'    => 'mobile_money_number', 'momo' => 'mobile_money_number',
            'account_number'  => 'bank_account','nssf'         => 'nssf_number',
            'daily_rate'      => 'rate',        'salary'       => 'rate',
            'email_address'   => 'email',       'telephone'    => 'phone',
        ];
        foreach ($aliases as $from => $to) {
            if (isset($columns[$from]) && !isset($columns[$to])) $columns[$to] = $columns[$from];
        }

        $missing = array_diff(['first_name', 'last_name'], array_keys($columns));
        if ($missing) {
            fclose($handle);
            return back()->with('error',
                'The sheet is missing required column(s): ' . implode(', ', $missing)
                . '. Download the template to see the expected headings.');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];
        $rowNo   = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNo++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) continue;

            $get = fn (string $col) => isset($columns[$col]) && isset($row[$columns[$col]])
                ? trim((string) $row[$columns[$col]])
                : null;

            $empNumber      = $get('emp_number');
            $firstName      = $get('first_name');
            $lastName       = $get('last_name');
            $middleName     = $get('middle_name');
            $employmentType = $get('employment_type');
            $salaryType     = $get('salary_type');
            $rate           = $get('rate');
            $departmentName = $get('department');
            $designationName= $get('designation');
            $hireDate       = $get('hire_date');
            $phone          = $get('phone');
            $nationalId     = $get('national_id');
            $paymentMode    = $get('payment_mode');
            $bankName       = $get('bank_name');
            $bankAccount    = $get('bank_account');
            $mobileMoneyNumber = $get('mobile_money_number');
            $chargePaye     = $get('charge_paye');
            $chargeNssf     = $get('charge_nssf');
            $nssfByEmployer = $get('nssf_paid_by_employer');
            $email          = $get('email');
            $nssfNumber     = $get('nssf_number');

            $firstName = trim($firstName ?? '');
            $lastName  = trim($lastName  ?? '');
            if (!$firstName || !$lastName) {
                $skipped++;
                $errors[] = "Row {$rowNo} skipped: missing first_name or last_name.";
                continue;
            }

            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$rowNo} ({$firstName} {$lastName}): '{$email}' is not a valid email — imported without a login.";
                $email = null;
            }

            $empNumber = trim($empNumber ?? '');

            // Department lookup / auto-create
            $departmentId = null;
            $deptName = trim($departmentName ?? '');
            if ($deptName) {
                if (!isset($departments[$deptName])) {
                    $dept = Department::firstOrCreate(['name' => $deptName]);
                    $departments[$deptName] = $dept->id;
                }
                $departmentId = $departments[$deptName];
            }

            // Designation lookup
            $designationId = null;
            $desigName = trim($designationName ?? '');
            if ($desigName) {
                if (!isset($designations[$desigName])) {
                    $desig = Designation::firstOrCreate(['title' => $desigName, 'department_id' => $departmentId]);
                    $designations[$desigName] = $desig->id;
                }
                $designationId = $designations[$desigName];
            }

            $empType = in_array(strtolower(trim($employmentType ?? '')), ['casual','contract','permanent','internship'])
                ? strtolower(trim($employmentType)) : 'casual';

            $hireDateParsed = null;
            if (trim($hireDate ?? '')) {
                try { $hireDateParsed = \Carbon\Carbon::parse(trim($hireDate))->format('Y-m-d'); } catch (\Exception $e) {}
            }

            // Find existing or create.
            //
            // The lookup is scoped to the client being imported for. Two companies
            // on the same system legitimately use their own internal numbering, so
            // a global match on emp_number would rewrite a different company's
            // employee and silently drag them onto this client's roster.
            $employee = null;
            if ($empNumber) {
                $employee = Employee::where('emp_number', $empNumber)
                    ->whereHas('clients', fn ($q) => $q->where('clients.id', $clientId))
                    ->first();

                if (!$employee && Employee::where('emp_number', $empNumber)->exists()) {
                    $skipped++;
                    $errors[] = "Row skipped: employee number {$empNumber} already belongs to another company. "
                              . "Use a number unique to {$client->company_name}, or leave the column blank to auto-generate one.";
                    continue;
                }
            }

            if ($employee) {
                // UPDATE: only touch columns the sheet actually filled in. Blank
                // cells must leave the stored value alone — otherwise an account
                // manager re-importing the file just to add bank details would
                // silently reset employment_type to casual and clear the
                // PAYE/NSSF flags for everyone on the sheet.
                $data = ['first_name' => $firstName, 'last_name' => $lastName];

                $optional = [
                    'middle_name'         => trim($middleName ?? ''),
                    'phone'               => trim($phone ?? ''),
                    'national_id'         => trim($nationalId ?? ''),
                    'bank_name'           => trim($bankName ?? ''),
                    'bank_account'        => trim($bankAccount ?? ''),
                    'mobile_money_number' => trim($mobileMoneyNumber ?? ''),
                    'nssf_number'         => trim($nssfNumber ?? ''),
                ];
                foreach ($optional as $col => $val) {
                    if ($val !== '') $data[$col] = $val;
                }

                if (trim($employmentType ?? '') !== '') $data['employment_type'] = $empType;
                if ($departmentId)                      $data['department_id']   = $departmentId;
                if ($designationId)                     $data['designation_id']  = $designationId;
                if ($hireDateParsed)                    $data['hire_date']       = $hireDateParsed;
                if (trim($paymentMode ?? '') !== '') {
                    $data['payment_mode'] = Employee::normalisePaymentMode(
                        $paymentMode,
                        trim($mobileMoneyNumber ?? '') ?: ($employee->mobile_money_number ?: trim($phone ?? ''))
                    );
                }
                foreach (['charge_paye' => $chargePaye, 'charge_nssf' => $chargeNssf,
                          'nssf_paid_by_employer' => $nssfByEmployer] as $col => $raw) {
                    $flag = strtolower(trim($raw ?? ''));
                    if ($flag !== '') $data[$col] = in_array($flag, ['yes', 'y', 'true', '1'], true);
                }

                $employee->update($data);
                $updated++;
            } else {
                // CREATE: defaults are fine, there is nothing to preserve.
                $data = [
                    'first_name'          => $firstName,
                    'last_name'           => $lastName,
                    'middle_name'         => trim($middleName ?? '') ?: null,
                    'employment_type'     => $empType,
                    'department_id'       => $departmentId,
                    'designation_id'      => $designationId,
                    'hire_date'           => $hireDateParsed,
                    'phone'               => trim($phone ?? '') ?: null,
                    'national_id'         => trim($nationalId ?? '') ?: null,
                    'payment_mode'        => Employee::normalisePaymentMode(
                        $paymentMode,
                        trim($mobileMoneyNumber ?? '') ?: trim($phone ?? '')
                    ),
                    'bank_name'           => trim($bankName ?? '') ?: null,
                    'bank_account'        => trim($bankAccount ?? '') ?: null,
                    'mobile_money_number' => trim($mobileMoneyNumber ?? '') ?: null,
                    'nssf_number'         => trim($nssfNumber ?? '') ?: null,
                    'charge_paye'         => strtolower(trim($chargePaye ?? '')) === 'yes',
                    'charge_nssf'         => strtolower(trim($chargeNssf ?? '')) === 'yes',
                    'nssf_paid_by_employer'=> strtolower(trim($nssfByEmployer ?? '')) === 'yes',
                    'status'              => 'active',
                ];

                // Auto-generate emp_number if blank
                if (!$empNumber) {
                    $count = Employee::where('emp_number', 'like', "{$prefix}%")->count() + 1;
                    do {
                        $empNumber = $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);
                        $count++;
                    } while (Employee::where('emp_number', $empNumber)->exists());
                }
                $data['emp_number'] = $empNumber;
                $employee = Employee::create($data);
                $created++;
            }

            // Give the employee a login when an email was supplied. Without a user
            // record there is no address to send a payslip to, so an employee
            // imported without one silently drops out of every payslip email run.
            if ($email && !$employee->user) {
                $user = User::where('email', $email)->first();
                if ($user && Employee::where('user_id', $user->id)->where('id', '!=', $employee->id)->exists()) {
                    $errors[] = "Row {$rowNo} ({$firstName} {$lastName}): {$email} is already used by another employee — imported without a login.";
                } else {
                    if (!$user) {
                        $user = User::create([
                            'name'     => trim("{$firstName} {$lastName}"),
                            'email'    => $email,
                            'password' => Hash::make('Password@123'),
                        ]);
                    }
                    if (!$user->hasRole('employee')) $user->assignRole('employee');
                    $employee->update(['user_id' => $user->id]);
                }
            } elseif ($email && $employee->user && $employee->user->email !== $email) {
                if (User::where('email', $email)->where('id', '!=', $employee->user->id)->exists()) {
                    $errors[] = "Row {$rowNo} ({$firstName} {$lastName}): {$email} is already in use — email not changed.";
                } else {
                    $employee->user->update(['email' => $email]);
                }
            }

            // Ensure employee is attached to this client
            if (!$client->employees()->where('employees.id', $employee->id)->exists()) {
                $client->employees()->attach($employee->id, ['assigned_by' => auth()->id()]);
            }

            // Salary record
            $rateVal      = (float)(trim($rate ?? '') ?: 0);
            $salaryTypeVal = in_array(strtolower(trim($salaryType ?? '')), ['daily','hourly','monthly'])
                ? strtolower(trim($salaryType)) : 'daily';
            if ($rateVal > 0) {
                EmployeeSalary::updateOrCreate(
                    ['employee_id' => $employee->id, 'is_current' => true],
                    ['basic_salary' => $rateVal, 'salary_type' => $salaryTypeVal,
                     'effective_from' => $hireDateParsed ?? today(), 'created_by' => auth()->id()]
                );
            }
        }
        fclose($handle);

        $msg = "Import into {$client->company_name}: {$created} new employee(s) added, {$updated} updated"
             . ($skipped ? ", {$skipped} skipped" : '') . '.';

        if ($errors) {
            $shown = array_slice($errors, 0, 5);
            $msg  .= ' Issues: ' . implode(' | ', $shown);
            if (count($errors) > 5) $msg .= ' (+' . (count($errors) - 5) . ' more)';
        }

        return back()->with($created + $updated > 0 ? 'success' : 'error', $msg);
    }
}
