<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;

class AuthApiController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $key = 'api-login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json(['message' => 'Too many login attempts.'], 429);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            RateLimiter::hit($key, 60);
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        RateLimiter::clear($key);
        $user = Auth::user();

        if (!$user->isActive()) {
            Auth::logout();
            return response()->json(['message' => 'Account is inactive.'], 403);
        }

        // MFA gate
        if ($user->mfa_enabled) {
            return response()->json([
                'mfa_required' => true,
                'mfa_token'    => encrypt($user->id . '|' . now()->timestamp),
            ], 200);
        }

        $token = $user->createToken('hrms-desktop')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->userData($user),
        ]);
    }

    public function mfaVerify(Request $request)
    {
        $request->validate([
            'mfa_token' => 'required|string',
            'code'      => 'required|digits:6',
        ]);

        try {
            $decrypted = decrypt($request->mfa_token);
            [$userId,] = explode('|', $decrypted);
            $user = User::findOrFail($userId);
        } catch (\Exception) {
            return response()->json(['message' => 'Invalid MFA token.'], 400);
        }

        $g2fa = new Google2FA();
        if (!$g2fa->verifyKey($user->mfa_secret, $request->code)) {
            return response()->json(['message' => 'Invalid verification code.'], 401);
        }

        $token = $user->createToken('hrms-desktop')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->userData($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->userData($request->user())]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    private function userData(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'avatar_url' => $user->avatar_url,
            'status'     => $user->status,
            'roles'      => $user->getRoleNames(),
            'permissions'=> $user->getAllPermissions()->pluck('name'),
            'employee'   => $user->employee ? [
                'id'         => $user->employee->id,
                'emp_number' => $user->employee->emp_number,
                'full_name'  => $user->employee->full_name,
                'department' => $user->employee->department?->name,
                'designation'=> $user->employee->designation?->title ?? null,
            ] : null,
            'client' => \App\Models\Client::where('user_id', $user->id)->first()?->only(['id','company_name','contact_person']),
        ];
    }
}
