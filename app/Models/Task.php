<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'assignee_id', 'title', 'description', 'status', 'priority', 'due_date'])]
class Task extends Model
{
    use HasFactory;

    /**
     * Значения по умолчанию держим и в модели тоже: дефолт в миграции
     * проставляется базой, но только что созданный объект о нём не знает
     * и вернул бы в ответе null.
     */
    protected $attributes = [
        'status' => 'todo',
        'priority' => 'normal',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Фильтры списка задач. Всё считается запросом к базе,
     * чтобы не тянуть в память лишние строки.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $q, $priority) => $q->where('priority', $priority))
            ->when($filters['assignee_id'] ?? null, fn (Builder $q, $id) => $q->where('assignee_id', $id))
            ->when($filters['due_before'] ?? null, fn (Builder $q, $date) => $q->whereDate('due_date', '<=', $date))
            ->when($filters['search'] ?? null, fn (Builder $q, $text) => $q->where('title', 'like', "%{$text}%"));
    }
}
