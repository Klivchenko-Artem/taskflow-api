<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserSuggestionResource;
use App\Models\Project;
use App\Models\User;
use App\Support\Like;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserController extends Controller
{
    /**
     * Сколько символов нужно, чтобы начать искать.
     *
     * Без обязательного поиска ручка отдавала имена и почты всех
     * зарегистрированных по 50 за страницу: пять секунд на регистрацию, минута
     * на выкачивание всей адресной книги, и готовая база для рассылки
     * «по задаче в TaskFlow».
     */
    private const MIN_SEARCH_LENGTH = 3;

    /** Сколько подсказок отдаём максимум. */
    private const SUGGESTIONS_LIMIT = 20;

    #[OA\Get(
        path: '/api/users',
        summary: 'Подсказки по людям, чтобы выбрать, кого позвать в проект',
        description: 'Поиск по имени, обязателен, от трёх символов. По почте не ищет и почту не отдаёт: '.
            'пригласить по адресу можно через POST /api/projects/{project}/members с полем email.',
        security: [['bearerAuth' => []]],
        tags: ['Пользователи'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: true, description: 'Часть имени, от трёх символов', schema: new OA\Schema(type: 'string', minLength: 3)),
            new OA\Parameter(name: 'exclude_project', in: 'query', description: 'Убрать тех, кто уже в этом проекте (только для своих проектов)', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Подсказки'),
            new OA\Response(response: 401, description: 'Нужен токен'),
            new OA\Response(response: 404, description: 'Проект в exclude_project не ваш'),
            new OA\Response(response: 422, description: 'Слишком короткий поиск'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => 'required|string|min:'.self::MIN_SEARCH_LENGTH.'|max:255',
            'exclude_project' => 'nullable|integer',
        ]);

        $search = $validated['search'];

        // Только по имени. По почте не ищем вовсе: подстрокой адрес
        // восстанавливался посимвольно, а точным совпадением ручка работала
        // справочником «почта -> имя» для любого, кто зарегистрировался.
        // Позвать человека по адресу можно через POST /projects/{id}/members
        $users = User::query()
            ->where(fn ($query) => Like::contains($query, 'name', $search))
            ->when(isset($validated['exclude_project']), function ($query) use ($request, $validated) {
                $projectId = (int) $validated['exclude_project'];

                // Состав чужого проекта, не наше дело.
                //
                // Раньше номер брался из запроса как есть: разница между полным
                // списком и списком с exclude_project давала поимённый состав
                // любого проекта, а перебором по номерам, карту всех команд.
                $project = Project::query()
                    ->whereKey($projectId)
                    ->whereHas('members', fn ($q) => $q->whereKey($request->user()->id))
                    ->first();

                if ($project === null) {
                    throw new NotFoundHttpException('Проект не найден');
                }

                $query->whereDoesntHave('projects', fn ($q) => $q->whereKey($projectId));
            })
            ->orderBy('name')
            ->limit(self::SUGGESTIONS_LIMIT)
            ->get();

        return UserSuggestionResource::collection($users);
    }
}
