@extends("layouts.app")
@section("title","Employee Documents")
@section("content")
<x-page-header title="{{ $employee->full_name }} — Documents">
    <a href="{{ route('employees.show',$employee) }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <x-data-table>
            <thead class="bg-slate-50"><tr>
                <th class="table-head px-6 py-3 text-left">Document</th>
                <th class="table-head px-4 py-3 text-left">Type</th>
                <th class="table-head px-4 py-3 text-left">Expiry</th>
                <th class="table-head px-4 py-3 text-left">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($documents as $doc)
                <tr class="table-row">
                    <td class="px-6 py-3"><p class="text-sm font-medium text-slate-800">{{ $doc->title }}</p><p class="text-xs text-slate-500">{{ $doc->file_name }}</p></td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ $doc->document_type }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        @if($doc->expiry_date)<span class="{{ $doc->expiry_date->isPast() ? 'text-red-500' : 'text-slate-600' }}">{{ $doc->expiry_date->format('M d, Y') }}</span>@else<span class="text-slate-400">—</span>@endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex gap-1">
                            <a href="{{ asset('storage/'.$doc->file_path) }}" target="_blank" class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Download"><i class="fas fa-download text-xs"></i></a>
                            <form method="POST" action="{{ route('employees.documents.destroy',$doc) }}" onsubmit="return confirm('Delete document?')">@csrf @method("DELETE")
                                <button class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"><i class="fas fa-trash text-xs"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-6 py-8 text-center text-slate-400">No documents uploaded</td></tr>
                @endforelse
            </tbody>
        </x-data-table>
    </div>

    <div class="card p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Upload Document</h3>
        <form method="POST" action="{{ route('employees.documents.store',$employee) }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <div><label class="form-label">Document Type *</label>
                <select name="document_type" class="form-select" required>
                    @foreach(["ID Document","Passport","Employment Contract","Qualification","Certificate","Tax Document","Bank Letter","Medical Certificate","Other"] as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                </select>
            </div>
            <div><label class="form-label">Title *</label><input type="text" name="title" class="form-input" required></div>
            <div><label class="form-label">File *</label><input type="file" name="file" class="form-input" required></div>
            <div><label class="form-label">Expiry Date</label><input type="date" name="expiry_date" class="form-input"></div>
            <div><label class="form-label">Notes</label><textarea name="notes" class="form-input" rows="2"></textarea></div>
            <button type="submit" class="btn-primary w-full justify-center"><i class="fas fa-upload"></i> Upload</button>
        </form>
    </div>
</div>
@endsection
