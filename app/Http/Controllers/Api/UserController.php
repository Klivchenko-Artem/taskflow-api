<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    #[OA\Get(
        path: '/api/users',
        summary: 'Зарегистрированные пользователи — чтобы выбрать, кого позвать в проект',
        security: [['bearerAuth' => []]],
        tags: ['Пользователи'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Поиск по имени или почте', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'exclude_project', in: 'query', description: 'Убрать тех, кто уже в этом проекте', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Список пользователей'),
            new OA\Response(response: 401, description: 'Нужен токен'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            // Уже состоящих в проекте предлагать незачем
            ->when($request->filled('exclude_project'), function ($query) use ($request) {
                $query->whereDoesntHave('projects', fn ($q) => $q
                    ->where('projects.id', $request->integer('exclude_project')));
            })
            ->orderBy('name')
            ->paginate(50);

        return UserResource::collection($users);
    }
}
