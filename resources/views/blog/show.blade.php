@extends('blog.layout')

@php
    $toc = $post->table_of_contents;
    $shareUrl = urlencode($post->url);
    $shareText = urlencode($post->title);
@endphp

@section('title', $post->meta_title_value)
@section('meta_description', $post->meta_description_value)
@section('meta_keywords', $post->meta_keywords ?: $post->tags->pluck('name')->implode(', '))
@section('meta_robots', $post->noindex ? 'noindex, follow' : 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1')
@section('canonical_url', $post->canonical)
@section('og_type', 'article')
@section('og_url', $post->url)
@section('og_title', $post->title)
@section('og_description', $post->meta_description_value)
@section('og_image', $post->og_image_url ?: asset('images/logo.png'))

@push('structured_data')
    {!! \App\Services\SeoService::script(\App\Services\SeoService::article($post)) !!}
    {!! \App\Services\SeoService::script(\App\Services\SeoService::breadcrumbs($crumbs)) !!}
    {!! \App\Services\SeoService::script(\App\Services\SeoService::faq($post->faqs)) !!}
@endpush

@push('styles')
<meta property="article:published_time" content="{{ optional($post->published_at)->toAtomString() }}">
<meta property="article:modified_time" content="{{ optional($post->updated_at)->toAtomString() }}">
@if($post->category)<meta property="article:section" content="{{ $post->category->name }}">@endif
@foreach($post->tags as $tag)<meta property="article:tag" content="{{ $tag->name }}">
@endforeach
<style>
    .article-hero { aspect-ratio: 16/9; max-height: 440px; object-fit: cover; object-position: center; background: #f1f5f9; }
    .article-body { color: #334155; font-size: 1.0625rem; line-height: 1.85; }
    .article-body > * + * { margin-top: 1.3rem; }
    .article-body h2 { font-size: 1.6rem; font-weight: 700; color: #0f172a; margin-top: 2.6rem; margin-bottom: .9rem; scroll-margin-top: 90px; }
    .article-body h3 { font-size: 1.25rem; font-weight: 600; color: #1e293b; margin-top: 1.9rem; margin-bottom: .7rem; scroll-margin-top: 90px; }
    .article-body h4 { font-size: 1.08rem; font-weight: 600; color: #1e293b; margin-top: 1.4rem; }
    .article-body a { color: #1d4ed8; text-decoration: underline; text-underline-offset: 3px; font-weight: 500; }
    .article-body a:hover { color: #1e3a8a; }
    .article-body ul, .article-body ol { padding-left: 1.5rem; }
    .article-body ul { list-style: disc; }
    .article-body ol { list-style: decimal; }
    .article-body li { margin-bottom: .5rem; }
    .article-body li::marker { color: #2563eb; }
    .article-body img { border-radius: 1rem; margin: 2rem auto; max-width: 100%; max-height: 520px; width: auto; height: auto; display: block; box-shadow: 0 10px 30px rgba(15,23,42,.08); }
    .article-body figure { margin: 2rem 0; }
    .article-body figcaption { text-align: center; font-size: .85rem; color: #94a3b8; margin-top: .6rem; }
    .article-body blockquote { border-left: 4px solid #2563eb; background: #eff6ff; padding: 1.1rem 1.4rem; border-radius: 0 .9rem .9rem 0; color: #1e3a8a; font-style: italic; }
    .article-body blockquote p { margin: 0; }
    .article-body table { width: 100%; border-collapse: collapse; font-size: .95rem; display: block; overflow-x: auto; }
    .article-body th, .article-body td { border: 1px solid #e2e8f0; padding: .7rem .9rem; text-align: left; }
    .article-body th { background: #f8fafc; font-weight: 600; color: #0f172a; }
    .article-body pre { background: #0f172a; color: #e2e8f0; padding: 1.1rem; border-radius: .9rem; overflow-x: auto; font-size: .9rem; }
    .article-body code { background: #f1f5f9; color: #1d4ed8; padding: .15rem .4rem; border-radius: .35rem; font-size: .9em; }
    .article-body pre code { background: transparent; color: inherit; padding: 0; }
    .article-body hr { border: 0; border-top: 1px solid #e2e8f0; margin: 2.4rem 0; }
    .article-body iframe { width: 100%; aspect-ratio: 16/9; border-radius: 1rem; border: 0; }
    .reading-bar { position: fixed; top: 0; left: 0; height: 3px; background: linear-gradient(90deg,#2563eb,#1e3a8a); z-index: 60; width: 0; transition: width .1s linear; }
</style>
@endpush

@section('content')
<div class="reading-bar" id="readingBar" aria-hidden="true"></div>

{{-- Article header --}}
<header class="bg-white border-b border-slate-200">
    <div class="max-w-4xl mx-auto px-4 pt-8 pb-10">
        <nav aria-label="Breadcrumb" class="mb-6">
            <ol class="flex flex-wrap items-center gap-2 text-sm text-slate-400">
                @foreach($crumbs as $i => $crumb)
                    <li class="flex items-center gap-2 min-w-0">
                        @if($i < count($crumbs) - 1)
                            <a href="{{ $crumb['url'] }}" class="hover:text-blue-600 transition">{{ $crumb['name'] }}</a>
                            <i class="fas fa-chevron-right text-[9px] text-slate-300"></i>
                        @else
                            <span class="text-slate-600 font-medium truncate">{{ \Illuminate\Support\Str::limit($crumb['name'], 45) }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>

        @if($post->category)
            <a href="{{ $post->category->url }}"
               class="inline-block px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide text-white mb-4"
               style="background: {{ $post->category->color ?: '#1d4ed8' }}">
                {{ $post->category->name }}
            </a>
        @endif

        <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 leading-tight mb-4">{{ $post->title }}</h1>

        @if($post->subtitle)
            <p class="article-lead text-lg text-slate-500 leading-relaxed mb-6">{{ $post->subtitle }}</p>
        @endif

        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-slate-500">
            <span class="flex items-center gap-2">
                @if($post->author_avatar_url)
                    <img src="{{ $post->author_avatar_url }}" alt="{{ $post->display_author }}"
                         width="32" height="32" loading="lazy" class="w-8 h-8 rounded-full object-cover">
                @else
                    <span class="w-8 h-8 rounded-full bg-blue-50 text-blue-700 flex items-center justify-center text-xs font-bold">
                        {{ strtoupper(substr($post->display_author, 0, 1)) }}
                    </span>
                @endif
                <span class="font-medium text-slate-700">{{ $post->display_author }}</span>
            </span>
            <span class="flex items-center gap-1.5">
                <i class="far fa-calendar"></i>
                <time datetime="{{ optional($post->published_at)->toDateString() }}">
                    {{ optional($post->published_at)->format('d F Y') }}
                </time>
            </span>
            <span class="flex items-center gap-1.5"><i class="far fa-clock"></i>{{ $post->reading_time }} min read</span>
            <span class="flex items-center gap-1.5"><i class="far fa-eye"></i>{{ number_format($post->view_count) }} views</span>
            @if($post->status !== 'published')
                <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs font-semibold">
                    Preview &middot; {{ ucfirst($post->status) }}
                </span>
            @endif
        </div>
    </div>
</header>

<main class="max-w-6xl mx-auto px-4 py-10">
    <div class="flex flex-col lg:flex-row gap-10">
        <article class="flex-1 min-w-0">
            @if($post->featured_image_url)
                <figure class="mb-8">
                    <img src="{{ $post->featured_image_url }}"
                         alt="{{ $post->featured_image_alt ?: $post->title }}"
                         width="1200" height="675" fetchpriority="high"
                         class="article-hero w-full rounded-2xl">
                    @if($post->featured_image_caption)
                        <figcaption class="text-center text-sm text-slate-400 mt-3">{{ $post->featured_image_caption }}</figcaption>
                    @endif
                </figure>
            @endif

            @if(count($toc) > 2)
                <nav class="bg-white rounded-2xl border border-slate-200 p-6 mb-8" aria-label="Table of contents">
                    <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
                        <i class="fas fa-list-ul text-blue-600 text-sm"></i>What's in this article
                    </h2>
                    <ol class="space-y-2 text-sm">
                        @foreach($toc as $item)
                            <li class="{{ $item['level'] === 3 ? 'pl-5' : '' }}">
                                <a href="#{{ $item['id'] }}" class="text-slate-600 hover:text-blue-700 transition flex gap-2">
                                    <span class="text-blue-300">&bull;</span>{{ $item['text'] }}
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif

            <div class="article-body bg-white rounded-2xl border border-slate-200 p-6 md:p-9">
                {!! $post->content_with_anchors !!}
            </div>

            @if($post->tags->count())
                <div class="flex flex-wrap items-center gap-2 mt-8">
                    <span class="text-sm font-semibold text-slate-500 mr-1">Tagged:</span>
                    @foreach($post->tags as $tag)
                        <a href="{{ $tag->url }}"
                           class="px-3 py-1.5 rounded-full text-xs font-medium bg-white border border-slate-200 text-slate-600 hover:bg-blue-600 hover:text-white hover:border-blue-600 transition">
                            #{{ $tag->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Share --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-6 mt-8">
                <h2 class="font-semibold text-slate-800 mb-4">Share this article</h2>
                <div class="flex flex-wrap gap-2">
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $shareUrl }}" target="_blank" rel="noopener nofollow"
                       class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#0A66C2] text-white text-sm font-semibold hover:opacity-90 transition">
                        <i class="fab fa-linkedin-in"></i>LinkedIn
                    </a>
                    <a href="https://api.whatsapp.com/send?text={{ $shareText }}%20{{ $shareUrl }}" target="_blank" rel="noopener nofollow"
                       class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#25D366] text-white text-sm font-semibold hover:opacity-90 transition">
                        <i class="fab fa-whatsapp"></i>WhatsApp
                    </a>
                    <a href="https://twitter.com/intent/tweet?url={{ $shareUrl }}&text={{ $shareText }}" target="_blank" rel="noopener nofollow"
                       class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-semibold hover:opacity-90 transition">
                        <i class="fab fa-x-twitter"></i>Post
                    </a>
                    <button type="button" onclick="copyArticleLink(this)"
                            class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-100 text-slate-700 text-sm font-semibold hover:bg-slate-200 transition">
                        <i class="fas fa-link"></i><span>Copy link</span>
                    </button>
                </div>
            </div>

            {{-- FAQ --}}
            @if(!empty($post->faqs))
                <section class="bg-white rounded-2xl border border-slate-200 p-6 md:p-8 mt-8">
                    <h2 class="font-bold text-xl text-slate-900 mb-5">Frequently asked questions</h2>
                    <div class="divide-y divide-slate-100">
                        @foreach($post->faqs as $faq)
                            @continue(empty($faq['question']) || empty($faq['answer']))
                            <details class="group py-4">
                                <summary class="flex items-center justify-between cursor-pointer list-none font-semibold text-slate-800 hover:text-blue-700 transition">
                                    <span>{{ $faq['question'] }}</span>
                                    <i class="fas fa-chevron-down text-sm text-slate-400 group-open:rotate-180 transition-transform"></i>
                                </summary>
                                <p class="mt-3 text-slate-600 leading-relaxed">{{ $faq['answer'] }}</p>
                            </details>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Author --}}
            <section class="bg-white rounded-2xl border border-slate-200 p-6 mt-8 flex gap-4 items-start">
                @if($post->author_avatar_url)
                    <img src="{{ $post->author_avatar_url }}" alt="{{ $post->display_author }}"
                         width="56" height="56" loading="lazy" class="w-14 h-14 rounded-2xl object-cover shrink-0">
                @else
                    <div class="w-14 h-14 rounded-2xl bg-blue-50 text-blue-700 flex items-center justify-center font-bold text-xl shrink-0">
                        {{ strtoupper(substr($post->display_author, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 font-semibold mb-1">Written by</p>
                    <h2 class="font-bold text-slate-900">{{ $post->display_author }}</h2>
                    <p class="text-sm text-slate-500 mt-1">
                        {{ $post->author_title ?: 'Writing on hiring, payroll and people management for Mastermind Consult Ltd.' }}
                    </p>
                </div>
            </section>

            {{-- The role this article points at --}}
            @if($post->relatedJob)
                <section class="mt-8 rounded-2xl p-6 text-white" style="background: linear-gradient(135deg,#1d4ed8 0%,#1e3a8a 100%)">
                    <p class="text-xs uppercase tracking-wide text-blue-200 font-semibold mb-2">We're hiring</p>
                    <h2 class="font-bold text-xl mb-1">{{ $post->relatedJob->title }}</h2>
                    <p class="text-blue-100 text-sm mb-5">
                        {{ $post->relatedJob->location ?: 'Kampala' }}
                        @if($post->relatedJob->employment_type) &middot; {{ ucfirst(str_replace('-', ' ', $post->relatedJob->employment_type)) }} @endif
                    </p>
                    <a href="{{ route('careers.show', $post->relatedJob->slug ?: $post->relatedJob->id) }}"
                       class="inline-block bg-white text-blue-700 font-semibold rounded-xl px-5 py-2.5 text-sm hover:bg-blue-50 transition">
                        View role &amp; apply
                    </a>
                </section>
            @endif

            {{-- Related reading --}}
            @if($related->count())
                <section class="mt-12">
                    <h2 class="font-bold text-xl text-slate-900 mb-5">Keep reading</h2>
                    <div class="grid sm:grid-cols-3 gap-5">
                        @foreach($related as $item)
                            @include('blog.partials.card', ['post' => $item])
                        @endforeach
                    </div>
                </section>
            @endif
        </article>

        <div class="w-full lg:w-80 shrink-0">
            <div class="lg:sticky lg:top-24">
                @include('blog.partials.sidebar', ['tags' => null, 'search' => null])
            </div>
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script>
    (function () {
        const bar = document.getElementById('readingBar');
        if (!bar) return;
        const update = () => {
            const h = document.documentElement;
            const scrolled = h.scrollTop / ((h.scrollHeight - h.clientHeight) || 1);
            bar.style.width = Math.min(100, Math.max(0, scrolled * 100)) + '%';
        };
        document.addEventListener('scroll', update, { passive: true });
        update();
    })();

    function copyArticleLink(button) {
        const url = @json($post->url);
        const label = button.querySelector('span');
        const done = () => {
            label.textContent = 'Copied!';
            setTimeout(() => { label.textContent = 'Copy link'; }, 2000);
        };
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(done).catch(() => {});
        } else {
            const input = document.createElement('input');
            input.value = url;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            input.remove();
            done();
        }
    }
</script>
@endpush
