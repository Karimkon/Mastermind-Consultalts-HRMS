<?php
namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Employee;
use App\Services\Payroll\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function index()
    {
        $runs = PayrollRun::withCount("payslips")->orderByDesc("year")->orderByDesc("month")->paginate(12);
        return view("payroll.index", compact("runs"));
    }

    public function create()  { return view("payroll.create"); }

    public function store(Request $request)
    {
        $data = $request->validate([
            "month" => "required|integer|between:1,12",
            "year"  => "required|integer|min:2020",
            "notes" => "nullable|string",
        ]);
        $data["title"]  = date("F", mktime(0,0,0,$data["month"],1)) . " " . $data["year"] . " Payroll";
        $data["status"] = "draft";
        $run = PayrollRun::create($data);
        return redirect()->route("payroll.show", $run)->with("success", "Payroll run created.");
    }

    public function show(PayrollRun $payroll)
    {
        $payroll->load(["payslips.employee.department"]);
        $totals = [
            "gross" => $payroll->payslips->sum("gross_salary"),
            "net"   => $payroll->payslips->sum("net_salary"),
            "tax"   => $payroll->payslips->sum("tax_amount"),
            "count" => $payroll->payslips->count(),
        ];
        return view("payroll.show", compact("payroll","totals"));
    }

    public function edit(PayrollRun $payroll)   { return view("payroll.edit", compact("payroll")); }
    public function update(Request $r, PayrollRun $payroll) {
        $payroll->update($r->validate(["notes" => "nullable|string"]));
        return back()->with("success","Updated.");
    }
    public function destroy(PayrollRun $payroll) {
        if ($payroll->status !== "draft") return back()->withErrors(["error" => "Only draft runs can be deleted."]);
        $payroll->delete();
        return redirect()->route("payroll.index")->with("success","Deleted.");
    }

    public function process(Request $request, PayrollRun $payroll_run)
    {
        if (!in_array($payroll_run->status, ["draft","processing"])) return back()->withErrors(["error" => "Cannot process this run."]);
        $payroll_run->update(["status" => "processing"]);
        $count = app(PayrollService::class)->processRun($payroll_run);
        return back()->with("success", "Processed {$count} employees.");
    }

    public function approve(Request $request, PayrollRun $payroll_run)
    {
        if ($payroll_run->status !== "processed") return back()->withErrors(["error" => "Run must be processed first."]);
        $payroll_run->update(["status" => "approved", "approved_by" => auth()->id(), "approved_at" => now()]);
        return back()->with("success", "Payroll approved.");
    }

    public function payslips(PayrollRun $payroll_run)
    {
        $payslips = $payroll_run->payslips()->with("employee.department")->orderBy("employee_id")->get();
        return view("payroll.payslips", compact("payroll_run","payslips"));
    }

    public function payslipPdf(PayrollRun $payroll_run, Employee $employee)
    {
        $payslip = Payslip::where("payroll_run_id",$payroll_run->id)->where("employee_id",$employee->id)->firstOrFail();
        $payslip->load("employee.department","employee.designation");
        $company = ["name" => \App\Models\Setting::get("company_name","Mastermind Consultants"), "currency" => \App\Models\Setting::get("currency_symbol","R")];
        $pdf     = Pdf::loadView("payroll.payslip-pdf", compact("payslip","payroll_run","company"))->setPaper("a4");
        return $pdf->download("payslip_{$employee->emp_number}_{$payroll_run->month}_{$payroll_run->year}.pdf");
    }
}
