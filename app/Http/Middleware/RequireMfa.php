<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireMfa
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        if ($user && $user->mfa_enabled && !session('mfa_verified')) {
            auth()->logout();
            session(['mfa_pending_user' => $user->id]);
            return redirect()->route('mfa.challenge');
        }
        return $next($request);
    }
}
