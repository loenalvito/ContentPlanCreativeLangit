<?php

namespace Tests\Feature;

use App\Models\{Content, ContentComment, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_new_comment_defaults_to_open_and_is_counted(): void
    {
        $lead = User::whereEmail('lead@kolabo.id')->firstOrFail();
        $content = Content::firstOrFail();

        $this->actingAs($lead)->post(route('content.comments', $content), [
            'body' => 'Needs a copy check.',
        ])->assertRedirect();

        $comment = ContentComment::where('content_id', $content->id)->firstOrFail();
        $this->assertSame('open', $comment->status);
        $this->assertNull($comment->resolved_by);
        $this->assertNull($comment->resolved_at);

        $counted = Content::withCount('comments')->withCount([
            'comments as open_comments_count' => fn ($query) => $query->where('status', 'open'),
        ])->findOrFail($content->id);
        $this->assertSame(1, $counted->comments_count);
        $this->assertSame(1, $counted->open_comments_count);
    }

    public function test_authorized_user_resolves_comment_without_deleting_history(): void
    {
        $lead = User::whereEmail('lead@kolabo.id')->firstOrFail();
        $content = Content::firstOrFail();
        $comment = $content->comments()->create([
            'user_id' => $lead->id,
            'body' => 'Keep this historical discussion.',
            'status' => 'open',
        ]);

        $this->actingAs($lead)
            ->patch(route('content.comments.resolve', [$content, $comment]))
            ->assertRedirect();

        $comment->refresh();
        $this->assertSame('resolved', $comment->status);
        $this->assertSame($lead->id, $comment->resolved_by);
        $this->assertNotNull($comment->resolved_at);
        $this->assertSame('Keep this historical discussion.', $comment->body);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Content::class,
            'subject_id' => $content->id,
            'causer_id' => $lead->id,
            'description' => 'resolved a comment',
        ]);

        $counted = Content::withCount('comments')->withCount([
            'comments as open_comments_count' => fn ($query) => $query->where('status', 'open'),
        ])->findOrFail($content->id);
        $this->assertSame(1, $counted->comments_count);
        $this->assertSame(0, $counted->open_comments_count);
    }

    public function test_user_without_permission_cannot_resolve_comment_directly(): void
    {
        $lead = User::whereEmail('lead@kolabo.id')->firstOrFail();
        $sales = User::whereEmail('sales@kolabo.id')->firstOrFail();
        $content = Content::firstOrFail();
        $comment = $content->comments()->create([
            'user_id' => $lead->id,
            'body' => 'Protected resolution.',
            'status' => 'open',
        ]);

        $this->actingAs($sales)
            ->patch(route('content.comments.resolve', [$content, $comment]))
            ->assertForbidden();

        $this->assertDatabaseHas('content_comments', [
            'id' => $comment->id,
            'status' => 'open',
            'resolved_by' => null,
        ]);
    }
}
