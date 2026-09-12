<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Фильтры списка задач.
 *
 * Раньше значения из строки запроса уходили в SQL как есть: на PostgreSQL
 * `?assignee_id=abc` давало 500 (invalid input syntax for type bigint),
 * а `?due_before=abc` — 500 на дате. Хуже того, на SQLite тот же запрос
 * молча возвращал все задачи, то есть фильтр врал даже не падая, — а тесты
 * гоняются именно на SQLite.
 */
class TaskFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'assignee_id' => ['nullable', 'integer', 'min:1'],
            'due_before' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** Только фильтры, без служебных параметров вроде page. */
    public function filters(): array
    {
        return $this->only(['status', 'priority', 'assignee_id', 'due_before', 'search']);
    }
}
