# Task Tracker API

REST API трекера задач на Laravel 13: проекты, участники, задачи, комментарии.
Токенная авторизация через Sanctum, права доступа на политиках, документация
OpenAPI, 41 тест, запуск одной командой в Docker.

Пет-проект, написан для демонстрации бэкенд-части: чистое API без интерфейса.

## Стек

| Слой | Технологии |
|---|---|
| Бэкенд | PHP 8.3, Laravel 13 |
| Авторизация | Laravel Sanctum (Bearer-токены) |
| База | PostgreSQL 16 (тесты — SQLite в памяти) |
| Документация | OpenAPI 3.0 через L5-Swagger, PHP-атрибуты |
| Тесты | PHPUnit 12, 41 функциональный тест |
| Запуск | Docker + docker compose (php-fpm, nginx, postgres) |

## Запуск

```bash
docker compose up -d --build
```

- API — http://localhost:8000/api
- Документация Swagger UI — http://localhost:8000/api/documentation

Миграции накатываются автоматически при старте.

## Модель данных

```
User ──владеет──> Project <──участвует──> User   (many-to-many, роль в pivot)
                     │
                     └──> Task ──> Comment ──> User
                           │
                           └──назначена──> User
```

- **Project** — проект с владельцем и списком участников;
- **Task** — задача внутри проекта: статус (todo, in_progress, testing, done), приоритет, срок, исполнитель;
- **Comment** — комментарий участника к задаче.

Статусы и приоритеты — PHP-перечисления (`App\Enums\TaskStatus`,
`App\Enums\TaskPriority`), а не строки россыпью: правила валидации и приведение
типов берутся из одного места.

## Эндпоинты

| Метод | Путь | Что делает |
|---|---|---|
| POST | `/api/register` | Регистрация, сразу выдаёт токен |
| POST | `/api/login` | Вход, выдаёт токен |
| POST | `/api/logout` | Отзывает текущий токен |
| GET | `/api/me` | Текущий пользователь |
| GET | `/api/projects` | Проекты пользователя, постранично |
| POST | `/api/projects` | Создать проект |
| GET | `/api/projects/{project}` | Проект с участниками |
| PUT | `/api/projects/{project}` | Изменить проект |
| DELETE | `/api/projects/{project}` | Удалить проект (только владелец) |
| POST | `/api/projects/{project}/members` | Добавить участника (только владелец) |
| GET | `/api/projects/{project}/tasks` | Задачи проекта с фильтрами |
| POST | `/api/projects/{project}/tasks` | Создать задачу |
| GET | `/api/tasks/{task}` | Одна задача |
| PUT | `/api/tasks/{task}` | Изменить задачу |
| DELETE | `/api/tasks/{task}` | Удалить задачу |
| GET | `/api/tasks/{task}/comments` | Комментарии к задаче |
| POST | `/api/tasks/{task}/comments` | Добавить комментарий |

Фильтры списка задач: `status`, `priority`, `assignee_id`, `due_before`,
`search` — считаются запросом к базе и комбинируются между собой.

## Пример работы

```bash
# регистрируемся и забираем токен
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Артём","email":"artem@example.com","password":"secret123","password_confirmation":"secret123"}'

# создаём проект
curl -X POST http://localhost:8000/api/projects \
  -H "Authorization: Bearer ТОКЕН" \
  -H "Content-Type: application/json" \
  -d '{"name":"Переезд на новый сервер"}'

# ставим задачу
curl -X POST http://localhost:8000/api/projects/1/tasks \
  -H "Authorization: Bearer ТОКЕН" \
  -H "Content-Type: application/json" \
  -d '{"title":"Настроить бэкапы","priority":"high","due_date":"2026-10-01"}'

# смотрим только срочные незакрытые
curl "http://localhost:8000/api/projects/1/tasks?priority=high&status=todo" \
  -H "Authorization: Bearer ТОКЕН"
```

## Права доступа

Разграничение — на политиках (`app/Policies`), а не проверками в контроллерах:

- проект видят и правят только его участники;
- удалить проект и добавить людей может только владелец;
- задачи и комментарии доступны участникам того проекта, которому принадлежит задача;
- исполнителем задачи можно назначить только участника этого же проекта —
  проверка на уровне правила валидации.

Посторонний получает `403`, неавторизованный — `401`.

## Тесты

```bash
php artisan test
```

41 функциональный тест на SQLite в памяти. Покрыто: регистрация и вход,
отзыв токена, доступ без токена, изоляция чужих проектов и задач, все правила
валидации, каждый фильтр списка задач, постраничная выдача, каскадное удаление
задач и комментариев вместе с проектом.

## Структура

```
app/
  Enums/                 — TaskStatus, TaskPriority
  Http/
    Controllers/Api/     — Auth, Project, Task, Comment
    Requests/            — валидация каждого запроса отдельным классом
    Resources/           — формат JSON-ответов
  Models/                — User, Project, Task, Comment
  Policies/              — права на проекты и задачи
database/
  migrations/            — схема, индексы под фильтры
  factories/             — фабрики для тестов
docker/                  — nginx.conf, entrypoint
tests/Feature/           — тесты по каждому разделу API
```

## Решения, которые стоит отметить

**Фильтры вынесены в scope модели.** `Task::filter()` собирает запрос через
`when()` — контроллер остаётся коротким, а фильтры комбинируются в любом
сочетании без ветвлений.

**Индексы поставлены под реальные запросы.** Составной индекс
`(project_id, status)` — потому что задачи всегда запрашиваются внутри проекта
и чаще всего с фильтром по статусу.

**Ответы отдаются через API Resources.** Формат ответа описан в одном месте,
пароль и служебные поля наружу не попадают, связанные данные подгружаются
только когда нужны (`whenLoaded`) — без лишних запросов к базе.

**Документация живёт рядом с кодом.** Описание эндпоинтов — PHP-атрибуты прямо
над методами контроллеров, поэтому не расходится с реализацией. Swagger UI
собирается из них автоматически.
