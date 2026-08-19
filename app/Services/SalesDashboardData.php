<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\{Content, Idea, User};
use Illuminate\Support\Collection;

class SalesDashboardData
{
    private const ACTIVE = ['planned', 'in_production', 'review', 'approved', 'scheduled'];

    public function for(User $user): array
    {
        $start = now()->startOfWeek();
        $end = now()->endOfWeek();

        return [
            'creativeWorkloads' => $this->creativeWorkloads($start, $end),
            'pendingRequests' => $this->pendingRequests(),
            'contentQueue' => $this->contentQueue(),
            'myRequests' => $this->myRequests($user),
            'start' => $start,
            'end' => $end,
        ];
    }

    private function creativeWorkloads($start, $end): Collection
    {
        $counts = ['assignedContents as week_total' => fn ($query) => $query->whereBetween('publish_date', [$start, $end])->whereIn('status', self::ACTIVE)];
        foreach (self::ACTIVE as $status) {
            $counts["assignedContents as week_{$status}"] = fn ($query) => $query->whereBetween('publish_date', [$start, $end])->where('status', $status);
        }

        return User::role(['Creative Lead', 'Creative Member'])->where('is_active', true)->withCount($counts)->get();
    }

    private function pendingRequests(): Collection
    {
        return Idea::query()
            ->where('is_content_request', true)
            ->whereIn('status', ['new', 'consider'])
            ->whereDoesntHave('content')
            ->with(['submitter.department', 'department', 'platforms'])
            ->oldest()
            ->get()
            ->map(fn (Idea $idea) => $this->pendingItem($idea));
    }

    private function contentQueue(): Collection
    {
        return Content::query()
            ->whereIn('status', self::ACTIVE)
            ->whereHas('sourceIdea', fn ($query) => $query->where('is_content_request', true))
            ->with(['sourceIdea.submitter.department', 'sourceIdea.department', 'pic', 'platforms.accounts', 'account'])
            ->get()
            ->sortBy(fn (Content $content) => $this->sortKey($content))
            ->values()
            ->map(fn (Content $content, int $index) => $this->queueItem($content, $index + 1));
    }

    private function myRequests(User $user): Collection
    {
        $ideas = Idea::query()
            ->where('is_content_request', true)
            ->where('submitted_by', $user->id)
            ->whereDoesntHave('content')
            ->with(['submitter.department', 'department', 'platforms'])
            ->get()
            ->map(fn (Idea $idea) => $this->pendingItem($idea) + ['type' => 'idea']);

        $contents = Content::query()
            ->whereHas('sourceIdea', fn ($query) => $query->where('is_content_request', true)->where('submitted_by', $user->id))
            ->with(['sourceIdea.submitter.department', 'sourceIdea.department', 'pic', 'platforms.accounts', 'account'])
            ->get()
            ->map(fn (Content $content) => $this->queueItem($content) + ['type' => 'content']);

        return $ideas->concat($contents)->sortByDesc('created_at')->values();
    }

    private function sortKey(Content $content): string
    {
        $idea = $content->sourceIdea;
        $deadline = $idea->needed_at ?? $content->publish_date;

        return sprintf('%d|%d|%020d|%020d|%020d',
            $idea->is_urgent ? 0 : 1,
            $deadline ? 0 : 1,
            $deadline?->getTimestamp() ?? PHP_INT_MAX,
            $idea->created_at->getTimestamp(),
            $content->id,
        );
    }

    private function pendingItem(Idea $idea): array
    {
        return [
            'id' => $idea->id,
            'title' => $idea->idea,
            'source_idea_id' => $idea->id,
            'requester_id' => $idea->submitted_by,
            'requester_name' => $idea->submitter?->name,
            'requester_department' => $idea->department?->name ?? $idea->submitter?->department?->name,
            'status' => $idea->status->value,
            'display_status' => $idea->status->label(),
            'is_urgent' => $idea->is_urgent,
            'needed_at' => $idea->needed_at,
            'urgent_purpose' => $idea->urgent_purpose,
            'platforms' => $idea->platforms->map(fn ($platform) => ['id' => $platform->id, 'name' => $platform->name])->values(),
            'created_at' => $idea->created_at,
        ];
    }

    private function queueItem(Content $content, ?int $position = null): array
    {
        $idea = $content->sourceIdea;
        $effectiveDeadline = $idea->needed_at ?? $content->publish_date;
        $accounts = $content->platforms->map(function ($platform) {
            $account = $platform->accounts->firstWhere('id', $platform->pivot->account_id);
            return $account ? ['id' => $account->id, 'name' => $account->account_name ?? $account->name, 'username' => $account->username] : null;
        })->filter()->values();

        return [
            'id' => $content->id,
            'title' => $content->title,
            'queue_position' => $position,
            'source_idea_id' => $idea->id,
            'requester_id' => $idea->submitted_by,
            'requester_name' => $idea->submitter?->name,
            'requester_department' => $idea->department?->name ?? $idea->submitter?->department?->name,
            'pic_id' => $content->pic_user_id,
            'pic_name' => $content->pic?->name,
            'pic_avatar' => $content->pic ? strtoupper(substr($content->pic->name, 0, 1)) : null,
            'status' => $content->status->value,
            'display_status' => $this->displayStatus($content),
            'is_urgent' => $idea->is_urgent,
            'needed_at' => $idea->needed_at,
            'urgent_purpose' => $idea->urgent_purpose,
            'publish_date' => $content->publish_date,
            'effective_deadline' => $effectiveDeadline,
            'platforms' => $content->platforms->map(fn ($platform) => ['id' => $platform->id, 'name' => $platform->name])->values(),
            'accounts' => $accounts,
            'created_at' => $idea->created_at,
        ];
    }

    private function displayStatus(Content $content): string
    {
        return match ($content->status) {
            ContentStatus::Planned => $content->pic_user_id ? 'Queued' : 'Waiting for PIC',
            ContentStatus::InProduction => 'Working',
            ContentStatus::Review => 'Reviewing',
            ContentStatus::Approved => 'Approved',
            ContentStatus::Scheduled => 'Scheduled',
            ContentStatus::Published => 'Done',
        };
    }
}
