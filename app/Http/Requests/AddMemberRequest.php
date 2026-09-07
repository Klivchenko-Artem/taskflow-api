<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddMemberRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'exists:users,id',
                // Второй раз того же человека в проект не добавить.
                Rule::unique('project_user', 'user_id')
                    ->where('project_id', $this->route('project')->id),
            ],
            'role' => ['sometimes', Rule::in(['member', 'owner'])],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.unique' => 'Этот пользователь уже участвует в проекте.',
        ];
    }
}
