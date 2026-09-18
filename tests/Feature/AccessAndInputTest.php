<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommented;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccessAndInputTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $owner;

    private User $member;

    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create();
        $this->owner = $this->project->owner;
        $this->member = User::factory()->create();
        $this->project->members()->attach($this->member->id, ['role' => 'member']);
        $this->stranger = User::factory()->create();
    }

    /** Кривое тело запроса к чужой задаче даёт 404, а не 422: так не узнать, что задача есть. */
    public function test_stranger_gets_404_before_validation(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->actingAs($this->stranger, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", ['title' => ''])
            ->assertNotFound();

        $this->actingAs($this->stranger, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/tasks", ['assignee_id' => $this->member->id])
            ->assertNotFound();

        $this->actingAs($this->stranger, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/comments", [])
            ->assertNotFound();

        $this->actingAs($this->stranger, 'sanctum')
            ->getJson("/api/projects/{$this->project->id}/tasks?status=nonsense")
            ->assertNotFound();
    }

    /** Удалять и звать людей посторонний не может, и ответ тот же, что на несуществующий проект. */
    public function test_stranger_gets_404_on_owner_actions(): void
    {
        $this->actingAs($this->stranger, 'sanctum')
            ->deleteJson("/api/projects/{$this->project->id}")
            ->assertNotFound();

        $this->actingAs($this->stranger, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/members", ['user_id' => $this->stranger->id])
            ->assertNotFound();

        $this->actingAs($this->member, 'sanctum')
            ->deleteJson("/api/projects/{$this->project->id}")
            ->assertForbidden();
    }

    /** Нечисловой id исполнителя отбивается проверкой, а не падает в базе. */
    public function test_non_integer_ids_are_rejected(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/tasks", ['title' => 'x', 'assignee_id' => 'abc'])
            ->assertJsonValidationErrors('assignee_id');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/members", ['user_id' => [1, 2]])
            ->assertJsonValidationErrors('user_id');
    }

    /** Дата только в формате YYYY-MM-DD: 17.09.2026 PostgreSQL не понимает или читает иначе. */
    public function test_dates_must_be_iso(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/projects/{$this->project->id}/tasks?due_before=17.09.2026")
            ->assertJsonValidationErrors('due_before');

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/projects/{$this->project->id}/tasks?due_before=2026-09-17")
            ->assertOk();
    }

    /**
     * Подсказки по почте не ищут ни куском, ни целиком: целиком ручка
     * отвечала на вопрос «как зовут владельца этого адреса». Позвать по
     * почте можно добавлением участника, это только владельцу проекта.
     */
    public function test_email_is_not_searchable(): void
    {
        $olga = User::factory()->create(['name' => 'Ольга', 'email' => 'olga.secret@example.com']);

        foreach (['secret@example', 'OLGA.secret@example.com'] as $search) {
            $this->actingAs($this->owner, 'sanctum')
                ->getJson('/api/users?search='.urlencode($search))
                ->assertOk()
                ->assertJsonCount(0, 'data');
        }

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/members", ['email' => 'OLGA.secret@example.com'])
            ->assertOk();

        $this->assertTrue($this->project->hasMember($olga));
    }

    /** Почта без учёта регистра: регистрация с заглавной и вход строчными. */
    public function test_email_case_does_not_matter(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Артём',
            'email' => 'Artem@Example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $this->postJson('/api/login', ['email' => 'artem@example.com', 'password' => 'password123'])
            ->assertOk();

        $this->postJson('/api/register', [
            'name' => 'Двойник',
            'email' => 'artem@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertJsonValidationErrors('email');
    }

    /** Тот же исполнитель строкой: письмо повторно не уходит. */
    public function test_same_assignee_as_string_does_not_notify_again(): void
    {
        Notification::fake();

        $task = Task::factory()->for($this->project)->create(['assignee_id' => $this->member->id]);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", ['assignee_id' => (string) $this->member->id, 'status' => 'done'])
            ->assertOk();

        Notification::assertNothingSent();
    }

    /** Очередь недоступна: задача всё равно создаётся один раз и клиент получает 201. */
    public function test_queue_failure_does_not_break_request(): void
    {
        $this->mock(Dispatcher::class)
            ->shouldReceive('send')
            ->andThrow(new \RuntimeException('Redis недоступен'));

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/tasks", [
                'title' => 'Настроить бэкапы',
                'assignee_id' => $this->member->id,
            ])
            ->assertCreated();

        $this->assertDatabaseCount('tasks', 1);
    }

    /** Задачу переназначили, пока письмо ждало в очереди: прежнему исполнителю оно не уходит. */
    public function test_notification_is_skipped_after_reassignment(): void
    {
        $task = Task::factory()->for($this->project)->create(['assignee_id' => $this->member->id]);
        $notification = new TaskAssigned($task, $this->owner);

        $task->update(['assignee_id' => $this->owner->id]);

        $this->assertFalse($notification->shouldSend($this->member, 'mail'));
    }

    /** Разметка из комментария в письме остаётся текстом, а не ссылкой. */
    public function test_markdown_in_comment_is_escaped_in_mail(): void
    {
        $task = Task::factory()->for($this->project)->create(['assignee_id' => $this->member->id]);
        $comment = $task->comments()->create([
            'user_id' => $this->owner->id,
            'body' => '[Войти](https://evil.example)',
        ]);

        $html = (string) (new TaskCommented($comment->load('task', 'user')))->toMail($this->member)->render();

        $this->assertStringNotContainsString('href="https://evil.example"', $html);
    }

    /**
     * Спецсимволы в письме выглядят как написали: без обратных слэшей
     * и двойного экранирования, которые оставляла ручная замена.
     */
    public function test_special_characters_are_readable_in_mail(): void
    {
        $this->owner->update(['name' => 'Анна_Ли']);
        $task = Task::factory()->for($this->project)->create(['assignee_id' => $this->member->id]);
        $comment = $task->comments()->create([
            'user_id' => $this->owner->id,
            'body' => 'Сравни 2 < 3 и *звёздочки*',
        ]);

        $mail = (new TaskCommented($comment->load('task', 'user')))->toMail($this->member);
        $html = (string) $mail->render();
        $text = (string) app(\Illuminate\Mail\Markdown::class)->renderText($mail->markdown, $mail->data());

        $this->assertStringContainsString('2 &lt; 3', $html);
        $this->assertStringNotContainsString('&amp;lt;', $html);
        $this->assertStringContainsString('Анна_Ли', $text);
        $this->assertStringContainsString('2 < 3', $text);
        $this->assertStringNotContainsString('\\', $text);
    }

    /** Поиск «0» ищет ноль, а не отключает фильтр. */
    public function test_search_zero_is_a_real_search(): void
    {
        Task::factory()->for($this->project)->create(['title' => 'Версия 0.9']);
        Task::factory()->for($this->project)->create(['title' => 'Без цифр']);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/projects/{$this->project->id}/tasks?search=0")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /** Год 0000 отбивается валидацией, а не падает в базе. */
    public function test_year_zero_due_date_is_rejected(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/tasks", ['title' => 'Задача', 'due_date' => '0000-01-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('due_date');

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/projects/{$this->project->id}/tasks?due_before=0000-01-01")
            ->assertUnprocessable();
    }

    /** id исполнителя строкой из запроса не отменяет письмо. */
    public function test_string_assignee_id_still_sends_mail(): void
    {
        $task = Task::factory()->for($this->project)->make(['assignee_id' => (string) $this->member->id]);

        $this->assertTrue((new TaskAssigned($task, $this->owner))->shouldSend($this->member, 'mail'));
    }
}
