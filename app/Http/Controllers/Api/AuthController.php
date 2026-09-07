<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/register',
        summary: 'Регистрация — сразу возвращает токен',
        tags: ['Авторизация'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Артём'),
                    new OA\Property(property: 'email', type: 'string', example: 'artem@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'secret123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', example: 'secret123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Пользователь создан, выдан токен'),
            new OA\Response(response: 422, description: 'Ошибка валидации'),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('api')->plainTextToken,
        ], 201);
    }

    #[OA\Post(
        path: '/api/login',
        summary: 'Вход, выдача токена',
        tags: ['Авторизация'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'artem@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'secret123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Токен выдан'),
            new OA\Response(response: 422, description: 'Неверный email или пароль'),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Неверный email или пароль.',
            ]);
        }

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('api')->plainTextToken,
        ]);
    }

    #[OA\Post(
        path: '/api/logout',
        summary: 'Выход — текущий токен отзывается',
        security: [['bearerAuth' => []]],
        tags: ['Авторизация'],
        responses: [
            new OA\Response(response: 204, description: 'Токен отозван'),
            new OA\Response(response: 401, description: 'Нужен токен'),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(status: 204);
    }

    #[OA\Get(
        path: '/api/me',
        summary: 'Текущий пользователь',
        security: [['bearerAuth' => []]],
        tags: ['Авторизация'],
        responses: [
            new OA\Response(response: 200, description: 'Данные пользователя'),
            new OA\Response(response: 401, description: 'Нужен токен'),
        ]
    )]
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
