@extends("layouts.auth")
@section("title", "Set New Password")
@section("content")
<div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 shadow-2xl border border-white/10">
    <h2 class="text-xl font-bold text-white mb-1">Set new password</h2>
    <p class="text-slate-400 text-sm mb-6">Choose a strong password for your account.</p>

    @if ($errors->any())
        <div class="mb-4 bg-red-500/20 border border-red-500/30 rounded-lg px-4 py-3">
            @foreach ($errors->all() as $error)
                <p class="text-red-300 text-sm">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">Email Address</label>
            <input type="email" name="email" value="{{ $email ?? old('email') }}" required
                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent text-sm"
                placeholder="you@mastermind.co.za">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">New Password</label>
            <input type="password" name="password" required
                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent text-sm"
                placeholder="Minimum 8 characters">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">Confirm New Password</label>
            <input type="password" name="password_confirmation" required
                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent text-sm"
                placeholder="Repeat password">
        </div>
        <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl transition-colors text-sm shadow-lg shadow-blue-900/50">
            Reset Password
        </button>
    </form>

    <div class="mt-4 text-center">
        <a href="{{ route('login') }}" class="text-sm text-blue-400 hover:text-blue-300 transition-colors">
            &larr; Back to Sign In
        </a>
    </div>
</div>
@endsection
