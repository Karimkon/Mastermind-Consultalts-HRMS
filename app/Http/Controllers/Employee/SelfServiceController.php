<?php
namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\{EmployeeDocument, Employee};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SelfServiceController extends Controller
{
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
            "file"          => "required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx",
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
