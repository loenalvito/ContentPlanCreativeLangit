@props(['name'])

@switch($name)
    @case('dashboard')
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><rect x="3.75" y="3.75" width="6.5" height="6.5" rx="1.5"/><rect x="13.75" y="3.75" width="6.5" height="6.5" rx="1.5"/><rect x="3.75" y="13.75" width="6.5" height="6.5" rx="1.5"/><rect x="13.75" y="13.75" width="6.5" height="6.5" rx="1.5"/></svg>
        @break
    @case('sales-dashboard')
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M4 19.25V11.5M10 19.25V7.75M16 19.25V4.75M3 19.25h18"/><path d="m4.5 8.25 4-3 4 1.75 5-4"/></svg>
        @break
    @case('ideas.index')
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M8.75 16.5h6.5M9.25 19.5h5.5"/><path d="M8.15 14.15A6.25 6.25 0 1 1 15.8 14c-.9.65-1.25 1.2-1.3 2.5h-5c-.05-1.22-.4-1.72-1.35-2.35Z"/></svg>
        @break
    @case('content.index')
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M9 5.25h9.25A1.75 1.75 0 0 1 20 7v12a1.75 1.75 0 0 1-1.75 1.75H7A1.75 1.75 0 0 1 5.25 19v-9"/><path d="M7.25 3.25h-2.5a1.5 1.5 0 0 0-1.5 1.5v2.5h4v-4ZM9.25 10h7.5M9.25 14h7.5M9.25 18h4.5"/></svg>
        @break
    @case('production')
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><rect x="3.25" y="4.25" width="5" height="15.5" rx="1.5"/><rect x="9.5" y="4.25" width="5" height="10" rx="1.5"/><rect x="15.75" y="4.25" width="5" height="13" rx="1.5"/></svg>
        @break
    @case('calendar')
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><rect x="3.5" y="5.25" width="17" height="15.25" rx="2.25"/><path d="M7.5 3.5v3.25M16.5 3.5v3.25M3.5 9.25h17M7.5 13h.01M12 13h.01M16.5 13h.01M7.5 17h.01M12 17h.01"/></svg>
        @break
    @case('published')
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.75"/><path d="m8.25 12.25 2.5 2.5 5-5.25"/></svg>
        @break
    @case('assets')
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M3.5 7.25h6l1.75 2h9.25v8.5a2.25 2.25 0 0 1-2.25 2.25H5.75a2.25 2.25 0 0 1-2.25-2.25V7.25Z"/><path d="M3.5 7.25V6A2 2 0 0 1 5.5 4h3.25l1.75 2h7A2 2 0 0 1 19.5 8v1.25"/><path d="m8 16 2-2 1.75 1.75L14.5 13l2.5 3"/></svg>
        @break
    @case('my-tasks')
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5V3.25h6V4.5M8.5 10.5l1.5 1.5 2.5-3M8.5 16h7"/></svg>
        @break
    @case('team')
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3"/><path d="M3.75 19.5v-1.25A4.75 4.75 0 0 1 8.5 13.5h1A4.75 4.75 0 0 1 14.25 18v1.5M15.25 5.5a3 3 0 0 1 0 5.75M16.5 13.5a4.75 4.75 0 0 1 3.75 4.65v1.35"/></svg>
        @break
    @case('users')
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.5"/><path d="M5.5 20v-1.75A5.75 5.75 0 0 1 11.25 12.5h1.5a5.75 5.75 0 0 1 5.75 5.75V20"/></svg>
        @break
    @case('roles')
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="M12 3.25 19 6v5.25c0 4.35-2.8 7.8-7 9.5-4.2-1.7-7-5.15-7-9.5V6l7-2.75Z"/><path d="m8.75 11.75 2.1 2.1 4.4-4.6"/></svg>
        @break
    @case('accounts')
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.75"/><path d="M15.5 15.5V9.75a3.5 3.5 0 1 0-1.15 2.6c.2 1.8 3.15 1.9 4.4.15M15.5 9.75V13"/></svg>
        @break
    @case('masters')
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="m12 3.5 8.25 4.25L12 12 3.75 7.75 12 3.5Z"/><path d="m5.5 11.25-1.75.9L12 16.5l8.25-4.35-1.75-.9M5.5 15.75l-1.75.9L12 21l8.25-4.35-1.75-.9"/></svg>
        @break
    @default
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5"/></svg>
@endswitch
