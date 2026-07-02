@extends("layouts.app")
@section("title", $activeClient ? $activeClient->company_name . ' — Employees' : 'All Employees')
@section("content")

<x-page-header
    :title="$activeClient ? $activeClient->company_name . ' — Employees' : 'All Employees'"
    :subtitle="$activeClient ? 'Showing employees for ' . $activeClient->company_name . ' only' : 'Employees across all your assigned companies'">
    <div class="flex gap-2 flex-wrap">
        {{-- Export CSV --}}
        <a href="{{ route('account-manager.employees.export', $clientId ? ['client_id'=>$clientId] : []) }}"
           class="btn-secondary text-sm">
            <i class="fas fa-file-csv mr-1 text-green-600"></i> CSV
        </a>
        {{-- Export Excel --}}
        <a href="{{ route('account-manager.employees.export-excel', $clientId ? ['client_id'=>$clientId] : []) }}"
           class="btn-secondary text-sm">
            <i class="fas fa-file-excel mr-1 text-emerald-600"></i> Excel
        </a>
        {{-- Update Import (existing employees) --}}
        <button onclick="document.getElementById('import-modal').classList.remove('hidden')" class="btn-secondary text-sm">
            <i class="fas fa-file-upload mr-1 text-blue-500"></i> Update Employees
        </button>
        {{-- Full Import (add new employees) --}}
        <button onclick="document.getElementById('full-import-modal').classList.remove('hidden')" class="btn-primary text-sm">
            <i class="fas fa-users mr-1"></i> Add Employees (Bulk)
        </button>
        <a href="{{ route('account-manager.dashboard') }}" class="btn-secondary text-sm">
            <i class="fas fa-arrow-left mr-1"></i> Dashboard
        </a>
    </div>
</x-page-header>

{{-- Import Modal --}}
<div id="import-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between p-5 border-b border-slate-100">
            <h3 class="font-bold text-slate-800 text-base"><i class="fas fa-file-upload mr-2 text-blue-500"></i>Import Employee Data</h3>
            <button onclick="document.getElementById('import-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-5">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 text-sm text-blue-700">
                <i class="fas fa-info-circle mr-1"></i>
                Upload a CSV file to update employee contact info and status in bulk.
                Updatable fields: <strong>Phone, Address, City, Status, Emergency Contact</strong>.
            </div>
            <a href="{{ route('account-manager.employees.import-template') }}"
               class="text-sm text-blue-600 hover:underline flex items-center gap-1 mb-4">
                <i class="fas fa-download"></i> Download CSV Template
            </a>
            <form method="POST" action="{{ route('account-manager.employees.import') }}" enctype="multipart/form-data">
                @csrf
                @if($clientId)<input type="hidden" name="client_id" value="{{ $clientId }}">@endif
                <div class="mb-4">
                    <label class="form-label">CSV File</label>
                    <input type="file" name="csv_file" accept=".csv,.txt" class="form-input w-full" required>
                </div>
                <div class="flex gap-3 justify-end">
                    <button type="button" onclick="document.getElementById('import-modal').classList.add('hidden')" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary"><i class="fas fa-upload mr-1"></i>Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Full Import Modal --}}
<div id="full-import-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
        <div class="flex items-center justify-between p-5 border-b border-slate-100">
            <h3 class="font-bold text-slate-800 text-base">
                <i class="fas fa-users mr-2 text-blue-500"></i>Bulk Add / Update Employees
            </h3>
            <button onclick="document.getElementById('full-import-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-5">
            <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-3 mb-4 text-sm text-emerald-800">
                <strong><i class="fas fa-check-circle mr-1"></i>This import creates NEW employees</strong> and assigns them to the selected client.
                If an employee number already exists, it will be updated instead.<br>
                Salary rates (daily/hourly/monthly) are also set from the file.
            </div>

            <div class="grid grid-cols-2 gap-3 mb-4 text-xs text-slate-600 bg-slate-50 rounded-lg p-3 border">
                <div>
                    <p class="font-semibold text-slate-700 mb-1">Required columns:</p>
                    <ul class="space-y-0.5">
                        <li><i class="fas fa-dot-circle text-red-400 mr-1 text-xs"></i>first_name</li>
                        <li><i class="fas fa-dot-circle text-red-400 mr-1 text-xs"></i>last_name</li>
                        <li><i class="fas fa-dot-circle text-red-400 mr-1 text-xs"></i>employment_type</li>
                        <li><i class="fas fa-dot-circle text-red-400 mr-1 text-xs"></i>salary_type</li>
                        <li><i class="fas fa-dot-circle text-red-400 mr-1 text-xs"></i>rate</li>
                    </ul>
                </div>
                <div>
                    <p class="font-semibold text-slate-700 mb-1">Optional columns:</p>
                    <ul class="space-y-0.5">
                        <li><i class="fas fa-circle text-slate-300 mr-1 text-xs"></i>emp_number (auto if blank)</li>
                        <li><i class="fas fa-circle text-slate-300 mr-1 text-xs"></i>department, designation</li>
                        <li><i class="fas fa-circle text-slate-300 mr-1 text-xs"></i>phone, national_id</li>
                        <li><i class="fas fa-circle text-slate-300 mr-1 text-xs"></i>bank / mobile money</li>
                        <li><i class="fas fa-circle text-slate-300 mr-1 text-xs"></i>charge_paye, charge_nssf</li>
                    </ul>
                </div>
            </div>

            <a href="{{ route('account-manager.employees.full-import-template') }}"
               class="text-sm text-blue-600 hover:underline flex items-center gap-1 mb-4">
                <i class="fas fa-download"></i> Download Full Import Template (CSV)
            </a>

            <form method="POST" action="{{ route('account-manager.employees.full-import') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Client Company *</label>
                    <select name="client_id" class="form-input" required>
                        <option value="">— Select Client —</option>
                        @foreach($clients as $c)
                        <option value="{{ $c->id }}" @selected($clientId === $c->id)>{{ $c->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">CSV File *</label>
                    <input type="file" name="csv_file" accept=".csv,.txt" class="form-input w-full" required>
                </div>
                <div class="flex gap-3 justify-end">
                    <button type="button" onclick="document.getElementById('full-import-modal').classList.add('hidden')" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary"><i class="fas fa-users mr-1"></i> Import Employees</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Company filter tabs --}}
<div class="flex flex-wrap gap-2 mb-5 items-center">
    <a href="{{ route('account-manager.employees') }}"
       class="px-4 py-2 rounded-lg text-sm font-medium border transition
              {{ !$clientId ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
        <i class="fas fa-globe-africa mr-1"></i> All Companies
    </a>
    @foreach($clients as $c)
    <a href="{{ route('account-manager.employees', ['client_id' => $c->id]) }}"
       class="px-4 py-2 rounded-lg text-sm font-medium border transition flex items-center gap-2
              {{ $clientId === $c->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
        <span class="w-2 h-2 rounded-full {{ $c->status === 'active' ? 'bg-emerald-400' : 'bg-slate-300' }}"></span>
        {{ $c->company_name }}
        <span class="ml-1 text-xs {{ $clientId === $c->id ? 'bg-indigo-500 text-white' : 'bg-slate-100 text-slate-500' }} px-1.5 py-0.5 rounded-full">
            {{ $c->employees->count() }}
        </span>
    </a>
    @endforeach
</div>

{{-- Search / status filter --}}
<form class="flex flex-wrap gap-2 mb-5" method="GET">
    @if($clientId)<input type="hidden" name="client_id" value="{{ $clientId }}">@endif
    <input type="text" name="search" value="{{ request('search') }}"
           class="form-input w-56" placeholder="Search name or ID...">
    <select name="status" class="form-select w-36">
        <option value="">All Status</option>
        @foreach(['active'=>'Active','on_leave'=>'On Leave','suspended'=>'Suspended'] as $v=>$l)
        <option value="{{ $v }}" {{ request('status') == $v ? 'selected' : '' }}>{{ $l }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn-primary"><i class="fas fa-search mr-1"></i>Filter</button>
    @if(request('search') || request('status'))
    <a href="{{ route('account-manager.employees', $clientId ? ['client_id'=>$clientId] : []) }}" class="btn-secondary">Clear</a>
    @endif
</form>

@if($activeClient && $activeClient->work_site_address)
<div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700 flex items-center gap-2">
    <i class="fas fa-map-marker-alt"></i>
    Work site: <strong>{{ $activeClient->work_site_address }}</strong>
    @if($activeClient->geo_fence_radius)
    &nbsp;·&nbsp; Geo-fence radius: <strong>{{ $activeClient->geo_fence_radius }}m</strong>
    @endif
</div>
@endif

<x-data-table>
    <thead class="bg-slate-50">
        <tr>
            <th class="table-head px-6 py-3 text-left">Employee</th>
            <th class="table-head px-4 py-3 text-left">Department</th>
            @if(!$clientId)<th class="table-head px-4 py-3 text-left">Company</th>@endif
            <th class="table-head px-4 py-3 text-left">Contact</th>
            <th class="table-head px-4 py-3 text-left">Status</th>
            <th class="table-head px-4 py-3 text-left">Actions</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($employees as $emp)
        @php $company = $clients->first(fn($c) => $c->employees->contains('id', $emp->id)); @endphp
        <tr class="table-row">
            <td class="px-6 py-3">
                <div class="flex items-center gap-3">
                    <img src="{{ $emp->avatar_url }}" class="w-9 h-9 rounded-full object-cover ring-2 ring-slate-100">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">{{ $emp->full_name }}</p>
                        <p class="text-xs text-slate-400">{{ $emp->emp_number }}</p>
                        @if($emp->designation)
                        <p class="text-xs text-slate-400">{{ $emp->designation->name }}</p>
                        @endif
                    </div>
                </div>
            </td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $emp->department?->name ?? '—' }}</td>
            @if(!$clientId)
            <td class="px-4 py-3">
                @if($company)
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                    <i class="fas fa-building text-indigo-400 text-xs"></i>
                    {{ $company->company_name }}
                </span>
                @else
                <span class="text-slate-400 text-xs">—</span>
                @endif
            </td>
            @endif
            <td class="px-4 py-3 text-sm text-slate-500">
                @if($emp->user?->email)
                <p class="text-xs">{{ $emp->user->email }}</p>
                @endif
                @if($emp->phone)
                <p class="text-xs">{{ $emp->phone }}</p>
                @endif
            </td>
            <td class="px-4 py-3">{!! $emp->status_badge !!}</td>
            <td class="px-4 py-3">
                <a href="{{ route('account-manager.employees.show', $emp) }}"
                   class="btn-xs btn-blue"><i class="fas fa-eye mr-1"></i>View</a>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="{{ $clientId ? 5 : 6 }}" class="py-14 text-center text-slate-400">
                <i class="fas fa-users text-3xl opacity-30 block mb-2"></i>
                No employees found{{ $activeClient ? ' for ' . $activeClient->company_name : '' }}.
            </td>
        </tr>
        @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $employees->withQueryString()->links() }}</div>
@endsection

