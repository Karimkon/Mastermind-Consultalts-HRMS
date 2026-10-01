<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Mastermind Careers')</title>
    <meta name="robots" content="noindex">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <style>body{font-family:"Inter",sans-serif;}</style>
</head>
<body class="bg-slate-50">

<header class="bg-white border-b border-slate-200 sticky top-0 z-50">
    <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('home') }}">
                <img src="{{ asset('images/logo.png') }}" alt="Mastermind Consult Ltd"
                     class="h-8 w-auto object-contain">
            </a>
            <a href="{{ route('careers.index') }}"
               class="flex items-center gap-2 text-slate-600 hover:text-slate-800 text-sm font-medium border-l border-slate-200 pl-4">
                <i class="fas fa-arrow-left"></i> Jobs
            </a>
        </div>
        <div class="flex items-center gap-5">
            <a href="{{ route('careers.status') }}" class="text-sm text-slate-600 hover:text-blue-600">Track Application</a>
            <a href="{{ route('login') }}" class="text-sm text-blue-600 font-medium hover:underline">Employee Login →</a>
        </div>
    </div>
</header>

<main class="max-w-4xl mx-auto px-4 py-10">
    @yield('content')
</main>

<footer class="bg-white border-t border-slate-200 py-6 text-center text-sm text-slate-400 mt-10">
    &copy; {{ date('Y') }} Mastermind Consult Ltd. All rights reserved.
</footer>
</body>
</html>
