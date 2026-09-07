<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AddMemberRequest extends FormRequest
{
    /**
     * Человека зовут в проект либо по id, либо по почте — интерфейсу удобнее
     * почта, машине удобнее id.
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required_without:email',
                'nullable',
                'exists:users,id',
                Rule::unique('project_user', 'user_id')
                    ->where('project_id', $this->route('project')->id),
            ],
            'email' => ['required_without:user_id', 'nullable', 'email', 'exists:users,email'],
            'role' => ['sometimes', Rule::in(['member', 'owner'])],
        ];
    }

    /** Проверяем, что найденный по почте человек ещё не в проекте. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || ! $this->filled('email')) {
                    return;
                }

                $alreadyMember = $this->route('project')
                    ->members()
                    ->where('email', $this->input('email'))
                    ->exists();

                if ($alreadyMember) {
                    $validator->errors()->add('email', 'Этот пользователь уже участвует в проекте.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.unique' => 'Этот пользователь уже участвует в проекте.',
            'email.exists' => 'Пользователь с такой почтой не зарегистрирован.',
        ];
    }

    /** Кого именно добавляем — id из запроса или найденный по почте. */
    public function memberId(): int
    {
        return $this->filled('user_id')
            ? (int) $this->input('user_id')
            : User::where('email', $this->input('email'))->value('id');
    }
}
