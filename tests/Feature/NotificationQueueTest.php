<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Уведомления действительно уходят в очередь.
 *
 * Отдельным файлом, потому что в NotificationTest весь набор идёт под
 * `Notification::fake()`: он перехватывает отправку до диспетчера очередей,
 * и проверить «через очередь или напрямую» там физически нельзя. Прежний тест
 * это и обходил — проверял `instanceof ShouldQueue`, то есть строчку
 * в сигнатуре класса, которая не могла не сойтись.
 */
class NotificationQueueTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $owner;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create();
        $this->owner = $this->project->owner;
        $this->member = User::factory()->create();
        $this->project->members()->attach($this->member->id, ['role' => 'member']);
    }

    public function test_assignment_notification_is_queued(): void
    {
        Queue::fake();

        $task = Task::factory()->for($this->project)->create();

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", ['assignee_id' => $this->member->id])
            ->assertOk();

        // Письмо отправляет воркер, а не запрос пользователя: иначе лежащий
        // почтовый сервер держал бы ручку до таймаута
        Queue::assertPushed(SendQueuedNotifications::class);
    }

    public function test_comment_notification_is_queued(): void
    {
        Queue::fake();

        $task = Task::factory()->for($this->project)->create([
            'assignee_id' => $this->member->id,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Готово?'])
            ->assertCreated();

        Queue::assertPushed(SendQueuedNotifications::class);
    }

    public function test_status_change_does_not_queue_anything(): void
    {
        Queue::fake();

        $task = Task::factory()->for($this->project)->create([
            'assignee_id' => $this->member->id,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", ['status' => 'in_progress'])
            ->assertOk();

        // Перетаскивание по доске не должно засыпать людей письмами
        Queue::assertNothingPushed();
    }
}
