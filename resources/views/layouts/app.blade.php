<!DOCTYPE html>
<html lang="en" x-data="{ sidebarOpen: localStorage.getItem('sidebarOpen') !== 'false', mobileMenu: false, toggleSidebar(){ this.sidebarOpen=!this.sidebarOpen; localStorage.setItem('sidebarOpen', this.sidebarOpen); } }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1d4ed8">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
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

        /* Sidebar */
        .sidebar-link { display:flex; align-items:center; gap:0.75rem; padding:0.625rem 1rem; border-radius:0.5rem; font-size:0.875rem; font-weight:500; color:#cbd5e1; text-decoration:none; transition:all 0.15s; }
        .sidebar-link:hover { background:#334155; color:#fff; }
        .sidebar-link.active { background:#1d4ed8; color:#fff; }
        .sidebar-group { padding:0.25rem 0.75rem; font-size:0.65rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.25rem; margin-top:1rem; }

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
<aside class="fixed inset-y-0 left-0 z-50 flex flex-col w-64 bg-slate-900 transition-transform duration-300"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0 lg:w-16'">

    {{-- Logo --}}
    <div class="flex items-center gap-3 h-16 px-4 border-b border-slate-700/50 shrink-0">
        <div class="flex items-center justify-center w-9 h-9 bg-blue-600 rounded-xl shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        </div>
        <span class="text-white font-semibold text-sm" x-show="sidebarOpen">Mastermind HRMS</span>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-0.5">
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="fas fa-home w-4 text-center"></i><span x-show="sidebarOpen">Dashboard</span>
        </a>

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
            <i class="fas fa-calendar-minus w-4 text-center"></i><span x-show="sidebarOpen">My Leave</span>
        </a>
        <a href="{{ route('training.index') }}" class="sidebar-link {{ request()->routeIs('training.*') ? 'active' : '' }}">
            <i class="fas fa-graduation-cap w-4 text-center"></i><span x-show="sidebarOpen">Training</span>
        </a>
        <a href="{{ route('meetings.calendar') }}" class="sidebar-link {{ request()->routeIs('meetings.*') ? 'active' : '' }}">
            <i class="fas fa-calendar-alt w-4 text-center"></i><span x-show="sidebarOpen">Calendar</span>
        </a>
        @endrole

        {{-- ===== PAYROLL OFFICER ===== --}}
        @role('payroll-officer')
        <p class="sidebar-group" x-show="sidebarOpen">Payroll</p>
        <a href="{{ route('payroll.index') }}" class="sidebar-link {{ request()->routeIs('payroll.*') ? 'active' : '' }}">
            <i class="fas fa-money-bill-wave w-4 text-center"></i><span x-show="sidebarOpen">Payroll Runs</span>
        </a>
        <a href="{{ route('salary.index') }}" class="sidebar-link {{ request()->routeIs('salary.*') ? 'active' : '' }}">
            <i class="fas fa-coins w-4 text-center"></i><span x-show="sidebarOpen">Salary Setup</span>
        </a>
        <a href="{{ route('employees.index') }}" class="sidebar-link {{ request()->routeIs('employees.*') ? 'active' : '' }}">
            <i class="fas fa-users w-4 text-center"></i><span x-show="sidebarOpen">Employees</span>
        </a>
        <a href="{{ route('reports.payroll') }}" class="sidebar-link {{ request()->routeIs('reports.payroll*') ? 'active' : '' }}">
            <i class="fas fa-chart-bar w-4 text-center"></i><span x-show="sidebarOpen">Payroll Report</span>
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
        @endrole

        {{-- ===== MANAGER / HR ADMIN / SUPER ADMIN: full menu ===== --}}
        @role('super-admin|hr-admin|manager')
        <p class="sidebar-group" x-show="sidebarOpen">Human Resources</p>
        <a href="{{ route('employees.index') }}" class="sidebar-link {{ request()->routeIs('employees.*') ? 'active' : '' }}">
            <i class="fas fa-users w-4 text-center"></i><span x-show="sidebarOpen">Employees</span>
        </a>
        <a href="{{ route('attendance.index') }}" class="sidebar-link {{ request()->routeIs('attendance.*') ? 'active' : '' }}">
            <i class="fas fa-clock w-4 text-center"></i><span x-show="sidebarOpen">Attendance</span>
        </a>
        <a href="{{ route('leaves.index') }}" class="sidebar-link {{ request()->routeIs('leaves.*') ? 'active' : '' }}">
            <i class="fas fa-calendar-minus w-4 text-center"></i><span x-show="sidebarOpen">Leave</span>
        </a>
        @role('super-admin|hr-admin')
        <a href="{{ route('payroll.index') }}" class="sidebar-link {{ request()->routeIs('payroll.*') || request()->routeIs('salary.*') ? 'active' : '' }}">
            <i class="fas fa-money-bill-wave w-4 text-center"></i><span x-show="sidebarOpen">Payroll</span>
        </a>
        @endrole

        <p class="sidebar-group" x-show="sidebarOpen">Talent</p>
        @role('super-admin|hr-admin')
        <a href="{{ route('recruitment.jobs.index') }}" class="sidebar-link {{ request()->routeIs('recruitment.*') ? 'active' : '' }}">
            <i class="fas fa-briefcase w-4 text-center"></i><span x-show="sidebarOpen">Recruitment</span>
        </a>
        @endrole
        <a href="{{ route('performance.index') }}" class="sidebar-link {{ request()->routeIs('performance.index') ? 'active' : '' }}">
            <i class="fas fa-chart-line w-4 text-center"></i><span x-show="sidebarOpen">Performance</span>
        </a>
        <a href="{{ route('goals.index') }}" class="sidebar-link {{ request()->routeIs('goals.*') ? 'active' : '' }}">
            <i class="fas fa-bullseye w-4 text-center"></i><span x-show="sidebarOpen">Goals</span>
        </a>
        <a href="{{ route('pips.index') }}" class="sidebar-link {{ request()->routeIs('pips.*') ? 'active' : '' }}">
            <i class="fas fa-clipboard-list w-4 text-center"></i><span x-show="sidebarOpen">PIPs</span>
        </a>
        <a href="{{ route('training.index') }}" class="sidebar-link {{ request()->routeIs('training.*') ? 'active' : '' }}">
            <i class="fas fa-graduation-cap w-4 text-center"></i><span x-show="sidebarOpen">Training</span>
        </a>

        <p class="sidebar-group" x-show="sidebarOpen">Workspace</p>
        <a href="{{ route('meetings.index') }}" class="sidebar-link {{ request()->routeIs('meetings.index') || request()->routeIs('meetings.show*') ? 'active' : '' }}">
            <i class="fas fa-video w-4 text-center"></i><span x-show="sidebarOpen">Meetings</span>
        </a>
        <a href="{{ route('meetings.calendar') }}" class="sidebar-link {{ request()->routeIs('meetings.calendar*') ? 'active' : '' }}">
            <i class="fas fa-calendar-alt w-4 text-center"></i><span x-show="sidebarOpen">Calendar</span>
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
        <a href="{{ route('admin.audit.index') }}" class="sidebar-link {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}">
            <i class="fas fa-history w-4 text-center"></i><span x-show="sidebarOpen">Audit Logs</span>
        </a>
        <a href="{{ route('admin.documentation.pdf') }}" target="_blank" class="sidebar-link">
            <i class="fas fa-file-pdf w-4 text-center"></i><span x-show="sidebarOpen">System Docs</span>
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

{{-- MAIN CONTENT --}}
<div class="transition-all duration-300" style="margin-left:256px" :style="sidebarOpen ? 'margin-left:256px' : 'margin-left:0px'">

    {{-- TOP NAVIGATION --}}
    <header class="sticky top-0 z-40 flex items-center justify-between h-16 bg-white border-b border-slate-200 px-6 shadow-sm">
        <div class="flex items-center gap-4">
            <button @click="toggleSidebar()" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors lg:hidden">
                <i class="fas fa-bars"></i>
            </button>
            {{-- Breadcrumb --}}
            <nav class="hidden sm:flex items-center gap-2 text-sm">
                <a href="{{ route('dashboard') }}" class="text-slate-400 hover:text-slate-600">Home</a>
                @hasSection("breadcrumb")
                    <i class="fas fa-chevron-right text-slate-300 text-xs"></i>
                    @yield("breadcrumb")
                @endif
            </nav>
        </div>

        <div class="flex items-center gap-3">
            {{-- Notifications --}}
            <div class="relative" x-data="{ open: false, count: 0, items: [] }" x-init="fetchNotifications()">
                <button @click="open = !open; markAllRead()" class="relative p-2 rounded-lg text-slate-500 hover:bg-slate-100 transition-colors">
                    <i class="fas fa-bell"></i>
                    <span x-show="count > 0" x-text="count" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-bold"></span>
                </button>
                <div x-show="open" @click.outside="open = false" x-transition class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-slate-200 py-2 z-50">
                    <div class="px-4 py-2 border-b border-slate-100 flex justify-between items-center">
                        <span class="font-semibold text-sm text-slate-700">Notifications</span>
                        <button @click="markAll()" class="text-xs text-blue-600 hover:underline">Mark all read</button>
                    </div>
                    <div class="max-h-72 overflow-y-auto">
                        <template x-for="n in items" :key="n.id">
                            <div class="px-4 py-3 hover:bg-slate-50 border-b border-slate-50 cursor-pointer" :class="!n.read_at ? 'bg-blue-50/40' : ''">
                                <p class="text-sm font-medium text-slate-800" x-text="n.title"></p>
                                <p class="text-xs text-slate-500 mt-0.5" x-text="n.body"></p>
                            </div>
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

function fetchNotifications() {
    fetch("/ajax/notifications").then(r => r.json()).then(data => {
        this.count = data.unread;
        this.items = data.notifications;
    });
}
function markAllRead() {
    if (this.count > 0) {
        fetch("/ajax/notifications/read", {method:"POST", headers:{"X-CSRF-TOKEN":csrf}})
            .then(() => { this.count = 0; });
    }
}

// Select2 global init
$(document).ready(function () {
    $(".select2").select2({ theme: "classic", placeholder: "Select...", allowClear: true });
    $(".select2-ajax-employees").select2({
        theme: "classic", placeholder: "Search employee...", allowClear: true,
        ajax: { url: "/ajax/employees/search", dataType: "json", delay: 250,
            processResults: function (data) { return { results: data.results }; }
        }
    });
});
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
