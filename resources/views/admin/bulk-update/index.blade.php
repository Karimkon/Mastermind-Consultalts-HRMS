@extends('layouts.app')
@section('title','Bulk Update Staff')
@section('content')

<x-page-header title="Bulk Update Staff"
    subtitle="Fill in details on people who are already on the system">
    <a href="{{ route('employees.index') }}" class="btn-secondary">
        <i class="fas fa-users mr-1"></i> Employee Central
    </a>
</x-page-header>

@foreach(['success' => ['green','check-circle'], 'error' => ['red','circle-exclamation']] as $key => [$c,$icon])
    @if(session($key))
    <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-{{ $c }}-50 border border-{{ $c }}-200 rounded-lg text-{{ $c }}-700 text-sm">
        <i class="fas fa-{{ $icon }}"></i> {{ session($key) }}
    </div>
    @endif
@endforeach

{{-- What is actually missing, so it is obvious what is worth sending. --}}
<div class="card p-5 mb-5">
    <h3 class="font-semibold text-slate-800 mb-1">What is missing right now</h3>
    <p class="text-xs text-slate-500 mb-4">Across {{ number_format($gaps['total']) }} active staff.</p>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        @foreach([
            'bank_account' => ['Bank account', 'Cannot be paid by transfer', 'rose'],
            'manager_id'   => ['Line manager', 'Appraisals cannot route', 'amber'],
            'designation'  => ['Job title', '', 'amber'],
            'phone'        => ['Phone number', '', 'slate'],
            'nssf_number'  => ['NSSF number', '', 'slate'],
            'tin_number'   => ['TIN', '', 'slate'],
        ] as $key => [$label, $why, $colour])
        <div class="rounded-xl border border-{{ $colour }}-200 bg-{{ $colour }}-50 p-3">
            <p class="text-2xl font-bold text-{{ $colour }}-700">{{ number_format($gaps[$key]) }}</p>
            <p class="text-xs font-medium text-slate-700">{{ $label }}</p>
            @if($why)<p class="text-[11px] text-{{ $colour }}-600 mt-1">{{ $why }}</p>@endif
            @if($gaps[$key] > 0)
            <a href="{{ route('admin.bulk-update.template', ['missing' => $key]) }}"
               class="text-[11px] text-blue-600 hover:underline mt-2 inline-block">
                <i class="fas fa-download mr-1"></i>Get the sheet
            </a>
            @endif
        </div>
        @endforeach
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

    {{-- 1. Download --}}
    <div class="card p-5">
        <h3 class="font-semibold text-slate-800 mb-2">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-white text-xs mr-1">1</span>
            Download the sheet
        </h3>
        <p class="text-sm text-slate-600 mb-3">
            The staff numbers and names are already filled in, so nobody has to type 800 of them.
            Fill in the blank columns and leave the rest alone.
        </p>

        <a href="{{ route('admin.bulk-update.template') }}" class="btn-secondary text-sm">
            <i class="fas fa-file-csv mr-1"></i> All active staff
        </a>

        <div class="mt-4 text-xs text-slate-500 space-y-1">
            <p><strong class="text-slate-700">A blank cell changes nothing.</strong>
               Send bank accounts today and line managers next week — the second sheet
               will not wipe the first.</p>
            <p><strong class="text-slate-700">Nobody is created.</strong>
               A staff number that is not already on the register is reported and skipped,
               so a typo cannot add a second record beside a real person.</p>
            <p><strong class="text-slate-700">Line manager and job title</strong> are given as
               the manager's <em>staff number</em> and the job title exactly as it is spelt on the system.</p>
        </div>
    </div>

    {{-- 2. Upload --}}
    <div class="card p-5">
        <h3 class="font-semibold text-slate-800 mb-2">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-white text-xs mr-1">2</span>
            Send it back
        </h3>
        <p class="text-sm text-slate-600 mb-3">
            You will see exactly what would change before anything is written.
        </p>

        <form method="POST" action="{{ route('admin.bulk-update.preview') }}" enctype="multipart/form-data">
            @csrf
            <input type="file" name="csv_file" accept=".csv,text/csv" required
                   class="form-input text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm">

            @error('csv_file')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror

            <button class="btn-primary mt-3 text-sm">
                <i class="fas fa-eye mr-1"></i> Check the sheet
            </button>
        </form>

        <p class="text-xs text-slate-400 mt-3">
            CSV, up to 10MB. Columns are matched by heading, so they can be reordered
            or left out.
        </p>
    </div>
</div>

<div class="card p-5 mt-5">
    <h3 class="font-semibold text-slate-800 mb-3">Columns it reads</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-3 py-2 font-semibold text-slate-600">Column</th>
                    <th class="px-3 py-2 font-semibold text-slate-600">What it sets</th>
                    <th class="px-3 py-2 font-semibold text-slate-600">Also accepts</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach([
                    ['emp_number', 'Which staff member the row is about — required', 'staff_number, employee_number, staff_no'],
                    ['bank_account', 'Bank account number', 'account_number, account_no, bank_acc'],
                    ['bank_name', 'Bank', 'bank'],
                    ['bank_branch', 'Branch', 'branch'],
                    ['payment_mode', 'bank / mtn / airtel / cash / cheque', ''],
                    ['mobile_money_number', 'Mobile money number', 'momo, mobile_money'],
                    ['manager_emp_number', "Line manager, as their staff number", 'manager, supervisor, reports_to, line_manager'],
                    ['designation', 'Job title, spelt as it is on the system', 'job_title, title, position'],
                    ['phone', 'Phone number', 'telephone, mobile'],
                    ['national_id', 'NIN', 'nin'],
                    ['nssf_number', 'NSSF number', 'nssf'],
                    ['tin_number', 'TIN', 'tin'],
                    ['country', 'Country', ''],
                ] as [$col, $means, $alias])
                <tr>
                    <td class="px-3 py-2 font-mono text-slate-800">{{ $col }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ $means }}</td>
                    <td class="px-3 py-2 text-slate-400">{{ $alias ?: '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-xs text-slate-500 mt-3">
        <code class="text-slate-700">full_name</code> is in the download so you can see who each row is.
        It is never written back — names are edited in Employee Central.
    </p>
</div>
@endsection
