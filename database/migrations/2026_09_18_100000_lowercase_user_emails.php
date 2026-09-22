<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Почты пользователей строчными и уникальность без учёта регистра.
 *
 * Вход и регистрация приводят почту к строчным, но записи, заведённые
 * до этого, лежат как ввели: Artem@ не входил по artem@ (422), а регистрация
 * на artem@ проходила и заводила второй аккаунт того же человека.
 */
return new class extends Migration
{
    private const INDEX = 'users_email_lower_unique';

    public function up(): void
    {
        // Приводим в PHP, а не в SQL: lower() базы не трогает не-ASCII
        // (SQLite всегда, PostgreSQL с локалью C), и почта с кириллицей
        // или умляутом осталась бы в прежнем регистре, а индекс ниже
        // перестал бы пускать её владельца
        $users = DB::table('users')->select('id', 'email')->orderBy('id')->get();

        $byEmail = [];
        $clashes = [];

        foreach ($users as $user) {
            $normalized = mb_strtolower(trim((string) $user->email));

            if (isset($byEmail[$normalized])) {
                $clashes[] = $byEmail[$normalized]->id;
                $clashes[] = $user->id;

                continue;
            }

            $byEmail[$normalized] = $user;
        }

        // Два аккаунта, которые отличаются только регистром, слить сами не можем:
        // у каждого свои проекты и задачи. Пусть решает человек, а не миграция.
        // В тексте только id: исключение уедет в журнал, а почты там не нужны
        if ($clashes !== []) {
            throw new RuntimeException(
                'Есть аккаунты с одной почтой в разном регистре, объедините их вручную. id: '
                .implode(', ', array_unique($clashes))
            );
        }

        foreach ($byEmail as $normalized => $user) {
            if ((string) $user->email !== $normalized) {
                DB::table('users')->where('id', $user->id)->update(['email' => $normalized]);
            }
        }

        // Индекс по lower(email) держит уникальность и для записей, которые
        // попадут в базу в обход модели. В MariaDB функциональных индексов нет,
        // там уникальность остаётся на модели
        match (DB::getDriverName()) {
            'mysql' => DB::statement('CREATE UNIQUE INDEX '.self::INDEX.' ON users ((lower(email)))'),
            'mariadb' => null,
            default => DB::statement('CREATE UNIQUE INDEX '.self::INDEX.' ON users (lower(email))'),
        };
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'mysql' => DB::statement('DROP INDEX '.self::INDEX.' ON users'),
            'mariadb' => null,
            default => DB::statement('DROP INDEX '.self::INDEX),
        };
    }
};
