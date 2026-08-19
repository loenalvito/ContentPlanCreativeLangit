@props(['total' => 0, 'open' => 0, 'compact' => false])

@if($total > 0)
<span data-testid="comment-indicator" aria-label="{{ $total }} total comments{{ $open > 0 ? ', '.$open.' unresolved' : '' }}" class="relative inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-[10px] font-semibold text-slate-500 shadow-sm">
    <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12c0 4.142-4.03 7.5-9 7.5a10.3 10.3 0 0 1-3.47-.59L3 20.25l1.55-4.12A6.82 6.82 0 0 1 3 12c0-4.142 4.03-7.5 9-7.5s9 3.358 9 7.5Z"/></svg>
    @unless($compact)<span data-testid="comment-total">{{ $total }}</span>@endunless
    @if($open > 0)
        <span data-testid="open-comment-count" class="absolute -right-2 -top-2 grid h-4 min-w-4 place-items-center rounded-full bg-red-500 px-1 text-[9px] font-bold leading-none text-white ring-2 ring-white">{{ $open }}</span>
    @endif
</span>
@endif
