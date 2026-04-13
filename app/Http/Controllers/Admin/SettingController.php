<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $skip = ['_token', '_method'];
        foreach ($request->except($skip) as $key => $value) {
            Setting::set($key, $value);
        }
        return back()->with('success', 'Settings saved.');
    }
}