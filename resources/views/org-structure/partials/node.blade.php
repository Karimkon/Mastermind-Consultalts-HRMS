{{-- One box and everything under it. Recurses into itself.

     Kept deliberately small: a chart is read by scanning it, and boxes big
     enough to be comfortable one at a time push the rest of the organisation
     off the screen. Only the client sites start collapsed. --}}
@php
    $kids       = $node->children;
    $held       = $node->employees;
    $isSiteList = $kids->isNotEmpty() && $kids->first()->client_id !== null;
    $startsOpen = ! $isSiteList;
@endphp

<li @if($kids->isNotEmpty()) x-data="{ open: {{ $startsOpen ? 'true' : 'false' }} }" @endif>
    <div class="org-node">
        <div class="inline-flex items-center gap-1.5 rounded-md border bg-white px-2 py-1 text-left
                    {{ $node->client_id ? 'border-emerald-200' : 'border-slate-200' }}
                    hover:border-blue-400 hover:shadow-sm transition-all">

            @if($held->isNotEmpty() && ! $node->client_id)
                <div class="flex -space-x-1.5 shrink-0">
                    @foreach($held->take(3) as $person)
                        <img src="{{ $person->avatar_url }}" alt="{{ $person->full_name }}"
                             title="{{ $person->full_name }}"
                             class="w-6 h-6 rounded-full object-cover border border-white ring-1 ring-slate-200">
                    @endforeach
                </div>
            @else
                <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0
                            {{ $node->client_id ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400' }}">
                    <i class="fas {{ $node->client_id ? 'fa-building' : 'fa-user-slash' }} text-[9px]"></i>
                </div>
            @endif

            <div class="min-w-0 text-left leading-tight">
                <a href="{{ route('org-structure.show', $node) }}"
                   class="block font-semibold text-slate-800 text-xs hover:text-blue-600 whitespace-nowrap">
                    {{ $node->title }}
                </a>

                @if($held->isNotEmpty())
                    <p class="text-[11px] text-slate-500 whitespace-nowrap">
                        @if($held->count() === 1)
                            {{ $held->first()->full_name }}
                        @else
                            {{ $held->first()->full_name }}
                            <span class="text-slate-400">+{{ $held->count() - 1 }}</span>
                        @endif
                    </p>
                @else
                    <p class="text-[11px] text-amber-600 whitespace-nowrap">Vacant</p>
                @endif
            </div>

            @if(($headcount[$node->id] ?? 0) > 0)
                <span class="px-1 rounded bg-slate-100 text-slate-500 text-[10px] font-medium shrink-0">
                    {{ number_format($headcount[$node->id]) }}
                </span>
            @endif

            @if($kids->isNotEmpty())
                <button type="button" @click="open = !open"
                        class="w-4 h-4 rounded border border-slate-200 text-slate-400 shrink-0
                               hover:bg-slate-50 hover:text-blue-600 leading-none"
                        :title="open ? 'Collapse' : 'Expand'">
                    <i class="fas text-[8px]" :class="open ? 'fa-minus' : 'fa-plus'"></i>
                </button>
            @endif
        </div>
    </div>

    @if($kids->isNotEmpty())
        <ul x-show="open" x-cloak>
            @foreach($kids as $child)
                @include('org-structure.partials.node', ['node' => $child, 'headcount' => $headcount])
            @endforeach
        </ul>
    @endif
</li>
