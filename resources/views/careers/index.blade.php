<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Careers — Mastermind Consultants</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <style>body{font-family:"Inter",sans-serif;}</style>
</head>
<body class="bg-slate-50">

{{-- Header --}}
<header class="bg-white border-b border-slate-200 sticky top-0 z-50">
    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center">
                <i class="fas fa-users text-white text-sm"></i>
            </div>
            <div>
                <p class="font-bold text-slate-800 text-sm">Mastermind Consultants</p>
                <p class="text-xs text-slate-500">Careers Portal</p>
            </div>
        </div>
        <a href="{{ route('login') }}" class="text-sm text-blue-600 font-medium hover:underline">Employee Login →</a>
    </div>
</header>

{{-- Hero --}}
<section class="bg-gradient-to-br from-blue-700 to-blue-900 text-white py-16">
    <div class="max-w-6xl mx-auto px-4 text-center">
        <h1 class="text-4xl font-extrabold mb-3">Join Our Team</h1>
        <p class="text-blue-200 text-lg mb-8">{{ $totalJobs }} open position{{ $totalJobs !== 1 ? 's' : '' }} across multiple companies</p>
        <form method="GET" action="{{ route('careers.index') }}" class="max-w-xl mx-auto flex gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search job title or location..."
                class="flex-1 px-4 py-3 rounded-xl text-slate-800 text-sm outline-none focus:ring-2 focus:ring-blue-300">
            <button type="submit" class="bg-white text-blue-700 font-semibold px-6 py-3 rounded-xl text-sm hover:bg-blue-50 transition">Search</button>
        </form>
    </div>
</section>

{{-- Filters + Jobs --}}
<main class="max-w-6xl mx-auto px-4 py-10">

    {{-- Alert --}}
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-5 py-4 mb-6 flex items-center gap-3">
        <i class="fas fa-check-circle text-green-500"></i> {{ session('success') }}
    </div>
    @endif

    <div class="flex flex-col md:flex-row gap-6">

        {{-- Sidebar filters --}}
        <aside class="w-full md:w-56 shrink-0">
            <form method="GET" class="bg-white rounded-2xl border border-slate-200 p-5 space-y-5 sticky top-20">
                <h3 class="font-semibold text-slate-700">Filter Jobs</h3>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">Department</label>
                    <select name="department" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 outline-none">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ request('department')==$dept->id?'selected':'' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">Job Type</label>
                    <select name="type" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 outline-none">
                        <option value="">All Types</option>
                        @foreach(['full_time'=>'Full Time','part_time'=>'Part Time','contract'=>'Contract','intern'=>'Internship'] as $v=>$l)
                        <option value="{{ $v }}" {{ request('type')==$v?'selected':'' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                <button type="submit" class="w-full bg-blue-600 text-white text-sm font-medium py-2 rounded-lg hover:bg-blue-700 transition">Apply Filters</button>
                @if(request()->anyFilled(['department','type','search']))
                <a href="{{ route('careers.index') }}" class="block text-center text-xs text-slate-400 hover:text-slate-600">Clear filters</a>
                @endif
            </form>
        </aside>

        {{-- Job grid --}}
        <section class="flex-1">
            <p class="text-sm text-slate-500 mb-4">{{ $jobs->total() }} job{{ $jobs->total() !== 1 ? 's' : '' }} found</p>
            @forelse($jobs as $job)
            <a href="{{ route('careers.show', $job) }}" class="block bg-white rounded-2xl border border-slate-200 hover:border-blue-300 hover:shadow-md transition p-6 mb-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex flex-wrap gap-2 mb-2">
                            <span class="text-xs font-medium bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{{ $job->department?->name ?? 'General' }}</span>
                            <span class="text-xs font-medium bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">{{ ucfirst(str_replace('_',' ',$job->employment_type)) }}</span>
                            @if($job->location)<span class="text-xs text-slate-500"><i class="fas fa-map-marker-alt mr-1"></i>{{ $job->location }}</span>@endif
                        </div>
                        <h2 class="text-lg font-bold text-slate-800 mb-1">{{ $job->title }}</h2>
                        <p class="text-sm text-slate-500 line-clamp-2">{{ Str::limit(strip_tags($job->description), 120) }}</p>
                    </div>
                    <div class="text-right shrink-0">
                        @if($job->salary_min && $job->salary_max)
                        <p class="text-sm font-semibold text-green-700">R{{ number_format($job->salary_min/1000,0) }}K–R{{ number_format($job->salary_max/1000,0) }}K</p>
                        @endif
                        <p class="text-xs text-slate-400 mt-1">{{ $job->created_at->diffForHumans() }}</p>
                        @if($job->deadline)<p class="text-xs text-{{ $job->deadline->isPast() ? 'red' : 'orange' }}-500 mt-1">Closes {{ $job->deadline->format('d M Y') }}</p>@endif
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <span class="text-xs text-slate-500">{{ $job->vacancies }} vacancy{{ $job->vacancies > 1 ? 'ies' : '' }}</span>
                    <span class="text-sm font-semibold text-blue-600 hover:underline">View & Apply →</span>
                </div>
            </a>
            @empty
            <div class="text-center py-20">
                <i class="fas fa-briefcase text-5xl text-slate-300 mb-4 block"></i>
                <p class="text-slate-500 text-lg">No open positions match your search.</p>
                <a href="{{ route('careers.index') }}" class="text-blue-600 text-sm hover:underline mt-2 block">View all jobs</a>
            </div>
            @endforelse
            <div class="mt-6">{{ $jobs->withQueryString()->links() }}</div>
        </section>
    </div>
</main>

<footer class="bg-white border-t border-slate-200 py-6 text-center text-sm text-slate-400">
    &copy; {{ date('Y') }} Mastermind Consultants. All rights reserved.
</footer>
</body>
</html>