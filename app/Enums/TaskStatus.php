<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    /** Значения для правил валидации и документации. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
