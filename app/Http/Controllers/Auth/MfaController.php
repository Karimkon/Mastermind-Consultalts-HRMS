<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class MfaController extends Controller
{
    private function g2fa(): Google2FA { return new Google2FA(); }

    /** Show QR code setup page */
    public function setup()
    {
        $user   = auth()->user();
        $g2fa   = $this->g2fa();
        $secret = $user->mfa_secret ?? $g2fa->generateSecretKey();

        if (!$user->mfa_secret) {
            $user->update(['mfa_secret' => $secret]);
        }

        $qrUrl = $g2fa->getQRCodeUrl(
            config('app.name', 'Mastermind HRMS'),
            $user->email,
            $secret
        );

        return view('auth.mfa-setup', compact('secret', 'qrUrl'));
    }

    /** Verify TOTP and enable MFA */
    public function enable(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);
        $user = auth()->user();
        $g2fa = $this->g2fa();

        if (!$g2fa->verifyKey($user->mfa_secret, $request->code)) {
            return back()->withErrors(['code' => 'Invalid verification code. Please try again.']);
        }

        $user->update(['mfa_enabled' => true, 'mfa_confirmed_at' => now()]);
        session(['mfa_verified' => true]);

        return redirect()->route('dashboard')->with('success', 'Two-factor authentication has been enabled.');
    }

    /** Show TOTP challenge (post-login gate) */
    public function challenge()
    {
        if (!session('mfa_pending_user')) {
            return redirect()->route('login');
        }
        return view('auth.mfa-challenge');
    }

    /** Verify TOTP challenge and complete login */
    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);

        $userId = session('mfa_pending_user');
        if (!$userId) return redirect()->route('login');

        $user = \App\Models\User::findOrFail($userId);
        $g2fa = $this->g2fa();

        if (!$g2fa->verifyKey($user->mfa_secret, $request->code)) {
            return back()->withErrors(['code' => 'Invalid code. Please try again.']);
        }

        auth()->login($user);
        session()->forget('mfa_pending_user');
        session(['mfa_verified' => true]);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /** Disable MFA (requires password confirmation) */
    public function disable(Request $request)
    {
        $request->validate(['password' => 'required']);
        $user = auth()->user();

        if (!Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $user->update(['mfa_enabled' => false, 'mfa_secret' => null, 'mfa_confirmed_at' => null]);
        session()->forget('mfa_verified');

        return back()->with('success', 'Two-factor authentication has been disabled.');
    }
}
