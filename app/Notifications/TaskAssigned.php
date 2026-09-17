<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    /** Четыре попытки: первая и три повтора через минуту, пять и пятнадцать. */
    public int $tries = 4;

    public array $backoff = [60, 300, 900];

    /** Задачу удалили раньше, чем ушло письмо: слать уже не о чем. */
    public bool $deleteWhenMissingModels = true;

    /** Попытки кончились: записываем в журнал, чтобы потеря письма была видна. */
    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error('Уведомление о назначении не доставлено', [
            'task_id' => $this->task->id,
            'error' => $exception->getMessage(),
        ]);
    }

    public function __construct(
        private readonly Task $task,
        private readonly User $assignedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** Пока письмо ждало в очереди, задачу могли переназначить на другого. */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $this->task->assignee_id === $notifiable->id;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Вам назначена задача: {$this->task->title}")
            ->greeting('Здравствуйте, '.MailText::escape($notifiable->name).'!')
            ->line(MailText::escape($this->assignedBy->name).' назначил на вас задачу «'.MailText::escape($this->task->title).'».')
            ->lineIf(
                $this->task->due_date !== null,
                'Срок: ' . $this->task->due_date?->toDateString()
            )
            ->line('Приоритет: ' . $this->task->priority->value);
    }
}
