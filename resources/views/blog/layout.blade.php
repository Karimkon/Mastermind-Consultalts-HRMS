<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>@yield('title', 'Insights') — Mastermind Consult Ltd</title>

<meta name="description" content="@yield('meta_description', 'Practical HR guidance from Mastermind Consult Ltd: hiring, payroll, labour law and people management in Uganda and East Africa.')">
<meta name="keywords" content="@yield('meta_keywords', 'HR Uganda, recruitment Uganda, payroll Uganda, labour law, staff outsourcing, Mastermind Consult')">
<meta name="author" content="Mastermind Consult Ltd">
<meta name="robots" content="@yield('meta_robots', 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1')">
<meta name="googlebot" content="@yield('meta_robots', 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1')">
<meta name="theme-color" content="#1d4ed8">
<meta name="geo.region" content="UG">
<meta name="geo.placename" content="Kampala">

<link rel="canonical" href="@yield('canonical_url', url()->current())">
<link rel="alternate" type="application/rss+xml" title="Mastermind Insights" href="{{ route('blog.feed') }}">
<link rel="sitemap" type="application/xml" title="Sitemap" href="{{ url('/sitemap.xml') }}">

<meta property="og:type" content="@yield('og_type', 'website')">
<meta property="og:url" content="@yield('og_url', url()->current())">
<meta property="og:title" content="@yield('og_title', 'Insights from Mastermind Consult')">
<meta property="og:description" content="@yield('og_description', 'Practical HR guidance for organisations across Uganda and East Africa.')">
<meta property="og:image" content="@yield('og_image', asset('images/logo.png'))">
<meta property="og:site_name" content="Mastermind Consult Ltd">
<meta property="og:locale" content="en_UG">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="@yield('og_title', 'Insights from Mastermind Consult')">
<meta name="twitter:description" content="@yield('og_description', 'Practical HR guidance for organisations across Uganda and East Africa.')">
<meta name="twitter:image" content="@yield('og_image', asset('images/logo.png'))">

<link rel="icon" href="{{ asset('images/logo.png') }}" type="image/png">

{!! \App\Services\SeoService::script(\App\Services\SeoService::siteGraph()) !!}
@stack('structured_data')

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
    body { font-family: "Inter", sans-serif; }
    .line-clamp-2 { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .line-clamp-3 { display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; }
</style>
@stack('styles')
</head>
<body class="bg-slate-50">

{{-- Header: same shell as the careers portal --}}
<header class="bg-white border-b border-slate-200 sticky top-0 z-50">
    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
        <a href="{{ url('/') }}" class="flex items-center gap-3">
            <div class="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center">
                <i class="fas fa-lightbulb text-white text-sm"></i>
            </div>
            <div>
                <p class="font-bold text-slate-800 text-sm">Mastermind Consult Ltd</p>
                <p class="text-xs text-slate-500">Insights</p>
            </div>
        </a>
        <nav class="flex items-center gap-5 text-sm">
            <a href="{{ url('/') }}" class="text-slate-600 hover:text-blue-600 hidden sm:inline">Home</a>
            <a href="{{ route('careers.index') }}" class="text-slate-600 hover:text-blue-600">Careers</a>
            <a href="{{ route('blog.index') }}" class="text-blue-600 font-medium">Insights</a>
            <a href="{{ route('login') }}" class="text-blue-600 font-medium hover:underline">Staff Login →</a>
        </nav>
    </div>
</header>

@yield('content')

<footer class="bg-white border-t border-slate-200 mt-16">
    <div class="max-w-6xl mx-auto px-4 py-10">
        <div class="grid sm:grid-cols-3 gap-8 mb-8">
            <div>
                <p class="font-bold text-slate-800 mb-2">Mastermind Consult Ltd</p>
                <p class="text-sm text-slate-500 leading-relaxed">
                    Your strategic HR solutions partner — executive recruitment, staffing, payroll
                    and HR systems across Uganda and East Africa.
                </p>
            </div>
            <div>
                <p class="font-semibold text-slate-700 text-sm mb-3">Explore</p>
                <ul class="space-y-2 text-sm text-slate-500">
                    <li><a href="{{ url('/') }}" class="hover:text-blue-600">Home</a></li>
                    <li><a href="{{ route('careers.index') }}" class="hover:text-blue-600">Open roles</a></li>
                    <li><a href="{{ route('blog.index') }}" class="hover:text-blue-600">Insights</a></li>
                    <li><a href="{{ route('blog.feed') }}" class="hover:text-blue-600">RSS feed</a></li>
                </ul>
            </div>
            <div>
                <p class="font-semibold text-slate-700 text-sm mb-3">Working with us</p>
                <ul class="space-y-2 text-sm text-slate-500">
                    <li><a href="{{ url('/#services') }}" class="hover:text-blue-600">Services</a></li>
                    <li><a href="{{ url('/#about') }}" class="hover:text-blue-600">About</a></li>
                    <li><a href="{{ url('/#contact') }}" class="hover:text-blue-600">Contact</a></li>
                </ul>
            </div>
        </div>
        <p class="text-center text-sm text-slate-400 border-t border-slate-100 pt-6">
            &copy; {{ date('Y') }} Mastermind Consult Ltd. All rights reserved.
        </p>
    </div>
</footer>

@stack('scripts')
</body>
</html>
