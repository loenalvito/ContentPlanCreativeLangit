@extends('layouts.app')

@section('content')
@php
    $seriesData = $pillars->flatMap(fn ($pillar) => $pillar->series->map(fn ($series) => ['id' => $series->id, 'name' => $series->name, 'pillar_id' => $pillar->id]))->values();
    $accountData = $accounts->map(fn ($account) => ['id' => $account->id, 'name' => $account->display_name, 'platform_id' => $account->platform_id])->values();
@endphp
<div x-data="productionBoard()" @kanban-published-ready.window="openPublished($event.detail)" @keydown.escape.window="publishedOpen && cancelPublished()">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div><h1 class="page-title">Production Board</h1><p class="page-subtitle">Track every piece of content through the production workflow.</p></div>
        <span data-testid="production-context" class="badge {{ $canViewAllTasks ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $canViewAllTasks && empty($filters['pic_user_id']) && $filters['period']==='all' ? 'All Tasks · All Dates' : ((!$canViewAllTasks || (int)$filters['pic_user_id']===auth()->id() ? 'My Tasks' : ($users->firstWhere('id',(int)$filters['pic_user_id'])?->name ?? 'Filtered Tasks')).' · '.match($filters['period']){'all'=>'All Dates','this_week'=>'This Week','last_week'=>'Last Week','next_week'=>'Next Week','this_month'=>'This Month','specific'=>'Specific Date','range'=>'Date Range','unscheduled'=>'Unscheduled',default=>'Selected Period'}) }}</span>
    </div>

    <form data-testid="production-filters" class="panel mb-5 p-3" x-data="{pillar:'{{ $filters['pillar_id'] ?? '' }}',series:'{{ $filters['series_id'] ?? '' }}',platform:'{{ $filters['platform_id'] ?? '' }}',account:'{{ $filters['account_id'] ?? '' }}',period:'{{ $filters['period'] }}',seriesData:@js($seriesData),accountData:@js($accountData),seriesOptions(){return this.seriesData.filter(item=>!this.pillar||String(item.pillar_id)===String(this.pillar))},accountOptions(){return this.accountData.filter(item=>!this.platform||String(item.platform_id)===String(this.platform))}}">
        <div class="flex flex-wrap items-end gap-2">
            @if($canViewAllTasks)
                <label class="text-xs font-semibold">User / PIC<select data-testid="production-filter-pic" class="field mt-1" name="pic_user_id"><option value="">All Users</option><option value="{{ auth()->id() }}" @selected((int)($filters['pic_user_id']??0)===auth()->id())>My Tasks</option>@foreach($users->where('id','!=',auth()->id()) as $user)<option value="{{ $user->id }}" @selected((int)($filters['pic_user_id']??0)===$user->id)>{{ $user->name }}</option>@endforeach</select></label>
            @else
                <input type="hidden" name="pic_user_id" value="{{ auth()->id() }}"><label class="text-xs font-semibold">User / PIC<span data-testid="production-filter-pic-locked" class="field mt-1 block">My Tasks</span></label>
            @endif
            <label class="text-xs font-semibold">Date / Period<select data-testid="production-filter-period" x-model="period" class="field mt-1" name="period">@if($canViewAllTasks)<option value="all">All Dates</option>@endif<option value="this_week">This Week</option><option value="last_week">Last Week</option><option value="next_week">Next Week</option><option value="this_month">This Month</option><option value="specific">Specific Date</option><option value="range">Date Range</option><option value="unscheduled">Unscheduled</option></select></label>
            <label class="text-xs font-semibold">Platform<select data-testid="production-filter-platform" x-model="platform" @change="account=''" class="field mt-1" name="platform_id"><option value="">All Platforms</option>@foreach($platforms as $platform)<option value="{{ $platform->id }}">{{ $platform->name }}</option>@endforeach</select></label>
            <label class="text-xs font-semibold">Account<select data-testid="production-filter-account" x-model="account" class="field mt-1" name="account_id"><option value="">All Accounts</option><template x-for="item in accountOptions()" :key="item.id"><option :value="item.id" x-text="item.name"></option></template></select></label>
            <label class="text-xs font-semibold">Pillar<select data-testid="production-filter-pillar" x-model="pillar" @change="series=''" class="field mt-1" name="pillar_id"><option value="">All Pillars</option>@foreach($pillars as $pillar)<option value="{{ $pillar->id }}">{{ $pillar->name }}</option>@endforeach</select></label>
            <label class="text-xs font-semibold">Series<select data-testid="production-filter-series" x-model="series" class="field mt-1" name="series_id"><option value="">All Series</option><template x-for="item in seriesOptions()" :key="item.id"><option :value="item.id" x-text="item.name"></option></template></select></label>
            <label class="text-xs font-semibold">Format<select data-testid="production-filter-format" class="field mt-1" name="format_id"><option value="">All Formats</option>@foreach($formats as $format)<option value="{{ $format->id }}" @selected((int)($filters['format_id']??0)===$format->id)>{{ $format->name }}</option>@endforeach</select></label>
            <button data-testid="production-filter-apply" class="btn btn-blue">Apply Filters</button><a data-testid="production-filter-reset" href="{{ route('production') }}" class="btn border">Reset Filters</a>
        </div>
        <div class="mt-3 flex flex-wrap gap-2 rounded-lg bg-slate-50 p-3" x-show="['specific','range'].includes(period)" x-cloak>
            <label x-show="period==='specific'" class="text-xs font-semibold">Specific Date<input data-testid="production-filter-specific-date" class="field mt-1" type="date" name="specific_date" value="{{ $filters['specific_date'] ?? '' }}"></label>
            <label x-show="period==='range'" class="text-xs font-semibold">Start Date<input data-testid="production-filter-start-date" class="field mt-1" type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}"></label>
            <label x-show="period==='range'" class="text-xs font-semibold">End Date<input data-testid="production-filter-end-date" class="field mt-1" type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}"></label>
        </div>
    </form>

    @if($isEmpty)
        <div data-testid="production-empty" class="panel mb-5 border-dashed p-8 text-center"><p class="font-semibold text-slate-700">{{ !$canViewAllTasks && $filters['period']==='this_week' ? 'No tasks assigned to you this week.' : 'No tasks match the active filters.' }}</p>@if(!$canViewAllTasks && $filters['period']==='this_week')<a class="mt-3 inline-block text-xs font-semibold text-blue-600" href="{{ route('production',['period'=>'next_week']) }}">View Next Week →</a>@endif</div>
    @endif

    <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6" id="board">
        @foreach(App\Enums\ContentStatus::cases() as $status)
            <section class="min-h-[460px] rounded-[14px] border border-slate-200/80 p-3 {{ ['planned'=>'bg-slate-50','in_production'=>'bg-orange-50/60','review'=>'bg-violet-50/60','approved'=>'bg-green-50/60','scheduled'=>'bg-cyan-50/60','published'=>'bg-emerald-50/60'][$status->value] }}" data-status="{{ $status->value }}">
                <div class="mb-3 flex items-center justify-between"><h2 class="text-xs font-bold text-slate-700">{{ $status->label() }}</h2><span data-testid="kanban-count" class="grid h-6 min-w-6 place-items-center rounded-full bg-white px-1.5 text-[10px] font-bold text-slate-500 shadow-sm">{{ ($groups[$status->value] ?? collect())->count() }}</span></div>
                <div class="space-y-2.5 cards min-h-[22rem]" data-testid="kanban-column" data-status="{{ $status->value }}">
                    @foreach($groups[$status->value] ?? [] as $content)
                        <a href="{{ route('content.show', $content) }}" draggable="{{ auth()->user()->can('production.change_status') ? 'true' : 'false' }}" data-id="{{ $content->id }}" data-testid="kanban-card" class="block cursor-grab rounded-xl border border-slate-200 bg-white p-3.5 text-xs shadow-[0_3px_10px_rgba(15,23,42,.04)] hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md active:cursor-grabbing">
                            <b class="block leading-5 text-[#152642]">{{ $content->title }}</b>
                            <div class="mt-2.5 flex items-center justify-between gap-2 text-[10px] text-slate-500"><span>{{ $content->series->name }} · {{ $content->platforms->pluck('name')->join(', ') }}</span><x-comment-indicator :total="$content->comments_count" :open="$content->open_comments_count" /></div>
                            <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2.5 text-[10px]"><span class="font-semibold text-slate-600">{{ $content->pic?->name }}</span><span class="text-slate-400">{{ $content->publish_date?->format('d M') ?? 'Unscheduled' }}</span></div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    <div data-testid="kanban-published-modal" x-cloak x-show="publishedOpen" @click.self="cancelPublished()" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-4">
        <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl"><button data-testid="kanban-published-close" type="button" @click="cancelPublished()" :disabled="publishedBusy" class="absolute right-5 top-4 disabled:cursor-not-allowed disabled:opacity-50" aria-label="Close published link modal">✕</button><h2 class="text-lg font-bold">Record Published Link</h2><p class="page-subtitle mb-4">Choose how to confirm this Content as Published. Cancel restores its previous Kanban status.</p><p data-testid="kanban-published-error" x-show="publishedError" x-text="publishedError" class="mb-3 rounded-lg bg-red-50 p-3 text-xs text-red-700"></p><label class="text-xs font-semibold">Posting Link<input data-testid="kanban-published-url" x-model="published.final_url" :disabled="publishedBusy" class="field mt-1 w-full disabled:opacity-60" type="url" placeholder="https://instagram.com/..."></label><div class="mt-5 flex flex-wrap justify-end gap-2"><button data-testid="kanban-published-cancel" type="button" @click="cancelPublished()" :disabled="publishedBusy" class="btn border disabled:cursor-not-allowed disabled:opacity-50">Cancel</button><button data-testid="kanban-not-for-public" type="button" @click="savePublished('not_for_public')" :disabled="publishedBusy" class="btn border disabled:cursor-not-allowed disabled:opacity-50">Not for Public</button><button data-testid="kanban-save-published-url" type="button" @click="savePublished('public')" :disabled="publishedBusy" class="btn bg-emerald-600 text-white disabled:cursor-not-allowed disabled:opacity-50">Submit</button></div></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/sortablejs/Sortable.min.js') }}"></script>
<script>
const canChangeProductionStatus = {{ Illuminate\Support\Js::from(auth()->user()->can('production.change_status')) }};
function productionBoard() {
    return {
        publishedOpen: false,
        publishedBusy: false,
        publishedError: '',
        published: { action: '', final_url: '', contentId: null, previous_status: null, trigger_source: null },
        openPublished(data) {
            this.published = { action: data.published_information_url, final_url: data.final_url || '', contentId: data.id, previous_status: data.previous_status, trigger_source: 'kanban_drag' };
            this.publishedError = '';
            this.publishedBusy = false;
            this.publishedOpen = true;
        },
        async savePublished(visibility) {
            if (visibility === 'public' && !this.published.final_url) { this.publishedError = 'Posting Link is required.'; return; }
            if (this.publishedBusy) return;
            this.publishedBusy = true; this.publishedError = '';
            const response = await fetch(this.published.action, { method: 'PUT', headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}, body: JSON.stringify({visibility, final_url: this.published.final_url}) });
            if (!response.ok) { let message='Published information could not be saved.'; try { const data=await response.json(); message=data.message||Object.values(data.errors||{})[0]?.[0]||message; } catch (_) {} this.publishedError=message; this.publishedBusy=false; return; }
            this.publishedOpen = false;
            this.publishedBusy = false;
        },
        async cancelPublished() {
            if (!this.publishedOpen || this.publishedBusy || this.published.trigger_source !== 'kanban_drag') return;
            this.publishedBusy = true; this.publishedError = '';
            const response = await fetch(`/production/${this.published.contentId}`, { method: 'PATCH', headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}, body: JSON.stringify({status: this.published.previous_status, cancelled_publishing: true}) });
            if (!response.ok) { this.publishedError='Failed to restore previous status. Please try again.'; this.publishedBusy=false; return; }
            const card = document.querySelector(`[data-testid=kanban-card][data-id="${this.published.contentId}"]`);
            const destination = document.querySelector(`section[data-status="${this.published.previous_status}"] .cards`);
            if (card && destination) destination.appendChild(card);
            refreshKanbanCounts();
            this.publishedOpen = false;
            this.publishedBusy = false;
        },
    };
}
function refreshKanbanCounts() { document.querySelectorAll('#board section[data-status]').forEach(section => { section.querySelector('[data-testid=kanban-count]').textContent = section.querySelectorAll('[data-testid=kanban-card]').length; }); }
document.querySelectorAll('.cards').forEach(el => new Sortable(el, {
    group: 'content', animation: 150, disabled: !canChangeProductionStatus,
    onEnd: async event => {
        if (event.from === event.to) return;
        const status = event.to.closest('section').dataset.status;
        const previousStatus = event.from.closest('section').dataset.status;
        const response = await fetch(`/production/${event.item.dataset.id}`, { method: 'PATCH', headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Accept':'application/json'}, body: JSON.stringify({ status }) });
        if (!response.ok) {
            const reference = event.from.children[event.oldIndex] ?? null;
            event.from.insertBefore(event.item, reference);
            refreshKanbanCounts();
            let message = 'Status change failed.'; try { message = (await response.json()).message || message; } catch (_) {} window.alert(message); return;
        }
        const data = await response.json(); refreshKanbanCounts();
        if (status === 'published') {
            window.dispatchEvent(new CustomEvent('kanban-published-ready', {detail: {...data, id: event.item.dataset.id, previous_status: previousStatus, trigger_source: 'kanban_drag'}}));
        }
    },
}));
</script>
@endpush
