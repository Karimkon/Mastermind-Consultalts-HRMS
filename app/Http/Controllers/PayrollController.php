<?php
namespace App\Http\Controllers;

use App\Exports\BankPaymentExport;
use App\Models\{PayrollRun, Payslip, Employee, SalaryGrade, SalaryComponent, EmployeeSalary};
use App\Services\Payroll\PayrollService;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $runs = PayrollRun::with('processor')
            ->when($request->year, fn($q) => $q->where('year', $request->year))
            ->orderByDesc('year')->orderByDesc('month')->paginate(20);
        return view('payroll.index', compact('runs'));
    }

    public function create() { return view('payroll.create'); }

    public function store(Request $request)
    {
        $request->validate(['month' => 'required|integer|min:1|max:12', 'year' => 'required|integer|min:2020']);
        $exists = PayrollRun::where('month', $request->month)->where('year', $request->year)->exists();
        if ($exists) return back()->with('error', 'Payroll run for this period already exists.');
        $title = date('F', mktime(0, 0, 0, $request->month, 1)) . ' ' . $request->year . ' Payroll';
        $run = PayrollRun::create(['title' => $title, 'month' => $request->month, 'year' => $request->year, 'status' => 'draft', 'processed_by' => auth()->id()]);
        return redirect()->route('payroll.show', $run)->with('success', 'Payroll run created.');
    }

    public function show(PayrollRun $payroll)
    {
        $payroll->load('payslips.employee.department');
        $totals = [
            'count' => $payroll->payslips->count(),
            'gross' => $payroll->payslips->sum('gross_salary'),
            'net'   => $payroll->payslips->sum('net_salary'),
            'tax'   => $payroll->payslips->sum('tax_amount'),
            'deductions' => $payroll->payslips->sum('total_deductions'),
        ];
        return view('payroll.show', ['payroll' => $payroll, 'totals' => $totals]);
    }

    public function edit(PayrollRun $payroll) { return view('payroll.edit', ['run' => $payroll]); }

    public function update(Request $request, PayrollRun $payroll)
    {
        $payroll->update($request->only('month', 'year', 'status'));
        return redirect()->route('payroll.show', $payroll)->with('success', 'Updated.');
    }

    public function destroy(PayrollRun $payroll) { $payroll->delete(); return redirect()->route('payroll.index')->with('success', 'Deleted.'); }

    public function process(PayrollRun $payroll)
    {
        if ($payroll->status === 'approved') return back()->with('error', 'Payroll already approved.');
        app(PayrollService::class)->processRun($payroll);
        return back()->with('success', 'Payroll processed successfully.');
    }

    public function approve(PayrollRun $payroll)
    {
        $payroll->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        return back()->with('success', 'Payroll approved.');
    }

    public function markPaid(PayrollRun $payroll)
    {
        $payroll->update(['status' => 'paid', 'payment_date' => now()->toDateString()]);
        return back()->with('success', 'Payroll marked as paid.');
    }

    public function bankExport(PayrollRun $payroll)
    {
        if (!in_array($payroll->status, ['approved','paid'])) {
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
            'currency' => \App\Models\Setting::get('currency_symbol', 'R'),
        ];
        $pdf = Pdf::loadView('payroll.payslip-pdf', compact('payslip', 'payroll_run', 'company'))->setPaper('a4');
        return $pdf->download("payslip-{$employee->emp_number}-{$payroll->month}-{$payroll->year}.pdf");
    }

    // Salary management
    public function salaryIndex(Request $request)
    {
        $salaries = EmployeeSalary::with('employee.department')->paginate(25);
        $employees = Employee::where('status','active')->with('user')->get();
        return view('payroll.salary.index', compact('salaries', 'employees'));
    }

    public function salaryCreate()
    {
        $employees   = Employee::where('status','active')->with('user')->get();
        $components  = SalaryComponent::all();
        $grades      = SalaryGrade::all();
        return view('payroll.salary.create', compact('employees', 'components', 'grades'));
    }

    public function salaryStore(Request $request)
    {
        $request->validate(['employee_id'=>'required','basic_salary'=>'required|numeric','effective_from'=>'required|date']);
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
        $request->validate(['grade'=>'required','basic_min'=>'required|numeric','basic_max'=>'required|numeric']);
        SalaryGrade::create($request->only('grade','basic_min','basic_max'));
        return back()->with('success', 'Grade created.');
    }

    public function componentsIndex()
    {
        $components = SalaryComponent::paginate(20);
        return view('payroll.salary.components', compact('components'));
    }

    public function componentsStore(Request $request)
    {
        $request->validate(['name'=>'required','type'=>'required']);
        SalaryComponent::create($request->only('name','type','is_taxable','is_fixed','amount','percentage'));
        return back()->with('success', 'Component created.');
    }
}