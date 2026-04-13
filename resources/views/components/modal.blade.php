@props(["id", "title", "size" => "max-w-lg"])
<div x-data="{ open: false }" x-on:open-modal-{{ $id }}.window="open = true" x-on:close-modal-{{ $id }}.window="open = false">
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="open = false">
        <div x-show="open" x-transition class="bg-white rounded-2xl shadow-2xl w-full {{ $size }} max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <h3 class="text-base font-semibold text-slate-800">{{ $title }}</h3>
                <button @click="open = false" class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-6">{{ $slot }}</div>
        </div>
    </div>
</div>
