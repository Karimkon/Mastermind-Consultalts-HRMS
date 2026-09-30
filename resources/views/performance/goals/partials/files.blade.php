{{-- Evidence attached to a goal: who sent it, when, and what it shows. --}}
@if($files->isNotEmpty())
<ul class="mt-2 space-y-1.5">
    @foreach($files as $file)
    <li class="flex items-start justify-between gap-3 bg-slate-50 rounded px-2.5 py-1.5">
        <div class="min-w-0">
            <a href="{{ route('goals.attachments.download', [$goal, $file]) }}"
               class="text-xs font-medium text-blue-600 hover:underline break-all">
                <i class="fas fa-paperclip"></i> {{ $file->original_name }}
            </a>
            <p class="text-[11px] text-slate-500">
                {{ $file->uploader?->name ?? 'Unknown' }} ·
                {{ $file->created_at->format('d M Y H:i') }} ·
                {{ $file->readable_size }}
            </p>
            @if(filled($file->note))
                <p class="text-xs text-slate-600 mt-0.5">{{ $file->note }}</p>
            @endif
        </div>

        {{-- Removable by whoever attached it, or by an administrator. The server
             checks the same thing; this only keeps the button off screens where
             it would fail. --}}
        @if($file->uploaded_by === auth()->id() || auth()->user()->hasAnyRole(['super-admin','hr-admin','manager','md']))
        <form method="POST" action="{{ route('goals.attachments.destroy', [$goal, $file]) }}"
              onsubmit="return confirm('Remove {{ addslashes($file->original_name) }}?')">
            @csrf @method('DELETE')
            <button class="text-slate-400 hover:text-red-600 text-xs shrink-0" title="Remove">
                <i class="fas fa-trash"></i>
            </button>
        </form>
        @endif
    </li>
    @endforeach
</ul>
@endif
