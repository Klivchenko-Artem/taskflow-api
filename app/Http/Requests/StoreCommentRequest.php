<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreCommentRequest extends FormRequest
{
    /**
     * Права проверяем до валидации: иначе посторонний по ответу 422 или 404
     * узнавал бы, существует ли чужая запись и кто состоит в чужом проекте.
     */
    public function authorize(): Response
    {
        return Gate::inspect('comment', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
