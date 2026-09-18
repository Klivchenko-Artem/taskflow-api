<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Трейты, которые стирают базу перед тестом или после него. */
    private const DESTRUCTIVE_TRAITS = [
        RefreshDatabase::class,
        DatabaseMigrations::class,
        DatabaseTruncation::class,
    ];

    /**
     * Тесты, которые стирают базу, гоняются только на sqlite в памяти. Если
     * окружение контейнера перебило настройки phpunit.xml и подключение смотрит
     * в PostgreSQL стенда, лучше упасть сразу, чем снести данные.
     *
     * Проверка стоит здесь, а не в beforeRefreshingDatabase: метод трейта
     * перекрывает метод родителя, и защита там молча не срабатывает.
     */
    protected function setUpTraits()
    {
        if (array_intersect(self::DESTRUCTIVE_TRAITS, class_uses_recursive(static::class)) !== []) {
            $connection = config('database.default');
            $settings = config("database.connections.$connection");

            // DB_URL главнее отдельных настроек: с ним database=:memory:
            // в конфиге ничего не значит, подключение уйдёт по адресу
            if ($connection !== 'sqlite' || ($settings['database'] ?? null) !== ':memory:' || filled($settings['url'] ?? null)) {
                throw new \RuntimeException(
                    "Тесты запущены не на sqlite в памяти (сейчас: $connection). Гоняйте их через make test."
                );
            }
        }

        return parent::setUpTraits();
    }
}
