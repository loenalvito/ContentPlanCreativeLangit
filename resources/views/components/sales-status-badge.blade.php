@props(['label'])

@php
    $normalized = strtolower((string) $label);
    $classes = match ($normalized) {
        'waiting for pic' => 'bg-slate-100 text-slate-600',
        'queued' => 'bg-blue-50 text-blue-700',
        'working' => 'bg-orange-50 text-orange-700',
        'reviewing' => 'bg-violet-50 text-violet-700',
        'approved' => 'bg-emerald-50 text-emerald-700',
        'scheduled' => 'bg-cyan-50 text-cyan-700',
        'done', 'published' => 'bg-green-50 text-green-700',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp

<span data-testid="queue-status" class="badge {{ $classes }}">{{ $label }}</span>
