.PHONY: up down logs build test shell docs

up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f app

# Воркер собирается отдельным образом: без него он продолжал бы разбирать
# очередь старым кодом. queue:restart просит его перечитать код после выкладки
build:
	docker compose build app worker
	docker compose up -d app worker
	docker compose exec -T app php artisan queue:restart

# Переменные задаются прямо здесь: в контейнере они выставлены настоящим
# окружением, а оно главнее <env> из phpunit.xml. Без них тесты пошли бы
# в PostgreSQL стенда, реальную очередь Redis и почту. Код в образ копируется
# при сборке, поэтому после правок нужен make build
test:
	docker compose exec -T -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e QUEUE_CONNECTION=sync -e MAIL_MAILER=array -e CACHE_STORE=array -e SESSION_DRIVER=array app php artisan test

shell:
	docker compose exec app sh

docs:
	docker compose exec -T app php artisan l5-swagger:generate
