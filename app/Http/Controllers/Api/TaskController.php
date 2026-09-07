<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class TaskController extends Controller
{
    #[OA\Get(
        path: '/api/projects/{project}/tasks',
        summary: 'Задачи проекта с фильтрами и постраничной выдачей',
        security: [['bearerAuth' => []]],
        tags: ['Задачи'],
        parameters: [
            new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', description: 'todo, in_progress, done', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'priority', in: 'query', description: 'low, normal, high', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'assignee_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'due_before', in: 'query', description: 'Срок не позже даты, YYYY-MM-DD', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Поиск по названию', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Список задач'),
            new OA\Response(response: 403, description: 'Вы не участник проекта'),
        ]
    )]
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('view', $project);

        $tasks = $project->tasks()
            ->filter($request->only(['status', 'priority', 'assignee_id', 'due_before', 'search']))
            ->with('assignee')
            ->withCount('comments')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    #[OA\Post(
        path: '/api/projects/{project}/tasks',
        summary: 'Создать задачу в проекте',
        security: [['bearerAuth' => []]],
        tags: ['Задачи'],
        parameters: [
            new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Настроить бэкапы'),
                    new OA\Property(property: 'description', type: 'string', example: 'Ежедневно в 3 часа ночи'),
                    new OA\Property(property: 'status', type: 'string', example: 'todo'),
                    new OA\Property(property: 'priority', type: 'string', example: 'high'),
                    new OA\Property(property: 'due_date', type: 'string', example: '2026-10-01'),
                    new OA\Property(property: 'assignee_id', type: 'integer', example: 2),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Задача создана'),
            new OA\Response(response: 422, description: 'Ошибка валидации'),
        ]
    )]
    public function store(StoreTaskRequest $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $task = $project->tasks()->create($request->validated());

        return (new TaskResource($task->load('assignee')))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/tasks/{task}',
        summary: 'Одна задача',
        security: [['bearerAuth' => []]],
        tags: ['Задачи'],
        parameters: [
            new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Данные задачи'),
            new OA\Response(response: 403, description: 'Задача из чужого проекта'),
        ]
    )]
    public function show(Task $task): TaskResource
    {
        $this->authorize('view', $task);

        return new TaskResource($task->load('assignee')->loadCount('comments'));
    }

    #[OA\Put(
        path: '/api/tasks/{task}',
        summary: 'Изменить задачу',
        security: [['bearerAuth' => []]],
        tags: ['Задачи'],
        parameters: [
            new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Задача обновлена'),
            new OA\Response(response: 403, description: 'Задача из чужого проекта'),
        ]
    )]
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        $task->update($request->validated());

        return new TaskResource($task->load('assignee'));
    }

    #[OA\Delete(
        path: '/api/tasks/{task}',
        summary: 'Удалить задачу',
        security: [['bearerAuth' => []]],
        tags: ['Задачи'],
        parameters: [
            new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Задача удалена'),
            new OA\Response(response: 403, description: 'Задача из чужого проекта'),
        ]
    )]
    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(status: 204);
    }
}
