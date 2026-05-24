<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        $heroSlides  = json_decode($settings['hero_slides']  ?? '[]', true) ?: [];
        $teamPhotos  = json_decode($settings['team_photos']  ?? '[]', true) ?: [];
        $clientLogos = json_decode($settings['client_logos'] ?? '[]', true) ?: [];
        return view('admin.settings.index', compact('settings', 'heroSlides', 'teamPhotos', 'clientLogos'));
    }

    public function update(Request $request)
    {
        $skip = ['_token', '_method'];
        if ($request->hasFile('company_logo')) {
            $path = $request->file('company_logo')->store('uploads/branding', 'public');
            Setting::set('company_logo', $path);
        }
        foreach ($request->except(array_merge($skip, ['company_logo'])) as $key => $value) {
            if (!is_array($value)) Setting::set($key, $value);
        }
        return back()->with('success', 'Settings saved.');
    }

    public function updateWebsite(Request $request)
    {
        // Hero slides
        $slides = [];
        foreach ($request->input('slide_title', []) as $i => $title) {
            $imgPath = $request->input('slide_existing_image')[$i] ?? null;
            if ($request->hasFile("slide_image.$i")) {
                $imgPath = $request->file("slide_image.$i")->store('uploads/slides', 'public');
            }
            if ($title || $imgPath) {
                $slides[] = [
                    'image'      => $imgPath,
                    'eyebrow'    => $request->input('slide_eyebrow')[$i] ?? 'Mastermind Consults',
                    'title'      => $title,
                    'subtitle'   => $request->input('slide_subtitle')[$i] ?? '',
                    'btn1_label' => $request->input('slide_btn1_label')[$i] ?? 'View Jobs',
                    'btn1_url'   => $request->input('slide_btn1_url')[$i] ?? '#jobs',
                    'btn2_label' => $request->input('slide_btn2_label')[$i] ?? 'Our Services',
                    'btn2_url'   => $request->input('slide_btn2_url')[$i] ?? '#services',
                    'gradient'   => 'linear-gradient(110deg,#1C1C1E 0%,#2d2420 100%)',
                ];
            }
        }
        Setting::set('hero_slides', json_encode($slides));

        // Team photos
        $team = [];
        foreach ($request->input('team_name', []) as $i => $name) {
            $imgPath = $request->input('team_existing_image')[$i] ?? null;
            if ($request->hasFile("team_image.$i")) {
                $imgPath = $request->file("team_image.$i")->store('uploads/team', 'public');
            }
            if ($name) {
                $team[] = ['name' => $name, 'role' => $request->input('team_role')[$i] ?? '', 'image' => $imgPath];
            }
        }
        Setting::set('team_photos', json_encode($team));

        // Client logos
        $clients = [];
        foreach ($request->input('client_name', []) as $i => $name) {
            $imgPath = $request->input('client_existing_logo')[$i] ?? null;
            if ($request->hasFile("client_logo.$i")) {
                $imgPath = $request->file("client_logo.$i")->store('uploads/clients', 'public');
            }
            if ($name) {
                $clients[] = ['name' => $name, 'url' => $request->input('client_url')[$i] ?? '#', 'logo' => $imgPath];
            }
        }
        Setting::set('client_logos', json_encode($clients));

        return back()->with('success', 'Website content saved.');
    }
}