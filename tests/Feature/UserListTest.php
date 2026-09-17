<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Подсказки по людям для приглашения в проект.
 *
 * Раньше ручка отдавала имена и почты всех зарегистрированных по 50 за
 * страницу, и тесты закрепляли это как правильное поведение. Теперь поиск
 * обязателен, почта наружу не уходит, а состав чужого проекта не подсматривается.
 */
class UserListTest extends TestCase
{
    use RefreshDatabase;

    /** Без токена список не отдаём. */
    public function test_user_list_requires_token(): void
    {
        $this->getJson('/api/users?search=Пётр')->assertUnauthorized();
    }

    /** Без поиска ручка ничего не отдаёт. */
    public function test_search_is_required(): void
    {
        $user = User::factory()->create();
        User::factory()->count(3)->create();

        // Иначе пять секунд на регистрацию и минута на выкачивание всей
        // адресной книги, готовая база для рассылки «по задаче в TaskFlow»
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('search');
    }

    /** Двух символов мало. */
    public function test_short_search_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users?search=Пё')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('search');
    }

    /** Почту посторонних наружу не отдаём. */
    public function test_suggestions_do_not_expose_emails(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['name' => 'Пётр Петров', 'email' => 'petr@example.com']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/users?search=Пётр')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Пётр Петров');

        // Для приглашения достаточно идентификатора: адрес приглашающий
        // и так знает, а список чужих адресов, это чужие данные
        $response->assertJsonMissingPath('data.0.email');
        $response->assertJsonMissingPath('data.0.password');
    }

    /** Поиск по почте работает: пригласить по адресу надо уметь. */
    public function test_search_by_email_works(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create(['email' => 'newcomer@example.com']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users?search=newcomer@example.com')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $target->id);
    }

    /** Служебные символы LIKE не должны работать как шаблон. */
    public function test_like_wildcards_are_escaped(): void
    {
        $user = User::factory()->create();
        User::factory()->count(3)->create();

        // Раньше `%` возвращал всех, то есть обходил требование поиска
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users?search=%25%25%25')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /** Тех, кто уже в проекте, из подсказок убираем. */
    public function test_project_members_are_excluded(): void
    {
        $project = Project::factory()->create();
        $outsider = User::factory()->create(['name' => 'Посторонний Пётр']);
        $project->owner->update(['name' => 'Владелец Пётр']);

        $this->actingAs($project->owner, 'sanctum')
            ->getJson("/api/users?search=Пётр&exclude_project={$project->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $outsider->id);
    }

    /** Состав чужого проекта не подсматривается. */
    public function test_exclude_project_requires_membership(): void
    {
        $foreign = Project::factory()->create();
        $stranger = User::factory()->create();

        // Разница между полным списком и списком с exclude_project давала
        // поимённый состав любого проекта, а перебор по номерам, карту команд
        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/users?search=Пётр&exclude_project={$foreign->id}")
            ->assertNotFound();
    }

    /** Подсказок отдаём не больше двадцати. */
    public function test_suggestions_are_limited(): void
    {
        $user = User::factory()->create();
        User::factory()->count(30)->create(['name' => 'Пётр Петров']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users?search=Пётр')
            ->assertOk()
            ->assertJsonCount(20, 'data');
    }
}
