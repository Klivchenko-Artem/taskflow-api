<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /** Смотреть проект может любой его участник. */
    public function view(User $user, Project $project): bool
    {
        return $project->hasMember($user);
    }

    /** Менять проект — тоже участник. */
    public function update(User $user, Project $project): bool
    {
        return $project->hasMember($user);
    }

    /** Удалить проект может только владелец. */
    public function delete(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id;
    }

    /** Добавлять людей в проект может только владелец. */
    public function addMember(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id;
    }
}
