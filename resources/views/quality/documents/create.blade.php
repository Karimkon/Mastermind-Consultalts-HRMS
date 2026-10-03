@extends('layouts.app')
@section('title', 'New Document')
@section('content')

@php($cats = \App\Models\QualityDocument::CATEGORIES)

<x-page-header title="New Document" subtitle="Initiate a controlled document and route it for editing and approval">
    <a href="{{ route('quality.documents.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

@if($errors->any())<div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif

<div class="max-w-2xl rounded-xl bg-white border border-slate-200 p-6">
    <form action="{{ route('quality.documents.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4" data-upload-progress>
        @csrf
        <div><label class="form-label">Title</label><input name="title" required value="{{ old('title') }}" class="form-input"></div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><label class="form-label">Category</label><select name="category" class="form-select">@foreach($cats as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div><x-file-drop name="file" label="Attach document (optional)" /></div>
        </div>
        <div><label class="form-label">Description</label><textarea name="description" rows="3" class="form-input">{{ old('description') }}</textarea></div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><label class="form-label">Editor (responsible for editing)</label><select name="editor_id" class="form-select"><option value="">Assign later</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
            <div><label class="form-label">Approver</label><select name="approver_id" class="form-select"><option value="">Assign later</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
            <div><label class="form-label">Effective from</label><input type="date" name="start_date" value="{{ old('start_date') }}" class="form-input"></div>
            <div><label class="form-label">Review by / expires</label><input type="date" name="end_date" value="{{ old('end_date') }}" class="form-input"></div>
        </div>
        <p class="text-xs text-slate-400">If you name an editor now, the document is sent to them immediately for editing.</p>
        <div class="flex justify-end"><button class="btn-primary">Create document</button></div>
    </form>
</div>

<x-transfer-progress />

@endsection
