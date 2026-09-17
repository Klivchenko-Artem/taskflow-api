<?php

namespace App\Support;

/**
 * Пользовательский текст для письма.
 *
 * Строки MailMessage проходят через Markdown, и комментарий вида
 * [Войти](https://evil.example) превращался в ссылку от имени сервиса.
 */
final class MailText
{
    public static function escape(string $text): string
    {
        return preg_replace('/([\\\\`*_\[\]()!<>#])/u', '\\\\$1', $text);
    }
}
