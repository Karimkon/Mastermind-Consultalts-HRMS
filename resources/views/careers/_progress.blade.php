{{-- The stages of an application, drawn once and used by the thank-you page,
     the tracking page and the client flow chart. --}}
<ol class="relative">
    @foreach($progress as $i => $stage)
    <li class="flex gap-4 {{ $i === count($progress) - 1 ? '' : 'pb-6' }} relative">
        @if($i !== count($progress) - 1)
        <span class="absolute left-[15px] top-8 bottom-0 w-0.5 {{ $stage['state'] === 'done' ? 'bg-green-400' : 'bg-slate-200' }}"></span>
        @endif
        <span class="relative z-10 flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold
            @if($stage['state'] === 'done') bg-green-500 text-white
            @elseif($stage['state'] === 'current') bg-blue-600 text-white ring-4 ring-blue-100
            @else bg-slate-200 text-slate-400 @endif">
            @if($stage['state'] === 'done')
                <i class="fas fa-check"></i>
            @elseif($stage['state'] === 'current')
                <i class="fas fa-circle text-[7px]"></i>
            @else
                {{ $i + 1 }}
            @endif
        </span>
        <div class="pt-1">
            <p class="font-semibold text-sm
                @if($stage['state'] === 'pending') text-slate-400 @else text-slate-800 @endif">
                {{ $stage['label'] }}
                @if($stage['state'] === 'current')
                <span class="ml-2 text-[10px] uppercase tracking-wide bg-blue-50 text-blue-700 rounded-full px-2 py-0.5 font-bold">In progress</span>
                @endif
            </p>
            <p class="text-xs mt-0.5 {{ $stage['state'] === 'pending' ? 'text-slate-400' : 'text-slate-500' }}">{{ $stage['blurb'] }}</p>
        </div>
    </li>
    @endforeach
</ol>
