<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(TaskStatus::values())],
            'priority' => ['sometimes', Rule::in(TaskPriority::values())],
            'due_date' => ['nullable', 'date'],
            // Исполнителем может быть только участник этого проекта.
            'assignee_id' => [
                'nullable',
                Rule::exists('project_user', 'user_id')
                    ->where('project_id', $this->route('project')->id),
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
