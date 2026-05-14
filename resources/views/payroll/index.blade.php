@extends("layouts.app")
@section("title","Payroll Runs")
@section("content")

<x-page-header title="Payroll Runs" subtitle="Process and manage payroll by client company">
    <a href="{{ route('payroll.create') }}" class="btn-primary"><i class="fas fa-plus mr-1"></i> New Payroll Run</a>
    <a href="{{ route('salary.index') }}" class="btn-secondary"><i class="fas fa-money-bill-wave mr-1"></i> Salary Setup</a>
    <a href="{{ route('salary.components') }}" class="btn-secondary"><i class="fas fa-list mr-1"></i> Components</a>
</x-page-header>
<x-alert/>

{{-- Filters --}}
<form class="flex flex-wrap gap-2 mb-5" method="GET">
    <select name="client_id" class="form-select w-48">
        <option value="">All Companies</option>
        @foreach($clients as $c)
        <option value="{{ $c->id }}" {{ request('client_id') == $c->id ? 'selected' : '' }}>{{ $c->company_name }}</option>
        @endforeach
    </select>
    <select name="year" class="form-select w-28">
        <option value="">All Years</option>
        @for($y = date('Y'); $y >= date('Y')-3; $y--)
        <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>{{ $y }}</option>
        @endfor
    </select>
    <select name="status" class="form-select w-36">
        <option value="">All Statuses</option>
        @foreach(['draft'=>'Draft','processing'=>'Processing','processed'=>'Processed','approved'=>'Approved','paid'=>'Paid'] as $v=>$l)
        <option value="{{ $v }}" {{ request('status') == $v ? 'selected' : '' }}>{{ $l }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn-primary"><i class="fas fa-search mr-1"></i>Filter</button>
    @if(request()->hasAny(['client_id','year','status']))
    <a href="{{ route('payroll.index') }}" class="btn-secondary">Clear</a>
    @endif
</form>

<x-data-table>
    <thead class="bg-slate-50">
        <tr>
            <th class="table-head px-6 py-3 text-left">Payroll Run</th>
            <th class="table-head px-4 py-3 text-left">Client Company</th>
            <th class="table-head px-4 py-3 text-center">Staff</th>
            <th class="table-head px-4 py-3 text-right">Gross</th>
            <th class="table-head px-4 py-3 text-right">Net Pay</th>
            <th class="table-head px-4 py-3 text-left">Status</th>
            <th class="table-head px-4 py-3 text-left">Actions</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($runs as $run)
        <tr class="table-row {{ $run->isLocked() ? 'bg-red-50/30' : '' }}">
            <td class="px-6 py-3">
                <p class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                    @if($run->isLocked())
                    <i class="fas fa-lock text-red-400 text-xs"></i>
                    @endif
                    {{ $run->title }}
                </p>
                <p class="text-xs text-slate-400">{{ date('F Y', mktime(0,0,0,$run->month,1,$run->year)) }}</p>
                @if($run->payment_date)
                <p class="text-xs text-emerald-600"><i class="fas fa-calendar-check mr-1"></i>Paid: {{ $run->payment_date->format('d M Y') }}</p>
                @endif
            </td>
            <td class="px-4 py-3">
                @if($run->client)
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium bg-indigo-50 text-indigo-700">
                    <i class="fas fa-building text-xs"></i> {{ $run->client->company_name }}
                </span>
                @else
                <span class="text-xs text-slate-400 italic">General / No client</span>
                @endif
            </td>
            <td class="px-4 py-3 text-center text-sm font-semibold text-slate-800">{{ $run->payslips_count }}</td>
            <td class="px-4 py-3 text-right text-sm text-slate-700">
                UGX {{ number_format($run->payslips->sum('gross_salary'), 0) }}
            </td>
            <td class="px-4 py-3 text-right text-sm font-semibold text-emerald-700">
                UGX {{ number_format($run->payslips->sum('net_salary'), 0) }}
            </td>
            <td class="px-4 py-3">{!! $run->status_badge !!}</td>
            <td class="px-4 py-3">
                <div class="flex gap-1 flex-wrap">
                    <a href="{{ route('payroll.show', $run) }}"
                       class="btn-xs btn-blue"><i class="fas fa-eye"></i></a>

                    @if(!$run->isLocked() && in_array($run->status, ['draft','processing']))
                    <form method="POST" action="{{ route('payroll.process', $run) }}" class="inline">
                        @csrf
                        <button class="btn-xs btn-green" title="Process"><i class="fas fa-play"></i></button>
                    </form>
                    @endif

                    @if(!$run->isLocked() && $run->status === 'processed')
                    <form method="POST" action="{{ route('payroll.lock', $run) }}" class="inline">
                        @csrf
                        <button class="btn-xs bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-md px-2 py-1 text-xs" title="Lock">
                            <i class="fas fa-lock"></i>
                        </button>
                    </form>
                    @endif

                    @if($run->isLocked() && auth()->user()->hasRole('super-admin'))
                    <form method="POST" action="{{ route('payroll.unlock', $run) }}" class="inline"
                          onsubmit="return confirm('Unlock this payroll run? This will allow changes.')">
                        @csrf
                        <button class="btn-xs bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 rounded-md px-2 py-1 text-xs" title="Unlock (Super Admin)">
                            <i class="fas fa-unlock"></i>
                        </button>
                    </form>
                    @endif
                </div>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="7" class="py-14 text-center text-slate-400">
                <i class="fas fa-money-check-alt text-3xl opacity-30 block mb-2"></i>
                No payroll runs found. <a href="{{ route('payroll.create') }}" class="text-blue-600 underline">Create one</a>.
            </td>
        </tr>
        @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $runs->withQueryString()->links() }}</div>
@endsection
