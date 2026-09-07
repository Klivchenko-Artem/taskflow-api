<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Открытые маршруты
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// Всё остальное — только с токеном Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    Route::get('users', [UserController::class, 'index']);

    Route::apiResource('projects', ProjectController::class);
    Route::post('projects/{project}/members', [ProjectController::class, 'addMember']);

    // Задачи живут внутри проекта, поэтому список и создание — вложенные
    Route::get('projects/{project}/tasks', [TaskController::class, 'index']);
    Route::post('projects/{project}/tasks', [TaskController::class, 'store']);

    // А конкретная задача адресуется своим id, без проекта в пути
    Route::apiResource('tasks', TaskController::class)->only(['show', 'update', 'destroy']);

    Route::get('tasks/{task}/comments', [CommentController::class, 'index']);
    Route::post('tasks/{task}/comments', [CommentController::class, 'store']);
});
