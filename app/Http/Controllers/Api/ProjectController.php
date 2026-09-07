<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddMemberRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class ProjectController extends Controller
{
    #[OA\Get(
        path: '/api/projects',
        summary: 'Проекты, в которых участвует пользователь',
        security: [['bearerAuth' => []]],
        tags: ['Проекты'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Постраничный список проектов'),
            new OA\Response(response: 401, description: 'Нужен токен'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = $request->user()
            ->projects()
            ->with('owner')
            ->withCount('tasks')
            ->latest()
            ->paginate(15);

        return ProjectResource::collection($projects);
    }

    #[OA\Post(
        path: '/api/projects',
        summary: 'Создать проект — автор сразу становится владельцем и участником',
        security: [['bearerAuth' => []]],
        tags: ['Проекты'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Переезд на новый сервер'),
                    new OA\Property(property: 'description', type: 'string', example: 'Всё, что нужно успеть до пятницы'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Проект создан'),
            new OA\Response(response: 422, description: 'Ошибка валидации'),
        ]
    )]
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $request->user()->ownedProjects()->create($request->validated());

        // Владелец автоматически становится участником — иначе не увидит свой же проект.
        $project->members()->attach($request->user()->id, ['role' => 'owner']);

        return (new ProjectResource($project->load('owner')->loadCount('tasks')))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/projects/{project}',
        summary: 'Проект целиком, вместе с участниками',
        security: [['bearerAuth' => []]],
        tags: ['Проекты'],
        parameters: [
            new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Данные проекта'),
            new OA\Response(response: 403, description: 'Вы не участник проекта'),
            new OA\Response(response: 404, description: 'Проект не найден'),
        ]
    )]
    public function show(Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        return new ProjectResource($project->load('owner', 'members')->loadCount('tasks'));
    }

    #[OA\Put(
        path: '/api/projects/{project}',
        summary: 'Изменить проект',
        security: [['bearerAuth' => []]],
        tags: ['Проекты'],
        parameters: [
            new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Проект обновлён'),
            new OA\Response(response: 403, description: 'Вы не участник проекта'),
        ]
    )]
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        return new ProjectResource($project->load('owner'));
    }

    #[OA\Delete(
        path: '/api/projects/{project}',
        summary: 'Удалить проект вместе с задачами — только владелец',
        security: [['bearerAuth' => []]],
        tags: ['Проекты'],
        parameters: [
            new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Проект удалён'),
            new OA\Response(response: 403, description: 'Удалять может только владелец'),
        ]
    )]
    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json(status: 204);
    }

    #[OA\Post(
        path: '/api/projects/{project}/members',
        summary: 'Добавить участника — только владелец',
        security: [['bearerAuth' => []]],
        tags: ['Проекты'],
        parameters: [
            new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                description: 'Нужно передать либо user_id, либо email',
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer', example: 2),
                    new OA\Property(property: 'email', type: 'string', example: 'petr@example.com'),
                    new OA\Property(property: 'role', type: 'string', example: 'member'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Участник добавлен'),
            new OA\Response(response: 403, description: 'Добавлять может только владелец'),
            new OA\Response(response: 422, description: 'Пользователь уже в проекте'),
        ]
    )]
    public function addMember(AddMemberRequest $request, Project $project): ProjectResource
    {
        $this->authorize('addMember', $project);

        $project->members()->attach(
            $request->memberId(),
            ['role' => $request->input('role', 'member')]
        );

        return new ProjectResource($project->load('owner', 'members'));
    }
}
