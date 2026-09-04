<article class="group bg-white rounded-2xl border border-slate-200 overflow-hidden hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 flex flex-col h-full">
    <a href="{{ $post->url }}" class="block relative aspect-[16/10] bg-slate-100 overflow-hidden" aria-label="{{ $post->title }}">
        @if($post->featured_image_url)
            <img src="{{ $post->featured_image_url }}"
                 alt="{{ $post->featured_image_alt ?: $post->title }}"
                 width="800" height="500" loading="lazy" decoding="async"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
        @else
            <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blue-700 to-blue-900">
                <i class="fas fa-lightbulb text-white/60 text-3xl"></i>
            </div>
        @endif

        @if($post->category)
            <span class="absolute top-3 left-3 px-3 py-1 rounded-full text-[11px] font-semibold tracking-wide uppercase text-white"
                  style="background: {{ $post->category->color ?: '#1d4ed8' }}">
                {{ $post->category->name }}
            </span>
        @endif
    </a>

    <div class="p-5 flex flex-col flex-1">
        <h3 class="font-bold text-lg text-slate-900 leading-snug mb-2 line-clamp-2">
            <a href="{{ $post->url }}" class="hover:text-blue-700 transition-colors">{{ $post->title }}</a>
        </h3>

        <p class="text-sm text-slate-500 line-clamp-2 mb-4 flex-1">{{ $post->excerpt }}</p>

        <div class="flex items-center justify-between text-xs text-slate-400 pt-3 border-t border-slate-100">
            <span class="flex items-center gap-1.5">
                <i class="far fa-calendar"></i>
                <time datetime="{{ optional($post->published_at)->toDateString() }}">
                    {{ optional($post->published_at)->format('d M Y') }}
                </time>
            </span>
            <span class="flex items-center gap-1.5"><i class="far fa-clock"></i>{{ $post->reading_time }} min read</span>
        </div>
    </div>
</article>
