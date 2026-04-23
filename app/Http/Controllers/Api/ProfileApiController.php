<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileApiController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('employee.department', 'employee.designation');
        return response()->json([
            'data' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'avatar_url' => $user->avatar_url,
                'status'     => $user->status,
                'mfa_enabled'=> $user->mfa_enabled,
                'roles'      => $user->getRoleNames(),
                'employee'   => $user->employee ? [
                    'id'              => $user->employee->id,
                    'emp_number'      => $user->employee->emp_number,
                    'full_name'       => $user->employee->full_name,
                    'phone'           => $user->employee->phone,
                    'personal_email'  => $user->employee->personal_email,
                    'date_of_birth'   => $user->employee->date_of_birth?->format('Y-m-d'),
                    'address'         => $user->employee->address,
                    'city'            => $user->employee->city,
                    'country'         => $user->employee->country,
                    'department'      => $user->employee->department?->name,
                    'designation'     => $user->employee->designation?->title ?? null,
                    'hire_date'       => $user->employee->hire_date?->format('Y-m-d'),
                    'employment_type' => $user->employee->employment_type,
                    'bio'             => $user->employee->bio,
                ] : null,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);
        $user->update($request->only('name', 'email'));
        return response()->json(['message' => 'Profile updated.', 'data' => ['name' => $user->name, 'email' => $user->email]]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ]);
        $user = $request->user();
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }
        $user->update(['password' => Hash::make($request->password)]);
        return response()->json(['message' => 'Password updated.']);
    }

    public function updateAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|image|max:2048']);
        $user = $request->user();
        if ($user->avatar) Storage::disk('public')->delete($user->avatar);
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);
        return response()->json(['avatar_url' => $user->fresh()->avatar_url]);
    }
}
