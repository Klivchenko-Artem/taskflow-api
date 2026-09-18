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
        // Два аккаунта, которые отличаются только регистром, слить сами не можем:
        // у каждого свои проекты и задачи. Пусть решает человек, а не миграция
        $clashes = DB::table('users')
            ->selectRaw('lower(trim(email)) as normalized, count(*) as total')
            ->groupByRaw('lower(trim(email))')
            ->havingRaw('count(*) > 1')
            ->pluck('normalized');

        if ($clashes->isNotEmpty()) {
            throw new RuntimeException(
                'Есть аккаунты с одной почтой в разном регистре, объедините их вручную: '
                .$clashes->join(', ')
            );
        }

        DB::table('users')->update(['email' => DB::raw('lower(trim(email))')]);

        // Индекс по lower(email) держит уникальность и для записей, которые
        // попадут в базу в обход модели
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement('CREATE UNIQUE INDEX '.self::INDEX.' ON users ((lower(email)))'),
            default => DB::statement('CREATE UNIQUE INDEX '.self::INDEX.' ON users (lower(email))'),
        };
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement('DROP INDEX '.self::INDEX.' ON users'),
            default => DB::statement('DROP INDEX '.self::INDEX),
        };
    }
};
