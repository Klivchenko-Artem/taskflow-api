<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['owner_id', 'name', 'description'])]
class Project extends Model
{
    use HasFactory;

    /** Создатель проекта. Он же единственный, кто может проект удалить. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Участники, включая владельца. Доступ к задачам проверяется по этому списку. */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** Участвует ли пользователь в проекте. */
    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }
}
