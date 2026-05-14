<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield("title","Login") — Mastermind HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:"Inter",sans-serif;}</style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md px-4">
        <div class="text-center mb-8">
            <div class="inline-block mb-4">
                <img src="/images/logo.png?v=2" alt="Mastermind Consultants"
                     style="height:60px;width:auto;background:#fff;padding:8px 16px;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.3);">
            </div>
            <h1 class="text-2xl font-bold text-white">Mastermind HRMS</h1>
            <p class="text-slate-400 text-sm mt-1">Human Resource Management System</p>
        </div>
        @yield("content")
    </div>
</body>
</html>
