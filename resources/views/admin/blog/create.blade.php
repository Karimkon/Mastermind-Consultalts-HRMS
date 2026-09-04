@extends("layouts.app")

@section('title', 'New Blog Post')

@section("breadcrumb")<a href="{{ route('admin.blog.index') }}" class="text-slate-500 hover:text-blue-600 text-sm">Blog</a><span class="text-slate-300 mx-1">/</span><span class="text-slate-600 text-sm font-medium">New article</span>@endsection

@section('content')
<x-page-header title="New article" subtitle="Write an article and publish it to mastermind.autos/blog" />


<div class="mb-5">
    <a href="{{ route('admin.blog.index') }}" class="text-sm text-gray-500 hover:text-blue-600">
        <i class="fas fa-arrow-left mr-1.5"></i>Back to all posts
    </a>
</div>

@include('admin.blog.form')
@endsection
