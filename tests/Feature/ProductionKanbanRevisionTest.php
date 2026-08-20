<?php

namespace Tests\Feature;

use App\Models\{Account, Content, Department, Format, Pillar, Platform, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductionKanbanRevisionTest extends TestCase
{
    use RefreshDatabase;

    private User $lead;
    private User $fadly;
    private User $nabila;
    private Pillar $pillar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->lead = User::whereEmail('lead@kolabo.id')->firstOrFail();
        $this->fadly = User::whereEmail('fadly@kolabo.id')->firstOrFail();
        $this->nabila = User::whereEmail('nabila@kolabo.id')->firstOrFail();
        $this->pillar = Pillar::whereName('Product')->firstOrFail();
    }

    private function content(string $title, User $pic, $date, array $extra = []): Content
    {
        return Content::create(array_merge([
            'title' => $title,
            'publish_date' => $date,
            'pillar_id' => $this->pillar->id,
            'series_id' => $this->pillar->series()->firstOrFail()->id,
            'format_id' => Format::whereName('Reels')->value('id'),
            'pic_user_id' => $pic->id,
            'status' => 'approved',
            'created_by' => $this->lead->id,
            'updated_by' => $this->lead->id,
        ], $extra));
    }

    public function test_normal_user_defaults_to_own_tasks_this_week_and_cannot_bypass_pic_scope(): void
    {
        $a = $this->content('Personal This Week', $this->fadly, today());
        $b = $this->content('Personal Next Week', $this->fadly, today()->addWeek());
        $c = $this->content('Other This Week', $this->nabila, today());
        $this->content('Undated Personal', $this->fadly, null);

        $this->actingAs($this->fadly)->get('/production')->assertOk()
            ->assertSee($a->title)->assertDontSee($b->title)->assertDontSee($c->title)
            ->assertDontSee('Undated Personal')->assertSee('My Tasks · This Week');

        $this->get('/production?period=next_week')->assertOk()
            ->assertSee($b->title)->assertDontSee($a->title)->assertDontSee($c->title);

        $this->get('/production?pic_user_id='.$this->nabila->id.'&period=all')->assertOk()
            ->assertSee($a->title)->assertSee($b->title)->assertDontSee($c->title);
    }

    public function test_view_all_user_defaults_to_all_users_and_all_dates_then_can_filter(): void
    {
        $a = $this->content('All A', $this->fadly, today());
        $b = $this->content('All B', $this->fadly, today()->addWeek());
        $c = $this->content('All C', $this->nabila, today());
        $d = $this->content('Undated Global Item', $this->nabila, null);

        $this->assertTrue($this->lead->can('production.view_all_tasks'));
        $this->actingAs($this->lead)->get('/production')->assertOk()
            ->assertSee($a->title)->assertSee($b->title)->assertSee($c->title)->assertSee($d->title)
            ->assertSee('All Tasks · All Dates');

        $this->get('/production?pic_user_id='.$this->fadly->id.'&period=this_week')->assertOk()
            ->assertSee($a->title)->assertDontSee($b->title)->assertDontSee($c->title)->assertDontSee($d->title);
    }

    public function test_metadata_filters_and_combinations_are_applied_in_sql(): void
    {
        $instagram = Platform::whereName('Instagram')->firstOrFail();
        $account = Account::where('platform_id', $instagram->id)->firstOrFail();
        $match = $this->content('Combined Match', $this->nabila, today(), ['account_id' => $account->id]);
        $match->platforms()->sync([$instagram->id => ['account_id' => $account->id]]);
        $other = $this->content('Combined Other', $this->fadly, today());
        $other->platforms()->sync([Platform::whereName('TikTok')->value('id')]);

        $query = http_build_query([
            'pic_user_id' => $this->nabila->id,
            'period' => 'this_month',
            'platform_id' => $instagram->id,
            'account_id' => $account->id,
            'pillar_id' => $this->pillar->id,
            'series_id' => $this->pillar->series()->first()->id,
            'format_id' => Format::whereName('Reels')->value('id'),
        ]);
        $this->actingAs($this->lead)->get('/production?'.$query)->assertOk()
            ->assertSee($match->title)->assertDontSee($other->title);
    }

    public function test_view_all_permission_does_not_grant_status_content_or_published_mutations(): void
    {
        $role = Role::create(['name' => 'Kanban Observer', 'guard_name' => 'web', 'is_active' => true]);
        $role->syncPermissions(['production.view', 'production.view_all_tasks']);
        $observer = User::create([
            'name' => 'Kanban Observer', 'email' => 'observer@kolabo.test', 'password' => Hash::make('password'),
            'department_id' => Department::whereName('Creative')->value('id'), 'is_active' => true,
        ]);
        $observer->assignRole($role);
        $content = $this->content('Observer Visible', $this->nabila, today(), ['status' => 'published']);

        $this->actingAs($observer)->get('/production')->assertOk()->assertSee($content->title);
        $this->patchJson(route('production.update', $content), ['status' => 'review'])->assertForbidden();
        $this->putJson(route('content.update', $content), ['title' => 'Forbidden'])->assertForbidden();
        $this->putJson(route('content.published-information', $content), ['visibility' => 'not_for_public'])->assertForbidden();
    }

    public function test_published_information_is_optional_and_survives_flexible_rollback(): void
    {
        $content = $this->content('Published Lifecycle', $this->lead, today(), ['status' => 'review']);
        $this->actingAs($this->lead)->patchJson(route('production.update', $content), ['status' => 'published'])
            ->assertOk()->assertJson(['status' => 'published', 'has_published_information' => false]);
        $this->assertDatabaseHas('contents', ['id' => $content->id, 'status' => 'published', 'final_url' => null]);

        $this->putJson(route('content.published-information', $content), ['visibility' => 'public', 'final_url' => 'https://instagram.com/kanban-test'])->assertOk();
        $this->patchJson(route('production.update', $content), ['status' => 'planned'])->assertOk();
        $this->assertDatabaseHas('contents', ['id' => $content->id, 'status' => 'planned', 'final_url' => 'https://instagram.com/kanban-test']);
        $this->patchJson(route('production.update', $content), ['status' => 'published'])
            ->assertOk()->assertJson(['has_published_information' => true]);
    }

    public function test_cancelled_publishing_restores_every_actual_origin_and_persists(): void
    {
        $this->actingAs($this->lead);

        foreach (['planned', 'in_production', 'review', 'approved', 'scheduled'] as $origin) {
            $content = $this->content('Cancel Publish '.$origin, $this->lead, today(), ['status' => $origin]);
            $this->patchJson(route('production.update', $content), ['status' => 'published'])
                ->assertOk()->assertJson(['status' => 'published']);
            $this->patchJson(route('production.update', $content), [
                'status' => $origin,
                'cancelled_publishing' => true,
            ])->assertOk()->assertJson(['status' => $origin]);

            $this->assertDatabaseHas('contents', ['id' => $content->id, 'status' => $origin]);
            $this->assertDatabaseHas('activity_log', [
                'subject_id' => $content->id,
                'description' => $this->lead->name.' cancelled publishing and restored Content to '.\App\Enums\ContentStatus::from($origin)->label().'.',
            ]);
        }
    }

    public function test_cancelled_publishing_does_not_delete_historical_information(): void
    {
        $content = $this->content('Historical Publish Cancel', $this->lead, today(), [
            'status' => 'review',
            'final_url' => 'https://instagram.com/historical-link',
            'is_not_for_public' => false,
        ]);

        $this->actingAs($this->lead)->patchJson(route('production.update', $content), ['status' => 'published'])->assertOk();
        $this->patchJson(route('production.update', $content), ['status' => 'review', 'cancelled_publishing' => true])->assertOk();

        $this->assertDatabaseHas('contents', [
            'id' => $content->id,
            'status' => 'review',
            'final_url' => 'https://instagram.com/historical-link',
            'is_not_for_public' => false,
        ]);
    }

    public function test_unauthorized_user_cannot_send_publishing_rollback(): void
    {
        $content = $this->content('Forbidden Publish Cancel', $this->lead, today(), ['status' => 'published']);

        $this->actingAs(User::whereEmail('sales@kolabo.id')->firstOrFail())
            ->patchJson(route('production.update', $content), ['status' => 'review', 'cancelled_publishing' => true])
            ->assertForbidden();

        $this->assertDatabaseHas('contents', ['id' => $content->id, 'status' => 'published']);
    }

    public function test_roles_page_exposes_friendly_view_all_permission_label(): void
    {
        $this->actingAs(User::whereEmail('admin@kolabo.id')->firstOrFail())->get('/admin/roles')
            ->assertOk()->assertSee('View All Tasks in Kanban');
    }
}
