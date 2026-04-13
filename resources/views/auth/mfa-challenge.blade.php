<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Two-Factor Authentication — Mastermind HRMS</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center">
<div class="w-full max-w-sm px-4">
    <div class="text-center mb-8">
        <div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-shield-alt text-white text-2xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-white">Two-Factor Authentication</h1>
        <p class="text-slate-400 text-sm mt-1">Enter the 6-digit code from your authenticator app</p>
    </div>
    <div class="bg-white rounded-2xl shadow-2xl p-8">
        @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
            <p class="text-red-600 text-sm">{{ $errors->first() }}</p>
        </div>
        @endif
        <form method="POST" action="{{ route('mfa.verify') }}">@csrf
            <div class="mb-6">
                <input type="text" name="code" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-center text-3xl tracking-widest font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500" maxlength="6" pattern="\d{6}" placeholder="000000" autofocus required>
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl transition-colors">
                <i class="fas fa-unlock mr-2"></i> Verify
            </button>
        </form>
        <div class="text-center mt-4">
            <a href="{{ route('logout') }}" onclick="event.preventDefault();document.getElementById('logout-form').submit();" class="text-sm text-slate-400 hover:text-slate-600">Cancel and log out</a>
            <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
        </div>
    </div>
</div>
</body>
</html>
