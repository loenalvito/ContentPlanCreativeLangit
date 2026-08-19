<?php

namespace Tests\Feature;

use App\Models\{Content, Format, Idea, Pillar, User};
use App\Services\SalesDashboardData;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesContentQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $sales;
    private User $lead;
    private Pillar $pillar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->sales = User::whereEmail('sales@kolabo.id')->firstOrFail();
        $this->lead = User::whereEmail('lead@kolabo.id')->firstOrFail();
        $this->pillar = Pillar::whereName('Product')->firstOrFail();
    }

    private function request(string $title, array $attributes = [], ?User $owner = null): Idea
    {
        return Idea::create(array_merge([
            'idea' => $title,
            'pillar_id' => $this->pillar->id,
            'series_id' => $this->pillar->series()->firstOrFail()->id,
            'format_id' => Format::first()->id,
            'submitted_by' => ($owner ?? $this->sales)->id,
            'source_department_id' => ($owner ?? $this->sales)->department_id,
            'status' => 'new',
            'is_content_request' => true,
            'is_urgent' => false,
        ], $attributes));
    }

    private function content(Idea $idea, array $attributes = []): Content
    {
        $content = Content::create(array_merge([
            'title' => $idea->idea,
            'publish_date' => null,
            'pillar_id' => $idea->pillar_id,
            'series_id' => $idea->series_id,
            'format_id' => $idea->format_id,
            'pic_user_id' => $this->lead->id,
            'status' => 'planned',
            'source_idea_id' => $idea->id,
            'created_by' => $this->lead->id,
            'updated_by' => $this->lead->id,
        ], $attributes));
        $idea->update(['status' => 'converted']);
        return $content;
    }

    private function data(?User $user = null): array
    {
        return app(SalesDashboardData::class)->for($user ?? $this->sales);
    }

    public function test_queue_orders_by_urgent_deadline_then_request_age(): void
    {
        $a = $this->request('A', ['needed_at' => '2026-08-25 10:00']);
        $b = $this->request('B', ['is_urgent' => true, 'needed_at' => '2026-08-26 10:00']);
        $c = $this->request('C', ['is_urgent' => true, 'needed_at' => '2026-08-20 10:00']);
        $this->content($a); $this->content($b); $this->content($c);

        $queue = $this->data()['contentQueue'];
        $this->assertSame(['C', 'B', 'A'], $queue->pluck('title')->all());
        $this->assertSame([1, 2, 3], $queue->pluck('queue_position')->all());
    }

    public function test_needed_at_precedes_publish_date_and_created_time_breaks_ties(): void
    {
        $older = $this->request('Older', ['created_at' => '2026-08-01 10:00', 'updated_at' => '2026-08-01 10:00']);
        $newer = $this->request('Newer', ['created_at' => '2026-08-02 10:00', 'updated_at' => '2026-08-02 10:00']);
        $fallback = $this->request('Publish fallback');
        $needed = $this->request('Needed first', ['needed_at' => '2026-08-19 10:00']);
        $this->content($older, ['publish_date' => '2026-08-21']);
        $this->content($newer, ['publish_date' => '2026-08-21']);
        $this->content($fallback, ['publish_date' => '2026-08-20']);
        $this->content($needed, ['publish_date' => '2026-08-25']);

        $this->assertSame(['Needed first', 'Publish fallback', 'Older', 'Newer'], $this->data()['contentQueue']->pluck('title')->all());
    }

    public function test_published_leaves_queue_and_flexible_rollback_reenters(): void
    {
        $content = $this->content($this->request('Lifecycle'), ['status' => 'scheduled']);
        $this->assertTrue($this->data()['contentQueue']->contains('id', $content->id));
        $content->update(['status' => 'published']);
        $this->assertFalse($this->data()['contentQueue']->contains('id', $content->id));
        $content->update(['status' => 'in_production']);
        $this->assertTrue($this->data()['contentQueue']->contains('id', $content->id));
    }

    public function test_pending_rules_my_requests_and_no_pending_queue_duplicate(): void
    {
        $new = $this->request('New request');
        $consider = $this->request('Consider request', ['status' => 'consider']);
        $archived = $this->request('Archived request', ['status' => 'archived']);
        $converted = $this->request('Converted request');
        $this->content($converted);
        $other = User::whereEmail('admin@kolabo.id')->firstOrFail();
        $this->request('Other request', [], $other);

        $data = $this->data();
        $this->assertEqualsCanonicalizing([$new->id, $consider->id], $data['pendingRequests']->whereIn('source_idea_id', [$new->id, $consider->id, $archived->id, $converted->id])->pluck('source_idea_id')->all());
        $this->assertFalse($data['pendingRequests']->contains('source_idea_id', $converted->id));
        $this->assertTrue($data['contentQueue']->contains('source_idea_id', $converted->id));
        $this->assertFalse($data['myRequests']->contains('title', 'Other request'));
        $this->assertSame($data['myRequests']->count(), $data['myRequests']->pluck('source_idea_id')->unique()->count());
    }

    public function test_sales_dashboard_requires_permission_and_exposes_contract(): void
    {
        $this->actingAs(User::whereEmail('fadly@kolabo.id')->firstOrFail())->get('/sales-dashboard')->assertForbidden();
        $this->actingAs($this->sales)->get('/sales-dashboard')->assertOk()
            ->assertViewHasAll(['creativeWorkloads', 'pendingRequests', 'contentQueue', 'myRequests', 'members']);
    }
}
