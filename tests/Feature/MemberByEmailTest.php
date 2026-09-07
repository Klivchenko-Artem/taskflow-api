<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberByEmailTest extends TestCase
{
    use RefreshDatabase;

    /** Участника можно позвать по почте, а не только по id. */
    public function test_member_can_be_added_by_email(): void
    {
        $project = Project::factory()->create();
        $newbie = User::factory()->create(['email' => 'petr@example.com']);

        $this->actingAs($project->owner, 'sanctum')
            ->postJson("/api/projects/{$project->id}/members", ['email' => 'petr@example.com'])
            ->assertOk();

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $newbie->id,
        ]);
    }

    /** Незнакомая почта — понятная ошибка. */
    public function test_unknown_email_is_rejected(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($project->owner, 'sanctum')
            ->postJson("/api/projects/{$project->id}/members", ['email' => 'nobody@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    /** По почте того же человека дважды не добавить. */
    public function test_existing_member_email_is_rejected(): void
    {
        $project = Project::factory()->create();
        $member = User::factory()->create(['email' => 'petr@example.com']);
        $project->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($project->owner, 'sanctum')
            ->postJson("/api/projects/{$project->id}/members", ['email' => 'petr@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    /** Без id и без почты запрос не проходит. */
    public function test_either_id_or_email_is_required(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($project->owner, 'sanctum')
            ->postJson("/api/projects/{$project->id}/members", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'email']);
    }
}
