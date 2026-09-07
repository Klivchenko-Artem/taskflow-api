<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Task Tracker API',
    description: 'REST API трекера задач: проекты, участники, задачи, комментарии. Авторизация — токены Sanctum.'
)]
#[OA\Server(url: 'http://localhost:8000', description: 'Локальный запуск')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    description: 'Токен из /api/register или /api/login'
)]
abstract class Controller
{
    use AuthorizesRequests;
}
