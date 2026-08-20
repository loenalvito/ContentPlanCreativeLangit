<?php

namespace App\Policies;

use App\Models\Content;
use App\Models\User;

class ContentPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('Super Admin') ? true : null;
    }

    public function view(User $user, Content $content): bool
    {
        return $user->can('content.view') || $this->isParticipant($user, $content);
    }

    public function update(User $user, Content $content): bool
    {
        return $user->can('content.edit') || $this->isParticipant($user, $content);
    }

    public function publish(User $user, Content $content): bool
    {
        return $user->can('production.change_status') || $user->can('content.change_status');
    }

    public function managePublishedInformation(User $user, Content $content): bool
    {
        return $this->update($user, $content) || $this->publish($user, $content);
    }

    private function isParticipant(User $user, Content $content): bool
    {
        if (in_array($user->id, array_filter([$content->created_by, $content->pic_user_id]), true)) {
            return true;
        }

        return $content->sourceIdea()->where('submitted_by', $user->id)->exists();
    }
}
