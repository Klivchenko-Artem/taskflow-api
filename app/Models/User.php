<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Почта без учёта регистра и пробелов по краям: иначе Artem@ и artem@
     * это два аккаунта, а вход строчными не находит того, кто писал заглавными.
     *
     * Одно место на всё приложение: запросы, лимитер и поиск зовут его,
     * а не повторяют у себя. Всё, что пришло не строкой (массив из формы,
     * null), возвращается как есть: разбираться с этим дело валидации,
     * а не наше, иначе лимитер падал бы 500 раньше неё.
     */
    public static function normalizeEmail(mixed $email): mixed
    {
        return is_string($email) ? mb_strtolower(trim($email)) : $email;
    }

    /** Почта пишется в базу уже приведённой, откуда бы ни пришла. */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (mixed $value) => self::normalizeEmail($value));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Проекты, в которых пользователь участвует. */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /** Проекты, которые пользователь создал. */
    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    /** Задачи, назначенные на пользователя. */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }
}
