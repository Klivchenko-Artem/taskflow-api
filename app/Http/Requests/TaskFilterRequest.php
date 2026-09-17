<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Фильтры списка задач.
 *
 * Раньше значения из строки запроса уходили в SQL как есть: на PostgreSQL
 * `?assignee_id=abc` давало 500 (invalid input syntax for type bigint),
 * а `?due_before=abc`, 500 на дате. Хуже того, на SQLite тот же запрос
 * молча возвращал все задачи, то есть фильтр врал даже не падая, а тесты
 * гоняются именно на SQLite.
 */
class TaskFilterRequest extends FormRequest
{
    /**
     * Права проверяем до валидации: иначе посторонний по ответу 422 или 404
     * узнавал бы, существует ли чужая запись и кто состоит в чужом проекте.
     */
    public function authorize(): Response
    {
        return Gate::inspect('view', $this->route('project'));
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'assignee_id' => ['nullable', 'integer', 'min:1'],
            'due_before' => ['nullable', 'date_format:Y-m-d'],
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
