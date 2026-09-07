<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    /** В списке только те проекты, где пользователь участвует. */
    public function test_user_sees_only_own_projects(): void
    {
        $user = User::factory()->create();
        Project::factory()->create(['owner_id' => $user->id]);
        Project::factory()->create(); // чужой

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /** Создатель проекта сразу попадает в участники. */
    public function test_creator_becomes_member(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/projects', ['name' => 'Переезд на сервер'])
            ->assertCreated();

        $this->assertDatabaseHas('project_user', [
            'project_id' => $response->json('data.id'),
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    /** Проект без названия не создаётся. */
    public function test_project_requires_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/projects', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    /** Чужой проект не показываем. */
    public function test_stranger_cannot_view_project(): void
    {
        $project = Project::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/projects/{$project->id}")
            ->assertForbidden();
    }

    /** Участник проект видит. */
    public function test_member_can_view_project(): void
    {
        $project = Project::factory()->create();
        $member = User::factory()->create();
        $project->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.name', $project->name);
    }

    /** Удалить проект может только владелец. */
    public function test_only_owner_can_delete_project(): void
    {
        $project = Project::factory()->create();
        $member = User::factory()->create();
        $project->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member, 'sanctum')
            ->deleteJson("/api/projects/{$project->id}")
            ->assertForbidden();

        $this->actingAs($project->owner, 'sanctum')
            ->deleteJson("/api/projects/{$project->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    /** Владелец добавляет участника. */
    public function test_owner_can_add_member(): void
    {
        $project = Project::factory()->create();
        $newbie = User::factory()->create();

        $this->actingAs($project->owner, 'sanctum')
            ->postJson("/api/projects/{$project->id}/members", ['user_id' => $newbie->id])
            ->assertOk();

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $newbie->id,
        ]);
    }

    /** Одного и того же человека дважды не добавить. */
    public function test_member_cannot_be_added_twice(): void
    {
        $project = Project::factory()->create();
        $newbie = User::factory()->create();
        $project->members()->attach($newbie->id, ['role' => 'member']);

        $this->actingAs($project->owner, 'sanctum')
            ->postJson("/api/projects/{$project->id}/members", ['user_id' => $newbie->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    /** Обычный участник добавлять людей не может. */
    public function test_member_cannot_add_others(): void
    {
        $project = Project::factory()->create();
        $member = User::factory()->create();
        $project->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/projects/{$project->id}/members", [
                'user_id' => User::factory()->create()->id,
            ])
            ->assertForbidden();
    }
}
