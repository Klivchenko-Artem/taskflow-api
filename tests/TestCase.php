<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Тесты с RefreshDatabase пересоздают базу целиком. Если окружение
     * контейнера перебило настройки phpunit.xml и подключение смотрит в
     * PostgreSQL стенда, лучше упасть сразу, чем снести данные.
     *
     * Проверка стоит здесь, а не в beforeRefreshingDatabase: метод трейта
     * перекрывает метод родителя, и защита там молча не срабатывает.
     */
    protected function setUpTraits()
    {
        if (isset(class_uses_recursive(static::class)[RefreshDatabase::class])) {
            $connection = config('database.default');

            if ($connection !== 'sqlite' || config("database.connections.$connection.database") !== ':memory:') {
                throw new \RuntimeException(
                    "Тесты запущены не на sqlite в памяти (сейчас: $connection). Гоняйте их через make test."
                );
            }
        }

        return parent::setUpTraits();
    }
}
