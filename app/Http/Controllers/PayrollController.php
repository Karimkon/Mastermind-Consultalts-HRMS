<?php
namespace App\Http\Controllers;

use App\Exports\BankPaymentExport;
use App\Models\{PayrollRun, Payslip, Employee, SalaryGrade, SalaryComponent, EmployeeSalary, Client};
use App\Services\Payroll\PayrollService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class PayrollController extends Controller
{
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
        $clients = Client::where('status', 'active')->orderBy('company_name')->get();
        return view('payroll.create', compact('clients'));
    }

    public function store(Request $request)
    {
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
        ];
        return view('payroll.show', ['payroll' => $payroll, 'totals' => $totals]);
    }

    public function edit(PayrollRun $payroll)
    {
        $clients = Client::where('status', 'active')->orderBy('company_name')->get();
        return view('payroll.edit', ['run' => $payroll, 'clients' => $clients]);
    }

    public function update(Request $request, PayrollRun $payroll)
    {
        if ($payroll->isLocked()) {
            return back()->with('error', 'This payroll is locked. Only a Super Admin can unlock it.');
        }
        $payroll->update($request->only('month', 'year', 'status', 'client_id', 'notes'));
        return redirect()->route('payroll.show', $payroll)->with('success', 'Updated.');
    }

    public function destroy(PayrollRun $payroll)
    {
        if ($payroll->isLocked()) {
            return back()->with('error', 'Cannot delete a locked payroll run.');
        }
        $payroll->delete();
        return redirect()->route('payroll.index')->with('success', 'Deleted.');
    }

    public function process(PayrollRun $payroll)
    {
        if ($payroll->isLocked()) {
            return back()->with('error', 'This payroll is locked and cannot be re-processed.');
        }
        if ($payroll->status === 'approved') {
            return back()->with('error', 'Payroll already approved.');
        }
        app(PayrollService::class)->processRun($payroll);
        return back()->with('success', 'Payroll processed successfully.');
    }

    public function approve(PayrollRun $payroll)
    {
        if ($payroll->isLocked()) {
            return back()->with('error', 'This payroll is locked.');
        }
        $payroll->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        return back()->with('success', 'Payroll approved.');
    }

    public function markPaid(PayrollRun $payroll)
    {
        if ($payroll->isLocked()) {
            return back()->with('error', 'This payroll is locked.');
        }
        $payroll->update(['status' => 'paid', 'payment_date' => now()->toDateString()]);

        // Auto-lock after marking paid
        $payroll->update(['locked_at' => now(), 'locked_by' => auth()->id()]);

        $ns = app(NotificationService::class);
        $payroll->payslips()->with(['employee.user', 'payrollRun'])->each(fn($slip) => $ns->payrollProcessed($slip));

        return back()->with('success', 'Payroll marked as paid and locked. Only a Super Admin can unlock it.');
    }

    /** Lock payroll — any admin can lock */
    public function lock(PayrollRun $payroll)
    {
        if ($payroll->isLocked()) {
            return back()->with('error', 'Already locked.');
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

    public function bankExport(PayrollRun $payroll)
    {
        if (!in_array($payroll->status, ['approved', 'paid'])) {
            return back()->with('error', 'Payroll must be approved before exporting bank file.');
        }
        $filename = 'bank_payment_' . $payroll->year . '_' . str_pad($payroll->month, 2, '0', STR_PAD_LEFT) . '.csv';
        return Excel::download(new BankPaymentExport($payroll), $filename, \Maatwebsite\Excel\Excel::CSV);
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
        $payslip->load('employee.department', 'employee.designation');
        $payroll_run = $payroll;
        $company     = [
            'name'     => \App\Models\Setting::get('company_name', 'Mastermind Consultants'),
            'email'    => \App\Models\Setting::get('company_email', ''),
            'phone'    => \App\Models\Setting::get('company_phone', ''),
            'currency' => \App\Models\Setting::get('currency_symbol', 'UGX'),
        ];
        $pdf = Pdf::loadView('payroll.payslip-pdf', compact('payslip', 'payroll_run', 'company'))->setPaper('a4');
        return $pdf->download("payslip-{$employee->emp_number}-{$payroll->month}-{$payroll->year}.pdf");
    }

    // ─── Salary management ───────────────────────────────────────────

    public function salaryIndex(Request $request)
    {
        $salaries  = EmployeeSalary::with('employee.department')->paginate(25);
        $employees = Employee::where('status', 'active')->with('user')->get();
        return view('payroll.salary.index', compact('salaries', 'employees'));
    }

    public function salaryCreate()
    {
        $employees  = Employee::where('status', 'active')->with('user')->get();
        $components = SalaryComponent::all();
        $grades     = SalaryGrade::all();
        return view('payroll.salary.create', compact('employees', 'components', 'grades'));
    }

    public function salaryStore(Request $request)
    {
        $request->validate(['employee_id' => 'required', 'basic_salary' => 'required|numeric', 'effective_from' => 'required|date']);
        EmployeeSalary::updateOrCreate(
            ['employee_id' => $request->employee_id, 'is_current' => true],
            ['basic_salary' => $request->basic_salary, 'components' => $request->components ?? [], 'effective_from' => $request->effective_from, 'is_current' => true]
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
        $components = SalaryComponent::all();
        return view('payroll.salary.edit', compact('salary', 'components'));
    }

    public function salaryUpdate(Request $request, EmployeeSalary $salary)
    {
        $salary->update($request->only('basic_salary', 'components', 'effective_from'));
        return redirect()->route('salary.show', $salary)->with('success', 'Salary updated.');
    }

    public function gradesIndex()
    {
        $grades = SalaryGrade::paginate(20);
        return view('payroll.salary.grades', compact('grades'));
    }

    public function gradesStore(Request $request)
    {
        $request->validate(['grade' => 'required', 'basic_min' => 'required|numeric', 'basic_max' => 'required|numeric']);
        SalaryGrade::create($request->only('grade', 'basic_min', 'basic_max'));
        return back()->with('success', 'Grade created.');
    }

    public function componentsIndex()
    {
        $components = SalaryComponent::paginate(20);
        return view('payroll.salary.components', compact('components'));
    }

    public function componentsStore(Request $request)
    {
        $request->validate(['name' => 'required', 'type' => 'required']);
        SalaryComponent::create($request->only('name', 'type', 'is_taxable', 'is_fixed', 'amount', 'percentage'));
        return back()->with('success', 'Component created.');
    }
}
