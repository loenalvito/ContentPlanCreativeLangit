@extends('layouts.app')

@section('content')
<div class="mb-5">
    <h1 class="text-2xl font-bold">Production Board</h1>
    <p class="text-xs text-slate-500">Pantau proses produksi konten</p>
</div>
<div class="grid md:grid-cols-3 xl:grid-cols-6 gap-3" id="board">
    @foreach(App\Enums\ContentStatus::cases() as $status)
        <section class="rounded-xl p-3 min-h-96 {{ ['planned'=>'bg-slate-100','in_production'=>'bg-blue-50','review'=>'bg-amber-50','approved'=>'bg-cyan-50','scheduled'=>'bg-violet-50','published'=>'bg-emerald-50'][$status->value] }}" data-status="{{ $status->value }}">
            <h2 class="font-bold text-xs mb-3">{{ $status->label() }} ({{ ($groups[$status->value] ?? collect())->count() }})</h2>
            <div class="space-y-2 cards">
                @foreach($groups[$status->value] ?? [] as $c)
                    <a href="{{ route('content.show', $c) }}" draggable="true" data-id="{{ $c->id }}" class="block bg-white border rounded-lg p-3 shadow-sm text-xs">
                        <b>{{ $c->title }}</b>
                        <div class="text-slate-400 mt-2">{{ $c->series->name }} · {{ $c->platforms->pluck('name')->join(', ') }}</div>
                        <div class="flex justify-between mt-2"><span>{{ $c->pic?->name }}</span><span>{{ $c->publish_date?->format('d M') }}</span></div>
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
document.querySelectorAll('.cards').forEach(el => new Sortable(el, {
    group: 'content',
    animation: 150,
    onAdd: async event => {
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

        if (!response.ok) location.reload();
    },
}));
</script>
@endpush
