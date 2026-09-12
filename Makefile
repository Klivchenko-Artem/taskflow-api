.PHONY: up down logs build test shell docs

up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f app

build:
	docker compose build app

# APP_ENV задаётся прямо здесь: в контейнере он выставлен настоящей переменной
# окружения, а она главнее <env> из phpunit.xml — без этого тесты идут
# по боевой конфигурации. Код в образ копируется при сборке, поэтому
# после правок нужен make build
test:
	docker compose exec -T -e APP_ENV=testing app php artisan test

shell:
	docker compose exec app sh

docs:
	docker compose exec -T app php artisan l5-swagger:generate
