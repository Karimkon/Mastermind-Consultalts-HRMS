<!DOCTYPE html>
<html lang="en" x-data="{ sidebarOpen: localStorage.getItem('sidebarOpen') !== 'false', mobileMenu: false, toggleSidebar(){ this.sidebarOpen=!this.sidebarOpen; localStorage.setItem('sidebarOpen', this.sidebarOpen); } }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- The browser checks against this before sending, so an oversized file is
         refused instantly instead of after the whole upload has finished. Same
         config the server validates with, so the two cannot disagree. --}}
    <meta name="upload-max-mb" content="{{ \App\Support\Uploads::maxMb() }}">
    <meta name="theme-color" content="#1d4ed8">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/favicon.ico?v=3" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32x32.png?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="/icons/favicon-16x16.png?v=3">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png?v=2">
    <title>@yield("title","Dashboard") — Mastermind HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50:"#eff6ff",100:"#dbeafe",200:"#bfdbfe",300:"#93c5fd",400:"#60a5fa",500:"#3b82f6",600:"#2563eb",700:"#1d4ed8",800:"#1e40af",900:"#1e3a8a" },
                        sidebar: { DEFAULT:"#0f172a", hover:"#1e293b", active:"#1e40af" }
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    @stack("styles")
    <style>
        body { font-family: "Inter", sans-serif; }

        /* ── Printing ──────────────────────────────────────────────────
           The chrome is for working in, not for the page somebody signs.
           Anything marked .no-print is dropped, the sidebar margin is
           released, and colours are forced on - a scorecard whose
           perspective bands and selected ratings print white is unreadable,
           because the rating IS the colour. */
        @media print {
            aside, header, .no-print { display: none !important; }

            body {
                background: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            #app-main { margin-left: 0 !important; }
            main { padding: 0 !important; }

            .card {
                box-shadow: none !important;
                border: 1px solid #cbd5e1 !important;
                break-inside: avoid;
            }

            /* A form control on paper is just its value. */
            input, textarea, select {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                background: transparent !important;
                -webkit-appearance: none;
                appearance: none;
            }

            table { width: 100% !important; min-width: 0 !important; font-size: 10.5px; }
            thead { display: table-header-group; }   /* repeat the head on every page */
            tr, img { break-inside: avoid; }

            a[href]::after { content: none !important; }   /* no URLs after links */

            @page { margin: 12mm; size: A4 landscape; }
        }

        /* Sidebar */
        .sidebar-link { display:flex; align-items:center; gap:0.75rem; padding:0.625rem 1rem; border-radius:0.5rem; font-size:0.875rem; font-weight:500; color:#cbd5e1; text-decoration:none; transition:all 0.15s; }
        .sidebar-link:hover { background:#334155; color:#fff; }
        .sidebar-link.active { background:#1d4ed8; color:#fff; }
        [x-cloak] { display:none !important; }
        .sidebar-group { padding:0.25rem 0.75rem; font-size:0.65rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.25rem; margin-top:1rem; }

        [data-nav-badge][hidden] { display: none !important; }

        /* Cards */
        .card { background:#fff; border-radius:0.75rem; box-shadow:0 1px 3px rgba(0,0,0,.06); border:1px solid #f1f5f9; }

        /* Buttons */
        .btn-primary { display:inline-flex; align-items:center; gap:0.5rem; padding:0.5rem 1rem; background:#2563eb; color:#fff; font-size:0.875rem; font-weight:500; border-radius:0.5rem; border:none; cursor:pointer; text-decoration:none; transition:background .15s; }
        .btn-primary:hover { background:#1d4ed8; color:#fff; }
        .btn-secondary { display:inline-flex; align-items:center; gap:0.5rem; padding:0.5rem 1rem; background:#fff; color:#374151; font-size:0.875rem; font-weight:500; border-radius:0.5rem; border:1px solid #e2e8f0; cursor:pointer; text-decoration:none; transition:background .15s; }
        .btn-secondary:hover { background:#f8fafc; color:#374151; }
        .btn-danger { display:inline-flex; align-items:center; gap:0.5rem; padding:0.5rem 1rem; background:#dc2626; color:#fff; font-size:0.875rem; font-weight:500; border-radius:0.5rem; border:none; cursor:pointer; text-decoration:none; transition:background .15s; }
        .btn-danger:hover { background:#b91c1c; color:#fff; }
        .btn-xs { display:inline-flex; align-items:center; padding:0.25rem 0.625rem; font-size:0.75rem; font-weight:500; border-radius:0.375rem; transition:background .15s; border:none; cursor:pointer; text-decoration:none; }
        .btn-blue { background:#eff6ff; color:#1d4ed8; }
        .btn-blue:hover { background:#dbeafe; }
        .btn-amber { background:#fffbeb; color:#b45309; }
        .btn-amber:hover { background:#fef3c7; }
        .btn-green { background:#f0fdf4; color:#15803d; }
        .btn-green:hover { background:#dcfce7; }

        /* Badges */
        .badge { display:inline-flex; align-items:center; padding:0.125rem 0.625rem; border-radius:9999px; font-size:0.75rem; font-weight:500; }
        .badge-green  { display:inline-flex; align-items:center; padding:0.125rem 0.625rem; border-radius:9999px; font-size:0.75rem; font-weight:500; background:#dcfce7; color:#166534; }
        .badge-red    { display:inline-flex; align-items:center; padding:0.125rem 0.625rem; border-radius:9999px; font-size:0.75rem; font-weight:500; background:#fee2e2; color:#991b1b; }
        .badge-yellow { display:inline-flex; align-items:center; padding:0.125rem 0.625rem; border-radius:9999px; font-size:0.75rem; font-weight:500; background:#fef9c3; color:#854d0e; }
        .badge-blue   { display:inline-flex; align-items:center; padding:0.125rem 0.625rem; border-radius:9999px; font-size:0.75rem; font-weight:500; background:#dbeafe; color:#1e40af; }
        .badge-gray   { display:inline-flex; align-items:center; padding:0.125rem 0.625rem; border-radius:9999px; font-size:0.75rem; font-weight:500; background:#f3f4f6; color:#374151; }
        .badge-orange { display:inline-flex; align-items:center; padding:0.125rem 0.625rem; border-radius:9999px; font-size:0.75rem; font-weight:500; background:#ffedd5; color:#9a3412; }
        .badge-purple { display:inline-flex; align-items:center; padding:0.125rem 0.625rem; border-radius:9999px; font-size:0.75rem; font-weight:500; background:#f3e8ff; color:#6b21a8; }
        .badge-indigo { display:inline-flex; align-items:center; padding:0.125rem 0.625rem; border-radius:9999px; font-size:0.75rem; font-weight:500; background:#e0e7ff; color:#3730a3; }
        .badge-teal   { display:inline-flex; align-items:center; padding:0.125rem 0.625rem; border-radius:9999px; font-size:0.75rem; font-weight:500; background:#ccfbf1; color:#115e59; }
        .badge-slate  { display:inline-flex; align-items:center; padding:0.125rem 0.625rem; border-radius:9999px; font-size:0.75rem; font-weight:500; background:#f1f5f9; color:#475569; }

        /* Tables */
        .table-header { background:#f8fafc; border-bottom:1px solid #e2e8f0; }
        .table-header th { padding:0.75rem 1rem; text-align:left; font-size:0.75rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.05em; }
        .table-head { font-size:0.75rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.05em; }
        .table-row { border-bottom:1px solid #f1f5f9; transition:background .1s; }
        .table-row:hover { background:#f8fafc; }

        /* Forms */
        .form-label { display:block; font-size:0.875rem; font-weight:500; color:#374151; margin-bottom:0.25rem; }
        .form-input { display:block; width:100%; border-radius:0.5rem; border:1px solid #d1d5db; padding:0.5rem 0.75rem; font-size:0.875rem; color:#111827; transition:border-color .15s,box-shadow .15s; outline:none; }
        .form-input:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.15); }
        .form-select { display:block; width:100%; border-radius:0.5rem; border:1px solid #d1d5db; padding:0.5rem 0.75rem; font-size:0.875rem; color:#111827; outline:none; }
        .form-select:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.15); }
        .form-textarea { display:block; width:100%; border-radius:0.5rem; border:1px solid #d1d5db; padding:0.5rem 0.75rem; font-size:0.875rem; color:#111827; transition:border-color .15s; outline:none; }
        .form-textarea:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.15); }

        /* Select2 fix */
        .select2-container--classic .select2-selection--single { border-radius:0.5rem !important; border-color:#d1d5db !important; height:38px !important; line-height:36px !important; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900">

{{-- SIDEBAR --}}
@php($navUnread = \App\Models\Notification::unreadCountsFor(auth()->user()))
<aside class="fixed inset-y-0 left-0 z-50 flex flex-col w-64 bg-slate-900 transition-transform duration-300"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0 lg:w-16'">

    {{-- Logo --}}
    <div class="flex items-center justify-center h-16 px-3 border-b border-slate-700/50 shrink-0">
        {{-- Collapsed: small square icon --}}
        <div class="rounded-lg overflow-hidden bg-white shrink-0" x-show="!sidebarOpen" style="width:36px;height:36px;padding:3px;">
            <img src="/images/logo.png" alt="M" class="w-full h-full object-contain">
        </div>
        {{-- Expanded: full logo on white pill --}}
        <div x-show="sidebarOpen" class="rounded-xl bg-white px-3 py-2 w-full flex items-center justify-center">
            <img src="/images/logo.png" alt="Mastermind HRMS" class="h-8 w-auto object-contain">
        </div>
    </div>

    {{-- Navigation --}}
    <nav id="sidebar-nav" class="flex-1 overflow-y-auto py-4 px-3 space-y-0.5">
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="fas fa-home w-4 text-center"></i><span x-show="sidebarOpen">Dashboard</span>
        </a>

        {{-- Every Mastermind role, not just the admin ones: "who do I report to"
             is a question all staff have a reason to ask, and it was previously
             buried in a block only super-admin, hr-admin and manager could see.
             Clients are excluded - the internal chain of command is not theirs
             to browse. --}}
        @unlessrole('client')
        <a href="{{ route('org-structure.index') }}" class="sidebar-link {{ request()->routeIs('org-structure.*') ? 'active' : '' }}">
            <i class="fas fa-sitemap w-4 text-center"></i><span x-show="sidebarOpen">Organisational Structure</span>
        </a>
        {{-- Quality documents and goals are company-wide: every logged-in
             employee can read published documents and see goals assigned to them,
             so these live outside the admin-only Quality group. --}}
        <a href="{{ route('quality.documents.library') }}" class="sidebar-link {{ request()->routeIs('quality.documents.library') ? 'active' : '' }}">
            <i class="fas fa-book w-4 text-center"></i><span x-show="sidebarOpen">Company Documents</span>
        </a>
        <a href="{{ route('quality.goals.index') }}" class="sidebar-link {{ request()->routeIs('quality.goals.*') ? 'active' : '' }}">
            <i class="fas fa-bullseye w-4 text-center"></i><span x-show="sidebarOpen">Goals</span>
        </a>
        @endunlessrole

        {{-- ===== CLIENT PORTAL ===== --}}
        @role('client')
        <p class="sidebar-group" x-show="sidebarOpen">Client Portal</p>
        <a href="{{ route('client.dashboard') }}" class="sidebar-link {{ request()->routeIs('client.dashboard') ? 'active' : '' }}">
            <i class="fas fa-home w-4 text-center"></i><span x-show="sidebarOpen">Dashboard</span>
        </a>
        <p class="sidebar-group" x-show="sidebarOpen">Approvals</p>
        <a href="{{ route('client.leaves.index') }}" class="sidebar-link {{ request()->routeIs('client.leaves.*') ? 'active' : '' }}">
            <i class="fas fa-calendar-minus w-4 text-center"></i><span x-show="sidebarOpen">Leave Approvals</span>
        </a>
        <a href="{{ route('client.recruitment.index') }}" class="sidebar-link {{ request()->routeIs('client.recruitment.*') ? 'active' : '' }}">
            <i class="fas fa-user-check w-4 text-center"></i><span x-show="sidebarOpen">Shortlisting</span>
        </a>
        @endrole

        {{-- ===== ACCOUNT MANAGER ===== --}}
        @role("account-manager")
        <p class="sidebar-group" x-show="sidebarOpen">Account Manager</p>
        <a href="{{ route("account-manager.dashboard") }}" class="sidebar-link {{ request()->routeIs("account-manager.dashboard") ? "active" : "" }}">
            <i class="fas fa-home w-4 text-center"></i><span x-show="sidebarOpen">Dashboard</span>
        </a>
        <a href="{{ route("account-manager.employees") }}" class="sidebar-link {{ request()->routeIs("account-manager.employees*") ? "active" : "" }}">
            <i class="fas fa-users w-4 text-center"></i><span x-show="sidebarOpen">Employees</span>
        </a>
        <a href="{{ route("account-manager.leaves") }}" class="sidebar-link {{ request()->routeIs("account-manager.leaves*") ? "active" : "" }}">
            <i class="fas fa-calendar-minus w-4 text-center"></i><span x-show="sidebarOpen">Leave Management</span>
        </a>
        <a href="{{ route("account-manager.payroll") }}" class="sidebar-link {{ request()->routeIs("account-manager.payroll*") ? "active" : "" }}">
            <i class="fas fa-file-invoice-dollar w-4 text-center"></i><span x-show="sidebarOpen">Payroll Runs</span>
        </a>
        <a href="{{ route('account-manager.salary-payments') }}" class="sidebar-link {{ request()->routeIs('account-manager.salary-payments*') ? 'active' : '' }}">
            <i class="fas fa-money-bill-wave w-4 text-center"></i><span x-show="sidebarOpen">Salary Payments</span>
        </a>
        <a href="{{ route('office-attendance.index') }}" class="sidebar-link {{ request()->routeIs('office-attendance.*') ? 'active' : '' }}">
            <i class="fas fa-building-user w-4 text-center"></i><span x-show="sidebarOpen">Office Attendance</span>
        </a>
        <a href="{{ route('am-visits.index') }}" class="sidebar-link {{ request()->routeIs('am-visits.*') ? 'active' : '' }}">
            <i class="fas fa-map-marker-alt w-4 text-center"></i><span x-show="sidebarOpen">Site Visits</span>
        </a>
        <a href="{{ route('holiday-pay.index') }}" class="sidebar-link {{ request()->routeIs('holiday-pay.*') ? 'active' : '' }}">
            <i class="fas fa-calendar-star w-4 text-center"></i><span x-show="sidebarOpen">Holiday Pay</span>
        </a>
        <a href="{{ route('appraisals.index') }}" class="sidebar-link {{ request()->routeIs('appraisals.*') ? 'active' : '' }}">
            <i class="fas fa-chart-bar w-4 text-center"></i><span x-show="sidebarOpen">Appraisals</span>
        </a>
        @endrole

        {{-- ===== EMPLOYEE ROLE: personal menu only ===== --}}
        @role('employee')
        <p class="sidebar-group" x-show="sidebarOpen">My Work</p>
        <a href="{{ route('profile') }}" class="sidebar-link {{ request()->routeIs('profile*') ? 'active' : '' }}">
            <i class="fas fa-user w-4 text-center"></i><span x-show="sidebarOpen">My Profile</span>
        </a>
        <a href="{{ route('attendance.index') }}" class="sidebar-link {{ request()->routeIs('attendance.*') ? 'active' : '' }}">
            <i class="fas fa-clock w-4 text-center"></i><span x-show="sidebarOpen">My Attendance</span>
        </a>
        <a href="{{ route('leaves.index') }}" class="sidebar-link {{ request()->routeIs('leaves.*') ? 'active' : '' }}">
            <i class="fas fa-calendar-minus w-4 text-center"></i>
            <span x-show="sidebarOpen" class="flex-1">My Leave</span>
            <x-nav-badge :count="$navUnread['leave'] ?? 0" area="leave" />
        </a>
        {{-- Neither of these was in the menu. The payslip list did not exist on
             the web at all, and My Documents was a route nothing pointed at. --}}
        <a href="{{ route('employee.payslips') }}" class="sidebar-link {{ request()->routeIs('employee.payslips') ? 'active' : '' }}">
            <i class="fas fa-file-invoice-dollar w-4 text-center"></i>
            <span x-show="sidebarOpen" class="flex-1">My Payslips</span>
            <x-nav-badge :count="$navUnread['payslips'] ?? 0" area="payslips" />
        </a>
        <a href="{{ route('employee.documents') }}" class="sidebar-link {{ request()->routeIs('employee.documents') ? 'active' : '' }}">
            <i class="fas fa-folder-open w-4 text-center"></i>
            <span x-show="sidebarOpen" class="flex-1">My Documents</span>
            <x-nav-badge :count="$navUnread['documents'] ?? 0" area="documents" />
        </a>
        <a href="{{ route('training.index') }}" class="sidebar-link {{ request()->routeIs('training.index') || request()->routeIs('training.show') || request()->routeIs('training.create') || request()->routeIs('training.edit') ? 'active' : '' }}">
            <i class="fas fa-graduation-cap w-4 text-center"></i>
            <span x-show="sidebarOpen" class="flex-1">Training</span>
            <x-nav-badge :count="$navUnread['training'] ?? 0" area="training" />
        </a>
        {{-- The plan is the year's schedule and its approvals; the courses list
             above it is the catalogue those sessions draw from. --}}
        <a href="{{ route('training.plan.index') }}" class="sidebar-link {{ request()->routeIs('training.plan.*') ? 'active' : '' }}">
            <i class="fas fa-calendar-days w-4 text-center"></i>
            <span x-show="sidebarOpen" class="flex-1">Training Plan</span>
            <x-nav-badge :count="$navUnread['training_plan'] ?? 0" area="training_plan" />
        </a>
        <a href="{{ route('appraisals.index') }}" class="sidebar-link {{ request()->routeIs('appraisals.*') ? 'active' : '' }}">
            <i class="fas fa-balance-scale w-4 text-center"></i>
            <span x-show="sidebarOpen" class="flex-1">My Appraisal</span>
            <x-nav-badge :count="$navUnread['appraisals'] ?? 0" area="appraisals" />
        </a>
        {{-- An improvement plan has dates on it and consequences if they pass
             unmet, so the person on one needs a way to open it rather than
             relying on the email. The list is scoped to their own. --}}
        <a href="{{ route('pips.index') }}" class="sidebar-link {{ request()->routeIs('pips.*') ? 'active' : '' }}">
            <i class="fas fa-clipboard-list w-4 text-center"></i>
            <span x-show="sidebarOpen" class="flex-1">My PIPs</span>
            <x-nav-badge :count="$navUnread['pips'] ?? 0" area="pips" />
        </a>
        <a href="{{ route('meetings.calendar') }}" class="sidebar-link {{ request()->routeIs('meetings.*') ? 'active' : '' }}">
            <i class="fas fa-calendar-alt w-4 text-center"></i>
            <span x-show="sidebarOpen" class="flex-1">Calendar</span>
            <x-nav-badge :count="$navUnread['calendar'] ?? 0" area="calendar" />
        </a>
        @endrole

        {{-- ===== PAYROLL OFFICER ===== --}}
        @role('payroll-officer')
        <p class="sidebar-group" x-show="sidebarOpen">Payroll</p>
        @php($financeWaiting = \App\Models\PayrollRun::awaitingCountFor(auth()->user()))
        <a href="{{ route('payroll.index') }}" class="sidebar-link {{ request()->routeIs('payroll.*') ? 'active' : '' }}">
            <i class="fas fa-money-bill-wave w-4 text-center"></i>
            <span x-show="sidebarOpen" class="flex-1">Payroll Runs</span>
            @if($financeWaiting)
            {{-- Finance owns two stages: approving after HR, and paying after the MD. --}}
            <span x-show="sidebarOpen" title="Payroll runs waiting on you"
                  class="ml-auto px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[11px] font-semibold">
                {{ $financeWaiting }}
            </span>
            @endif
        </a>
        <a href="{{ route('salary.index') }}" class="sidebar-link {{ request()->routeIs('salary.*') ? 'active' : '' }}">
            <i class="fas fa-coins w-4 text-center"></i><span x-show="sidebarOpen">Salary Setup</span>
        </a>
        <a href="{{ route('employees.index') }}" class="sidebar-link {{ request()->routeIs('employees.*') ? 'active' : '' }}">
            <i class="fas fa-users w-4 text-center"></i><span x-show="sidebarOpen">Employee Central</span>
        </a>
        <a href="{{ route('reports.payroll') }}" class="sidebar-link {{ request()->routeIs('reports.payroll*') ? 'active' : '' }}">
            <i class="fas fa-chart-bar w-4 text-center"></i><span x-show="sidebarOpen">Payroll Report</span>
        </a>
        @endrole

        {{-- ===== MD (Managing Director) ===== --}}
        @role('md')
        <p class="sidebar-group" x-show="sidebarOpen">Payroll Approval</p>
        @php($mdWaiting = \App\Models\PayrollRun::awaitingCountFor(auth()->user()))
        <a href="{{ route('payroll.index') }}" class="sidebar-link {{ request()->routeIs('payroll.*') ? 'active' : '' }}">
            <i class="fas fa-money-bill-wave w-4 text-center"></i>
            <span x-show="sidebarOpen" class="flex-1">Payroll Runs</span>
            @if($mdWaiting)
            <span x-show="sidebarOpen" title="Payroll runs waiting on you"
                  class="ml-auto px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[11px] font-semibold">
                {{ $mdWaiting }}
            </span>
            @endif
        </a>
        {{-- Every other role could reach a scorecard from the menu; this one
             could not, so an MD sent an appraisal had nowhere to click even
             though the route has always been open to them. --}}
        <a href="{{ route('appraisals.index') }}" class="sidebar-link {{ request()->routeIs('appraisals.*') ? 'active' : '' }}">
            <i class="fas fa-balance-scale w-4 text-center"></i>
            <span x-show="sidebarOpen" class="flex-1">Balanced Scorecard</span>
            <x-nav-badge :count="$navUnread['appraisals'] ?? 0" area="appraisals" />
        </a>
        @endrole

        {{-- ===== RECRUITER ===== --}}
        @role('recruiter')
        <p class="sidebar-group" x-show="sidebarOpen">Recruitment</p>
        <a href="{{ route('recruitment.jobs.index') }}" class="sidebar-link {{ request()->routeIs('recruitment.jobs.*') ? 'active' : '' }}">
            <i class="fas fa-briefcase w-4 text-center"></i><span x-show="sidebarOpen">Job Postings</span>
        </a>
        <a href="{{ route('recruitment.candidates.index') }}" class="sidebar-link {{ request()->routeIs('recruitment.candidates.*') ? 'active' : '' }}">
            <i class="fas fa-user-tie w-4 text-center"></i><span x-show="sidebarOpen">Candidates</span>
        </a>
        <a href="{{ route('recruitment.interviews.index') }}" class="sidebar-link {{ request()->routeIs('recruitment.interviews.*') ? 'active' : '' }}">
            <i class="fas fa-comments w-4 text-center"></i><span x-show="sidebarOpen">Interviews</span>
        </a>
        <a href="{{ route('recruitment.analytics') }}" class="sidebar-link {{ request()->routeIs('recruitment.analytics') ? 'active' : '' }}">
            <i class="fas fa-chart-pie w-4 text-center"></i><span x-show="sidebarOpen">Recruitment Analytics</span>
        </a>
        @endrole

        {{-- ===== MANAGER / HR ADMIN / SUPER ADMIN: full menu ===== --}}
        @role('super-admin|hr-admin|manager')
        <p class="sidebar-group" x-show="sidebarOpen">Website</p>
        <a href="{{ route('admin.blog.index') }}" class="sidebar-link {{ request()->routeIs('admin.blog.index') || request()->routeIs('admin.blog.create') || request()->routeIs('admin.blog.edit') ? 'active' : '' }}">
            <i class="fas fa-newspaper w-4 text-center"></i><span x-show="sidebarOpen">Blog Posts</span>
        </a>
        <a href="{{ route('admin.blog.categories.index') }}" class="sidebar-link {{ request()->routeIs('admin.blog.categories.*') ? 'active' : '' }}">
            <i class="fas fa-folder-tree w-4 text-center"></i><span x-show="sidebarOpen">Blog Topics</span>
        </a>
        <a href="{{ route('admin.blog.media') }}" class="sidebar-link {{ request()->routeIs('admin.blog.media') ? 'active' : '' }}">
            <i class="fas fa-images w-4 text-center"></i><span x-show="sidebarOpen">Blog Media</span>
        </a>
        <a href="{{ route('admin.blog.tokens') }}" class="sidebar-link {{ request()->routeIs('admin.blog.tokens') ? 'active' : '' }}">
            <i class="fas fa-robot w-4 text-center"></i><span x-show="sidebarOpen">AI Publishing Keys</span>
        </a>

        <p class="sidebar-group" x-show="sidebarOpen">Human Resources</p>
        <a href="{{ route('employees.index') }}" class="sidebar-link {{ request()->routeIs('employees.*') ? 'active' : '' }}">
            <i class="fas fa-users w-4 text-center"></i><span x-show="sidebarOpen">Employee Central</span>
        </a>
        <a href="{{ route('attendance.index') }}" class="sidebar-link {{ request()->routeIs('attendance.*') ? 'active' : '' }}">
            <i class="fas fa-clock w-4 text-center"></i><span x-show="sidebarOpen">Attendance</span>
        </a>
        <a href="{{ route('office-attendance.index') }}" class="sidebar-link {{ request()->routeIs('office-attendance.*') ? 'active' : '' }}">
            <i class="fas fa-building-user w-4 text-center"></i><span x-show="sidebarOpen">Office Attendance</span>
        </a>
        @role('super-admin|hr-admin|manager')
        <a href="{{ route('overtime.index') }}" class="sidebar-link {{ request()->routeIs('overtime.*') ? 'active' : '' }}">
            <i class="fas fa-hourglass-half w-4 text-center"></i><span x-show="sidebarOpen">Overtime Approval</span>
        </a>
        <a href="{{ route('holiday-pay.index') }}" class="sidebar-link {{ request()->routeIs('holiday-pay.*') ? 'active' : '' }}">
            <i class="fas fa-calendar-star w-4 text-center"></i><span x-show="sidebarOpen">Holiday Pay</span>
        </a>
        @endrole
        @role('super-admin|hr-admin')
        @php($awaitingApproval = \App\Models\PendingChange::pending()->count())
        <a href="{{ route('admin.change-approvals.index') }}" class="sidebar-link {{ request()->routeIs('admin.change-approvals.*') ? 'active' : '' }}">
            <i class="fas fa-user-check w-4 text-center"></i>
            <span x-show="sidebarOpen" class="flex-1">Change Approvals</span>
            @if($awaitingApproval)
            {{-- A queue nobody can see is a queue nobody clears. --}}
            <span x-show="sidebarOpen" class="ml-auto px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[11px] font-semibold">
                {{ $awaitingApproval }}
            </span>
            @endif
        </a>
        <a href="{{ route('admin.bulk-update.index') }}" class="sidebar-link {{ request()->routeIs('admin.bulk-update.*') ? 'active' : '' }}">
            <i class="fas fa-file-arrow-up w-4 text-center"></i><span x-show="sidebarOpen">Bulk Update Staff</span>
        </a>
        <a href="{{ route('admin.rating-scales.index') }}" class="sidebar-link {{ request()->routeIs('admin.rating-scales.*') ? 'active' : '' }}">
            <i class="fas fa-list-ol w-4 text-center"></i><span x-show="sidebarOpen">Rating Scales</span>
        </a>
        {{-- Appraisal templates now live inside the Performance Management group
             further down, rather than as a second entry point under Admin. --}}
        @endrole
        <a href="{{ route('leaves.index') }}" class="sidebar-link {{ request()->routeIs('leaves.*') ? 'active' : '' }}">
            <i class="fas fa-calendar-minus w-4 text-center"></i>
            <span x-show="sidebarOpen" class="flex-1">Leave</span>
            <x-nav-badge :count="$navUnread['leave'] ?? 0" area="leave" />
        </a>
        @role('super-admin|hr-admin|payroll-officer|md')
        @php($payrollWaiting = \App\Models\PayrollRun::awaitingCountFor(auth()->user()))
        <a href="{{ route('payroll.index') }}" class="sidebar-link {{ request()->routeIs('payroll.*') || request()->routeIs('salary.*') ? 'active' : '' }}">
            <i class="fas fa-money-bill-wave w-4 text-center"></i>
            <span x-show="sidebarOpen" class="flex-1">Payroll</span>
            @if($payrollWaiting)
            {{-- Runs used to wait in silence for whoever was next in the chain. --}}
            <span x-show="sidebarOpen" title="Payroll runs waiting on you"
                  class="ml-auto px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[11px] font-semibold">
                {{ $payrollWaiting }}
            </span>
            @endif
        </a>
        @endrole
        {{-- Close the admin block that opened above (Website / Human Resources /
             Payroll). Quality has to sit OUTSIDE it: that block is
             super-admin|hr-admin|manager, and widening it to admit the quality
             manager would hand him Website, Payroll and Talent as well. --}}
        @endrole

        {{-- Quality Management: the control layer that sits over every HR module.
             Collapsible, like Performance, with a badge counting the open issues
             the quality engine has raised across the system.

             quality-manager and auditor both belong here. Every route in this
             block already admits them, but this gate left them out - so the one
             person appointed to run quality could open /quality/documents/create
             by typing the URL and had no link to it anywhere, and an appointed
             auditor saw no Quality Management menu at all. Their only entry
             point was the read-only published library.

             The group is one gate, not per-link: the quality manager and the
             auditor see the same eleven screens, and the controllers decide what
             each may DO there - the auditor reads the document register, the
             manager authors it. The one exception is Quality Team below, which
             stays with the CEO. --}}
        @role('super-admin|hr-admin|manager|quality-manager|auditor')
        <p class="sidebar-group" x-show="sidebarOpen">Quality</p>
        @php($qualityOpen = request()->routeIs('quality.*'))
        @php($qualityOpenNc = \Illuminate\Support\Facades\Schema::hasTable('quality_nonconformities') ? \App\Models\QualityNonconformity::whereIn('status', ['open', 'investigating'])->count() : 0)
        <div x-data="{ open: {{ $qualityOpen ? 'true' : 'false' }} }">
            <button type="button" @click="open = !open" class="sidebar-link w-full {{ $qualityOpen ? 'active' : '' }}">
                <i class="fas fa-shield-halved w-4 text-center"></i>
                <span x-show="sidebarOpen" class="flex-1 text-left">Quality Management</span>
                @if($qualityOpenNc)
                <span x-show="sidebarOpen" title="Open non-conformities"
                      class="px-1.5 py-0.5 rounded-full bg-red-100 text-red-700 text-[11px] font-semibold">{{ $qualityOpenNc }}</span>
                @endif
                <i x-show="sidebarOpen" class="fas fa-chevron-down text-[10px] transition-transform ml-1" :class="{ 'rotate-180': open }"></i>
            </button>
            <div x-show="open && sidebarOpen" class="ml-4 pl-3 border-l border-white/10">
                <a href="{{ route('quality.dashboard') }}" class="sidebar-link {{ request()->routeIs('quality.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-gauge-high w-4 text-center"></i><span x-show="sidebarOpen">Quality Dashboard</span>
                </a>
                <a href="{{ route('quality.checks.index') }}" class="sidebar-link {{ request()->routeIs('quality.checks.*') ? 'active' : '' }}">
                    <i class="fas fa-list-check w-4 text-center"></i><span x-show="sidebarOpen">Quality Checks</span>
                </a>
                <a href="{{ route('quality.standards.index') }}" class="sidebar-link {{ request()->routeIs('quality.standards.*') ? 'active' : '' }}">
                    <i class="fas fa-clipboard-check w-4 text-center"></i><span x-show="sidebarOpen">Standards</span>
                </a>
                <a href="{{ route('quality.nonconformities.index') }}" class="sidebar-link {{ request()->routeIs('quality.nonconformities.*') ? 'active' : '' }}">
                    <i class="fas fa-triangle-exclamation w-4 text-center"></i>
                    <span x-show="sidebarOpen" class="flex-1">Non-conformities</span>
                    @if($qualityOpenNc)<span x-show="sidebarOpen" class="px-1.5 py-0.5 rounded-full bg-red-100 text-red-700 text-[10px] font-semibold">{{ $qualityOpenNc }}</span>@endif
                </a>
                <a href="{{ route('quality.audits.index') }}" class="sidebar-link {{ request()->routeIs('quality.audits.*') ? 'active' : '' }}">
                    <i class="fas fa-clipboard-list w-4 text-center"></i><span x-show="sidebarOpen">Audits</span>
                </a>
                <a href="{{ route('quality.compliance.index') }}" class="sidebar-link {{ request()->routeIs('quality.compliance.*') ? 'active' : '' }}">
                    <i class="fas fa-scale-balanced w-4 text-center"></i><span x-show="sidebarOpen">Compliance</span>
                </a>
                <a href="{{ route('quality.reports.index') }}" class="sidebar-link {{ request()->routeIs('quality.reports.*') ? 'active' : '' }}">
                    <i class="fas fa-chart-line w-4 text-center"></i><span x-show="sidebarOpen">Reports</span>
                </a>
                <a href="{{ route('quality.reviews.index') }}" class="sidebar-link {{ request()->routeIs('quality.reviews.*') ? 'active' : '' }}">
                    <i class="fas fa-users-rectangle w-4 text-center"></i><span x-show="sidebarOpen">Reviews</span>
                </a>
                <a href="{{ route('quality.documents.index') }}" class="sidebar-link {{ request()->routeIs('quality.documents.index') || request()->routeIs('quality.documents.create') || request()->routeIs('quality.documents.show') ? 'active' : '' }}">
                    <i class="fas fa-file-signature w-4 text-center"></i><span x-show="sidebarOpen">Document Control</span>
                </a>
                @role('super-admin|md')
                <a href="{{ route('quality.team.index') }}" class="sidebar-link {{ request()->routeIs('quality.team.*') ? 'active' : '' }}">
                    <i class="fas fa-user-shield w-4 text-center"></i><span x-show="sidebarOpen">Quality Team</span>
                </a>
                @endrole
            </div>
        </div>
        @endrole

        {{-- Back inside the admin block for Talent and everything below it. --}}
        @role('super-admin|hr-admin|manager')

        <p class="sidebar-group" x-show="sidebarOpen">Talent</p>
        @role('super-admin|hr-admin')
        <a href="{{ route('recruitment.jobs.index') }}" class="sidebar-link {{ request()->routeIs('recruitment.*') ? 'active' : '' }}">
            <i class="fas fa-briefcase w-4 text-center"></i><span x-show="sidebarOpen">Recruitment</span>
        </a>
        <a href="{{ route('recruitment.analytics') }}" class="sidebar-link {{ request()->routeIs('recruitment.analytics') ? 'active' : '' }}">
            <i class="fas fa-chart-pie w-4 text-center"></i><span x-show="sidebarOpen">Recruitment Analytics</span>
        </a>        <a href="{{ route('careers.index') }}" target="_blank" class="sidebar-link">
            <i class="fas fa-globe w-4 text-center text-emerald-400"></i><span x-show="sidebarOpen" class="text-emerald-400">Public Job Board ↗</span>
        </a>
        @endrole
        {{-- Performance Management is the whole of it: goal setting, improvement
             plans and the templates they are scored against. These were three
             separate top-level entries plus a "Performance" link onto an empty
             module, so one subject appeared in four places in the menu. --}}
        @php($perfOpen = request()->routeIs('goals.*') || request()->routeIs('pips.*') || request()->routeIs('admin.appraisal-templates.*'))
        <div x-data="{ open: {{ $perfOpen ? 'true' : 'false' }} }">
            <button type="button" @click="open = !open"
                    class="sidebar-link w-full {{ $perfOpen ? 'active' : '' }}">
                <i class="fas fa-chart-line w-4 text-center"></i>
                <span x-show="sidebarOpen" class="flex-1 text-left">Performance Management</span>
                <i x-show="sidebarOpen" class="fas fa-chevron-down text-[10px] transition-transform"
                   :class="{ 'rotate-180': open }"></i>
            </button>
            <div x-show="open && sidebarOpen" class="ml-4 pl-3 border-l border-white/10">
                <a href="{{ route('goals.index') }}" class="sidebar-link {{ request()->routeIs('goals.*') ? 'active' : '' }}">
                    <i class="fas fa-bullseye w-4 text-center"></i><span x-show="sidebarOpen">Goal Settings</span>
                </a>
                <a href="{{ route('pips.index') }}" class="sidebar-link {{ request()->routeIs('pips.*') ? 'active' : '' }}">
                    <i class="fas fa-clipboard-list w-4 text-center"></i><span x-show="sidebarOpen">PIPs</span>
                </a>
                @role('super-admin|hr-admin')
                <a href="{{ route('admin.appraisal-templates.index') }}" class="sidebar-link {{ request()->routeIs('admin.appraisal-templates.*') ? 'active' : '' }}">
                    <i class="fas fa-sliders w-4 text-center"></i><span x-show="sidebarOpen">Appraisal Templates</span>
                </a>
                @endrole
            </div>
        </div>
        <a href="{{ route('training.index') }}" class="sidebar-link {{ request()->routeIs('training.index') || request()->routeIs('training.show') || request()->routeIs('training.create') || request()->routeIs('training.edit') ? 'active' : '' }}">
            <i class="fas fa-graduation-cap w-4 text-center"></i>
            <span x-show="sidebarOpen" class="flex-1">Training</span>
            <x-nav-badge :count="$navUnread['training'] ?? 0" area="training" />
        </a>
        {{-- The plan is the year's schedule and its approvals; the courses list
             above it is the catalogue those sessions draw from. --}}
        <a href="{{ route('training.plan.index') }}" class="sidebar-link {{ request()->routeIs('training.plan.*') ? 'active' : '' }}">
            <i class="fas fa-calendar-days w-4 text-center"></i>
            <span x-show="sidebarOpen" class="flex-1">Training Plan</span>
            <x-nav-badge :count="$navUnread['training_plan'] ?? 0" area="training_plan" />
        </a>
        <a href="{{ route('appraisals.index') }}" class="sidebar-link {{ request()->routeIs('appraisals.*') ? 'active' : '' }}">
            <i class="fas fa-balance-scale w-4 text-center"></i>
            <span x-show="sidebarOpen" class="flex-1">Balanced Scorecard</span>
            <x-nav-badge :count="$navUnread['appraisals'] ?? 0" area="appraisals" />
        </a>
        @role('super-admin|hr-admin')
        <a href="{{ route('probation.index') }}" class="sidebar-link {{ request()->routeIs('probation.*') ? 'active' : '' }}">
            <i class="fas fa-user-clock w-4 text-center"></i><span x-show="sidebarOpen">Probation</span>
        </a>
        @endrole

        <p class="sidebar-group" x-show="sidebarOpen">Workspace</p>
        <a href="{{ route('meetings.index') }}" class="sidebar-link {{ request()->routeIs('meetings.index') || request()->routeIs('meetings.show*') ? 'active' : '' }}">
            <i class="fas fa-video w-4 text-center"></i><span x-show="sidebarOpen">Meetings</span>
        </a>
        <a href="{{ route('meetings.calendar') }}" class="sidebar-link {{ request()->routeIs('meetings.calendar*') ? 'active' : '' }}">
            <i class="fas fa-calendar-alt w-4 text-center"></i>
            <span x-show="sidebarOpen" class="flex-1">Calendar</span>
            <x-nav-badge :count="$navUnread['calendar'] ?? 0" area="calendar" />
        </a>
        <a href="{{ route('reports.index') }}" class="sidebar-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
            <i class="fas fa-file-alt w-4 text-center"></i><span x-show="sidebarOpen">Reports</span>
        </a>
        @endrole

        {{-- ===== ADMIN ONLY ===== --}}
        @role('super-admin|hr-admin')
        <p class="sidebar-group" x-show="sidebarOpen">Administration</p>
        <a href="{{ route('admin.users.index') }}" class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            <i class="fas fa-user-cog w-4 text-center"></i><span x-show="sidebarOpen">Users</span>
        </a>
        <a href="{{ route('admin.departments.index') }}" class="sidebar-link {{ request()->routeIs('admin.departments.*') ? 'active' : '' }}">
            <i class="fas fa-building w-4 text-center"></i><span x-show="sidebarOpen">Departments</span>
        </a>
        <a href="{{ route('admin.roles.index') }}" class="sidebar-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
            <i class="fas fa-shield-alt w-4 text-center"></i><span x-show="sidebarOpen">Roles & Permissions</span>
        </a>
        <a href="{{ route('admin.clients.index') }}" class="sidebar-link {{ request()->routeIs('admin.clients.*') ? 'active' : '' }}">
            <i class="fas fa-building w-4 text-center"></i><span x-show="sidebarOpen">Clients</span>
        </a>
        <a href="{{ route('admin.account-managers.index') }}" class="sidebar-link {{ request()->routeIs('admin.account-managers.*') ? 'active' : '' }}">
            <i class="fas fa-user-tie w-4 text-center"></i><span x-show="sidebarOpen">Account Managers</span>
        </a>
        <a href="{{ route('admin.audit.index') }}" class="sidebar-link {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}">
            <i class="fas fa-history w-4 text-center"></i><span x-show="sidebarOpen">Audit Logs</span>
        </a>
        <a href="{{ route('admin.documentation.pdf') }}" target="_blank" class="sidebar-link">
            <i class="fas fa-file-pdf w-4 text-center"></i><span x-show="sidebarOpen">System Docs</span>
        </a>
        <a href="{{ route('admin.public-holidays.index') }}" class="sidebar-link {{ request()->routeIs('admin.public-holidays.*') ? 'active' : '' }}">
            <i class="fas fa-calendar-alt w-4 text-center"></i><span x-show="sidebarOpen">Public Holidays</span>
        </a>
        <a href="{{ route('admin.settings.index') }}" class="sidebar-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
            <i class="fas fa-cog w-4 text-center"></i><span x-show="sidebarOpen">Settings</span>
        </a>
        @endrole
    </nav>

    {{-- Toggle Sidebar --}}
    <div class="p-3 border-t border-slate-700/50">
        <button @click="toggleSidebar()" class="w-full flex items-center justify-center p-2 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas" :class="sidebarOpen ? 'fa-chevron-left' : 'fa-chevron-right'"></i>
        </button>
    </div>
</aside>
{{-- Keep the sidebar where you left it. On a full page load the nav used to
     jump back to the top, so after clicking something far down (e.g. inside
     Performance Management) you had to scroll down again to find your place.
     We remember the scroll position per browser and restore it before paint. --}}
<script>
    (function () {
        var nav = document.getElementById('sidebar-nav');
        if (!nav) return;
        try {
            var saved = localStorage.getItem('sidebarScroll');
            if (saved !== null) nav.scrollTop = parseInt(saved, 10) || 0;
        } catch (e) {}
        var t;
        nav.addEventListener('scroll', function () {
            clearTimeout(t);
            t = setTimeout(function () {
                try { localStorage.setItem('sidebarScroll', nav.scrollTop); } catch (e) {}
            }, 80);
        });
    })();
</script>

{{-- MAIN CONTENT --}}
<div id="app-main" class="transition-all duration-300" style="margin-left:256px" :style="sidebarOpen ? 'margin-left:256px' : 'margin-left:0px'">

    {{-- TOP NAVIGATION --}}
    <header class="sticky top-0 z-40 flex items-center justify-between h-16 bg-white border-b border-slate-200 px-6 shadow-sm">
        <div class="flex items-center gap-4">
            <button @click="toggleSidebar()" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors lg:hidden">
                <i class="fas fa-bars"></i>
            </button>
            {{-- Breadcrumb --}}
            <nav class="hidden sm:flex items-center gap-2 text-sm">
                <a href="{{ auth()->user()->hasRole('client') ? route('client.dashboard') : route('dashboard') }}" class="text-slate-400 hover:text-slate-600">Home</a>
                @hasSection("breadcrumb")
                    <i class="fas fa-chevron-right text-slate-300 text-xs"></i>
                    @yield("breadcrumb")
                @endif
            </nav>
        </div>

        <div class="flex items-center gap-3">
            {{-- Notifications --}}
            <div class="relative" x-data="notificationBell()">
                {{-- Opening the bell used to mark everything read, so a notice could be
                     cleared a second after it arrived and look as though it had never
                     come. Reading is now a deliberate act: click the notice, or the
                     "Mark all read" button already sitting in the panel. --}}
                <button @click="open = !open" class="relative p-2 rounded-lg text-slate-500 hover:bg-slate-100 transition-colors">
                    <i class="fas fa-bell"></i>
                    <span x-show="count > 0" x-text="count" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-bold"></span>
                </button>
                <div x-show="open" @click.outside="open = false" x-transition class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-slate-200 py-2 z-50">
                    <div class="px-4 py-2 border-b border-slate-100 flex justify-between items-center">
                        <span class="font-semibold text-sm text-slate-700">Notifications</span>
                        <button @click="markAllRead()" class="text-xs text-blue-600 hover:underline">Mark all read</button>
                    </div>
                    <div class="max-h-72 overflow-y-auto">
                        {{-- Every notification is written with a `url` in its data,
                             but this row carried cursor-pointer and no handler, so
                             it looked clickable and went nowhere. An appraisal
                             notice that cannot be opened is a dead end for anyone
                             whose menu has no link to the card. --}}
                        <template x-for="n in items" :key="n.id">
                            <a :href="n.data && n.data.url ? n.data.url : null"
                               @click="markOneRead(n)"
                               class="block px-4 py-3 hover:bg-slate-50 border-b border-slate-50"
                               :class="[!n.read_at ? 'bg-blue-50/40' : '',
                                        n.data && n.data.url ? 'cursor-pointer' : 'cursor-default']">
                                <p class="text-sm font-medium text-slate-800" x-text="n.title"></p>
                                <p class="text-xs text-slate-500 mt-0.5" x-text="n.body"></p>
                            </a>
                        </template>
                        <div x-show="items.length === 0" class="px-4 py-6 text-center text-sm text-slate-400">No notifications</div>
                    </div>
                </div>
            </div>

            {{-- User Menu --}}
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-100 transition-colors">
                    <img src="{{ auth()->user()->avatar_url }}" alt="" class="w-8 h-8 rounded-full object-cover border-2 border-blue-200">
                    <span class="hidden sm:block text-sm font-medium text-slate-700">{{ auth()->user()->name }}</span>
                    <i class="fas fa-chevron-down text-xs text-slate-400"></i>
                </button>
                <div x-show="open" @click.outside="open = false" x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-slate-200 py-2 z-50">
                    <div class="px-4 py-2 border-b border-slate-100">
                        <p class="text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-500">{{ auth()->user()->getRoleNames()->first() }}</p>
                    </div>
                    <a href="{{ route('profile') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"><i class="fas fa-user w-4"></i> My Profile</a>
                    <a href="{{ route('mfa.setup') }}" class="flex items-center gap-3 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"><i class="fas fa-lock w-4"></i> Two-Factor Auth</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button type="submit" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-500 hover:bg-red-50"><i class="fas fa-sign-out-alt w-4"></i> Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    {{-- PAGE CONTENT --}}
    <main class="p-6">
        @if(session("success"))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="mb-4 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3">
                <i class="fas fa-check-circle text-green-500"></i>
                <span class="text-sm font-medium">{{ session("success") }}</span>
                <button @click="show = false" class="ml-auto text-green-400 hover:text-green-600"><i class="fas fa-times"></i></button>
            </div>
        @endif
        @if(session("error") || $errors->any())
            <div class="mb-4 flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3">
                <i class="fas fa-exclamation-circle text-red-500 mt-0.5"></i>
                <div class="text-sm">
                    @if(session("error")) {{ session("error") }} @endif
                    @if($errors->any()) <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul> @endif
                </div>
            </div>
        @endif

        @yield("content")
    </main>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
$.ajaxSetup({ headers: { "X-CSRF-TOKEN": csrf } });

/* The bell.
 *
 * This used to be a bare x-data object plus two global functions called from
 * x-init and @click. A plain function invoked that way has `this` bound to
 * window, not to the component - so "this.items = data.notifications" was
 * setting window.items, and the panel read "No notifications" no matter how many
 * had arrived. The badge count never moved for the same reason. Only the fetch
 * inside markAllRead had any effect, which is why notices were being marked read
 * server-side while never appearing on screen.
 *
 * As a component the methods keep their binding. */
/**
 * Repaints the menu counts from the bell's poll.
 *
 * Server-rendered on load; updated here so somebody sitting on one screen sees
 * a payslip or an improvement plan land without refreshing.
 */
function paintNavBadges(areas) {
    document.querySelectorAll("[data-nav-badge]").forEach(function (el) {
        const n = areas[el.dataset.navBadge] || 0;
        el.textContent = n > 99 ? "99+" : n;
        el.hidden = n === 0;
    });
}

function notificationBell() {
    return {
        open: false,
        count: 0,
        items: [],

        init() {
            this.load();
            // Picked up without a page refresh; someone can sit on one screen all
            // morning and an appraisal still reaches them.
            setInterval(() => this.load(), 30000);
        },

        load() {
            fetch("/ajax/notifications", { headers: { "Accept": "application/json" } })
                .then(r => r.ok ? r.json() : null)
                .then(data => {
                    if (!data) return;
                    this.count = data.unread ?? 0;
                    this.items = data.notifications ?? [];
                    paintNavBadges(data.areas || {});
                })
                .catch(() => {});
        },

        markAllRead() {
            if (this.count === 0) return;
            fetch("/ajax/notifications/read", { method: "POST", headers: { "X-CSRF-TOKEN": csrf } })
                .then(() => {
                    this.count = 0;
                    this.items = this.items.map(n => ({ ...n, read_at: new Date().toISOString() }));
                    paintNavBadges({});   // the menu was cleared along with the bell
                });
        },

        /* Marks the one being opened, rather than wiping the rest. keepalive
         * lets the request finish after the browser has started navigating. */
        markOneRead(n) {
            if (n.read_at) return;
            n.read_at = new Date().toISOString();
            this.count = Math.max(0, this.count - 1);
            fetch("/ajax/notifications/" + n.id + "/read", {
                method: "POST",
                headers: { "X-CSRF-TOKEN": csrf },
                keepalive: true,
            }).catch(() => {});
        },
    };
}

// Select2 global init
$(document).ready(function () {
    $(".select2").select2({ theme: "classic", placeholder: "Select...", allowClear: true });
    $(".select2-ajax-employees").select2({
        theme: "classic", placeholder: "Search employee...", allowClear: true,
        ajax: {
            url: "/ajax/employees/search", dataType: "json", delay: 250,
            data: function (params) {
                // Some pickers must not offer the signed-in user their own name
                // - you cannot cover your own leave.
                return { q: params.term, exclude_self: $(this).data("exclude-self") ? 1 : 0 };
            },
            processResults: function (data) { return { results: data.results }; }
        }
    });
});
</script>

{{-- Staff messaging. Included before the scripts stack so the panel's own
     @push lands after Alpine is available. Signed-in pages only - there is
     nobody to message from the login screen. --}}
@auth
    @include('partials.chat-panel')
@endauth

{{-- Progress feedback.

     Sending an appraisal, uploading a document or running payroll all take a
     visible moment, and the page gave no sign anything was happening - so people
     clicked twice. Any submit button now turns into a spinner and locks until
     the page moves, and forms carrying a file get a full overlay because an
     upload can run for a while.

     Opt out on a form with data-no-loader; set the wording with
     data-loading-label on the button. --}}
<div id="hrms-overlay" class="hrms-overlay" hidden>
    <div class="hrms-overlay-card">
        {{-- Indeterminate work (a payroll run) keeps the ring. An upload swaps
             it for a real bar, because "it is still going" and "it is 7% done
             and will take nine more minutes" are very different messages to
             somebody holding a phone on a client site. --}}
        <div class="hrms-ring" data-role="ring"></div>

        <div class="hrms-prog" data-role="prog" hidden>
            <div class="hrms-prog-pct" data-role="pct">0%</div>
            <div class="hrms-prog-track"><div class="hrms-prog-fill" data-role="fill"></div></div>
            <div class="hrms-prog-meta">
                <span data-role="bytes"></span>
                <span data-role="rate"></span>
            </div>
        </div>

        <p class="hrms-overlay-text" data-role="text">Uploading…</p>
        <p class="hrms-overlay-sub"  data-role="sub">Please keep this page open.</p>
        <p class="hrms-overlay-file" data-role="file" hidden></p>

        <button type="button" class="hrms-prog-cancel" data-role="cancel" hidden>Cancel upload</button>
    </div>
</div>

<style>
    @keyframes hrms-spin { to { transform: rotate(360deg); } }
    @keyframes hrms-fade { from { opacity: 0; } to { opacity: 1; } }

    .hrms-spinner {
        display: inline-block; width: 0.85em; height: 0.85em;
        border: 2px solid currentColor; border-right-color: transparent;
        border-radius: 50%; animation: hrms-spin 0.6s linear infinite;
        vertical-align: -0.1em; margin-right: 0.4em;
    }

    .hrms-overlay {
        position: fixed; inset: 0; z-index: 9999;
        background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px);
        display: flex; align-items: center; justify-content: center;
        animation: hrms-fade 0.15s ease-out;
    }
    .hrms-overlay[hidden] { display: none; }

    .hrms-overlay-card {
        background: #fff; border-radius: 0.9rem; padding: 1.6rem 2.2rem;
        text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,.2);
    }
    .hrms-ring {
        width: 42px; height: 42px; margin: 0 auto 0.9rem;
        border: 3px solid #e2e8f0; border-top-color: #2563eb;
        border-radius: 50%; animation: hrms-spin 0.7s linear infinite;
    }
    .hrms-overlay-text { font-weight: 600; color: #1e293b; font-size: 0.95rem; }
    .hrms-overlay-sub  { color: #64748b; font-size: 0.78rem; margin-top: 0.15rem; }

    /* The button keeps its size while its label changes, so nothing jumps. */
    button[data-loading] { opacity: .85; cursor: progress; }

    /* ── Upload progress ────────────────────────────────────────────── */
    .hrms-overlay-card { min-width: 300px; max-width: 92vw; }
    .hrms-prog[hidden] { display: none; }
    .hrms-prog { margin: 0 auto 0.9rem; }

    .hrms-prog-pct {
        font-size: 1.9rem; font-weight: 700; color: #1e293b;
        line-height: 1.1; margin-bottom: 0.55rem;
        font-variant-numeric: tabular-nums;   /* the number stops jittering */
    }
    .hrms-prog-track {
        height: 9px; background: #e2e8f0; border-radius: 999px; overflow: hidden;
    }
    .hrms-prog-fill {
        height: 100%; width: 0%; border-radius: 999px;
        background: linear-gradient(90deg, #2563eb, #38bdf8);
        transition: width .18s ease-out;
    }
    /* The last stretch is the server writing the file, with nothing left to
       report — so the bar keeps moving rather than sitting at 100% looking
       stuck. */
    .hrms-prog-fill.is-finishing {
        background: linear-gradient(90deg, #2563eb, #38bdf8, #2563eb);
        background-size: 200% 100%;
        animation: hrms-slide 1.1s linear infinite;
    }
    @keyframes hrms-slide { to { background-position: -200% 0; } }

    .hrms-prog-meta {
        display: flex; justify-content: space-between; gap: 1rem;
        font-size: 0.72rem; color: #64748b; margin-top: 0.4rem;
        font-variant-numeric: tabular-nums;
    }
    .hrms-overlay-file {
        color: #475569; font-size: 0.75rem; margin-top: 0.5rem;
        max-width: 26rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .hrms-overlay-file[hidden] { display: none; }

    .hrms-prog-cancel {
        margin-top: 0.9rem; border: 1px solid #e2e8f0; background: #fff;
        color: #64748b; font-size: 0.76rem; font-weight: 600;
        padding: 0.32rem 0.85rem; border-radius: 0.5rem; cursor: pointer;
    }
    .hrms-prog-cancel:hover { background: #f8fafc; color: #334155; }
    .hrms-prog-cancel[hidden] { display: none; }

    .hrms-overlay-card.is-error .hrms-prog-fill { background: #dc2626; animation: none; }
    .hrms-overlay-card.is-error .hrms-overlay-text { color: #b91c1c; }
</style>

<script>
(function () {
    const overlay = document.getElementById('hrms-overlay');

    // Bubble phase, not capture: several forms carry onsubmit="return confirm(...)"
    // and a capturing listener would start the spinner before the user had
    // answered - then leave it spinning on a submit they cancelled.
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.hasAttribute('data-no-loader')) return;

        // Somebody already cancelled this submit - a confirm() answered "no", or
        // a script called preventDefault. Nothing is going anywhere.
        if (e.defaultPrevented) return;

        // A form the browser is about to reject never reaches the server, and a
        // spinner left running on it would have to be clicked away.
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;

        // The button that actually triggered this submit. Two cases make the
        // old "first submit button in the form" guess wrong: the overtime rows,
        // where Approve and Reject share one form and differ only by
        // formaction, and buttons that sit outside their form and are tied to
        // it by a form="" attribute - querySelector never sees those at all.
        const btn = e.submitter
            || form.querySelector('button[type="submit"], button:not([type])');

        if (btn && btn.tagName === 'BUTTON' && !btn.dataset.loading) {
            const width = btn.offsetWidth;
            btn.dataset.loading = '1';
            btn.dataset.originalHtml = btn.innerHTML;
            btn.style.minWidth = width + 'px';

            // An icon-only button (the row actions on the payroll list) gets the
            // spinner alone. Adding wording would stretch it and break the row.
            const isIconOnly = btn.textContent.trim() === '';
            btn.innerHTML = isIconOnly
                ? '<span class="hrms-spinner" style="margin:0"></span>'
                : '<span class="hrms-spinner"></span>' + (btn.dataset.loadingLabel || 'Working…');

            // Disabled on the next tick: doing it inside the submit handler can
            // drop the button's own name/value from what is posted.
            setTimeout(function () { btn.disabled = true; }, 0);
        }

        // The overlay is for work that runs long enough that somebody would
        // otherwise navigate away mid-way: an upload in progress, or a payroll
        // run grinding through hundreds of payslips.
        const fileInputs = Array.from(form.querySelectorAll('input[type="file"]'))
            .filter(i => i.files && i.files.length > 0);
        const hasFile = fileInputs.length > 0;
        const wantsOverlay = form.hasAttribute('data-loading-overlay')
            || (btn && btn.hasAttribute && btn.hasAttribute('data-loading-overlay'));

        // An upload is sent by hand so its progress can actually be watched. A
        // plain form post is done by the browser, which reports nothing back,
        // so the old overlay could only spin and hope. Everything else — the
        // payroll run, a long report — still goes the ordinary way.
        if (hasFile && canSendByHand()) {
            const stop = sendWithProgress(e, form, btn, fileInputs);
            if (stop) return;   // handled; the native submit was cancelled
        }

        if ((hasFile || wantsOverlay) && overlay) {
            showOverlay({
                text: form.dataset.loadingLabel
                    || (btn && btn.dataset && btn.dataset.loadingLabel)
                    || (hasFile ? 'Uploading…' : 'Working…'),
                sub: form.dataset.loadingSub || 'Please keep this page open.',
            });
        }
    });

    // ── Sending an upload by hand ───────────────────────────────────────────

    function canSendByHand() {
        return typeof FormData !== 'undefined'
            && typeof XMLHttpRequest !== 'undefined'
            && 'upload' in new XMLHttpRequest();
    }

    function bytes(n) {
        if (!isFinite(n) || n < 0) return '';
        if (n < 1024) return n + ' B';
        if (n < 1048576) return (n / 1024).toFixed(0) + ' KB';
        if (n < 1073741824) return (n / 1048576).toFixed(1) + ' MB';
        return (n / 1073741824).toFixed(2) + ' GB';
    }

    function duration(seconds) {
        if (!isFinite(seconds) || seconds < 0) return '';
        if (seconds < 60) return Math.ceil(seconds) + 's left';
        const m = Math.floor(seconds / 60);
        if (m < 60) return m + 'm ' + Math.ceil(seconds % 60) + 's left';
        return Math.floor(m / 60) + 'h ' + (m % 60) + 'm left';
    }

    const el = (role) => overlay && overlay.querySelector('[data-role="' + role + '"]');

    function showOverlay(opts) {
        if (!overlay) return;
        const card = overlay.querySelector('.hrms-overlay-card');
        card.classList.remove('is-error');
        el('text').textContent = opts.text || 'Working…';
        el('sub').textContent  = opts.sub || '';
        el('ring').hidden   = !!opts.progress;
        el('prog').hidden   = !opts.progress;
        el('cancel').hidden = !opts.cancellable;
        el('file').hidden   = !opts.file;
        if (opts.file) el('file').textContent = opts.file;
        overlay.hidden = false;
    }

    function hideOverlay() {
        if (!overlay) return;
        overlay.hidden = true;
        overlay.querySelector('.hrms-overlay-card').classList.remove('is-error');
        el('fill').style.width = '0%';
        el('fill').classList.remove('is-finishing');
        el('pct').textContent = '0%';
        el('bytes').textContent = '';
        el('rate').textContent = '';
    }

    function restoreButton(b) {
        if (!b || !b.dataset.loading) return;
        b.innerHTML = b.dataset.originalHtml || b.innerHTML;
        b.disabled = false;
        b.style.minWidth = '';
        delete b.dataset.loading;
    }

    function failOverlay(message, btn) {
        restoreButton(btn);
        if (!overlay) { alert(message); return; }
        overlay.querySelector('.hrms-overlay-card').classList.add('is-error');
        el('ring').hidden = true;
        el('prog').hidden = false;
        el('fill').classList.remove('is-finishing');
        el('fill').style.width = '100%';
        el('pct').textContent = '';
        el('bytes').textContent = '';
        el('rate').textContent = '';
        el('text').textContent = message;
        el('sub').textContent = 'Nothing was saved. Close this and try again.';
        el('cancel').hidden = false;
        el('cancel').textContent = 'Close';
    }

    /**
     * Checks the file is within the limits BEFORE sending it.
     *
     * A size rule in a controller can only run once the whole file has
     * arrived: a 205MB clip spent minutes uploading and was then told it was
     * too big. The limit and the accepted list come from the same config the
     * server validates against, rendered into the page, so the two cannot say
     * different things.
     */
    function checkFile(file, input) {
        const perInput = parseFloat(input.dataset.maxMb || '');
        const maxMb = isFinite(perInput) && perInput > 0
            ? perInput
            : parseFloat(document.querySelector('meta[name="upload-max-mb"]')?.content || '0');

        if (maxMb > 0 && file.size > maxMb * 1048576) {
            return 'That file is ' + bytes(file.size) + '. The limit is ' + maxMb + 'MB.';
        }

        // Only when the input says what it takes; an input with no accept
        // attribute is left to the server to judge.
        const accept = (input.getAttribute('accept') || '').trim();
        if (accept) {
            const exts = accept.split(',').map(s => s.trim().toLowerCase()).filter(s => s.startsWith('.'));
            if (exts.length) {
                const name = file.name.toLowerCase();
                if (!exts.some(x => name.endsWith(x))) {
                    return 'That file type is not accepted here.';
                }
            }
        }
        return null;
    }

    function sendWithProgress(event, form, btn, fileInputs) {
        // Everything selected, checked before a single byte goes out.
        for (const input of fileInputs) {
            for (const file of input.files) {
                const problem = checkFile(file, input);
                if (problem) {
                    event.preventDefault();
                    restoreButton(btn);
                    showOverlay({ text: problem, sub: 'Choose a different file.', cancellable: true });
                    overlay.querySelector('.hrms-overlay-card').classList.add('is-error');
                    el('cancel').textContent = 'Close';
                    return true;
                }
            }
        }

        const data = new FormData(form);

        // FormData leaves the submit button out, and several screens rely on
        // its name to tell which action was pressed.
        if (btn && btn.name) data.append(btn.name, btn.value || '');

        const action = (btn && btn.getAttribute('formaction')) || form.action || window.location.href;
        const method = ((btn && btn.getAttribute('formmethod')) || form.method || 'POST').toUpperCase();

        const total = fileInputs.reduce((sum, i) =>
            sum + Array.from(i.files).reduce((s, f) => s + f.size, 0), 0);
        const names = fileInputs.flatMap(i => Array.from(i.files).map(f => f.name));

        const xhr = new XMLHttpRequest();
        xhr.open(method, action, true);
        xhr.responseType = 'text';

        // Deliberately NOT X-Requested-With: that makes Laravel answer a
        // validation failure with 422 JSON instead of the redirect-back these
        // forms are written for. Left alone, the redirect is followed here and
        // the page it lands on carries the flash message or the error list.
        xhr.setRequestHeader('Accept', 'text/html');

        let started = Date.now(), lastShown = 0;

        xhr.upload.addEventListener('progress', function (ev) {
            if (!ev.lengthComputable) return;
            const pct = Math.min(99, Math.floor((ev.loaded / ev.total) * 100));

            // Repainting on every event makes the number unreadable.
            const now = Date.now();
            if (now - lastShown < 100 && pct < 99) return;
            lastShown = now;

            el('pct').textContent = pct + '%';
            el('fill').style.width = pct + '%';
            el('bytes').textContent = bytes(ev.loaded) + ' of ' + bytes(ev.total);

            const elapsed = (now - started) / 1000;
            if (elapsed > 1.5 && ev.loaded > 0) {
                const rate = ev.loaded / elapsed;
                const left = (ev.total - ev.loaded) / rate;
                el('rate').textContent = bytes(rate) + '/s · ' + duration(left);
            }
        });

        // The bytes are all sent; the server is still writing the file. Saying
        // 100% and freezing looks like a hang, so the bar keeps moving.
        xhr.upload.addEventListener('load', function () {
            el('pct').textContent = '100%';
            el('fill').style.width = '100%';
            el('fill').classList.add('is-finishing');
            el('rate').textContent = '';
            el('text').textContent = 'Saving…';
            el('sub').textContent = 'The file is uploaded. Finishing up.';
            el('cancel').hidden = true;
        });

        xhr.addEventListener('load', function () {
            if (xhr.status >= 200 && xhr.status < 400) {
                // The redirect has already been followed, so responseURL is the
                // page the server wants us on — with its flash message.
                window.location.href = xhr.responseURL || window.location.href;
                return;
            }
            if (xhr.status === 413) {
                failOverlay('The server refused that file — it is too large.', btn);
            } else if (xhr.status === 419) {
                failOverlay('Your session expired while uploading.', btn);
                el('sub').textContent = 'Sign in again, then retry. Reloading…';
                setTimeout(() => window.location.reload(), 2500);
            } else if (xhr.status === 422) {
                failOverlay('That file was rejected.', btn);
            } else {
                failOverlay('Upload failed (error ' + xhr.status + ').', btn);
            }
        });

        xhr.addEventListener('error', function () {
            failOverlay('The connection dropped during upload.', btn);
        });

        xhr.addEventListener('abort', function () {
            hideOverlay();
            restoreButton(btn);
        });

        el('cancel').onclick = function () {
            if (el('cancel').textContent === 'Close') { hideOverlay(); restoreButton(btn); return; }
            xhr.abort();
        };
        el('cancel').textContent = 'Cancel upload';

        showOverlay({
            text: form.dataset.loadingLabel || 'Uploading…',
            sub: 'Please keep this page open.',
            progress: true,
            cancellable: true,
            file: names.length === 1 ? names[0] : names.length + ' files · ' + bytes(total),
        });
        el('bytes').textContent = bytes(0) + ' of ' + bytes(total);

        event.preventDefault();
        xhr.send(data);
        return true;
    }

    // Coming back via the back button restores a cached page with its buttons
    // still spinning, so they are put back the way they were.
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        if (overlay) overlay.hidden = true;
        document.querySelectorAll('button[data-loading]').forEach(function (btn) {
            btn.innerHTML = btn.dataset.originalHtml || btn.innerHTML;
            btn.disabled = false;
            btn.style.minWidth = '';
            delete btn.dataset.loading;
        });
    });
})();
</script>
@stack("scripts")
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
</script>
</body>
</html>
