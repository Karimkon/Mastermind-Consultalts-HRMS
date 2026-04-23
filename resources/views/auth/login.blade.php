@extends("layouts.auth")
@section("title", "Sign In")
@section("content")
<div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 shadow-2xl border border-white/10">
    <h2 class="text-xl font-bold text-white mb-1">Welcome back</h2>
    <p class="text-slate-400 text-sm mb-6">Sign in to your HRMS account</p>

    @if (session('status'))
        <div class="mb-4 bg-green-500/20 border border-green-500/30 rounded-lg px-4 py-3">
            <p class="text-green-300 text-sm">{{ session('status') }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 bg-red-500/20 border border-red-500/30 rounded-lg px-4 py-3">
            @foreach ($errors->all() as $error)
                <p class="text-red-300 text-sm">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">Email Address</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent text-sm"
                placeholder="you@mastermind.co.za">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">Password</label>
            <input type="password" name="password" required
                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent text-sm"
                placeholder="••••••••">
        </div>
        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-500 bg-white/10 text-blue-500 focus:ring-blue-400">
                <span class="text-sm text-slate-300">Remember me</span>
            </label>
            <a href="{{ route('password.request') }}" class="text-sm text-blue-400 hover:text-blue-300 transition-colors">Forgot password?</a>
        </div>
        <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl transition-colors text-sm shadow-lg shadow-blue-900/50">
            Sign In
        </button>
    </form>

</div>
@endsection
