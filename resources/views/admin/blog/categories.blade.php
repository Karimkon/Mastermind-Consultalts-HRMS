@extends("layouts.app")

@section('title', 'Blog Categories')

@section("breadcrumb")<a href="{{ route('admin.blog.index') }}" class="text-slate-500 hover:text-blue-600 text-sm">Blog</a><span class="text-slate-300 mx-1">/</span><span class="text-slate-600 text-sm font-medium">Blog topics</span>@endsection

@section('content')
<x-page-header title="Blog topics" subtitle="Topics group your articles and create their own landing pages" />


@include('admin.blog.partials.flash')

<div class="mb-5">
    <a href="{{ route('admin.blog.index') }}" class="text-sm text-gray-500 hover:text-blue-600">
        <i class="fas fa-arrow-left mr-1.5"></i>Back to all posts
    </a>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    {{-- New category --}}
    <div class="bg-white rounded-xl shadow-sm p-6 h-fit">
        <h2 class="font-bold text-gray-900 mb-4">Add a category</h2>
        <form method="POST" action="{{ route('admin.blog.categories.store') }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" required maxlength="120" placeholder="Buying guides"
                       class="w-full rounded-lg border-gray-200 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                <textarea name="description" rows="2" maxlength="1000"
                          placeholder="Shown on the category page and used as its meta description fallback."
                          class="w-full rounded-lg border-gray-200 text-sm"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Colour</label>
                    <input type="color" name="color" value="#4f46e5" class="w-full h-10 rounded-lg border-gray-200">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Order</label>
                    <input type="number" name="sort_order" value="0" min="0" class="w-full rounded-lg border-gray-200 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Meta title</label>
                <input type="text" name="meta_title" maxlength="255" class="w-full rounded-lg border-gray-200 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Meta description</label>
                <textarea name="meta_description" rows="2" maxlength="320" class="w-full rounded-lg border-gray-200 text-sm"></textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Cover image</label>
                <input type="file" name="image" accept="image/*" class="w-full text-sm text-gray-600">
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-blue-600">
                <span class="text-sm text-gray-700">Active</span>
            </label>
            <button type="submit" class="w-full py-2.5 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700">
                <i class="fas fa-plus mr-1.5"></i>Add category
            </button>
        </form>
    </div>

    {{-- Existing --}}
    <div class="lg:col-span-2 space-y-4">
        @forelse($categories as $category)
            <div class="bg-white rounded-xl shadow-sm p-5">
                <form method="POST" action="{{ route('admin.blog.categories.update', $category->id) }}" enctype="multipart/form-data">
                    @csrf @method('PUT')
                    <div class="flex items-start gap-4">
                        <span class="w-10 h-10 rounded-xl flex items-center justify-center text-white flex-shrink-0"
                              style="background: {{ $category->color ?: '#4f46e5' }}">
                            <i class="fas fa-folder"></i>
                        </span>

                        <div class="flex-1 grid md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Name</label>
                                <input type="text" name="name" value="{{ $category->name }}" required maxlength="120"
                                       class="w-full rounded-lg border-gray-200 text-sm font-medium">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Slug</label>
                                <input type="text" name="slug" value="{{ $category->slug }}" maxlength="140"
                                       class="w-full rounded-lg border-gray-200 text-sm">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Description</label>
                                <textarea name="description" rows="2" maxlength="1000"
                                          class="w-full rounded-lg border-gray-200 text-sm">{{ $category->description }}</textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Meta title</label>
                                <input type="text" name="meta_title" value="{{ $category->meta_title }}" maxlength="255"
                                       class="w-full rounded-lg border-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Meta description</label>
                                <input type="text" name="meta_description" value="{{ $category->meta_description }}" maxlength="320"
                                       class="w-full rounded-lg border-gray-200 text-sm">
                            </div>
                            <div class="grid grid-cols-3 gap-3 md:col-span-2">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Colour</label>
                                    <input type="color" name="color" value="{{ $category->color ?: '#4f46e5' }}" class="w-full h-9 rounded-lg border-gray-200">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Order</label>
                                    <input type="number" name="sort_order" value="{{ $category->sort_order }}" min="0"
                                           class="w-full rounded-lg border-gray-200 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Cover</label>
                                    <input type="file" name="image" accept="image/*" class="w-full text-xs text-gray-600 pt-2">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" @checked($category->is_active)
                                       class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm text-gray-700">Active</span>
                            </label>
                            <span class="text-xs text-gray-400">{{ $category->posts_count }} post{{ $category->posts_count === 1 ? '' : 's' }}</span>
                            <a href="{{ $category->url }}" target="_blank" rel="noopener" class="text-xs text-blue-600 hover:underline">
                                View page <i class="fas fa-arrow-up-right-from-square ml-0.5"></i>
                            </a>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                                Save
                            </button>
                        </div>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.blog.categories.destroy', $category->id) }}"
                      onsubmit="return confirm('Delete this category? Posts in it will simply lose their category.')" class="mt-2 text-right">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs text-red-500 hover:underline">Delete category</button>
                </form>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-gray-50 flex items-center justify-center mb-3">
                    <i class="fas fa-folder-open text-gray-300 text-xl"></i>
                </div>
                <p class="font-semibold text-gray-900 mb-1">No categories yet</p>
                <p class="text-gray-500">Add one on the left. Categories become their own landing pages at /blog/category/&hellip;</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
