@extends('blog.layout')

@php
    $pageTitle = $metaTitle ?? $heading;
    $pageDescription = $metaDescription ?? ($subheading ?? 'Practical HR guidance from Mastermind Consult Ltd.');
@endphp

@section('title', $pageTitle)
@section('meta_description', $pageDescription)
@section('canonical_url', $posts->currentPage() > 1 ? $posts->url($posts->currentPage()) : $canonicalUrl)
@section('og_url', $canonicalUrl)
@section('og_title', $pageTitle)
@section('og_description', $pageDescription)
@if($featured && $featured->og_image_url)
@section('og_image', $featured->og_image_url)
@endif

@push('structured_data')
    {!! \App\Services\SeoService::script(\App\Services\SeoService::breadcrumbs($crumbs)) !!}
    {!! \App\Services\SeoService::script(\App\Services\SeoService::blogListing($heading, $canonicalUrl, $posts->items())) !!}
@endpush

@push('styles')
    @if($posts->previousPageUrl())<link rel="prev" href="{{ $posts->previousPageUrl() }}">@endif
    @if($posts->nextPageUrl())<link rel="next" href="{{ $posts->nextPageUrl() }}">@endif
@endpush

@section('content')

{{-- Hero --}}
<section class="bg-gradient-to-br from-blue-700 to-blue-900 text-white">
    <div class="max-w-6xl mx-auto px-4 py-14">
        <nav aria-label="Breadcrumb" class="mb-5">
            <ol class="flex flex-wrap items-center gap-2 text-sm text-blue-200">
                @foreach($crumbs as $i => $crumb)
                    <li class="flex items-center gap-2">
                        @if($i < count($crumbs) - 1)
                            <a href="{{ $crumb['url'] }}" class="hover:text-white transition">{{ $crumb['name'] }}</a>
                            <i class="fas fa-chevron-right text-[9px] text-blue-300/60"></i>
                        @else
                            <span class="text-white font-medium">{{ $crumb['name'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>

        <h1 class="text-3xl md:text-4xl font-extrabold mb-3">{{ $heading }}</h1>
        <p class="text-blue-100 text-lg max-w-2xl mb-8">{{ $subheading }}</p>

        <form action="{{ route('blog.index') }}" method="GET" class="max-w-xl flex gap-2">
            <input type="search" name="q" value="{{ $search ?? '' }}" placeholder="Search articles..."
                   aria-label="Search articles"
                   class="flex-1 px-4 py-3 rounded-xl text-slate-800 text-sm outline-none focus:ring-2 focus:ring-blue-300">
            <button type="submit" class="bg-white text-blue-700 font-semibold px-6 py-3 rounded-xl text-sm hover:bg-blue-50 transition">
                Search
            </button>
        </form>

        @if($categories->count())
            <div class="flex gap-2 overflow-x-auto pb-1 mt-7 -mx-1 px-1">
                <a href="{{ route('blog.index') }}"
                   class="whitespace-nowrap px-4 py-2 rounded-full text-sm font-medium transition
                          {{ ! isset($activeCategory) ? 'bg-white text-blue-700' : 'bg-white/15 text-white hover:bg-white/25' }}">
                    All articles
                </a>
                @foreach($categories as $category)
                    <a href="{{ $category->url }}"
                       class="whitespace-nowrap px-4 py-2 rounded-full text-sm font-medium transition
                              {{ (isset($activeCategory) && $activeCategory->id === $category->id) ? 'bg-white text-blue-700' : 'bg-white/15 text-white hover:bg-white/25' }}">
                        {{ $category->name }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>

<main class="max-w-6xl mx-auto px-4 py-10">
    @if($search)
        <p class="mb-6 text-slate-600">
            {{ $posts->total() }} result{{ $posts->total() === 1 ? '' : 's' }} for
            <strong class="text-slate-900">"{{ $search }}"</strong>
            <a href="{{ route('blog.index') }}" class="text-blue-600 ml-2 text-sm hover:underline">Clear</a>
        </p>
    @endif

    {{-- Lead article --}}
    @if($featured)
        <a href="{{ $featured->url }}"
           class="group grid md:grid-cols-2 bg-white rounded-2xl border border-slate-200 overflow-hidden hover:shadow-lg transition-all duration-300 mb-10">
            <div class="relative aspect-[16/10] md:aspect-auto md:min-h-[300px] bg-slate-100 overflow-hidden">
                @if($featured->featured_image_url)
                    <img src="{{ $featured->featured_image_url }}"
                         alt="{{ $featured->featured_image_alt ?: $featured->title }}"
                         width="1200" height="750" fetchpriority="high"
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                @else
                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blue-700 to-blue-900">
                        <i class="fas fa-lightbulb text-white/60 text-4xl"></i>
                    </div>
                @endif
            </div>
            <div class="p-8 flex flex-col justify-center">
                <div class="flex items-center gap-3 mb-4">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                        <i class="fas fa-star mr-1"></i>Editor's pick
                    </span>
                    @if($featured->category)
                        <span class="text-xs font-semibold uppercase tracking-wide" style="color: {{ $featured->category->color ?: '#1d4ed8' }}">
                            {{ $featured->category->name }}
                        </span>
                    @endif
                </div>
                <h2 class="text-2xl md:text-3xl font-bold text-slate-900 leading-tight mb-3 group-hover:text-blue-700 transition">
                    {{ $featured->title }}
                </h2>
                <p class="text-slate-500 mb-6 line-clamp-3">{{ $featured->excerpt }}</p>
                <div class="flex items-center gap-3 text-sm text-slate-400">
                    <span>{{ $featured->display_author }}</span><span>&middot;</span>
                    <time datetime="{{ optional($featured->published_at)->toDateString() }}">
                        {{ optional($featured->published_at)->format('d M Y') }}
                    </time>
                    <span>&middot;</span><span>{{ $featured->reading_time }} min read</span>
                </div>
            </div>
        </a>
    @endif

    <div class="flex flex-col lg:flex-row gap-8">
        <div class="flex-1">
            @if($posts->count())
                <div class="grid sm:grid-cols-2 gap-6">
                    @foreach($posts as $post)
                        @include('blog.partials.card', ['post' => $post])
                    @endforeach
                </div>
                <div class="mt-10">{{ $posts->links() }}</div>
            @else
                <div class="bg-white rounded-2xl border border-slate-200 p-14 text-center">
                    <i class="far fa-newspaper text-5xl text-slate-300 mb-4 block"></i>
                    <h2 class="font-bold text-xl text-slate-900 mb-2">Nothing here yet</h2>
                    <p class="text-slate-500 mb-6">
                        @if($search)
                            No articles match "{{ $search }}".
                        @else
                            New articles are on the way. Check back shortly.
                        @endif
                    </p>
                    <a href="{{ route('careers.index') }}" class="inline-block bg-blue-600 text-white rounded-xl px-6 py-3 text-sm font-semibold hover:bg-blue-700 transition">
                        Browse open roles
                    </a>
                </div>
            @endif
        </div>

        <div class="w-full lg:w-80 shrink-0">
            <div class="lg:sticky lg:top-24">
                @include('blog.partials.sidebar')
            </div>
        </div>
    </div>
</main>
@endsection
