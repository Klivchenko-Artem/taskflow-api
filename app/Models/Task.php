<?php

namespace App\Models;

use App\Support\Like;
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
        // Везде filled, а не truthy-проверка: значение "0" это тоже фильтр,
        // а when() счёл бы его пустым и отдал бы весь список
        return $query
            ->when(filled($filters['status'] ?? null), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(filled($filters['priority'] ?? null), fn (Builder $q) => $q->where('priority', $filters['priority']))
            ->when(filled($filters['assignee_id'] ?? null), fn (Builder $q) => $q->where('assignee_id', $filters['assignee_id']))
            ->when(filled($filters['due_before'] ?? null), fn (Builder $q) => $q->whereDate('due_date', '<=', $filters['due_before']))
            ->when(filled($filters['search'] ?? null), fn (Builder $q) => Like::contains($q, 'title', (string) $filters['search']));
    }
}
