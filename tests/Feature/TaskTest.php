<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create();
        $this->owner = $this->project->owner;
    }

    /** Задача создаётся в проекте. */
    public function test_task_can_be_created(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/tasks", [
                'title' => 'Настроить бэкапы',
                'priority' => 'high',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Настроить бэкапы')
            ->assertJsonPath('data.priority', 'high');

        $this->assertDatabaseHas('tasks', ['title' => 'Настроить бэкапы']);
    }

    /** Без явных статуса и приоритета задача получает значения по умолчанию. */
    public function test_new_task_gets_default_status_and_priority(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/tasks", ['title' => 'Задача без деталей'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'todo')
            ->assertJsonPath('data.priority', 'normal');
    }

    /** Задача без названия не создаётся. */
    public function test_task_requires_title(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/tasks", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    /** Задача проходит весь путь по доске, включая тестирование. */
    public function test_task_moves_through_all_statuses(): void
    {
        $task = Task::factory()->for($this->project)->create();

        foreach (['in_progress', 'testing', 'done'] as $status) {
            $this->actingAs($this->owner, 'sanctum')
                ->putJson("/api/tasks/{$task->id}", ['status' => $status])
                ->assertOk()
                ->assertJsonPath('data.status', $status);
        }
    }

    /** Фильтр по статусу «в тестировании». */
    public function test_tasks_can_be_filtered_by_testing_status(): void
    {
        Task::factory()->for($this->project)->status(TaskStatus::Testing)->create();
        Task::factory()->for($this->project)->status(TaskStatus::Todo)->count(2)->create();

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/projects/{$this->project->id}/tasks?status=testing")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /** Неизвестный статус не пройдёт. */
    public function test_unknown_status_is_rejected(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/tasks", [
                'title' => 'Задача',
                'status' => 'kakoy-to-status',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    /** Исполнителем нельзя назначить постороннего. */
    public function test_assignee_must_be_project_member(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/tasks", [
                'title' => 'Задача',
                'assignee_id' => $stranger->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assignee_id');
    }

    /** Фильтр по статусу. */
    public function test_tasks_can_be_filtered_by_status(): void
    {
        Task::factory()->for($this->project)->status(TaskStatus::Done)->count(2)->create();
        Task::factory()->for($this->project)->status(TaskStatus::Todo)->count(3)->create();

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/projects/{$this->project->id}/tasks?status=done")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /** Фильтр по приоритету. */
    public function test_tasks_can_be_filtered_by_priority(): void
    {
        Task::factory()->for($this->project)->priority(TaskPriority::High)->create();
        Task::factory()->for($this->project)->priority(TaskPriority::Low)->count(2)->create();

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/projects/{$this->project->id}/tasks?priority=high")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /** Фильтр по сроку: только то, что нужно сдать не позже даты. */
    public function test_tasks_can_be_filtered_by_due_date(): void
    {
        Task::factory()->for($this->project)->create(['due_date' => '2026-01-10']);
        Task::factory()->for($this->project)->create(['due_date' => '2026-12-31']);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/projects/{$this->project->id}/tasks?due_before=2026-06-01")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /** Поиск по названию. */
    public function test_tasks_can_be_searched_by_title(): void
    {
        Task::factory()->for($this->project)->create(['title' => 'Настроить бэкапы']);
        Task::factory()->for($this->project)->create(['title' => 'Обновить зависимости']);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/projects/{$this->project->id}/tasks?search=бэкап")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /** Список отдаётся страницами. */
    public function test_task_list_is_paginated(): void
    {
        Task::factory()->for($this->project)->count(25)->create();

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/projects/{$this->project->id}/tasks")
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 25);
    }

    /** Задачу можно обновить. */
    public function test_task_can_be_updated(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('data.status', 'done');
    }

    /** Задачу можно удалить. */
    public function test_task_can_be_deleted(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson("/api/tasks/{$task->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    /** Чужие задачи закрыты и отвечают «не найдено». */
    public function test_stranger_cannot_touch_tasks(): void
    {
        $task = Task::factory()->for($this->project)->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')->getJson("/api/tasks/{$task->id}")->assertNotFound();
        $this->actingAs($stranger, 'sanctum')->putJson("/api/tasks/{$task->id}", ['title' => 'Взлом'])->assertNotFound();
        $this->actingAs($stranger, 'sanctum')->deleteJson("/api/tasks/{$task->id}")->assertNotFound();

        $this->assertDatabaseMissing('tasks', ['title' => 'Взлом']);
    }

    /**
     * Вложенные ручки задач тоже закрыты.
     *
     * Их не проверял ни один из прежних тестов: удали authorize из index
     * и store — и посторонний читал бы и создавал задачи в любом чужом
     * проекте по его номеру, а сьют остался бы зелёным.
     */
    public function test_stranger_cannot_use_project_task_endpoints(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/projects/{$this->project->id}/tasks")
            ->assertNotFound();

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/tasks", ['title' => 'Чужая задача'])
            ->assertNotFound();

        $this->assertDatabaseMissing('tasks', ['title' => 'Чужая задача']);
    }

    /** Кривые фильтры — это 422, а не пятисотка. */
    public function test_broken_filters_are_rejected(): void
    {
        Task::factory()->for($this->project)->create();

        // На PostgreSQL нечисловой assignee_id давал 500 (invalid input syntax
        // for type bigint), а на SQLite фильтр молча возвращал все задачи —
        // то есть врал, даже не падая
        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/projects/{$this->project->id}/tasks?assignee_id=abc")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assignee_id');

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/projects/{$this->project->id}/tasks?due_before=abc")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('due_before');

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/projects/{$this->project->id}/tasks?status=выдумка")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    /** Фильтр по исполнителю работает. */
    public function test_tasks_can_be_filtered_by_assignee(): void
    {
        $mine = Task::factory()->for($this->project)->create(['assignee_id' => $this->owner->id]);
        Task::factory()->for($this->project)->create(['assignee_id' => null]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/projects/{$this->project->id}/tasks?assignee_id={$this->owner->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    }

    /** Удаление проекта уносит его задачи. */
    public function test_deleting_project_removes_its_tasks(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson("/api/projects/{$this->project->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }
}
