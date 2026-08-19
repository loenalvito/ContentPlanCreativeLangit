@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Production Board</h1>
    <p class="page-subtitle">Track every piece of content through the production workflow.</p>
</div>
<div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6" id="board">
    @foreach(App\Enums\ContentStatus::cases() as $status)
        <section class="min-h-[460px] rounded-[14px] border border-slate-200/80 p-3 {{ ['planned'=>'bg-slate-50','in_production'=>'bg-orange-50/60','review'=>'bg-violet-50/60','approved'=>'bg-green-50/60','scheduled'=>'bg-cyan-50/60','published'=>'bg-emerald-50/60'][$status->value] }}" data-status="{{ $status->value }}">
            <div class="mb-3 flex items-center justify-between"><h2 class="text-xs font-bold text-slate-700">{{ $status->label() }}</h2><span class="grid h-6 min-w-6 place-items-center rounded-full bg-white px-1.5 text-[10px] font-bold text-slate-500 shadow-sm">{{ ($groups[$status->value] ?? collect())->count() }}</span></div>
            <div class="space-y-2.5 cards min-h-[22rem]" data-testid="kanban-column" data-status="{{ $status->value }}">
                @foreach($groups[$status->value] ?? [] as $c)
                    <a href="{{ route('content.show', $c) }}" draggable="{{ auth()->user()->can('production.change_status') ? 'true' : 'false' }}" data-id="{{ $c->id }}" data-testid="kanban-card" class="block cursor-grab rounded-xl border border-slate-200 bg-white p-3.5 text-xs shadow-[0_3px_10px_rgba(15,23,42,.04)] hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md active:cursor-grabbing">
                        <b class="block leading-5 text-[#152642]">{{ $c->title }}</b>
                        <div class="mt-2.5 flex items-center justify-between gap-2 text-[10px] text-slate-500"><span>{{ $c->series->name }} · {{ $c->platforms->pluck('name')->join(', ') }}</span><x-comment-indicator :total="$c->comments_count" :open="$c->open_comments_count" /></div>
                        <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2.5 text-[10px]"><span class="font-semibold text-slate-600">{{ $c->pic?->name }}</span><span class="text-slate-400">{{ $c->publish_date?->format('d M') }}</span></div>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/sortablejs/Sortable.min.js') }}"></script>
<script>
const canChangeProductionStatus = {{ Illuminate\Support\Js::from(auth()->user()->can('production.change_status')) }};
document.querySelectorAll('.cards').forEach(el => new Sortable(el, {
    group: 'content',
    animation: 150,
    disabled: !canChangeProductionStatus,
    onEnd: async event => {
        if (event.from === event.to) return;
        const status = event.to.closest('section').dataset.status;
        const response = await fetch(`/production/${event.item.dataset.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ status }),
        });

        if (!response.ok) {
            const reference = event.from.children[event.oldIndex] ?? null;
            event.from.insertBefore(event.item, reference);
            let message = 'Status change failed.';
            try { message = (await response.json()).message || message; } catch (_) {}
            window.alert(message);
        }
    },
}));
</script>
@endpush
