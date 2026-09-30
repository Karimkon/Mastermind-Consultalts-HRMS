@props(['count' => 0, 'area' => null])

{{-- Rendered even at zero, just hidden, so the 30-second poll can reveal it
     without a page reload. `hidden` covers "nothing waiting"; x-show covers
     "the sidebar is collapsed" — either one hides it. --}}
<span x-show="sidebarOpen"
      @if($area) data-nav-badge="{{ $area }}" @endif
      {{ $count > 0 ? '' : 'hidden' }}
      title="{{ $count }} unread"
      class="ml-auto min-w-[1.15rem] px-1.5 py-0.5 rounded-full bg-red-500 text-white
             text-[11px] font-bold leading-none text-center">{{ $count > 99 ? '99+' : $count }}</span>
