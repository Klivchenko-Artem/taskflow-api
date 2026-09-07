<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** Регистрация возвращает пользователя и токен. */
    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Артём',
            'email' => 'artem@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'artem@example.com']);
    }

    /** Пароль в ответе не светится. */
    public function test_password_is_not_returned(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Артём',
            'email' => 'artem@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertJsonMissingPath('user.password');
    }

    /** Занятый email не пропускаем. */
    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'artem@example.com']);

        $this->postJson('/api/register', [
            'name' => 'Другой',
            'email' => 'artem@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    /** Короткий пароль не принимается. */
    public function test_short_password_is_rejected(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Артём',
            'email' => 'artem@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    /** Вход с верным паролем выдаёт токен. */
    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'artem@example.com',
            'password' => 'secret123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'artem@example.com',
            'password' => 'secret123',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    /** Неверный пароль — ошибка, а не токен. */
    public function test_login_with_wrong_password_fails(): void
    {
        User::factory()->create([
            'email' => 'artem@example.com',
            'password' => 'secret123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'artem@example.com',
            'password' => 'nepravilnyy',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    /** Без токена защищённые маршруты отдают 401. */
    public function test_me_requires_token(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    /** С токеном /me отдаёт текущего пользователя. */
    public function test_me_returns_current_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    /** После выхода токен удаляется из базы и больше никого не пустит. */
    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
