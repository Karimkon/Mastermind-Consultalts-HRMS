@extends('layouts.app')
@section('title', 'Organisational Structure')

@section('content')
<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Organisational Structure</h1>
        <p class="text-sm text-slate-500">Mastermind's chain of command, top to bottom.</p>
    </div>
    <p class="hidden sm:block text-xs text-slate-400">
        <i class="fas fa-arrows-left-right"></i> Scroll sideways to see the full chart
    </p>
</div>

{{-- Gaps are shown rather than hidden: a chart that looks complete while people
     have no supervisor is worse than no chart. Only Head Office is counted for
     positions - staff placed at a client site report to their account manager
     and are not expected to hold a post on Mastermind's own chart. --}}
@if($unassigned > 0 || $noPosition > 0)
<div class="card px-3 py-2 mb-4 bg-amber-50 border-amber-200">
    <p class="text-xs text-amber-900 flex items-center flex-wrap gap-x-3 gap-y-1">
        <span><i class="fas fa-triangle-exclamation text-amber-500"></i>
              <span class="font-semibold">Not yet complete</span></span>
        @if($unassigned > 0)
            <span>{{ number_format($unassigned) }} without a supervisor</span>
        @endif
        @if($noPosition > 0)
            <span>{{ number_format($noPosition) }} Head Office staff hold no position</span>
        @endif
    </p>
</div>
@endif

@if(! $root)
    <div class="card p-10 text-center text-slate-400">
        <i class="fas fa-sitemap text-4xl mb-3"></i>
        <p class="font-medium text-slate-600">No structure has been created yet.</p>
        <p class="text-sm mt-1">Run <code class="px-1.5 py-0.5 bg-slate-100 rounded">php artisan hrms:seed-org-structure</code> to build it.</p>
    </div>
@else
    <div class="card p-4 overflow-x-auto">
        <ul class="org-chart">
            @include('org-structure.partials.node', ['node' => $root, 'headcount' => $headcount])
        </ul>
    </div>
@endif

@push('styles')
<style>
    [x-cloak] { display: none !important; }

    /* A landscape org chart drawn with nothing but nested lists and borders:
       each level is a flex row, and the connectors are the top/left edges of
       the list items. No charting library, and it stays readable if the CSS
       ever fails to load - it degrades to a plain nested list. */
    .org-chart, .org-chart ul { list-style: none; margin: 0; padding: 0; }

    .org-chart ul {
        display: flex;
        justify-content: center;
        padding-top: 0.85rem;
        position: relative;
    }

    .org-chart li {
        position: relative;
        padding: 0.85rem 0.35rem 0 0.35rem;
        text-align: center;
    }

    /* The two halves of the horizontal rule joining siblings. */
    .org-chart li::before,
    .org-chart li::after {
        content: '';
        position: absolute;
        top: 0;
        width: 50%;
        height: 0.85rem;
        border-top: 1px solid #cbd5e1;
    }
    .org-chart li::before { right: 50%; }
    .org-chart li::after  { left: 50%; border-left: 1px solid #cbd5e1; }

    /* An only child needs a straight drop, not a T-piece. */
    .org-chart li:only-child { padding-top: 0.85rem; }
    .org-chart li:only-child::before,
    .org-chart li:only-child::after { display: none; }

    /* The run of rule must stop at the first and last sibling. */
    .org-chart li:first-child::before,
    .org-chart li:last-child::after { border: 0 none; }
    .org-chart li:last-child::before { border-right: 1px solid #cbd5e1; border-radius: 0 6px 0 0; }
    .org-chart li:first-child::after { border-radius: 6px 0 0 0; }

    /* The stem dropping from a parent into its children's rule. */
    .org-chart ul::before {
        content: '';
        position: absolute;
        top: 0;
        left: 50%;
        width: 0;
        height: 0.85rem;
        border-left: 1px solid #cbd5e1;
    }
    /* The outermost list has no parent above it to join. */
    .org-chart > li:only-child > ul::before { top: 0; }
    .org-chart.org-chart > li::before,
    .org-chart.org-chart > li::after { display: none; }

    .org-node { display: inline-block; }
</style>
@endpush
@endsection
