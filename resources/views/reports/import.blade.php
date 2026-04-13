@extends('layouts.app')
@section('title', 'Import Employees')
@section('content')
<x-page-header title="Import Employees" subtitle="Upload an Excel/CSV file to bulk-import employees">
    <a href="{{ route('reports.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to Reports</a>
</x-page-header>

@if(session('success'))
<div class="mb-4 flex items-center gap-3 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
    <i class="fas fa-check-circle"></i> {{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
    <ul class="list-disc list-inside space-y-1">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Upload Form --}}
    <div class="lg:col-span-2">
        <div class="card p-6">
            <h2 class="text-base font-semibold text-slate-800 mb-4">Upload Employee File</h2>
            <form method="POST" action="{{ route('import.employees') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                <div>
                    <label class="form-label">Select File <span class="text-red-500">*</span></label>
                    <input type="file" name="file" accept=".xlsx,.xls,.csv"
                           class="block w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-slate-200 rounded-lg p-1 cursor-pointer">
                    <p class="mt-1 text-xs text-slate-500">Accepted formats: .xlsx, .xls, .csv — Max size: 5MB</p>
                </div>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-file-upload"></i> Import Employees
                </button>
            </form>
        </div>
    </div>

    {{-- Column Guide --}}
    <div>
        <div class="card p-6">
            <h2 class="text-base font-semibold text-slate-800 mb-4">
                <i class="fas fa-info-circle text-blue-500 mr-1"></i> Column Guide
            </h2>
            <p class="text-xs text-slate-500 mb-3">Your spreadsheet must have a header row with these column names:</p>
            <div class="space-y-2">
                @php
                $columns = [
                    ['col' => 'first_name',       'req' => true,  'note' => 'Employee first name'],
                    ['col' => 'last_name',         'req' => true,  'note' => 'Employee last name'],
                    ['col' => 'email',             'req' => true,  'note' => 'Work email (unique)'],
                    ['col' => 'emp_number',        'req' => false, 'note' => 'e.g. EMP0001 (auto if blank)'],
                    ['col' => 'department',        'req' => false, 'note' => 'Must match existing dept name'],
                    ['col' => 'designation',       'req' => false, 'note' => 'Must match existing designation'],
                    ['col' => 'hire_date',         'req' => false, 'note' => 'YYYY-MM-DD format'],
                    ['col' => 'employment_type',   'req' => false, 'note' => 'full_time / part_time / contract / intern'],
                ];
                @endphp
                @foreach($columns as $c)
                <div class="flex items-start gap-2">
                    <code class="text-xs bg-slate-100 px-2 py-0.5 rounded text-blue-700 shrink-0">{{ $c['col'] }}</code>
                    <span class="text-xs text-slate-500">
                        {{ $c['note'] }}
                        @if($c['req'])<span class="text-red-500 font-semibold">*</span>@endif
                    </span>
                </div>
                @endforeach
            </div>
            <p class="mt-4 text-xs text-slate-400">* Required fields. Default password for imported users: <code class="bg-slate-100 px-1 rounded">Password@123</code></p>
        </div>

        <div class="card p-6 mt-4">
            <h2 class="text-base font-semibold text-slate-800 mb-3">
                <i class="fas fa-download text-green-500 mr-1"></i> Export Existing Data
            </h2>
            <div class="space-y-2">
                <a href="{{ route('export.employees') }}" class="btn-secondary w-full justify-center text-sm">
                    <i class="fas fa-file-excel text-green-600"></i> Export Employees
                </a>
                <a href="{{ route('export.attendance') }}" class="btn-secondary w-full justify-center text-sm">
                    <i class="fas fa-file-excel text-green-600"></i> Export Attendance
                </a>
                <a href="{{ route('export.leave') }}" class="btn-secondary w-full justify-center text-sm">
                    <i class="fas fa-file-excel text-green-600"></i> Export Leave Records
                </a>
            </div>
        </div>
    </div>

</div>
@endsection
