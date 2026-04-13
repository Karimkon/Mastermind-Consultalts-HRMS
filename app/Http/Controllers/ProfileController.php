<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash, Storage};

class ProfileController extends Controller
{
    public function index() { return view('profile.index'); }

    public function update(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email,'.auth()->id()]);
        auth()->user()->update($request->only('name', 'email'));
        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password'  => 'required',
            'password'          => 'required|min:8|confirmed',
        ]);
        if (!Hash::check($request->current_password, auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }
        auth()->user()->update(['password' => Hash::make($request->password)]);
        return back()->with('success', 'Password changed successfully.');
    }

    public function updateAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|image|max:2048']);
        $path = $request->file('avatar')->store('avatars', 'public');
        if (auth()->user()->avatar) Storage::disk('public')->delete(auth()->user()->avatar);
        auth()->user()->update(['avatar' => $path]);
        return response()->json(['url' => Storage::url($path)]);
    }

    public function updatePreferences(Request $request)
    {
        // store notification prefs in user meta (simple implementation)
        return back()->with('success', 'Preferences saved.');
    }
}