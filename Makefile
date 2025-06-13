.PHONY: up down restart build exec migrate tests cs pint

up:
	docker-compose up -d
down:
	docker-compose down
restart:
	docker-compose restart
build:
	docker-compose build
exec:
	docker-compose exec khp-back bash
migrate:
	docker-compose exec khp-back php artisan migrate

tests:
	docker-compose exec khp-back php artisan test
cs:
	docker-compose exec khp-back vendor/bin/pint
pint:
	docker-compose exec khp-back vendor/bin/pint
larastan:
	docker-compose exec khp-back ./vendor/bin/phpstan analyse --memory-limit=2G
