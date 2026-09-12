<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    /**
     * Смотреть проект может любой его участник.
     *
     * Чужой проект отвечает «не найдено», а не «нельзя»: разница между 403
     * и 404 — это готовый оракул. За один проход по номерам можно узнать,
     * сколько в системе проектов и какие из них живые.
     */
    public function view(User $user, Project $project): Response
    {
        return $project->hasMember($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /** Менять проект — тоже участник. */
    public function update(User $user, Project $project): Response
    {
        return $project->hasMember($user)
            ? Response::allow()
            : Response::denyAsNotFound();
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
