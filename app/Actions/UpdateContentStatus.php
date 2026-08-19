<?php

namespace App\Actions;

use App\Enums\ContentStatus;
use App\Models\Content;
use App\Models\User;

class UpdateContentStatus
{
    public function execute(Content $content, ContentStatus $target, User $user): Content
    {
        $old = $content->status;

        if ($old === $target) {
            return $content;
        }

        $content->update([
            'status' => $target,
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
            ->log($user->name.' changed status from '.$old->label().' to '.$target->label().'.');

        return $content->refresh();
    }
}
