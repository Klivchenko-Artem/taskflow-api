<?php

namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TaskCommented extends Notification implements ShouldQueue
{
    use Queueable;

    /** Четыре попытки: первая и три повтора через минуту, пять и пятнадцать. */
    public int $tries = 4;

    public array $backoff = [60, 300, 900];

    /** Комментарий или задачу удалили раньше, чем ушло письмо. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(private readonly Comment $comment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** Пока письмо ждало в очереди, задачу могли переназначить на другого. */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        // Через int, как и в TaskAssigned: id из запроса бывает строкой
        return (int) $this->comment->task->assignee_id === (int) $notifiable->id;
    }

    /** Попытки кончились: записываем в журнал, чтобы потеря письма была видна. */
    public function failed(\Throwable $exception): void
    {
        Log::error('Уведомление о комментарии не доставлено', [
            'comment_id' => $this->comment->id,
            'error' => $exception->getMessage(),
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $task = $this->comment->task;
        $author = $this->comment->user;

        return (new MailMessage)
            ->subject("Новый комментарий к задаче: {$task->title}")
            ->greeting('Здравствуйте, '.$notifiable->name.'!')
            ->line($author->name.' прокомментировал задачу «'.$task->title.'»:')
            ->line(Str::limit($this->comment->body, 300));
    }
}
