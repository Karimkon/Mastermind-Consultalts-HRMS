<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) return redirect()->route('dashboard');
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        $key = 'login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors(['email' => "Too many login attempts. Please try again in {$seconds} seconds."])->withInput($request->only('email'));
        }

        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            RateLimiter::clear($key);
            $user = Auth::user();

            // A retired account must actually be unable to sign in. The API has
            // always checked this; the website never did, so `status` was a label
            // on a row rather than a gate, and deactivating somebody changed
            // nothing about what they could still do here.
            if (! $user->isActive()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'This account is no longer active. Speak to your administrator.',
                ])->withInput($request->only('email'));
            }

            // MFA gate
            if ($user->mfa_enabled) {
                Auth::logout();
                $request->session()->put('mfa_pending_user', $user->id);
                return redirect()->route('mfa.challenge');
            }

            $request->session()->regenerate();
            session(['mfa_verified' => true]);

            if ($user->hasRole('client')) {
                return redirect()->route('client.dashboard');
            }

            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($key, 60);
        return back()->withErrors(['email' => 'These credentials do not match our records.'])->withInput($request->only('email'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
