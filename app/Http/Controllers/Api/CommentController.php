<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Task;
use App\Notifications\TaskCommented;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class CommentController extends Controller
{
    #[OA\Get(
        path: '/api/tasks/{task}/comments',
        summary: 'Комментарии к задаче',
        security: [['bearerAuth' => []]],
        tags: ['Комментарии'],
        parameters: [
            new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Список комментариев'),
            new OA\Response(response: 403, description: 'Задача из чужого проекта'),
        ]
    )]
    public function index(Task $task): AnonymousResourceCollection
    {
        $this->authorize('view', $task);

        $comments = $task->comments()->with('user')->latest()->paginate(30);

        return CommentResource::collection($comments);
    }

    #[OA\Post(
        path: '/api/tasks/{task}/comments',
        summary: 'Добавить комментарий',
        security: [['bearerAuth' => []]],
        tags: ['Комментарии'],
        parameters: [
            new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['body'],
                properties: [
                    new OA\Property(property: 'body', type: 'string', example: 'Сделал, проверьте'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Комментарий добавлен'),
            new OA\Response(response: 403, description: 'Задача из чужого проекта'),
        ]
    )]
    public function store(StoreCommentRequest $request, Task $task): JsonResponse
    {
        $this->authorize('comment', $task);

        $comment = $task->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        // Исполнителю сообщаем, что по его задаче написали — но не когда
        // он комментирует сам себя
        $assignee = $task->assignee;

        if ($assignee && $assignee->isNot($request->user())) {
            $assignee->notify(new TaskCommented($comment->load('task', 'user')));
        }

        return (new CommentResource($comment->load('user')))
            ->response()
            ->setStatusCode(201);
    }
}
