@props(["icon", "label", "value", "color" => "blue", "trend" => null])
@php
$colors = [
    "blue"   => ["bg" => "bg-blue-100",   "icon" => "text-blue-600",   "border" => "border-l-blue-500"],
    "green"  => ["bg" => "bg-green-100",  "icon" => "text-green-600",  "border" => "border-l-green-500"],
    "yellow" => ["bg" => "bg-yellow-100", "icon" => "text-yellow-600", "border" => "border-l-yellow-500"],
    "red"    => ["bg" => "bg-red-100",    "icon" => "text-red-600",    "border" => "border-l-red-500"],
    "purple" => ["bg" => "bg-purple-100", "icon" => "text-purple-600", "border" => "border-l-purple-500"],
    "indigo" => ["bg" => "bg-indigo-100", "icon" => "text-indigo-600", "border" => "border-l-indigo-500"],
    "teal"   => ["bg" => "bg-teal-100",   "icon" => "text-teal-600",   "border" => "border-l-teal-500"],
];
$c = $colors[$color] ?? $colors["blue"];
@endphp
<div class="card p-5 border-l-4 {{ $c['border'] }} hover:shadow-md transition-shadow">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">{{ $label }}</p>
            <p class="text-3xl font-bold text-slate-900 mt-1">{{ number_format($value) }}</p>
        </div>
        <div class="w-12 h-12 {{ $c['bg'] }} rounded-xl flex items-center justify-center">
            <i class="{{ $icon }} text-xl {{ $c['icon'] }}"></i>
        </div>
    </div>
</div>
