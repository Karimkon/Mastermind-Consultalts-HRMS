<?php
namespace App\Http\Controllers;

use App\Exports\{BankPaymentExport, KcbEftExport, KcbMtnExport, KcbAirtelExport};
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

    /**
     * Stage 1 — Account Manager runs/processes payroll then submits to HR
     */
    public function process(PayrollRun $payroll)
    {
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
     * Stage 2 — HR Admin approves
     */
    public function hrApprove(PayrollRun $payroll)
    {
        abort_unless(auth()->user()->hasAnyRole(['super-admin','hr-admin']), 403);
        if ($payroll->status !== 'processed') {
            return back()->with('error', 'Payroll must be in Submitted to HR status to approve.');
        }
        $payroll->update([
            'status'          => 'hr_approved',
            'hr_approved_by'  => auth()->id(),
            'hr_approved_at'  => now(),
        ]);
        return back()->with('success', 'HR approved. Payroll submitted to Finance.');
    }

    /**
     * Stage 3 — Finance / Payroll Officer approves
     */
    public function financeApprove(PayrollRun $payroll)
    {
        abort_unless(auth()->user()->hasAnyRole(['super-admin','payroll-officer']), 403);
        if ($payroll->status !== 'hr_approved') {
            return back()->with('error', 'Payroll must be HR-approved before Finance can approve.');
        }
        $payroll->update([
            'status'              => 'finance_approved',
            'finance_approved_by' => auth()->id(),
            'finance_approved_at' => now(),
        ]);
        return back()->with('success', 'Finance approved. Payroll submitted to MD for final approval.');
    }

    /**
     * Stage 4 — MD (Super Admin) final approval — auto-locks payroll
     */
    public function approve(PayrollRun $payroll)
    {
        abort_unless(auth()->user()->hasRole('super-admin'), 403, 'Only the MD (Super Admin) can give final approval.');
        if ($payroll->status !== 'finance_approved') {
            return back()->with('error', 'Payroll must be Finance-approved before MD approval.');
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
        return back()->with('success', 'MD approved. Payroll is now locked. Finance can download PDF and Excel.');
    }

    public function markPaid(PayrollRun $payroll)
    {
        abort_unless(auth()->user()->hasAnyRole(['super-admin','payroll-officer']), 403);
        if (!in_array($payroll->status, ['md_approved','approved'])) {
            return back()->with('error', 'Payroll must be MD-approved before marking as paid.');
        }
        $payroll->update([
            'status'       => 'paid',
            'payment_date' => now()->toDateString(),
            'paid_by'      => auth()->id(),
            'paid_at'      => now(),
        ]);
        $ns = app(NotificationService::class);
        $payroll->payslips()->with(['employee.user', 'payrollRun'])->each(fn($slip) => $ns->payrollProcessed($slip));
        return back()->with('success', 'Payroll marked as paid. Employees have been notified.');
    }

    /** Lock payroll manually */
    public function lock(PayrollRun $payroll)
    {
        if ($payroll->isLocked()) return back()->with('error', 'Already locked.');
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
            'deductions' => $payroll->payslips->sum('total_deductions'),
        ];
        $company = [
            'name'     => \App\Models\Setting::get('company_name', 'Mastermind Consultants'),
            'email'    => \App\Models\Setting::get('company_email', ''),
            'currency' => \App\Models\Setting::get('currency_symbol', 'UGX'),
        ];
        $pdf = Pdf::loadView('payroll.summary-pdf', compact('payroll','totals','company'))->setPaper('a4','landscape');
        return $pdf->download("payroll-{$payroll->year}-{$payroll->month}-summary.pdf");
    }

    /** Export full payroll Excel — available after MD approval */
    public function exportExcel(PayrollRun $payroll)
    {
        abort_unless(auth()->user()->can('reports.export'), 403);
        if (!in_array($payroll->status, ['md_approved','approved','paid'])) {
            return back()->with('error', 'Payroll must be MD-approved before downloading.');
        }
        $filename = 'payroll-'.$payroll->year.'-'.str_pad($payroll->month,2,'0',STR_PAD_LEFT).'.xlsx';
        return Excel::download(new \App\Exports\PayrollRunExport($payroll), $filename);
    }

    // ─── KCB Bulk Payment Exports ─────────────────────────────────────

    private function requiresApproval(PayrollRun $payroll): bool
    {
        return !in_array($payroll->status, ['md_approved', 'approved', 'paid']);
    }

    public function kcbEft(PayrollRun $payroll)
    {
        abort_unless(auth()->user()->can('reports.export'), 403);
        if ($this->requiresApproval($payroll)) {
            return back()->with('error', 'Payroll must be MD-approved before downloading payment files.');
        }
        $month = str_pad($payroll->month, 2, '0', STR_PAD_LEFT);
        return Excel::download(new KcbEftExport($payroll), "KCB-EFT-{$payroll->year}-{$month}.xlsx");
    }

    public function kcbMtn(PayrollRun $payroll)
    {
        abort_unless(auth()->user()->can('reports.export'), 403);
        if ($this->requiresApproval($payroll)) {
            return back()->with('error', 'Payroll must be MD-approved before downloading payment files.');
        }
        $month = str_pad($payroll->month, 2, '0', STR_PAD_LEFT);
        return Excel::download(new KcbMtnExport($payroll), "KCB-MTN-{$payroll->year}-{$month}.xlsx");
    }

    public function kcbAirtel(PayrollRun $payroll)
    {
        abort_unless(auth()->user()->can('reports.export'), 403);
        if ($this->requiresApproval($payroll)) {
            return back()->with('error', 'Payroll must be MD-approved before downloading payment files.');
        }
        $month = str_pad($payroll->month, 2, '0', STR_PAD_LEFT);
        return Excel::download(new KcbAirtelExport($payroll), "KCB-AIRTEL-{$payroll->year}-{$month}.xlsx");
    }

    public function bankExport(PayrollRun $payroll)
    {
        if (!in_array($payroll->status, ['approved', 'paid', 'md_approved'])) {
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
