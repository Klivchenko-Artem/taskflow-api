<?php

namespace App\Support;

use Illuminate\Notifications\Notification;

/**
 * Постановка уведомления в очередь, которая не валит запрос.
 *
 * Задача или комментарий к этому моменту уже сохранены. Если Redis лежит,
 * клиент получал 500, повторял запрос и создавал дубль.
 */
final class SafeNotify
{
    public static function send(object $notifiable, Notification $notification): void
    {
        try {
            $notifiable->notify($notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
