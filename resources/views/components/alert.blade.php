@props(["type" => null, "message" => null])
@php
// Auto-detect from session flash if not passed explicitly
$resolvedType    = $type;
$resolvedMessage = $message;
if (!$resolvedMessage) {
    foreach (['success','error','warning','info'] as $t) {
        if (session($t)) { $resolvedMessage = session($t); $resolvedType = $resolvedType ?? $t; break; }
    }
}
$resolvedType = $resolvedType ?? 'success';
$styles = [
    "success" => "bg-green-50 border-green-200 text-green-800",
    "error"   => "bg-red-50 border-red-200 text-red-800",
    "warning" => "bg-yellow-50 border-yellow-200 text-yellow-800",
    "info"    => "bg-blue-50 border-blue-200 text-blue-800",
];
$icons = ["success" => "fa-check-circle", "error" => "fa-exclamation-circle", "warning" => "fa-exclamation-triangle", "info" => "fa-info-circle"];
@endphp
@if($resolvedMessage)
<div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
     class="flex items-center gap-3 border rounded-xl px-4 py-3 mb-4 {{ $styles[$resolvedType] ?? $styles['success'] }}">
    <i class="fas {{ $icons[$resolvedType] ?? 'fa-info-circle' }} flex-shrink-0"></i>
    <span class="text-sm font-medium">{{ $resolvedMessage }}</span>
    <button @click="show = false" class="ml-auto opacity-60 hover:opacity-100"><i class="fas fa-times text-xs"></i></button>
</div>
@endif
