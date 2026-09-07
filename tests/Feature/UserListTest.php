<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserListTest extends TestCase
{
    use RefreshDatabase;

    /** Без токена список не отдаём. */
    public function test_user_list_requires_token(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }

    /** Авторизованный видит зарегистрированных пользователей. */
    public function test_authenticated_user_sees_others(): void
    {
        $user = User::factory()->create();
        User::factory()->count(3)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    /** Пароли и служебные поля наружу не уходят. */
    public function test_list_does_not_leak_passwords(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonMissingPath('data.0.password');
    }

    /** Поиск по имени. */
    public function test_users_can_be_searched(): void
    {
        $user = User::factory()->create(['name' => 'Артём']);
        User::factory()->create(['name' => 'Пётр']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users?search=Пётр')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Пётр');
    }

    /** Тех, кто уже в проекте, из списка убираем. */
    public function test_project_members_are_excluded(): void
    {
        $project = Project::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($project->owner, 'sanctum')
            ->getJson("/api/users?exclude_project={$project->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $outsider->id);
    }
}
