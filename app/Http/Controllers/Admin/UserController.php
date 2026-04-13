<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('roles')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")->orWhere('email', 'like', "%{$request->search}%"))
            ->when($request->role, fn($q) => $q->role($request->role))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->paginate(20);
        $roles = Role::all();
        return view('admin.users.index', compact('users', 'roles'));
    }

    public function create() { return view('admin.users.create', ['roles' => Role::all()]); }

    public function store(Request $request)
    {
        $data = $request->validate(['name'=>'required','email'=>'required|email|unique:users','password'=>'required|min:8|confirmed','role'=>'required','status'=>'required']);
        $user = User::create(['name'=>$data['name'],'email'=>$data['email'],'password'=>Hash::make($data['password']),'status'=>$data['status']]);
        $user->assignRole($data['role']);
        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(User $user) { return view('admin.users.edit', ['user' => $user, 'roles' => Role::all()]); }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['name'=>'required','email'=>'required|email|unique:users,email,'.$user->id,'role'=>'required','status'=>'required']);
        $user->update(['name'=>$data['name'],'email'=>$data['email'],'status'=>$data['status']]);
        if ($request->password) {
            $request->validate(['password' => 'min:8|confirmed']);
            $user->update(['password' => Hash::make($request->password)]);
        }
        $user->syncRoles([$data['role']]);
        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) return back()->with('error', 'Cannot delete yourself.');
        $user->delete();
        return back()->with('success', 'User deleted.');
    }
}