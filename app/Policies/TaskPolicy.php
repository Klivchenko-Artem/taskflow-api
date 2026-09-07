<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /** Задачи видны участникам проекта, которому они принадлежат. */
    public function view(User $user, Task $task): bool
    {
        return $task->project->hasMember($user);
    }

    public function update(User $user, Task $task): bool
    {
        return $task->project->hasMember($user);
    }

    public function delete(User $user, Task $task): bool
    {
        return $task->project->hasMember($user);
    }

    /** Комментировать может участник проекта. */
    public function comment(User $user, Task $task): bool
    {
        return $task->project->hasMember($user);
    }
}
