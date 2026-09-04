@extends("layouts.app")

@section('title', 'Blog Posts')

@section("breadcrumb")<a href="{{ route('admin.blog.index') }}" class="text-slate-500 hover:text-blue-600 text-sm">Blog</a><span class="text-slate-300 mx-1">/</span><span class="text-slate-600 text-sm font-medium">Blog</span>@endsection

@section('content')
<x-page-header title="Blog" subtitle="Write, schedule and publish articles that bring search traffic to Mastermind" />


@include('admin.blog.partials.flash')

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
    @php
        $cards = [
            ['label' => 'Total posts', 'value' => $stats['total'], 'icon' => 'fa-newspaper', 'color' => 'indigo'],
            ['label' => 'Published', 'value' => $stats['published'], 'icon' => 'fa-circle-check', 'color' => 'green'],
            ['label' => 'Drafts', 'value' => $stats['draft'], 'icon' => 'fa-pen', 'color' => 'amber'],
            ['label' => 'Scheduled', 'value' => $stats['scheduled'], 'icon' => 'fa-clock', 'color' => 'blue'],
            ['label' => 'Total views', 'value' => number_format($stats['views']), 'icon' => 'fa-eye', 'color' => 'purple'],
            ['label' => 'From AI agent', 'value' => $stats['ai'], 'icon' => 'fa-robot', 'color' => 'pink'],
        ];
    @endphp
    @foreach($cards as $card)
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-xs text-gray-500 truncate">{{ $card['label'] }}</p>
                    <p class="text-xl font-bold text-gray-900">{{ $card['value'] }}</p>
                </div>
                <div class="w-9 h-9 rounded-lg bg-{{ $card['color'] }}-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas {{ $card['icon'] }} text-{{ $card['color'] }}-600 text-sm"></i>
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- Toolbar --}}
<div class="bg-white rounded-xl shadow-sm p-4 mb-6">
    <div class="flex flex-col lg:flex-row gap-3 lg:items-center lg:justify-between">
        <form method="GET" class="flex flex-wrap gap-2 flex-1">
            <div class="relative flex-1 min-w-[200px]">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search posts..."
                       class="w-full rounded-lg border-gray-200 pl-9 pr-3 py-2 text-sm focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-sm"></i>
            </div>
            <select name="status" class="rounded-lg border-gray-200 text-sm py-2" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach(['published' => 'Published', 'draft' => 'Draft', 'scheduled' => 'Scheduled', 'archived' => 'Archived'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="category" class="rounded-lg border-gray-200 text-sm py-2" onchange="this.form.submit()">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="source" class="rounded-lg border-gray-200 text-sm py-2" onchange="this.form.submit()">
                <option value="">Any source</option>
                <option value="manual" @selected(request('source') === 'manual')>Written here</option>
                <option value="ai" @selected(request('source') === 'ai')>AI agent</option>
            </select>
            <button class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm font-medium hover:bg-gray-200">Filter</button>
        </form>

        <div class="flex gap-2">
            <a href="{{ route('admin.blog.categories.index') }}"
               class="px-4 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 text-sm font-medium hover:bg-gray-50 whitespace-nowrap">
                <i class="fas fa-tags mr-1.5"></i>Categories
            </a>
            <a href="{{ route('admin.blog.media') }}"
               class="px-4 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 text-sm font-medium hover:bg-gray-50 whitespace-nowrap">
                <i class="fas fa-images mr-1.5"></i>Media
            </a>
            <a href="{{ route('admin.blog.tokens') }}"
               class="px-4 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 text-sm font-medium hover:bg-gray-50 whitespace-nowrap">
                <i class="fas fa-robot mr-1.5"></i>AI keys
            </a>
            <a href="{{ route('admin.blog.create') }}"
               class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 whitespace-nowrap">
                <i class="fas fa-plus mr-1.5"></i>New post
            </a>
        </div>
    </div>
</div>

{{-- Table --}}
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                <tr>
                    <th class="text-left font-semibold px-5 py-3">Post</th>
                    <th class="text-left font-semibold px-4 py-3">Status</th>
                    <th class="text-left font-semibold px-4 py-3">Category</th>
                    <th class="text-left font-semibold px-4 py-3">SEO</th>
                    <th class="text-left font-semibold px-4 py-3">Views</th>
                    <th class="text-left font-semibold px-4 py-3">Published</th>
                    <th class="text-right font-semibold px-5 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($posts as $post)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-lg bg-gray-100 overflow-hidden flex-shrink-0">
                                    @if($post->featured_image_url)
                                        <img src="{{ $post->featured_image_url }}" alt="" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center">
                                            <i class="far fa-image text-gray-300"></i>
                                        </div>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <a href="{{ route('admin.blog.edit', $post->id) }}" class="font-semibold text-gray-900 hover:text-blue-600 line-clamp-1">
                                        {{ $post->title }}
                                    </a>
                                    <p class="text-xs text-gray-400 mt-0.5 flex items-center gap-2">
                                        <span>/{{ $post->slug }}</span>
                                        @if(in_array($post->source, ['ai', 'api']))
                                            <span class="px-1.5 py-0.5 rounded bg-pink-50 text-pink-600 font-medium">
                                                <i class="fas fa-robot"></i> AI
                                            </span>
                                        @endif
                                        @if($post->is_featured)
                                            <span class="px-1.5 py-0.5 rounded bg-amber-50 text-amber-600 font-medium">Featured</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $badge = [
                                    'published' => 'bg-green-100 text-green-700',
                                    'draft' => 'bg-gray-100 text-gray-600',
                                    'scheduled' => 'bg-blue-100 text-blue-700',
                                    'archived' => 'bg-red-50 text-red-600',
                                ][$post->status] ?? 'bg-gray-100 text-gray-600';
                            @endphp
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $badge }}">{{ ucfirst($post->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $post->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @php
                                $score = (int) $post->seo_score;
                                $tone = $score >= 75 ? 'text-green-600' : ($score >= 50 ? 'text-amber-600' : 'text-red-500');
                            @endphp
                            <span class="font-semibold {{ $tone }}">{{ $score }}</span><span class="text-gray-300 text-xs">/100</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ number_format($post->view_count) }}</td>
                        <td class="px-4 py-3 text-gray-500 text-xs">
                            {{ $post->published_at?->format('M j, Y H:i') ?? '—' }}
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ $post->url }}" target="_blank" rel="noopener" title="View"
                                   class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-500">
                                    <i class="fas fa-arrow-up-right-from-square text-xs"></i>
                                </a>
                                <a href="{{ route('admin.blog.edit', $post->id) }}" title="Edit"
                                   class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-blue-600">
                                    <i class="fas fa-pen text-xs"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.blog.toggle-status', $post->id) }}">
                                    @csrf
                                    <button type="submit" title="{{ $post->status === 'published' ? 'Unpublish' : 'Publish' }}"
                                            class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center {{ $post->status === 'published' ? 'text-amber-600' : 'text-green-600' }}">
                                        <i class="fas {{ $post->status === 'published' ? 'fa-eye-slash' : 'fa-paper-plane' }} text-xs"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.blog.toggle-featured', $post->id) }}">
                                    @csrf
                                    <button type="submit" title="Feature"
                                            class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center {{ $post->is_featured ? 'text-amber-500' : 'text-gray-400' }}">
                                        <i class="{{ $post->is_featured ? 'fas' : 'far' }} fa-star text-xs"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.blog.destroy', $post->id) }}"
                                      onsubmit="return confirm('Move this post to trash?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Delete"
                                            class="w-8 h-8 rounded-lg hover:bg-red-50 flex items-center justify-center text-red-500">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-16 text-center">
                            <div class="w-14 h-14 mx-auto rounded-2xl bg-gray-50 flex items-center justify-center mb-3">
                                <i class="far fa-newspaper text-gray-300 text-xl"></i>
                            </div>
                            <p class="font-semibold text-gray-900 mb-1">No posts yet</p>
                            <p class="text-gray-500 mb-4">Write your first article, or let the AI agent publish one through the API.</p>
                            <a href="{{ route('admin.blog.create') }}" class="inline-block px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                                Write a post
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($posts->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $posts->links() }}
        </div>
    @endif
</div>
@endsection
