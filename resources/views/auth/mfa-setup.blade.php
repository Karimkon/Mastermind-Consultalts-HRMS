@extends('layouts.app')
@section('title','Two-Factor Authentication Setup')
@section('content')
<div class="max-w-lg mx-auto">
<div class="card p-8">
    <div class="text-center mb-6">
        <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-shield-alt text-blue-600 text-2xl"></i>
        </div>
        <h1 class="text-xl font-bold text-slate-800">Set Up Two-Factor Authentication</h1>
        <p class="text-sm text-slate-500 mt-1">Scan the QR code with your authenticator app (Google Authenticator, Authy, etc.)</p>
    </div>

    {{-- QR Code --}}
    <div class="bg-slate-50 rounded-xl p-6 flex flex-col items-center mb-6">
        <div class="bg-white p-3 rounded-lg shadow-sm mb-4">
            {!! \BaconQrCode\Renderer\ImageRenderer::class ? '' : '' !!}
            @php
                $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                    new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
                    new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
                );
                $writer = new \BaconQrCode\Writer($renderer);
                $qrSvg  = $writer->writeString($qrUrl);
            @endphp
            {!! $qrSvg !!}
        </div>
        <p class="text-xs text-slate-500 text-center mb-2">Can't scan? Enter this key manually:</p>
        <code class="bg-slate-200 text-slate-800 px-3 py-1.5 rounded font-mono text-sm tracking-widest">{{ $secret }}</code>
    </div>

    {{-- Verify Form --}}
    <form method="POST" action="{{ route('mfa.enable') }}" class="space-y-4">@csrf
        <div>
            <label class="form-label">Enter the 6-digit code from your app *</label>
            <input type="text" name="code" class="form-input text-center text-2xl tracking-widest font-mono" maxlength="6" pattern="\d{6}" placeholder="000000" autofocus required>
            @error('code')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn-primary w-full justify-center"><i class="fas fa-check"></i> Verify & Enable 2FA</button>
    </form>

    @if(auth()->user()->mfa_enabled)
    <div class="mt-6 pt-6 border-t border-slate-100">
        <p class="text-sm text-slate-500 mb-3">Want to disable two-factor authentication?</p>
        <form method="POST" action="{{ route('mfa.disable') }}" class="flex gap-3">@csrf
            <input type="password" name="password" class="form-input flex-1" placeholder="Confirm your password" required>
            <button type="submit" class="btn-danger">Disable 2FA</button>
        </form>
    </div>
    @endif
</div>
</div>
@endsection
