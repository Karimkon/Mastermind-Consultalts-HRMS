<aside class="space-y-6">
    {{-- Search --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <h2 class="font-semibold text-slate-800 mb-3">Search insights</h2>
        <form action="{{ route('blog.index') }}" method="GET" class="relative">
            <input type="search" name="q" value="{{ $search ?? '' }}"
                   placeholder="Search articles..." aria-label="Search articles"
                   class="w-full rounded-xl border border-slate-200 pl-10 pr-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
            <i class="fas fa-search absolute left-3.5 top-3.5 text-slate-400 text-sm"></i>
        </form>
    </div>

    {{-- Topics --}}
    @if(isset($categories) && $categories->count())
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-800 mb-3">Topics</h2>
            <ul class="space-y-1">
                @foreach($categories as $category)
                    <li>
                        <a href="{{ $category->url }}"
                           class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition
                                  {{ (isset($activeCategory) && $activeCategory->id === $category->id) ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-slate-600 hover:bg-slate-50' }}">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full" style="background: {{ $category->color ?: '#1d4ed8' }}"></span>
                                {{ $category->name }}
                            </span>
                            <span class="text-xs text-slate-400">{{ $category->posts_count ?? 0 }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Open roles: the article's job is to bring people here --}}
    @if(isset($jobs) && $jobs->count())
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-slate-800">Open roles</h2>
                <a href="{{ route('careers.index') }}" class="text-xs text-blue-600 hover:underline">All jobs</a>
            </div>
            <ul class="space-y-3">
                @foreach($jobs as $job)
                    <li>
                        <a href="{{ route('careers.show', $job->slug ?: $job->id) }}" class="block group">
                            <p class="text-sm font-semibold text-slate-800 group-hover:text-blue-700 leading-snug">{{ $job->title }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">
                                {{ $job->department->name ?? 'Mastermind Consult' }}@if($job->location) &middot; {{ $job->location }}@endif
                            </p>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Most read --}}
    @if(isset($popular) && $popular->count())
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-800 mb-4">Most read</h2>
            <ol class="space-y-4">
                @foreach($popular as $i => $item)
                    <li class="flex gap-3">
                        <span class="font-bold text-lg text-blue-200 leading-none w-5">{{ $i + 1 }}</span>
                        <div class="min-w-0">
                            <a href="{{ $item->url }}" class="text-sm font-semibold text-slate-700 hover:text-blue-700 line-clamp-2 leading-snug">
                                {{ $item->title }}
                            </a>
                            <p class="text-xs text-slate-400 mt-1">{{ $item->reading_time }} min read</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    {{-- Tags --}}
    @if(isset($tags) && $tags && $tags->count())
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-800 mb-3">Tags</h2>
            <div class="flex flex-wrap gap-2">
                @foreach($tags as $tag)
                    <a href="{{ $tag->url }}"
                       class="px-3 py-1.5 rounded-full text-xs font-medium bg-slate-50 text-slate-600 border border-slate-100 hover:bg-blue-600 hover:text-white hover:border-blue-600 transition">
                        #{{ $tag->name }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Talk to us --}}
    <div class="rounded-2xl p-6 text-white" style="background: linear-gradient(135deg,#1d4ed8 0%,#1e3a8a 100%)">
        <h2 class="font-bold text-lg mb-1">Need an HR partner?</h2>
        <p class="text-sm text-blue-100 mb-4">
            Recruitment, payroll, outsourcing and HR systems — handled by a team that does this every day.
        </p>
        <a href="{{ url('/#contact') }}"
           class="inline-block bg-white text-blue-700 font-semibold rounded-xl px-5 py-2.5 text-sm hover:bg-blue-50 transition">
            Talk to us
        </a>
    </div>
</aside>
