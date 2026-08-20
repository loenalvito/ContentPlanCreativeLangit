<?php

namespace App\Http\Controllers;

use App\Actions\UpdateContentStatus;
use App\Enums\ContentStatus;
use App\Models\Account;
use App\Models\Asset;
use App\Models\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ContentDetailController extends Controller
{
    public function update(Request $request, Content $content)
    {
        Gate::authorize('update', $content);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'publish_date' => ['nullable', 'date'],
            'pillar_id' => ['required', 'exists:pillars,id'],
            'series_id' => ['required', Rule::exists('series', 'id')->where(fn ($query) => $query->where('pillar_id', $request->integer('pillar_id')))],
            'format_id' => ['nullable', 'exists:formats,id'],
            'pic_user_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'platform_ids' => ['required', 'array', 'min:1'],
            'platform_ids.*' => ['distinct', 'exists:platforms,id'],
            'platform_accounts' => ['required', 'array'],
            'platform_accounts.*' => ['required', 'exists:accounts,id'],
            'hook' => ['nullable', 'string', 'max:5000'],
            'angle' => ['nullable', 'string', 'max:5000'],
            'key_message' => ['nullable', 'string', 'max:5000'],
            'cta' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'main_copy' => ['nullable', 'string', 'max:20000'],
            'reels_script' => ['nullable', 'string', 'max:20000'],
            'carousel_copy' => ['nullable', 'string', 'max:20000'],
            'caption' => ['nullable', 'string', 'max:20000'],
            'threads_copy' => ['nullable', 'string', 'max:20000'],
            'asset_name' => ['nullable', 'string', 'max:255', 'required_with:asset_url'],
            'asset_type' => ['nullable', 'string', 'max:100', 'required_with:asset_url'],
            'asset_url' => ['nullable', 'url'],
            'asset_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $accountMap = $data['platform_accounts'];
        foreach ($data['platform_ids'] as $platformId) {
            abort_unless(
                Account::withTrashed()->whereKey($accountMap[$platformId] ?? null)->where('platform_id', $platformId)->exists(),
                422,
                'Selected account does not belong to this platform.'
            );
        }

        $before = $content->only(['title', 'publish_date', 'pic_user_id']);
        DB::transaction(function () use ($request, $content, $data, $accountMap) {
            $content->update([
                'title' => $data['title'],
                'publish_date' => $data['publish_date'] ?? null,
                'account_id' => collect($accountMap)->first(),
                'pillar_id' => $data['pillar_id'],
                'series_id' => $data['series_id'],
                'format_id' => $data['format_id'] ?? null,
                'pic_user_id' => $data['pic_user_id'] ?? null,
                'updated_by' => $request->user()->id,
            ]);

            $content->platforms()->sync(collect($data['platform_ids'])->mapWithKeys(
                fn ($platformId) => [$platformId => ['account_id' => $accountMap[$platformId]]]
            )->all());

            $content->brief()->updateOrCreate([], collect($data)->only([
                'hook', 'angle', 'key_message', 'cta', 'notes', 'main_copy',
                'reels_script', 'carousel_copy', 'caption', 'threads_copy',
            ])->all());

            if (filled($data['asset_url'] ?? null)) {
                Asset::create([
                    'content_id' => $content->id,
                    'title' => $data['asset_name'],
                    'asset_type' => $data['asset_type'],
                    'url' => $data['asset_url'],
                    'notes' => $data['asset_notes'] ?? null,
                    'category' => 'Content Reference',
                    'added_by' => $request->user()->id,
                ]);
            }
        });

        $content->refresh();
        activity()->causedBy($request->user())->performedOn($content)
            ->withProperties(['old' => $before, 'new' => $content->only(['title', 'publish_date', 'pic_user_id'])])
            ->log($request->user()->name.' edited Content details.');

        return back()->with('success', 'Content details updated.');
    }

    public function publish(Request $request, Content $content, UpdateContentStatus $updateStatus)
    {
        Gate::authorize('publish', $content);
        $updateStatus->execute($content, ContentStatus::Published, $request->user());

        return back()->with('success', 'Content marked as Published.')
            ->with('open_published_modal', true);
    }

    public function updatePublishedInformation(Request $request, Content $content)
    {
        Gate::authorize('managePublishedInformation', $content);
        abort_unless($content->status === ContentStatus::Published, 422, 'Content must be Published first.');

        $data = $request->validate([
            'visibility' => ['required', Rule::in(['public', 'not_for_public'])],
            'final_url' => ['nullable', 'required_if:visibility,public', 'url', 'max:2048'],
        ]);

        $notForPublic = $data['visibility'] === 'not_for_public';
        $content->update([
            'final_url' => $notForPublic ? null : $data['final_url'],
            'is_not_for_public' => $notForPublic,
            'updated_by' => $request->user()->id,
        ]);

        activity()->causedBy($request->user())->performedOn($content)->log(
            $notForPublic
                ? $request->user()->name.' marked Content as Not for Public.'
                : $request->user()->name.' added Published Link.'
        );

        $message = $notForPublic ? 'Content marked as Not for Public.' : 'Published link saved.';
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'final_url' => $content->final_url,
                'is_not_for_public' => $content->is_not_for_public,
            ]);
        }

        return back()->with('success', $message);
    }
}
