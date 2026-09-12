<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    /**
     * Задачи видны участникам проекта, которому они принадлежат.
     *
     * Чужая задача отвечает «не найдено»: 403 на существующей и 404
     * на отсутствующей — это способ пересчитать чужие задачи по номерам.
     */
    public function view(User $user, Task $task): Response
    {
        return $this->forMember($user, $task);
    }

    public function update(User $user, Task $task): Response
    {
        return $this->forMember($user, $task);
    }

    public function delete(User $user, Task $task): Response
    {
        return $this->forMember($user, $task);
    }

    /** Комментировать может участник проекта. */
    public function comment(User $user, Task $task): Response
    {
        return $this->forMember($user, $task);
    }

    private function forMember(User $user, Task $task): Response
    {
        return $task->project->hasMember($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
