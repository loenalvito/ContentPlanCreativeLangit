<?php

namespace Tests\Feature;

use App\Models\{Account, Content, Format, Idea, Pillar, Platform, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LatestRevisionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $lead;
    private User $sales;
    private Pillar $pillar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::whereEmail('admin@kolabo.id')->firstOrFail();
        $this->lead = User::whereEmail('lead@kolabo.id')->firstOrFail();
        $this->sales = User::whereEmail('sales@kolabo.id')->firstOrFail();
        $this->pillar = Pillar::whereName('Product')->firstOrFail();
    }

    private function content(string $title, string $date, array $attributes = []): Content
    {
        return Content::create(array_merge([
            'title' => $title,
            'publish_date' => $date,
            'pillar_id' => $this->pillar->id,
            'series_id' => $this->pillar->series()->firstOrFail()->id,
            'format_id' => Format::first()->id,
            'pic_user_id' => $this->lead->id,
            'status' => 'planned',
            'created_by' => $this->lead->id,
            'updated_by' => $this->lead->id,
        ], $attributes));
    }

    public function test_content_plan_supports_exact_month_year_and_inclusive_range_filters(): void
    {
        foreach ([
            ['Filter Aug 01', '2026-08-01'], ['Filter Aug 10', '2026-08-10'],
            ['Filter Aug 20', '2026-08-20'], ['Filter Sep 10', '2026-09-10'],
            ['Filter Old', '2025-08-10'],
        ] as [$title, $date]) {
            $this->content($title, $date);
        }

        $this->actingAs($this->admin)->get('/content?search=Filter&date_mode=specific&specific_date=2026-08-10')
            ->assertOk()->assertSee('Filter Aug 10')->assertDontSee('Filter Aug 01')->assertDontSee('Filter Old');
        $this->get('/content?search=Filter&date_mode=month&month=2026-08')
            ->assertOk()->assertSee('Filter Aug 01')->assertSee('Filter Aug 10')->assertSee('Filter Aug 20')->assertDontSee('Filter Sep 10')->assertDontSee('Filter Old');
        $this->get('/content?search=Filter&date_mode=year&year=2026')
            ->assertOk()->assertSee('Filter Aug 01')->assertSee('Filter Sep 10')->assertDontSee('Filter Old');
        $this->get('/content?search=Filter&date_mode=range&start_date=2026-08-01&end_date=2026-08-15')
            ->assertOk()->assertSee('Filter Aug 01')->assertSee('Filter Aug 10')->assertDontSee('Filter Aug 20');
    }

    public function test_idea_status_actions_and_combined_filters_are_database_driven(): void
    {
        $other = $this->admin;
        $ideaA = Idea::create(['idea' => 'Consider Sales', 'pillar_id' => $this->pillar->id, 'series_id' => $this->pillar->series()->first()->id, 'submitted_by' => $this->sales->id, 'source_department_id' => $this->sales->department_id, 'status' => 'new']);
        Idea::create(['idea' => 'Consider Admin', 'pillar_id' => $this->pillar->id, 'series_id' => $this->pillar->series()->first()->id, 'submitted_by' => $other->id, 'source_department_id' => $other->department_id, 'status' => 'consider']);

        $this->actingAs($this->lead)->patch(route('ideas.status', $ideaA), ['status' => 'consider'])->assertRedirect();
        $this->assertDatabaseHas('ideas', ['id' => $ideaA->id, 'status' => 'consider']);
        $this->get('/ideas?status=consider&submitted_by='.$this->sales->id)->assertOk()->assertSee('Consider Sales')->assertDontSee('Consider Admin');
        $this->patch(route('ideas.status', $ideaA), ['status' => 'archived'])->assertRedirect();
        $this->assertDatabaseHas('ideas', ['id' => $ideaA->id, 'status' => 'archived']);
        $this->patch(route('ideas.status', $ideaA), ['status' => 'converted'])->assertSessionHasErrors('status');
    }

    public function test_publish_link_and_not_for_public_are_persistent_and_library_driven(): void
    {
        $content = $this->content('Publish Me', '2026-08-20', ['status' => 'approved']);
        $this->actingAs($this->lead)->post(route('content.publish', $content))->assertRedirect()->assertSessionHas('open_published_modal');
        $content->refresh();
        $this->assertSame('published', $content->status->value);
        $this->assertNotNull($content->published_at);
        $this->get('/published')->assertOk()->assertSee('Publish Me');

        $this->put(route('content.published-information', $content), ['visibility' => 'public', 'final_url' => 'https://instagram.com/test-post'])->assertRedirect();
        $this->assertDatabaseHas('contents', ['id' => $content->id, 'final_url' => 'https://instagram.com/test-post', 'is_not_for_public' => false, 'status' => 'published']);
        $this->get(route('content.show', $content))->assertOk()->assertSee('https://instagram.com/test-post');

        $this->put(route('content.published-information', $content), ['visibility' => 'not_for_public'])->assertRedirect();
        $this->assertDatabaseHas('contents', ['id' => $content->id, 'final_url' => null, 'is_not_for_public' => true, 'status' => 'published']);
        $this->get('/published')->assertSee('Not for Public');
    }

    public function test_creator_source_submitter_and_pic_can_edit_but_other_viewer_gets_403(): void
    {
        $platform = Platform::whereName('Instagram')->firstOrFail();
        $account = Account::where('platform_id', $platform->id)->firstOrFail();
        $idea = Idea::create(['idea' => 'Origin', 'pillar_id' => $this->pillar->id, 'series_id' => $this->pillar->series()->first()->id, 'submitted_by' => $this->sales->id, 'source_department_id' => $this->sales->department_id, 'status' => 'converted']);
        $content = $this->content('Editable Origin', '2026-08-20', ['source_idea_id' => $idea->id, 'created_by' => $this->admin->id, 'pic_user_id' => $this->lead->id]);
        $content->platforms()->sync([$platform->id => ['account_id' => $account->id]]);
        $payload = ['title' => 'Edited Safely', 'publish_date' => '2026-08-21', 'pillar_id' => $this->pillar->id, 'series_id' => $this->pillar->series()->first()->id, 'format_id' => Format::first()->id, 'pic_user_id' => $this->lead->id, 'platform_ids' => [$platform->id], 'platform_accounts' => [$platform->id => $account->id]];

        $this->actingAs($this->sales)->get(route('content.show', $content))->assertOk()->assertSee('Edit');
        $this->put(route('content.update', $content), $payload)->assertRedirect();
        $this->assertDatabaseHas('contents', ['id' => $content->id, 'title' => 'Edited Safely', 'created_by' => $this->admin->id, 'source_idea_id' => $idea->id]);
        $this->actingAs($this->lead)->put(route('content.update', $content), array_merge($payload, ['title' => 'PIC Edit']))->assertRedirect();

        $viewer = User::whereEmail('viewer@kolabo.id')->first();
        if (! $viewer) {
            $viewer = User::whereEmail('fadly@kolabo.id')->firstOrFail();
            $viewer->syncRoles('Viewer');
        }
        $this->actingAs($viewer)->get(route('content.show', $content))->assertOk()->assertDontSee('data-testid="edit-content"', false);
        $this->put(route('content.update', $content), $payload)->assertForbidden();
    }

    public function test_edit_rejects_platform_account_and_pillar_series_mismatches(): void
    {
        $content = $this->content('Validation Target', '2026-08-20');
        $instagram = Platform::whereName('Instagram')->firstOrFail();
        $wrongAccount = Account::where('platform_id', '!=', $instagram->id)->firstOrFail();
        $otherPillar = Pillar::whereName('Entertainment')->firstOrFail();
        $base = ['title' => 'Validation Target', 'publish_date' => '2026-08-20', 'pillar_id' => $this->pillar->id, 'series_id' => $this->pillar->series()->first()->id, 'format_id' => Format::first()->id, 'pic_user_id' => $this->lead->id, 'platform_ids' => [$instagram->id], 'platform_accounts' => [$instagram->id => $wrongAccount->id]];
        $this->actingAs($this->lead)->put(route('content.update', $content), $base)->assertStatus(422);
        $validAccount = Account::where('platform_id', $instagram->id)->firstOrFail();
        $this->put(route('content.update', $content), array_merge($base, ['series_id' => $otherPillar->series()->first()->id, 'platform_accounts' => [$instagram->id => $validAccount->id]]))->assertSessionHasErrors('series_id');
    }
}
