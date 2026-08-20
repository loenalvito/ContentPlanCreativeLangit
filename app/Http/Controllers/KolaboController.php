<?php

namespace App\Http\Controllers;

use App\Actions\UpdateContentStatus;
use App\Enums\ContentStatus;
use App\Enums\IdeaStatus;
use App\Models\Account;
use App\Models\Asset;
use App\Models\Content;
use App\Models\ContentBrief;
use App\Models\Department;
use App\Models\Format;
use App\Models\Idea;
use App\Models\IdeaAsset;
use App\Models\IdeaBrief;
use App\Models\Pillar;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class KolaboController extends Controller
{
    private function refs(): array
    {
        return ['pillars' => Pillar::where('is_active', true)->with(['series' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])->orderBy('sort_order')->get(), 'accounts' => Account::where('is_active', true)->get(), 'platforms' => Platform::where('is_active', true)->with(['accounts' => fn ($q) => $q->where('is_active', true)])->get(), 'formats' => Format::where('is_active', true)->orderBy('sort_order')->get(), 'users' => User::where('is_active', true)->with('department')->get()];
    }

    public function dashboard(Request $r)
    {
        abort_unless($r->user()->can('dashboard.view'), 403);
        $q = Content::query();
        $stats = ['Total Content' => (clone $q)->count(), 'In Production' => (clone $q)->where('status', 'in_production')->count(), 'Review' => (clone $q)->where('status', 'review')->count(), 'Scheduled' => (clone $q)->where('status', 'scheduled')->count(), 'Published' => (clone $q)->where('status', 'published')->count()];

        return view('dashboard', compact('stats') + ['today' => Content::with(['platforms', 'pic'])->whereDate('publish_date', today())->get(), 'upcoming' => Content::with('pic')->whereBetween('publish_date', [today(), today()->addDays(7)])->orderBy('publish_date')->get(), 'byPillar' => Content::select('pillar_id', DB::raw('count(*) total'))->with('pillar')->groupBy('pillar_id')->get(), 'byPlatform' => Platform::withCount('contents')->get()]);
    }

    public function contents(Request $r)
    {
        abort_unless($r->user()->can('content.view'), 403);
        $r->validate(['date_mode' => 'nullable|in:specific,month,year,range', 'specific_date' => 'nullable|date', 'month' => 'nullable|date_format:Y-m', 'year' => 'nullable|integer|min:2000|max:2100', 'start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date']);
        $q = Content::with(['account', 'platforms', 'pillar', 'series', 'format', 'pic'])->withCount('comments')->withCount(['comments as open_comments_count' => fn ($query) => $query->where('status', 'open')]);
        if ($r->filled('search')) {
            $q->where('title', 'like', '%'.$r->search.'%');
        }foreach (['pillar_id', 'series_id', 'format_id', 'pic_user_id', 'status'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->$f);
            }
        }if ($r->filled('account_id')) {
            $accountId = $r->integer('account_id');
            $q->where(fn ($query) => $query->where('account_id', $accountId)->orWhereHas('platforms', fn ($platformQuery) => $platformQuery->where('content_platform.account_id', $accountId)));
        }if ($r->filled('platform_id')) {
            $q->whereHas('platforms', fn ($query) => $query->where('platforms.id', $r->integer('platform_id')));
        }match ($r->input('date_mode')) {
            'specific' => $r->filled('specific_date') ? $q->whereDate('publish_date', $r->input('specific_date')) : null,'month' => $r->filled('month') ? $q->whereYear('publish_date', substr($r->input('month'), 0, 4))->whereMonth('publish_date', substr($r->input('month'), 5, 2)) : null,'year' => $r->filled('year') ? $q->whereYear('publish_date', $r->integer('year')) : null,'range' => $r->filled('start_date') && $r->filled('end_date') ? $q->whereDate('publish_date', '>=', $r->input('start_date'))->whereDate('publish_date', '<=', $r->input('end_date')) : null,default => null
        };

        return view('content.index', $this->refs() + ['contents' => $q->latest('publish_date')->paginate(12)->withQueryString()]);
    }

    public function storeContent(Request $r)
    {
        abort_unless($r->user()->can('content.create'), 403);
        $d = $this->contentData($r);
        $d['status'] = 'planned';
        $extra = $this->creativeData($r);
        $accountMap = $r->validate(['platform_accounts' => 'nullable|array', 'platform_accounts.*' => 'required|exists:accounts,id'])['platform_accounts'] ?? [];
        foreach ($r->input('platform_ids', []) as $platformId) {
            abort_unless(Account::whereKey($accountMap[$platformId] ?? null)->where('platform_id', $platformId)->where('is_active', true)->exists(), 422, 'Selected account does not belong to this platform.');
        }$d['account_id'] = collect($accountMap)->first();
        $c = DB::transaction(function () use ($d, $extra, $r, $accountMap) {
            $c = Content::create($d + ['created_by' => $r->user()->id, 'updated_by' => $r->user()->id]);
            $sync = [];
            foreach ($r->input('platform_ids', []) as $platformId) {
                $sync[$platformId] = ['account_id' => $accountMap[$platformId]];
            }$c->platforms()->sync($sync);
            ContentBrief::create(['content_id' => $c->id] + $extra['brief']);
            foreach ($extra['assets'] as $a) {
                Asset::create(['content_id' => $c->id, 'title' => $a['name'], 'asset_type' => $a['type'], 'url' => $a['url'], 'notes' => $a['notes'] ?? null, 'category' => 'Content Reference', 'added_by' => $r->user()->id]);
            }

return $c;
        });

        return redirect()->route('content.show', $c)->with('success', 'Content berhasil dibuat.');
    }

    public function showContent(Request $r, Content $content)
    {
        abort_unless($r->user()->can('view', $content), 403);
        $content->load(['account', 'platforms', 'pillar', 'series', 'format', 'pic', 'creator', 'sourceIdea.submitter.department', 'brief', 'assets.addedBy', 'revisions.author', 'comments.user', 'comments.resolver'])->loadCount('comments')->loadCount(['comments as open_comments_count' => fn ($query) => $query->where('status', 'open')]);

        return view('content.show', $this->refs() + ['content' => $content, 'activities' => $content->activities()->latest()->get(), 'canEdit' => $r->user()->can('update', $content), 'canPublish' => $r->user()->can('publish', $content), 'canManagePublishedInformation' => $r->user()->can('managePublishedInformation', $content)]);
    }

    private function contentData(Request $r): array
    {
        $d = $r->validate(['title' => 'required|max:255', 'publish_date' => 'nullable|date', 'account_id' => 'nullable|exists:accounts,id', 'pillar_id' => 'required|exists:pillars,id', 'series_id' => ['required', Rule::exists('series', 'id')->where(fn ($q) => $q->where('pillar_id', $r->pillar_id))], 'format_id' => 'nullable|exists:formats,id', 'pic_user_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)], 'status' => ['required', Rule::enum(ContentStatus::class)], 'final_url' => 'nullable|url', 'platform_ids' => 'array', 'platform_ids.*' => 'exists:platforms,id']);
        unset($d['platform_ids']);

        return $d;
    }

    private function creativeData(Request $r, bool $idea = false): array
    {
        $prefix = $idea ? 'idea_' : '';
        $d = $r->validate([$prefix.'hook' => 'nullable|string|max:5000', $prefix.'angle' => 'nullable|string|max:5000', $prefix.'key_message' => 'nullable|string|max:5000', $prefix.'cta' => 'nullable|string|max:5000', $prefix.'notes' => 'nullable|string|max:5000', $prefix.'script_copy' => 'nullable|string|max:20000', 'assets' => 'nullable|array', 'assets.*.name' => 'required_with:assets.*.url|max:255', 'assets.*.type' => ['required_with:assets.*.url', Rule::in(['Figma', 'Google Drive', 'Image', 'Video', 'Raw Footage', 'Reference', 'Thumbnail', 'Other'])], 'assets.*.url' => 'nullable|url', 'assets.*.notes' => 'nullable|string|max:5000']);
        $brief = ['hook' => $d[$prefix.'hook'] ?? null, 'angle' => $d[$prefix.'angle'] ?? null, 'key_message' => $d[$prefix.'key_message'] ?? null, 'cta' => $d[$prefix.'cta'] ?? null, 'notes' => $d[$prefix.'notes'] ?? null];
        if ($idea) {
            $brief['script_copy'] = strip_tags(html_entity_decode($d[$prefix.'script_copy'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'), '<b><strong><i><em><u><br><p>');
        } else {
            $brief['main_copy'] = $d[$prefix.'script_copy'] ?? null;
        }

return ['brief' => $brief, 'assets' => array_values(array_filter($d['assets'] ?? [], fn ($a) => filled($a['url'] ?? null)))];
    }

    public function ideas(Request $r)
    {
        $u = $r->user();
        abort_unless($u->can('ideas.view_all') || $u->can('ideas.view_own'), 403);
        $r->validate(['status' => ['nullable', Rule::enum(IdeaStatus::class)], 'submitted_by' => 'nullable|integer|exists:users,id']);
        $q = Idea::with(['pillar', 'series', 'format', 'brief', 'assets', 'submitter', 'department']);
        if (! $u->can('ideas.view_all')) {
            $q->where('submitted_by', $u->id);
        }if ($r->filled('status')) {
            $q->where('status', $r->input('status'));
        }if ($r->filled('submitted_by')) {
            $q->where('submitted_by', $r->integer('submitted_by'));
        }$submitterIds = (clone $q)->select('submitted_by')->distinct()->pluck('submitted_by');

        return view('ideas.index', $this->refs() + ['ideas' => $q->latest()->paginate(15)->withQueryString(), 'ideaSubmitters' => User::withTrashed()->whereIn('id', $submitterIds)->orderBy('name')->get()]);
    }

    public function storeIdea(Request $r)
    {
        abort_unless($r->user()->can('ideas.create'), 403);
        $d = $r->validate(['idea' => 'required|max:5000', 'pillar_id' => 'required|exists:pillars,id', 'series_id' => ['required', Rule::exists('series', 'id')->where(fn ($q) => $q->where('pillar_id', $r->pillar_id))], 'format_id' => 'nullable|exists:formats,id']);
        $d['idea'] = strip_tags(html_entity_decode($d['idea'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), '<b><strong><i><em><u><br><p>');
        $extra = $this->creativeData($r, true);
        $idea = DB::transaction(function () use ($d, $extra, $r) {
            $idea = Idea::create($d + ['submitted_by' => $r->user()->id, 'source_department_id' => $r->user()->department_id, 'status' => 'new']);
            IdeaBrief::create(['idea_id' => $idea->id] + $extra['brief']);
            foreach ($extra['assets'] as $a) {
                IdeaAsset::create(['idea_id' => $idea->id, 'name' => $a['name'], 'type' => $a['type'], 'url' => $a['url'], 'notes' => $a['notes'] ?? null]);
            }

return $idea;
        });
        activity()->causedBy($r->user())->performedOn($idea)->log('submitted an idea');

        return back()->with('success', 'Idea berhasil dikirim.');
    }

    public function ideaStatus(Request $r, Idea $idea)
    {
        abort_unless($r->user()->can('ideas.change_status') || $r->user()->can('ideas.select'), 403);
        abort_if($idea->status === IdeaStatus::Converted, 422, 'Converted Idea status is controlled by conversion.');
        $d = $r->validate(['status' => ['required', Rule::in([IdeaStatus::New->value, IdeaStatus::Consider->value, IdeaStatus::Archived->value])]]);
        $old = $idea->status;
        $idea->update($d);
        activity()->causedBy($r->user())->performedOn($idea)->withProperties(['old' => $old->value, 'new' => $d['status']])->log($r->user()->name.' changed Idea status from '.$old->label().' to '.IdeaStatus::from($d['status'])->label().'.');

        return back()->with('success', 'Status idea diperbarui.');
    }

    public function moveIdea(Request $r, Idea $idea)
    {
        abort_unless($r->user()->can('ideas.move_to_content'), 403);
        abort_unless($idea->status === IdeaStatus::Selected, 422, 'Idea harus Selected.');
        $r->merge(['status' => 'planned', 'format_id' => $r->input('format_id', $idea->format_id)]);
        $data = $this->contentData($r);
        $data['title'] = strip_tags(html_entity_decode($idea->idea, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        DB::transaction(function () use ($idea, $data, $r) {
            $idea->load(['brief', 'assets']);
            $c = Content::create($data + ['source_idea_id' => $idea->id, 'created_by' => $r->user()->id, 'updated_by' => $r->user()->id]);
            $c->platforms()->sync($r->input('platform_ids', []));
            ContentBrief::create(['content_id' => $c->id] + ($idea->brief?->only(['hook', 'angle', 'key_message', 'cta', 'notes']) ?? []) + ['main_copy' => $idea->brief?->script_copy]);
            foreach ($idea->assets as $a) {
                Asset::create(['content_id' => $c->id, 'title' => $a->name, 'asset_type' => $a->type, 'url' => $a->url, 'notes' => $a->notes, 'category' => 'Idea Reference', 'added_by' => $r->user()->id]);
            }$idea->update(['status' => 'converted']);
            activity()->causedBy($r->user())->performedOn($c)->log('converted idea to content');
        });

        return redirect()->route('content.index')->with('success', 'Idea dikonversi ke Content Plan.');
    }

    public function calendar(Request $r)
    {
        abort_unless($r->user()->can('calendar.view'), 403);
        $colors = ['Product' => '#3b82f6', 'News' => '#ec4899', 'Insight / Education' => '#f59e0b', 'Entertainment' => '#f97316', 'Community' => '#8b5cf6', 'Brand' => '#06b6d4'];
        $calendarEvents = Content::with(['platforms', 'pillar'])->whereNotNull('publish_date')->get()->map(fn (Content $content) => ['id' => $content->id, 'title' => $content->title, 'start' => $content->publish_date->format('Y-m-d'), 'url' => route('content.show', $content), 'backgroundColor' => $content->status === ContentStatus::Published ? '#16a34a' : ($colors[$content->pillar->name] ?? '#64748b'), 'classNames' => ['status-'.$content->status->value], 'extendedProps' => ['platforms' => $content->platforms->pluck('name')->values()->all(), 'status' => $content->status->value]])->values();

        return view('calendar', compact('calendarEvents'));
    }

    public function updateDate(Request $r, Content $content)
    {
        abort_unless($r->user()->can('calendar.edit'), 403);
        $content->update(['publish_date' => $r->validate(['publish_date' => 'required|date'])['publish_date'], 'updated_by' => $r->user()->id]);

        return response()->json(['ok' => true]);
    }

    public function production(Request $r)
    {
        abort_unless($r->user()->can('production.view'), 403);
        $filters = $r->validate([
            'pic_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'period' => ['nullable', Rule::in(['all', 'this_week', 'last_week', 'next_week', 'this_month', 'specific', 'range', 'unscheduled'])],
            'specific_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'platform_id' => ['nullable', 'integer', 'exists:platforms,id'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'pillar_id' => ['nullable', 'integer', 'exists:pillars,id'],
            'series_id' => ['nullable', 'integer', 'exists:series,id'],
            'format_id' => ['nullable', 'integer', 'exists:formats,id'],
        ]);

        $canViewAllTasks = $r->user()->can('production.view_all_tasks');
        $filters['pic_user_id'] = $canViewAllTasks ? ($filters['pic_user_id'] ?? null) : $r->user()->id;
        $filters['period'] = $filters['period'] ?? ($canViewAllTasks ? 'all' : 'this_week');

        $query = Content::with(['account', 'series', 'pillar', 'format', 'platforms', 'pic'])
            ->withCount('comments')
            ->withCount(['comments as open_comments_count' => fn ($commentQuery) => $commentQuery->where('status', 'open')]);

        if ($filters['pic_user_id']) {
            $query->where('pic_user_id', $filters['pic_user_id']);
        }
        foreach (['pillar_id', 'series_id', 'format_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['platform_id'])) {
            $query->whereHas('platforms', fn ($platformQuery) => $platformQuery->where('platforms.id', $filters['platform_id']));
        }
        if (! empty($filters['account_id'])) {
            $query->where(fn ($accountQuery) => $accountQuery
                ->where('account_id', $filters['account_id'])
                ->orWhereHas('platforms', fn ($platformQuery) => $platformQuery->where('content_platform.account_id', $filters['account_id'])));
        }

        $this->applyProductionPeriod($query, $filters);
        $items = $query->orderByRaw('publish_date is null')->orderBy('publish_date')->get();

        return view('production', $this->refs() + [
            'groups' => $items->groupBy(fn ($content) => $content->status->value),
            'filters' => $filters,
            'canViewAllTasks' => $canViewAllTasks,
            'isEmpty' => $items->isEmpty(),
        ]);
    }

    private function applyProductionPeriod($query, array $filters): void
    {
        $today = today();
        match ($filters['period']) {
            'this_week' => $query->whereBetween('publish_date', [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()]),
            'last_week' => $query->whereBetween('publish_date', [$today->copy()->subWeek()->startOfWeek(), $today->copy()->subWeek()->endOfWeek()]),
            'next_week' => $query->whereBetween('publish_date', [$today->copy()->addWeek()->startOfWeek(), $today->copy()->addWeek()->endOfWeek()]),
            'this_month' => $query->whereYear('publish_date', $today->year)->whereMonth('publish_date', $today->month),
            'specific' => ! empty($filters['specific_date']) ? $query->whereDate('publish_date', $filters['specific_date']) : null,
            'range' => ! empty($filters['start_date']) && ! empty($filters['end_date'])
                ? $query->whereDate('publish_date', '>=', $filters['start_date'])->whereDate('publish_date', '<=', $filters['end_date']) : null,
            'unscheduled' => $query->whereNull('publish_date'),
            default => null,
        };
    }

    public function updateStatus(Request $r, Content $content, UpdateContentStatus $updateStatus)
    {
        $permission = $r->routeIs('production.update') ? 'production.change_status' : 'content.change_status';
        abort_unless($r->user()->can($permission), 403);
        $d = $r->validate(['status' => ['required', Rule::enum(ContentStatus::class)], 'cancelled_publishing' => ['sometimes', 'boolean']]);
        $target = ContentStatus::from($d['status']);
        $isPublishingCancellation = ($d['cancelled_publishing'] ?? false)
            && $content->status === ContentStatus::Published
            && $target !== ContentStatus::Published;
        $activityMessage = $isPublishingCancellation
            ? $r->user()->name.' cancelled publishing and restored Content to '.$target->label().'.'
            : null;
        $updated = $updateStatus->execute($content, $target, $r->user(), $activityMessage);

        return response()->json([
            'ok' => true,
            'message' => 'Status updated successfully',
            'status' => $updated->status->value,
            'label' => $updated->status->label(),
            'published_information_url' => route('content.published-information', $updated),
            'final_url' => $updated->final_url,
            'is_not_for_public' => $updated->is_not_for_public,
            'has_published_information' => filled($updated->final_url) || $updated->is_not_for_public,
        ]);
    }

    public function published(Request $r)
    {
        abort_unless($r->user()->can('published.view'), 403);

        return view('published', ['contents' => Content::with(['account', 'platforms', 'pillar', 'series', 'pic'])->where('status', 'published')->latest('publish_date')->paginate(12)]);
    }

    public function myTasks(Request $r)
    {
        $items = Content::with(['platforms', 'pic'])->where('pic_user_id', $r->user()->id)->where('status', '!=', 'published')->orderBy('publish_date')->get();

        return view('my-tasks', ['groups' => ['Overdue' => $items->filter(fn ($x) => $x->publish_date?->isPast() && ! $x->publish_date->isToday()), 'Today' => $items->filter(fn ($x) => $x->publish_date?->isToday()), 'This Week' => $items->filter(fn ($x) => $x->publish_date?->between(today()->addDay(), today()->endOfWeek())), 'Upcoming' => $items->filter(fn ($x) => $x->publish_date?->gt(today()->endOfWeek()))]]);
    }

    public function team(Request $r)
    {
        abort_unless($r->user()->can('team.view'), 403);

        return view('team', ['users' => User::role(['Creative Lead', 'Creative Member'])->withCount(['assignedContents as assigned_count', 'assignedContents as progress_count' => fn ($q) => $q->where('status', 'in_production'), 'assignedContents as review_count' => fn ($q) => $q->where('status', 'review'), 'assignedContents as scheduled_count' => fn ($q) => $q->where('status', 'scheduled')])->get()]);
    }

    public function assets(Request $r)
    {
        abort_unless($r->user()->can('assets.view'), 403);

        return view('assets', ['assets' => Asset::with('addedBy')->latest()->paginate(15)]);
    }

    public function storeAsset(Request $r)
    {
        abort_unless($r->user()->can('assets.create'), 403);
        Asset::create($r->validate(['title' => 'required', 'category' => 'required', 'asset_type' => 'nullable', 'url' => 'required|url']) + ['added_by' => $r->user()->id]);

        return back()->with('success', 'Asset ditambahkan.');
    }

    public function users(Request $r)
    {
        abort_unless($r->user()->can('users.view'), 403);

        return view('admin.users', ['users' => User::withTrashed()->with(['department', 'roles'])->paginate(20), 'departments' => Department::all(), 'roles' => Role::all()]);
    }

    public function storeUser(Request $r)
    {
        abort_unless($r->user()->can('users.create'), 403);
        $d = $r->validate(['name' => 'required', 'email' => 'required|email|unique:users', 'password' => 'required|min:8', 'department_id' => 'required|exists:departments,id', 'role' => 'required|exists:roles,name']);
        $u = User::create($d);
        $u->syncRoles($d['role']);

        return back()->with('success', 'User dibuat.');
    }

    public function toggleUser(Request $r, User $user)
    {
        abort_unless($r->user()->can('users.change_status') || $r->user()->can('users.deactivate'), 403);
        abort_if($user->id === $r->user()->id || $user->hasRole('Super Admin'),422,'Super Admin cannot be deactivated.');
        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success','Status user diperbarui.');
    }

    public function roles(Request $r)
    {
        abort_unless($r->user()->can('roles.view'),403);

        return view('admin.roles',['roles' => Role::with('permissions')->get()]);
    }
}
