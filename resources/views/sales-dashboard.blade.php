@extends('layouts.app')

@section('content')
@php
    $seriesData = $pillars->flatMap(fn($pillar) => $pillar->series->map(fn($series) => ['id' => $series->id, 'name' => $series->name, 'pillar_id' => $pillar->id]))->values();
    $pendingRequests = collect($pendingRequests ?? []);
    $contentQueue = collect($contentQueue ?? []);
    $myRequests = collect($myRequests ?? []);
    $statusLabel = function ($value) {
        $status = $value instanceof BackedEnum ? $value->value : (string) $value;
        return match ($status) {
            'planned' => 'Queued', 'in_production' => 'Working', 'review' => 'Reviewing',
            'approved' => 'Approved', 'scheduled' => 'Scheduled', 'published' => 'Done',
            default => filled($status) ? str($status)->replace('_', ' ')->title() : 'Waiting for PIC',
        };
    };
    $formatDate = function ($value, bool $withTime = false) {
        if (! $value) return 'Not scheduled yet';
        $date = $value instanceof Carbon\CarbonInterface ? $value : Carbon\Carbon::parse($value);
        if ($date->isToday()) return 'Today'.($withTime ? ' · '.$date->format('H:i') : '');
        if ($date->isTomorrow()) return 'Tomorrow'.($withTime ? ' · '.$date->format('H:i') : '');
        return $date->format($withTime ? 'd M Y · H:i' : 'd M Y');
    };
@endphp

<div x-data="{open:false,urgent:false,pillar:'',series:@js($seriesData),list(){return this.series.filter(series=>String(series.pillar_id)===String(this.pillar))}}">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div><h1 class="page-title">Request Dashboard</h1><p class="page-subtitle">Monitor Creative workload and track your content requests.</p></div>
        @can('content_request.create')<button data-testid="request-content" @click="open=true" class="btn btn-blue">＋ Request Content</button>@endcan
    </div>

    <section data-testid="sales-workload" class="mt-6">
        <div class="mb-3"><h2 class="section-title">Creative Workload This Week</h2><p class="page-subtitle">{{ $start->format('d M') }}–{{ $end->format('d M Y') }} active production tasks.</p></div>
        <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            @forelse($members as $member)
                <article data-testid="workload-card" class="panel p-5">
                    <div class="flex items-center gap-3"><div class="avatar h-11 w-11 shrink-0">{{ strtoupper(substr($member->name,0,1)) }}</div><div class="min-w-0"><h3 class="truncate font-bold text-[#12233f]">{{ $member->name }}</h3><p class="text-xs text-slate-500">{{ $member->roles->first()?->name }} · Creative</p></div><div class="ml-auto text-right"><strong class="block text-2xl leading-none text-blue-600">{{ $member->week_total }}</strong><span class="text-[9px] font-semibold uppercase tracking-wide text-slate-400">Tasks</span></div></div>
                    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-5">@foreach(['planned'=>'Planned','in_production'=>'In Production','review'=>'Review','approved'=>'Approved','scheduled'=>'Scheduled'] as $key=>$label)<div class="rounded-lg border border-slate-100 bg-slate-50/80 px-2 py-2.5 text-center"><b class="block text-sm text-[#1d3152]">{{ $member->{'week_'.$key} }}</b><span class="block truncate text-[9px] text-slate-500" title="{{ $label }}">{{ $label }}</span></div>@endforeach</div>
                </article>
            @empty
                <div class="panel col-span-full p-8 text-center text-sm text-slate-500">No Creative workload scheduled this week.</div>
            @endforelse
        </div>
    </section>

    <section data-testid="pending-review" class="panel mt-6 overflow-hidden">
        <div class="panel-header"><div><h2 class="section-title">Pending Creative Review</h2><p class="page-subtitle">Requests waiting for Creative to review and schedule.</p></div><span class="grid h-7 min-w-7 place-items-center rounded-full bg-amber-50 px-2 text-xs font-bold text-amber-700">{{ $pendingRequests->count() }}</span></div>
        <div class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($pendingRequests as $pending)
                @php($pendingUrgent = (bool) data_get($pending, 'is_urgent', false))
                <article data-testid="pending-card" class="rounded-xl border p-4 {{ $pendingUrgent ? 'border-red-100 bg-red-50/20' : 'border-slate-200 bg-white' }}">
                    <div class="flex items-start justify-between gap-3"><h3 class="font-bold leading-5 text-[#152642]">{{ strip_tags((string) data_get($pending, 'idea', data_get($pending, 'title', 'Untitled request'))) }}</h3>@if($pendingUrgent)<span class="badge shrink-0 bg-red-50 text-red-700">URGENT REQUEST</span>@endif</div>
                    <div class="mt-3 space-y-1 text-xs text-slate-500"><p>Requested by <b class="text-slate-700">{{ data_get($pending, 'requester_name', 'Unknown') }}</b></p><p>{{ data_get($pending, 'created_at')?->diffForHumans() ?? 'Recently requested' }}</p>@if(data_get($pending, 'needed_at'))<p>Needed <b class="text-slate-700">{{ $formatDate(data_get($pending, 'needed_at'), true) }}</b></p>@endif</div>
                    <div class="mt-4 flex items-center justify-between"><x-sales-status-badge label="Pending Creative Review" />@if(data_get($pending, 'requester_id') === auth()->id())<a class="text-xs font-semibold text-blue-600 hover:text-blue-800" href="{{ route('ideas.show', data_get($pending, 'source_idea_id')) }}">View Detail →</a>@endif</div>
                </article>
            @empty
                <div data-testid="pending-empty" class="col-span-full rounded-xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">No requests waiting for Creative review.</div>
            @endforelse
        </div>
    </section>

    <section data-testid="content-queue" class="panel mt-6 overflow-hidden">
        <div class="panel-header"><div><h2 class="section-title">Content Queue</h2><p class="page-subtitle">Track active requests currently being handled by Creative.</p></div><span class="text-xs font-semibold text-slate-500">{{ $contentQueue->count() }} active</span></div>
        <div class="divide-y divide-slate-100">
            @forelse($contentQueue as $queueItem)
                @php([$queuePosition, $queueUrgent, $queueStatus, $effectiveDeadline, $hasNeededAt] = [data_get($queueItem, 'queue_position', $loop->iteration), (bool) data_get($queueItem, 'is_urgent', false), data_get($queueItem, 'display_status') ?: $statusLabel(data_get($queueItem, 'status')), data_get($queueItem, 'effective_deadline', data_get($queueItem, 'needed_at', data_get($queueItem, 'publish_date'))), (bool) data_get($queueItem, 'needed_at')])
                @php([$deadlineLabel, $deadlineType] = [data_get($queueItem, 'deadline_label') ?: $formatDate($effectiveDeadline, $hasNeededAt), data_get($queueItem, 'deadline_type', $hasNeededAt ? 'Needed' : 'Publish')])
                <article data-testid="queue-item" data-queue-position="{{ $queuePosition }}" class="grid gap-4 px-4 py-5 transition hover:bg-slate-50/60 md:grid-cols-[48px_minmax(0,1.6fr)_minmax(150px,.8fr)_minmax(145px,.75fr)_auto] md:items-center md:px-5 {{ $queueUrgent ? 'border-l-2 border-l-red-400' : '' }}">
                    <div data-testid="queue-position" class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-xs font-extrabold text-blue-700">#{{ $queuePosition }}</div>
                    <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="font-bold text-[#152642]">{{ data_get($queueItem, 'title', 'Untitled content') }}</h3>@if($queueUrgent)<span class="badge bg-red-50 text-red-700">URGENT REQUEST</span>@endif</div><p class="mt-1 text-xs text-slate-500">Requested by {{ data_get($queueItem, 'requester_name', 'Unknown') }}</p></div>
                    <div><span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">PIC Creative</span><p class="mt-1 text-xs font-semibold text-slate-700">{{ data_get($queueItem, 'pic_name') ?: 'Not assigned yet' }}</p></div>
                    <div><span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $deadlineType }}</span><p class="mt-1 text-xs font-semibold text-slate-700">{{ $deadlineLabel }}</p></div>
                    <div class="flex items-center gap-3 md:justify-end"><x-sales-status-badge :label="$queueStatus" />@if(isset($queueItem->comments_count))<x-comment-indicator :total="$queueItem->comments_count" :open="$queueItem->open_comments_count" />@endif</div>
                </article>
            @empty
                <div data-testid="queue-empty" class="p-10 text-center"><div class="mx-auto grid h-11 w-11 place-items-center rounded-full bg-blue-50 text-blue-600"><svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h12M4 17h8"/></svg></div><p class="mt-3 text-sm font-semibold text-slate-700">No active content in the queue.</p><p class="mt-1 text-xs text-slate-500">Converted requests will appear here once Creative starts scheduling them.</p></div>
            @endforelse
        </div>
    </section>

    <section data-testid="my-requests" class="panel mt-6 overflow-hidden">
        <div class="panel-header"><div><h2 class="section-title">My Content Requests</h2><p class="page-subtitle">Follow every request you have submitted, from review to publication.</p></div><span class="text-xs font-semibold text-slate-500">{{ $myRequests->count() }} requests</span></div>
        <div class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($myRequests as $myRequest)
                @php([$myStatus, $myDeadline] = [data_get($myRequest, 'type') === 'idea' ? 'Pending Creative Review' : (data_get($myRequest, 'display_status') ?: $statusLabel(data_get($myRequest, 'status'))), data_get($myRequest, 'needed_at', data_get($myRequest, 'publish_date'))])
                <article data-testid="my-request-item" class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="flex items-start justify-between gap-3"><h3 class="font-bold leading-5 text-[#152642]">{{ strip_tags((string) data_get($myRequest, 'idea', data_get($myRequest, 'title', 'Untitled request'))) }}</h3>@if(data_get($myRequest, 'is_urgent'))<span class="badge shrink-0 bg-red-50 text-red-700">URGENT</span>@endif</div>
                    <div class="mt-4 grid grid-cols-2 gap-3 text-xs"><div><span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">PIC</span><p class="mt-1 font-semibold text-slate-700">{{ data_get($myRequest, 'pic_name') ?: 'Not assigned yet' }}</p></div><div><span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Needed / Publish</span><p class="mt-1 font-semibold text-slate-700">{{ $formatDate($myDeadline, (bool) data_get($myRequest, 'needed_at')) }}</p></div></div>
                    <div class="mt-4 flex items-center justify-between gap-3"><div><x-sales-status-badge :label="$myStatus" /><span class="ml-2 text-[10px] text-slate-400">{{ data_get($myRequest, 'created_at')?->format('d M Y') }}</span></div>@if(data_get($myRequest, 'source_idea_id'))<a class="text-xs font-semibold text-blue-600 hover:text-blue-800" href="{{ route('ideas.show', data_get($myRequest, 'source_idea_id')) }}">View Detail →</a>@endif</div>
                </article>
            @empty
                <div data-testid="my-requests-empty" class="col-span-full rounded-xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">You have not submitted any content requests yet.</div>
            @endforelse
        </div>
    </section>

    <div data-testid="request-modal" x-cloak x-show="open" @keydown.escape.window="open=false" @click.self="open=false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <form method="post" action="{{ route('content-requests.store') }}" class="relative max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-white p-6 shadow-2xl">@csrf
            <button type="button" @click="open=false" aria-label="Close request form" class="absolute right-5 top-4">✕</button><h2 class="text-lg font-bold">Request Content</h2><p class="page-subtitle mb-4">Send a production-ready request to the Creative Ideas Bank.</p>
            <input data-testid="request-title" class="field w-full" name="idea" placeholder="Title / Idea" required><p class="mt-4 text-xs font-bold">PLATFORMS</p><div class="mt-2 flex flex-wrap gap-3">@foreach($platforms as $platform)<label class="text-xs"><input data-testid="request-platform-{{ $platform->slug }}" type="checkbox" name="platform_ids[]" value="{{ $platform->id }}"> {{ $platform->name }}</label>@endforeach</div>
            <div class="mt-4 grid gap-3 md:grid-cols-3"><select x-model="pillar" class="field" name="pillar_id" required><option value="">Pillar</option>@foreach($pillars as $pillar)<option value="{{ $pillar->id }}">{{ $pillar->name }}</option>@endforeach</select><select class="field" name="series_id" required><option value="">Series</option><template x-for="seriesItem in list()"><option :value="seriesItem.id" x-text="seriesItem.name"></option></template></select><select class="field" name="format_id"><option value="">Format</option>@foreach($formats as $format)<option value="{{ $format->id }}">{{ $format->name }}</option>@endforeach</select></div>
            <div class="mt-4 grid gap-3 md:grid-cols-2"><textarea class="field" name="idea_hook" placeholder="Brief / Hook"></textarea><textarea class="field" name="idea_script_copy" placeholder="Script / Copy"></textarea></div><div class="mt-4 grid gap-3 md:grid-cols-2"><input class="field" name="assets[0][name]" placeholder="Asset Name"><select class="field" name="assets[0][type]"><option>Figma</option><option>Google Drive</option><option>Reference</option></select><input class="field md:col-span-2" name="assets[0][url]" type="url" placeholder="Asset URL"></div>
            <label class="mt-5 flex items-center gap-2 text-sm font-bold"><input data-testid="urgent-toggle" x-model="urgent" type="checkbox" name="is_urgent" value="1"> Urgently Needed</label><div data-testid="urgent-fields" x-show="urgent" class="mt-3 grid gap-3 md:grid-cols-2"><label class="text-xs font-bold">Needed When<input class="field mt-1 w-full" type="datetime-local" name="needed_at" :required="urgent"></label><label class="text-xs font-bold">Needed For / Purpose<textarea class="field mt-1 w-full" name="urgent_purpose" :required="urgent"></textarea></label></div>
            <div class="mt-6 flex justify-end gap-2"><button type="button" @click="open=false" class="btn">Cancel</button><button data-testid="request-submit" class="btn btn-blue">Send Request</button></div>
        </form>
    </div>
</div>
@endsection
