@extends('layouts.app')
@section('title', 'Reports')
@section('content')
<x-page-header title="Reports & Analytics" subtitle="Generate and export HR data reports">
    <a href="{{ route('export.employees') }}" class="btn-secondary"><i class="fas fa-file-excel text-green-600"></i> Export Employees</a>
    <a href="{{ route('export.attendance') }}" class="btn-secondary"><i class="fas fa-file-excel text-green-600"></i> Export Attendance</a>
    <a href="{{ route('export.leave') }}" class="btn-secondary"><i class="fas fa-file-excel text-green-600"></i> Export Leave</a>
    <a href="{{ route('import.form') }}" class="btn-secondary"><i class="fas fa-file-upload text-blue-600"></i> Import</a>
</x-page-header>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    @php
    $reportCards = [
        ['title'=>'Employee Report', 'icon'=>'fa-users', 'color'=>'blue', 'desc'=>'Headcount, status, department breakdown', 'route'=>'reports.employees'],
        ['title'=>'Attendance Report', 'icon'=>'fa-clock', 'color'=>'green', 'desc'=>'Attendance summary, late arrivals, absences', 'route'=>'reports.attendance'],
        ['title'=>'Leave Report', 'icon'=>'fa-calendar-minus', 'color'=>'yellow', 'desc'=>'Leave taken, balances, types breakdown', 'route'=>'reports.leave'],
        ['title'=>'Payroll Report', 'icon'=>'fa-money-bill-wave', 'color'=>'purple', 'desc'=>'Payroll runs, gross/net, tax summary', 'route'=>'reports.payroll'],
        ['title'=>'Performance Report', 'icon'=>'fa-chart-line', 'color'=>'indigo', 'desc'=>'KPI scores, review summaries by cycle', 'route'=>'reports.performance'],
        ['title'=>'Training Report', 'icon'=>'fa-graduation-cap', 'color'=>'teal', 'desc'=>'Course completion, certifications', 'route'=>'reports.training'],
    ];
    $colorMap = ['blue'=>'bg-blue-100 text-blue-600','green'=>'bg-green-100 text-green-600','yellow'=>'bg-yellow-100 text-yellow-600','purple'=>'bg-purple-100 text-purple-600','indigo'=>'bg-indigo-100 text-indigo-600','teal'=>'bg-teal-100 text-teal-600'];
    @endphp
    @foreach($reportCards as $card)
    <a href="{{ route($card['route']) }}" class="card p-6 hover:shadow-md transition-shadow group">
        <div class="flex items-center gap-4 mb-3">
            <div class="w-12 h-12 rounded-xl {{ $colorMap[$card['color']] }} flex items-center justify-center group-hover:scale-110 transition-transform">
                <i class="fas {{ $card['icon'] }} text-xl"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800">{{ $card['title'] }}</h3>
                <p class="text-xs text-slate-500">{{ $card['desc'] }}</p>
            </div>
        </div>
        <div class="flex items-center text-xs text-blue-600 font-medium">View Report <i class="fas fa-arrow-right ml-1 group-hover:translate-x-1 transition-transform"></i></div>
    </a>
    @endforeach
</div>
@endsection