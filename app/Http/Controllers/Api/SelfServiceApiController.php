<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Employee, EmployeeDocument};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SelfServiceApiController extends Controller
{
    private function employee(Request $request): ?Employee
    {
        return $request->user()->employee;
    }

    public function documents(Request $request)
    {
        $emp = $this->employee($request);
        if (!$emp) return response()->json(['data' => []]);
        $docs = EmployeeDocument::where('employee_id', $emp->id)->latest()->get()
            ->map(fn($d) => [
                'id'            => $d->id,
                'document_type' => $d->document_type,
                'title'         => $d->title,
                'file_name'     => $d->file_name,
                'mime_type'     => $d->mime_type,
                'expiry_date'   => $d->expiry_date?->format('Y-m-d'),
                'notes'         => $d->notes,
                'created_at'    => $d->created_at?->format('Y-m-d'),
            ]);
        return response()->json(['data' => $docs]);
    }

    public function uploadDocument(Request $request)
    {
        $emp = $this->employee($request);
        if (!$emp) return response()->json(['message' => 'No employee profile.'], 422);

        $request->validate([
            'document_type' => 'required|string',
            'file'          => 'required|file|max:10240',
        ]);

        $path = $request->file('file')->store("employee-documents/{$emp->id}", 'local');

        $doc = EmployeeDocument::create([
            'employee_id'   => $emp->id,
            'document_type' => $request->document_type,
            'title'         => $request->title ?? $request->file('file')->getClientOriginalName(),
            'file_path'     => $path,
            'file_name'     => $request->file('file')->getClientOriginalName(),
            'mime_type'     => $request->file('file')->getMimeType(),
            'expiry_date'   => $request->expiry_date ?: null,
            'notes'         => $request->notes,
            'uploaded_by'   => $request->user()->id,
        ]);

        return response()->json(['data' => ['id' => $doc->id, 'title' => $doc->title], 'message' => 'Document uploaded.'], 201);
    }

    public function deleteDocument(Request $request, EmployeeDocument $document)
    {
        $emp = $this->employee($request);
        if (!$emp || $document->employee_id !== $emp->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        Storage::disk('local')->delete($document->file_path);
        $document->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    public function updateNok(Request $request)
    {
        $emp = $this->employee($request);
        if (!$emp) return response()->json(['message' => 'No employee profile.'], 422);
        $emp->update($request->only([
            'next_of_kin_name', 'next_of_kin_relation',
            'next_of_kin_phone', 'next_of_kin_email',
            'passport_number',
        ]));
        return response()->json(['message' => 'Profile updated.']);
    }
}
