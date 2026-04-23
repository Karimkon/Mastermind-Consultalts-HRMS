@extends("layouts.app")
@section("title", "My Documents")
@section("content")
<x-page-header title="My Documents" subtitle="Upload and manage your personal documents"/>

<x-alert/>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

{{-- Upload Form --}}
<div class="lg:col-span-1">
    <div class="card p-6 mb-6">
        <h3 class="font-semibold text-slate-800 mb-4"><i class="fas fa-upload text-blue-600 mr-2"></i>Upload Document</h3>
        <form method="POST" action="{{ route('employee.documents.store') }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <div>
                <label class="form-label">Document Type <span class="text-red-500">*</span></label>
                <select name="document_type" class="form-select" required>
                    <option value="">Select type...</option>
                    <option value="cv">CV / Resume</option>
                    <option value="national_id">National ID</option>
                    <option value="passport">Passport</option>
                    <option value="academic">Academic Certificate / Transcript</option>
                    <option value="professional">Professional Certificate</option>
                    <option value="medical">Medical Certificate</option>
                    <option value="contract">Employment Contract</option>
                    <option value="tax">Tax Document</option>
                    <option value="bank">Bank Details / Statement</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label class="form-label">Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" class="form-input" placeholder="e.g. Bachelor of Commerce Certificate" required>
            </div>
            <div>
                <label class="form-label">File <span class="text-red-500">*</span></label>
                <input type="file" name="file" class="form-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                <p class="text-xs text-slate-400 mt-1">PDF, JPG, PNG, DOC — max 10MB</p>
            </div>
            <div>
                <label class="form-label">Expiry Date (optional)</label>
                <input type="date" name="expiry_date" class="form-input">
            </div>
            <div>
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="2" class="form-textarea" placeholder="Optional notes..."></textarea>
            </div>
            <button type="submit" class="btn-primary w-full justify-center"><i class="fas fa-upload"></i> Upload</button>
        </form>
    </div>

    {{-- Next of Kin & Passport --}}
    <div class="card p-6">
        <h3 class="font-semibold text-slate-800 mb-4"><i class="fas fa-users text-purple-600 mr-2"></i>Next of Kin & Passport</h3>
        <form method="POST" action="{{ route('employee.next-of-kin.update') }}" class="space-y-3">
            @csrf @method("PUT")
            <div>
                <label class="form-label">Passport Number</label>
                <input type="text" name="passport_number" class="form-input" value="{{ $employee->passport_number }}">
            </div>
            <div>
                <label class="form-label">Next of Kin Name <span class="text-red-500">*</span></label>
                <input type="text" name="next_of_kin_name" class="form-input" value="{{ $employee->next_of_kin_name }}" required>
            </div>
            <div>
                <label class="form-label">Relationship</label>
                <select name="next_of_kin_relation" class="form-select">
                    <option value="">Select...</option>
                    @foreach(["Spouse","Parent","Sibling","Child","Friend","Other"] as $rel)
                    <option value="{{ $rel }}" {{ $employee->next_of_kin_relation == $rel ? "selected" : "" }}>{{ $rel }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Phone <span class="text-red-500">*</span></label>
                <input type="text" name="next_of_kin_phone" class="form-input" value="{{ $employee->next_of_kin_phone }}" required>
            </div>
            <div>
                <label class="form-label">Email</label>
                <input type="email" name="next_of_kin_email" class="form-input" value="{{ $employee->next_of_kin_email }}">
            </div>
            <button type="submit" class="btn-primary w-full justify-center"><i class="fas fa-save"></i> Save</button>
        </form>
    </div>
</div>

{{-- Document List --}}
<div class="lg:col-span-2">
    <div class="card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="font-semibold text-slate-800">Uploaded Documents ({{ $documents->count() }})</h3>
        </div>
        @forelse($documents as $doc)
        <div class="flex items-start gap-4 px-6 py-4 border-b border-slate-50 hover:bg-slate-50 transition-colors">
            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                @if(in_array($doc->mime_type, ["image/jpeg","image/png"]))
                    <i class="fas fa-image text-blue-600"></i>
                @elseif($doc->mime_type === "application/pdf")
                    <i class="fas fa-file-pdf text-red-500"></i>
                @else
                    <i class="fas fa-file-word text-blue-700"></i>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-800 truncate">{{ $doc->title }}</p>
                <div class="flex flex-wrap gap-2 mt-1">
                    <span class="badge-blue text-xs">{{ ucfirst(str_replace("_"," ",$doc->document_type)) }}</span>
                    @if($doc->expiry_date)
                        <span class="badge-{{ $doc->expiry_date->isPast() ? "red" : "green" }} text-xs">
                            Expires: {{ $doc->expiry_date->format("d M Y") }}
                        </span>
                    @endif
                    <span class="text-xs text-slate-400">{{ $doc->created_at->diffForHumans() }}</span>
                </div>
                @if($doc->notes)<p class="text-xs text-slate-500 mt-1">{{ $doc->notes }}</p>@endif
            </div>
            <div class="flex gap-2 shrink-0">
                <a href="{{ route('employee.documents.download', $doc) }}" class="btn-xs btn-blue" title="Download">
                    <i class="fas fa-download"></i>
                </a>
                <form method="POST" action="{{ route('employee.documents.destroy', $doc) }}" onsubmit="return confirm('Delete this document?')">
                    @csrf @method("DELETE")
                    <button type="submit" class="btn-xs btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                </form>
            </div>
        </div>
        @empty
        <div class="py-12 text-center text-slate-400">
            <i class="fas fa-folder-open text-4xl mb-3 block"></i>
            No documents uploaded yet. Use the form to upload your CV, ID, certificates, etc.
        </div>
        @endforelse
    </div>
</div>

</div>
@endsection
