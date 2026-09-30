<?php
namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\{EmployeeDocument, Employee};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SelfServiceController extends Controller
{
    /**
     * An employee's own payslips.
     *
     * The web portal had no way at all for the 1,093 people on the employee role
     * to see their pay: the only payslip route was the PDF, which needed a
     * payroll-run id and an employee id you had to already know, and nothing in
     * the menu pointed at it. The phone app had the list; the portal did not.
     *
     * Withheld slips are shown rather than hidden — somebody who was not paid
     * needs to be able to see that, and why.
     */
    public function payslips()
    {
        $employee = auth()->user()->employee;
        if (!$employee) return redirect()->route("dashboard")->with("error", "No employee profile found.");

        // Only runs that have actually been released. A slip from a run still
        // sitting in the approval chain is a draft figure, not somebody's pay.
        $payslips = \App\Models\Payslip::with('payrollRun')
            ->where('employee_id', $employee->id)
            ->whereHas('payrollRun', fn($q) => $q->whereIn('status', ['md_approved', 'approved', 'paid']))
            ->get()
            ->sortByDesc(fn($p) => sprintf('%04d%02d', $p->payrollRun->year, $p->payrollRun->month))
            ->values();

        return view("employee.payslips", compact("employee", "payslips"));
    }

    public function documents()
    {
        $employee = auth()->user()->employee;
        if (!$employee) return redirect()->route("dashboard")->with("error", "No employee profile found.");
        $documents = $employee->documents()->orderByDesc("created_at")->get();
        return view("employee.documents", compact("employee", "documents"));
    }

    public function storeDocument(Request $request)
    {
        $employee = auth()->user()->employee;
        if (!$employee) abort(403);

        $request->validate([
            "document_type" => "required|string|max:100",
            "title"         => "required|string|max:255",
            "file"          => \App\Support\Uploads::rules(),
            "expiry_date"   => "nullable|date|after:today",
            "notes"         => "nullable|string|max:500",
        ]);

        $file     = $request->file("file");
        $path     = $file->store("employee-documents/" . $employee->id, "local");
        EmployeeDocument::create([
            "employee_id"   => $employee->id,
            "document_type" => $request->document_type,
            "title"         => $request->title,
            "file_path"     => $path,
            "file_name"     => $file->getClientOriginalName(),
            "mime_type"     => $file->getMimeType(),
            "expiry_date"   => $request->expiry_date,
            "notes"         => $request->notes,
            "uploaded_by"   => auth()->id(),
        ]);

        return back()->with("success", "Document uploaded successfully.");
    }

    public function downloadDocument(EmployeeDocument $document)
    {
        // Employee can only download their own documents
        $employee = auth()->user()->employee;
        $isAdmin  = auth()->user()->hasAnyRole(["super-admin","hr-admin","manager","account-manager"]);
        if (!$isAdmin && (!$employee || $document->employee_id !== $employee->id)) {
            abort(403);
        }
        if (!Storage::disk("local")->exists($document->file_path)) {
            return back()->with("error", "File not found.");
        }
        return Storage::disk("local")->download($document->file_path, $document->file_name);
    }

    public function destroyDocument(EmployeeDocument $document)
    {
        $employee = auth()->user()->employee;
        $isAdmin  = auth()->user()->hasAnyRole(["super-admin","hr-admin","manager","account-manager"]);
        if (!$isAdmin && (!$employee || $document->employee_id !== $employee->id)) {
            abort(403);
        }
        Storage::disk("local")->delete($document->file_path);
        $document->delete();
        return back()->with("success", "Document deleted.");
    }

    public function updateNextOfKin(Request $request)
    {
        $employee = auth()->user()->employee;
        if (!$employee) abort(403);

        $request->validate([
            "next_of_kin_name"     => "required|string|max:255",
            "next_of_kin_relation" => "required|string|max:100",
            "next_of_kin_phone"    => "required|string|max:20",
            "next_of_kin_email"    => "nullable|email|max:255",
            "passport_number"      => "nullable|string|max:50",
        ]);

        $employee->update($request->only([
            "next_of_kin_name","next_of_kin_relation","next_of_kin_phone",
            "next_of_kin_email","passport_number",
        ]));

        return back()->with("success", "Profile updated successfully.");
    }
}
