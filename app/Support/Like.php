<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Поиск подстрокой без учёта регистра, одинаковый на PostgreSQL, MySQL и SQLite.
 *
 * Обычный like на PostgreSQL чувствителен к регистру, а символ экранирования
 * у баз разный (у SQLite его нет вовсе), поэтому он задаётся явно.
 */
final class Like
{
    private const ESCAPE = '!';

    public static function contains(Builder $query, string $column, string $text, string $boolean = 'and'): Builder
    {
        // ilike есть только в PostgreSQL. В MySQL like и так без учёта регистра
        // по сравнению строк, в SQLite без учёта регистра для латиницы
        $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return $query->whereRaw(
            $query->getGrammar()->wrap($column).' '.$operator." ? escape '".self::ESCAPE."'",
            ['%'.self::escape($text).'%'],
            $boolean,
        );
    }

    private static function escape(string $text): string
    {
        return str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'],
            $text,
        );
    }
}
