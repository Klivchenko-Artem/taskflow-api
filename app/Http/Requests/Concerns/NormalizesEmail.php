<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;

/**
 * Приводит почту из запроса к виду, в котором она лежит в базе,
 * до валидации: exists и unique сравнивают уже приведённую.
 */
trait NormalizesEmail
{
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => User::normalizeEmail($this->input('email'))]);
        }
    }
}
