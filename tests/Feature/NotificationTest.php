<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommented;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $owner;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->project = Project::factory()->create();
        $this->owner = $this->project->owner;
        $this->member = User::factory()->create();
        $this->project->members()->attach($this->member->id, ['role' => 'member']);
    }

    /** Назначили задачу на человека — он получает письмо. */
    public function test_assignee_is_notified_on_task_creation(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/tasks", [
                'title' => 'Настроить бэкапы',
                'assignee_id' => $this->member->id,
            ])
            ->assertCreated();

        Notification::assertSentTo($this->member, TaskAssigned::class);
    }

    /** Задача без исполнителя никого не беспокоит. */
    public function test_no_notification_when_task_has_no_assignee(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/tasks", ['title' => 'Задача'])
            ->assertCreated();

        Notification::assertNothingSent();
    }

    /** Назначил задачу сам на себя — письмо самому себе не нужно. */
    public function test_no_notification_when_assigning_to_yourself(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/tasks", [
                'title' => 'Задача',
                'assignee_id' => $this->owner->id,
            ])
            ->assertCreated();

        Notification::assertNothingSent();
    }

    /** Сменили исполнителя — письмо уходит новому. */
    public function test_new_assignee_is_notified_on_update(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", ['assignee_id' => $this->member->id])
            ->assertOk();

        Notification::assertSentTo($this->member, TaskAssigned::class);
    }

    /** Перетаскивание задачи по доске письмами не сыпет. */
    public function test_status_change_does_not_notify(): void
    {
        $task = Task::factory()->for($this->project)->create([
            'assignee_id' => $this->member->id,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", ['status' => 'in_progress'])
            ->assertOk();

        Notification::assertNothingSent();
    }

    /** Комментарий к задаче — исполнителю приходит письмо. */
    public function test_assignee_is_notified_about_comment(): void
    {
        $task = Task::factory()->for($this->project)->create([
            'assignee_id' => $this->member->id,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Что там по срокам?'])
            ->assertCreated();

        Notification::assertSentTo($this->member, TaskCommented::class);
    }

    /** Исполнитель пишет комментарий сам себе — письма не будет. */
    public function test_own_comment_does_not_notify(): void
    {
        $task = Task::factory()->for($this->project)->create([
            'assignee_id' => $this->member->id,
        ]);

        $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Сделал'])
            ->assertCreated();

        Notification::assertNothingSent();
    }

    /** Письма уходят в очередь, а не отправляются прямо в запросе. */
    public function test_notifications_are_queued(): void
    {
        $this->assertInstanceOf(
            ShouldQueue::class,
            new TaskAssigned(Task::factory()->for($this->project)->create(), $this->owner)
        );
    }
}
