<?php

namespace Tests\Unit;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use PHPUnit\Framework\TestCase;

/**
 * Перечисления статуса и приоритета.
 *
 * Тут был `assertTrue(true)` из скелета Laravel. Проверять же имеет смысл
 * `values()`: из него берутся правила валидации, фильтры и описание в OpenAPI,
 * и разъехавшийся список сразу означает, что либо ручка отбивает нормальный
 * статус, либо документация обещает несуществующий.
 */
class TaskEnumsTest extends TestCase
{
    public function test_status_values_match_cases(): void
    {
        $this->assertSame(
            ['todo', 'in_progress', 'testing', 'done'],
            TaskStatus::values(),
        );
    }

    public function test_status_has_testing_column(): void
    {
        // Колонка «тестирование» появилась позже остальных: без неё доска
        // и фильтр расходятся, и задачу некуда деть после работы
        $this->assertSame(TaskStatus::Testing, TaskStatus::from('testing'));
    }

    public function test_priority_values_match_cases(): void
    {
        $this->assertSame(TaskPriority::values(), array_column(TaskPriority::cases(), 'value'));
        $this->assertContains('normal', TaskPriority::values());
    }

    public function test_unknown_status_is_rejected(): void
    {
        $this->expectException(\ValueError::class);

        TaskStatus::from('выдумка');
    }
}
