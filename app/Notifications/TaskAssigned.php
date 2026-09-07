<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    /** Три попытки: почтовый сервер может лежать пару минут. */
    public int $tries = 3;

    /** Между попытками — минута, пять, пятнадцать. */
    public array $backoff = [60, 300, 900];

    public function __construct(
        private readonly Task $task,
        private readonly User $assignedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Вам назначена задача: {$this->task->title}")
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line("{$this->assignedBy->name} назначил на вас задачу «{$this->task->title}».")
            ->lineIf(
                $this->task->due_date !== null,
                'Срок: ' . $this->task->due_date?->toDateString()
            )
            ->line('Приоритет: ' . $this->task->priority->value);
    }
}
