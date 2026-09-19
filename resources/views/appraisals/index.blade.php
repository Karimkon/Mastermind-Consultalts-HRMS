@extends('layouts.app')
@section('title','Appraisals')
@section('content')

<x-page-header title="Balanced Scorecard — Appraisals" subtitle="Individual Balanced Score Cards">
    @if($canSetUp)
    <a href="{{ route('appraisals.create') }}" class="btn-primary"><i class="fas fa-plus mr-1"></i> Start an Appraisal</a>
    @endif
    @role('super-admin|hr-admin')
    <a href="{{ route('admin.appraisal-templates.index') }}" class="btn-secondary">
        <i class="fas fa-sliders mr-1"></i> Weighting
    </a>
    @endrole
</x-page-header>

{{-- The five stages, so anyone landing here can see where they fit without
     being told. Shown to the people who can start one. --}}
@if($canSetUp && $appraisals->total() === 0)
<div class="card p-6 mb-5">
    <h3 class="font-semibold text-slate-800 mb-1">How an appraisal works</h3>
    <p class="text-sm text-slate-500 mb-5">
        Five stages. The card moves from one person to the next, and whoever needs to act is notified.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-6">
        @foreach([
            ['1','Supervisor sets the KPIs','Pick the employee, add their key result areas, targets and weights — totalling 100%.','fa-list-check','blue'],
            ['2','Send to the appraiser','Usually the account manager who runs that site. They get a notification.','fa-paper-plane','purple'],
            ['3','Appraiser scores it','Fills in actuals, rates each KPI 1–5 and attaches evidence.','fa-star','amber'],
            ['4','Manager confirms','It returns to whoever started it — or to HR or the MD if the appraiser redirects it.','fa-check-double','emerald'],
            ['5','Employee self-appraises','They add their own comments and sign it off.','fa-signature','slate'],
        ] as [$n,$title,$body,$icon,$colour])
        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50">
            <div class="flex items-center gap-2 mb-2">
                <span class="w-7 h-7 rounded-full bg-{{ $colour }}-100 text-{{ $colour }}-700 flex items-center justify-center text-xs font-bold">{{ $n }}</span>
                <i class="fas {{ $icon }} text-{{ $colour }}-500 text-sm"></i>
            </div>
            <p class="text-sm font-semibold text-slate-800 leading-snug">{{ $title }}</p>
            <p class="text-xs text-slate-500 mt-1 leading-snug">{{ $body }}</p>
        </div>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-slate-100">
        <a href="{{ route('appraisals.create') }}" class="btn-primary">
            <i class="fas fa-plus mr-1"></i> Start an Appraisal
        </a>
        @role('super-admin|hr-admin')
        <a href="{{ route('admin.appraisal-templates.index') }}" class="btn-secondary">
            <i class="fas fa-sliders mr-1"></i> Set the weighting first
        </a>
        <p class="text-xs text-slate-500">
            Templates let you fix the perspective split per role — e.g. Operations Officer 30 / 35 / 25 / 10 —
            so supervisors start from your policy instead of a blank card.
        </p>
        @endrole
    </div>
</div>
@endif

@foreach(['success' => ['green','check-circle'], 'error' => ['red','circle-exclamation']] as $key => [$c,$icon])
    @if(session($key))
    <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-{{ $c }}-50 border border-{{ $c }}-200 rounded-lg text-{{ $c }}-700 text-sm">
        <i class="fas fa-{{ $icon }}"></i> {{ session($key) }}
    </div>
    @endif
@endforeach

{{-- What is sitting with me right now. This is the bit people actually act on. --}}
@if($mine->isNotEmpty())
<div class="mb-5 p-4 bg-blue-50 border border-blue-200 rounded-xl">
    <p class="font-semibold text-blue-900 mb-2">
        <i class="fas fa-inbox mr-1"></i> {{ $mine->count() }} appraisal(s) waiting on you
    </p>
    <div class="space-y-2">
        @foreach($mine as $a)
        <a href="{{ route('appraisals.show', $a) }}"
           class="flex items-center justify-between p-3 bg-white border border-blue-200 rounded-lg hover:bg-blue-50">
            <div>
                <p class="text-sm font-medium text-slate-800">{{ $a->employee?->full_name }} — {{ $a->title }}</p>
                <p class="text-xs text-slate-500">{{ $a->statusLabel() }}</p>
            </div>
            <i class="fas fa-arrow-right text-blue-500"></i>
        </a>
        @endforeach
    </div>
</div>
@endif

<x-filter-bar :action="route('appraisals.index')">
    <div><label class="form-label">Status</label>
        <select name="status" class="form-select w-48">
            <option value="">All</option>
            @foreach(['draft'=>'Setting KPIs','with_appraiser'=>'With appraiser','with_manager'=>'Awaiting manager','with_employee'=>'Awaiting employee','completed'=>'Completed'] as $v=>$l)
                <option value="{{ $v }}" {{ request('status')===$v?'selected':'' }}>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div><label class="form-label">Type</label>
        <select name="type" class="form-select w-40">
            <option value="">All</option>
            <option value="internal" {{ request('type')==='internal'?'selected':'' }}>Internal</option>
            <option value="external" {{ request('type')==='external'?'selected':'' }}>External (client)</option>
        </select>
    </div>
    <div><label class="form-label">Year</label>
        <select name="year" class="form-select w-28">
            <option value="">All</option>
            @for($y = now()->year - 2; $y <= now()->year + 1; $y++)
                <option value="{{ $y }}" {{ request('year')==$y?'selected':'' }}>{{ $y }}</option>
            @endfor
        </select>
    </div>
</x-filter-bar>

<x-data-table>
    <thead class="bg-slate-50"><tr>
        <th class="table-head px-6 py-3 text-left">Employee</th>
        <th class="table-head px-4 py-3 text-left">Appraisal</th>
        <th class="table-head px-4 py-3 text-left">Type</th>
        <th class="table-head px-4 py-3 text-left">Appraiser</th>
        <th class="table-head px-4 py-3 text-center">Weights</th>
        <th class="table-head px-4 py-3 text-center">Score</th>
        <th class="table-head px-4 py-3 text-left">Status</th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($appraisals as $a)
        <tr class="table-row cursor-pointer" onclick="window.location='{{ route('appraisals.show', $a) }}'">
            <td class="px-6 py-3">
                <p class="text-sm font-medium text-slate-800">{{ $a->employee?->full_name ?? '—' }}</p>
                <p class="text-xs text-slate-400">{{ $a->employee?->emp_number }}</p>
            </td>
            <td class="px-4 py-3 text-sm text-slate-600">
                {{ $a->title }}
                <span class="block text-xs text-slate-400">{{ $a->period }} {{ $a->year }}</span>
            </td>
            <td class="px-4 py-3">
                <span class="badge-{{ $a->type === 'external' ? 'purple' : 'blue' }}">
                    {{ $a->type === 'external' ? 'External' : 'Internal' }}
                </span>
            </td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ $a->appraiser?->name ?? '—' }}</td>
            <td class="px-4 py-3 text-center text-sm">
                @php $tw = $a->totalWeight(); @endphp
                <span class="{{ abs($tw - 100) < 0.01 ? 'text-emerald-600 font-semibold' : 'text-amber-600' }}">
                    {{ rtrim(rtrim(number_format($tw, 2), '0'), '.') }}%
                </span>
            </td>
            <td class="px-4 py-3 text-center text-sm font-semibold text-slate-800">
                {{ $a->overall_percent !== null ? number_format($a->overall_percent, 0) . '%' : '—' }}
            </td>
            <td class="px-4 py-3"><span class="badge-{{ $a->statusBadge() }}">{{ $a->statusLabel() }}</span></td>
        </tr>
        @empty
        <tr><td colspan="7" class="py-12 text-center">
            <i class="fas fa-clipboard-list text-3xl text-slate-300 mb-3 block"></i>
            <p class="text-slate-500 text-sm">No appraisals yet.</p>
            @if($canSetUp)
            <a href="{{ route('appraisals.create') }}" class="btn-primary mt-3 inline-flex">
                <i class="fas fa-plus mr-1"></i> Start the first one
            </a>
            @else
            <p class="text-slate-400 text-xs mt-1">Your appraisal will appear here once your manager starts it.</p>
            @endif
        </td></tr>
        @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $appraisals->links() }}</div>
@endsection
