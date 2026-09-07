<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'assignee_id' => null,
            'title' => $this->faker->unique()->sentence(4),
            'description' => $this->faker->paragraph(),
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Normal,
            'due_date' => null,
        ];
    }

    public function status(TaskStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function priority(TaskPriority $priority): static
    {
        return $this->state(fn () => ['priority' => $priority]);
    }
}
