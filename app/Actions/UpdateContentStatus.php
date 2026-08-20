<?php

namespace App\Actions;

use App\Enums\ContentStatus;
use App\Models\Content;
use App\Models\User;

class UpdateContentStatus
{
    public function execute(Content $content, ContentStatus $target, User $user, ?string $activityMessage = null): Content
    {
        $old = $content->status;

        if ($old === $target) {
            return $content;
        }

        $content->update([
            'status' => $target,
            'published_at' => $target === ContentStatus::Published ? ($content->published_at ?? now()) : $content->published_at,
            'updated_by' => $user->id,
        ]);

        activity()
            ->causedBy($user)
            ->performedOn($content)
            ->withProperties([
                'old' => $old->label(),
                'new' => $target->label(),
                'old_status' => $old->value,
                'new_status' => $target->value,
            ])
            ->log($activityMessage ?? $user->name.' changed status from '.$old->label().' to '.$target->label().'.');

        return $content->refresh();
    }
}
