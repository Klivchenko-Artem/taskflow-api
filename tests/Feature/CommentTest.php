<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    /** Участник комментирует задачу, автор проставляется сам. */
    public function test_member_can_comment_task(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($project->owner, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Сделал, проверьте'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Сделал, проверьте')
            ->assertJsonPath('data.author.id', $project->owner->id);
    }

    /** Пустой комментарий не принимается. */
    public function test_comment_body_is_required(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($project->owner, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/comments", ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    /** Комментарии отдаются от свежих к старым. */
    public function test_comments_are_listed_newest_first(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();

        Comment::factory()->for($task)->create(['body' => 'Первый', 'created_at' => now()->subHour()]);
        Comment::factory()->for($task)->create(['body' => 'Второй', 'created_at' => now()]);

        $this->actingAs($project->owner, 'sanctum')
            ->getJson("/api/tasks/{$task->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Второй');
    }

    /** Посторонний не читает и не пишет комментарии. */
    public function test_stranger_cannot_access_comments(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/tasks/{$task->id}/comments")
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Привет'])
            ->assertForbidden();
    }

    /** Удалили задачу — комментарии ушли вместе с ней. */
    public function test_deleting_task_removes_comments(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $comment = Comment::factory()->for($task)->create();

        $this->actingAs($project->owner, 'sanctum')
            ->deleteJson("/api/tasks/{$task->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }
}
