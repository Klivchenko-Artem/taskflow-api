<?php

namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TaskCommented extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(private readonly Comment $comment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $task = $this->comment->task;
        $author = $this->comment->user;

        return (new MailMessage)
            ->subject("Новый комментарий к задаче: {$task->title}")
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line("{$author->name} прокомментировал задачу «{$task->title}»:")
            ->line(Str::limit($this->comment->body, 300));
    }
}
