@extends("layouts.app")

@section('title', 'Edit: ' . $post->title)

@section("breadcrumb")<a href="{{ route('admin.blog.index') }}" class="text-slate-500 hover:text-blue-600 text-sm">Blog</a><span class="text-slate-300 mx-1">/</span><span class="text-slate-600 text-sm font-medium">Edit article</span>@endsection

@section('content')
<x-page-header title="Edit article" />


<div class="mb-5 flex items-center justify-between">
    <a href="{{ route('admin.blog.index') }}" class="text-sm text-gray-500 hover:text-blue-600">
        <i class="fas fa-arrow-left mr-1.5"></i>Back to all posts
    </a>
    <span class="text-xs text-gray-400">Last saved {{ $post->updated_at?->diffForHumans() }}</span>
</div>

@include('admin.blog.form')
@endsection
