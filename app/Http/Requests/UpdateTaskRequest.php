<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Права проверяем до валидации: иначе посторонний по ответу 422 или 404
     * узнавал бы, существует ли чужая запись и кто состоит в чужом проекте.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(TaskStatus::values())],
            'priority' => ['sometimes', Rule::in(TaskPriority::values())],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'assignee_id' => [
                'nullable',
                'integer',
                Rule::exists('project_user', 'user_id')
                    ->where('project_id', $this->route('task')->project_id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'assignee_id.exists' => 'Исполнитель должен быть участником проекта.',
        ];
    }
}
