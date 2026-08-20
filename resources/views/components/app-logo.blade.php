@props(['wordmark' => true])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="inline-grid h-[30px] w-[30px] shrink-0 place-items-center rounded-[9px] bg-[#102c32] shadow-[0_5px_14px_rgba(15,73,76,.2)]" aria-hidden="true">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none">
            <path d="M4.5 8.1 9.2 3.5l3.1 3.1-4.7 4.7L4.5 8.1Z" fill="#55d6b1"/>
            <path d="m11.7 17.4 4.7-4.7 3.1 3.2-4.7 4.6-3.1-3.1Z" fill="#55d6b1"/>
            <path d="m8 12 4-4 4 4-4 4-4-4Z" fill="#e7fff7"/>
            <path d="m4.5 15.9 3.1-3.2 4.7 4.7-3.1 3.1-4.7-4.6ZM11.7 6.6l3.1-3.1 4.7 4.6-3.1 3.2-4.7-4.7Z" fill="#1aa891"/>
        </svg>
    </span>
    @if($wordmark)<span class="text-[21px] font-extrabold tracking-[-.04em] text-[#102c32]">kolabo</span>@endif
</span>
