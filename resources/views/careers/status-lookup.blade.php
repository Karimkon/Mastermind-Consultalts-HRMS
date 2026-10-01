@extends('careers.layout')
@section('title', 'Track your application — Mastermind Careers')
@section('content')

<div class="max-w-md mx-auto">
    <div class="bg-white rounded-2xl border border-slate-200 p-7">
        <div class="text-center mb-6">
            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mx-auto mb-3 text-xl">
                <i class="fas fa-location-crosshairs"></i>
            </div>
            <h1 class="text-lg font-bold text-slate-800">Track your application</h1>
            <p class="text-sm text-slate-500 mt-1">Enter the reference you were given when you applied.</p>
        </div>

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-4 text-sm">
            {{ $errors->first() }}
        </div>
        @endif

        <form method="POST" action="{{ route('careers.status.find') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Reference</label>
                <input type="text" name="tracking_code" value="{{ old('tracking_code') }}" required
                       maxlength="16" autocomplete="off" spellcheck="false"
                       class="w-full border border-slate-200 rounded-xl px-4 py-3 text-center text-lg font-bold tracking-[0.2em] uppercase outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                       placeholder="XXXXXXXXXXXX">
                <p class="text-xs text-slate-400 mt-1.5">It was on the page after you applied and in your confirmation email.</p>
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl text-sm transition">
                Check my application
            </button>
        </form>

        <p class="text-xs text-slate-400 mt-5 text-center">
            Lost your reference? Reply to your confirmation email and we will look it up.
        </p>
    </div>
</div>

@endsection
