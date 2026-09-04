@extends("layouts.app")

@section('title', 'Blog Media')

@section("breadcrumb")<a href="{{ route('admin.blog.index') }}" class="text-slate-500 hover:text-blue-600 text-sm">Blog</a><span class="text-slate-300 mx-1">/</span><span class="text-slate-600 text-sm font-medium">Blog media</span>@endsection

@section('content')
<x-page-header title="Blog media" subtitle="Every image used in articles, uploaded here or pushed in by the AI writer" />


@include('admin.blog.partials.flash')

<div class="mb-5 flex items-center justify-between">
    <a href="{{ route('admin.blog.index') }}" class="text-sm text-gray-500 hover:text-blue-600">
        <i class="fas fa-arrow-left mr-1.5"></i>Back to all posts
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <form method="POST" action="{{ route('admin.blog.media.upload') }}" enctype="multipart/form-data"
          class="flex flex-col md:flex-row gap-3 md:items-end">
        @csrf
        <div class="flex-1">
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Upload an image</label>
            <input type="file" name="file" accept="image/*" required class="w-full text-sm text-gray-600">
        </div>
        <div class="flex-1">
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Alt text</label>
            <input type="text" name="alt" maxlength="255" placeholder="What is in the picture?"
                   class="w-full rounded-lg border-gray-200 text-sm">
        </div>
        <button type="submit" class="px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 whitespace-nowrap">
            <i class="fas fa-upload mr-1.5"></i>Upload
        </button>
    </form>
    <p class="text-xs text-gray-400 mt-3">JPG, PNG, WebP, GIF or AVIF up to 8 MB. Images are served from mastermind.autos so nothing hotlinks.</p>
</div>

@if($media->count())
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
        @foreach($media as $item)
            <div class="bg-white rounded-xl shadow-sm overflow-hidden group">
                <div class="aspect-square bg-gray-100 relative">
                    <img src="{{ $item->url }}" alt="{{ $item->alt }}" loading="lazy" class="w-full h-full object-cover">
                    @if(in_array($item->source, ['ai', 'api']))
                        <span class="absolute top-2 left-2 px-1.5 py-0.5 rounded bg-pink-500 text-white text-[10px] font-semibold">AI</span>
                    @endif
                </div>
                <div class="p-3">
                    <p class="text-xs text-gray-500 truncate" title="{{ $item->original_name }}">{{ $item->original_name ?: basename($item->path) }}</p>
                    <p class="text-[11px] text-gray-400 mt-0.5">
                        {{ $item->human_size }}@if($item->width) &middot; {{ $item->width }}×{{ $item->height }}@endif
                    </p>
                    <div class="flex gap-1 mt-2">
                        <button type="button" onclick="copyUrl('{{ $item->url }}', this)"
                                class="flex-1 text-[11px] py-1.5 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200">Copy URL</button>
                        <form method="POST" action="{{ route('admin.blog.media.destroy', $item->id) }}"
                              onsubmit="return confirm('Delete this image? Articles using it will show a broken image.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="w-8 h-7 rounded-lg text-red-500 hover:bg-red-50 text-[11px]">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6">{{ $media->links() }}</div>
@else
    <div class="bg-white rounded-xl shadow-sm p-16 text-center">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-gray-50 flex items-center justify-center mb-3">
            <i class="far fa-images text-gray-300 text-xl"></i>
        </div>
        <p class="font-semibold text-gray-900 mb-1">The library is empty</p>
        <p class="text-gray-500">Upload an image above, or add one from inside the post editor.</p>
    </div>
@endif
@endsection

@push('scripts')
<script>
    function copyUrl(url, btn) {
        navigator.clipboard.writeText(url).then(() => {
            const original = btn.textContent;
            btn.textContent = 'Copied!';
            setTimeout(() => { btn.textContent = original; }, 1500);
        });
    }
</script>
@endpush
