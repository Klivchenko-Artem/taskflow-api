<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Человек в подсказках поиска.
 *
 * Отличается от UserResource тем, что не отдаёт почту: для приглашения
 * достаточно идентификатора, а список адресов посторонних людей, это чужие
 * персональные данные. UserResource остаётся для «себя» (`GET /api/me`)
 * и для участников проекта, где почта коллеги уместна.
 */
class UserSuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
