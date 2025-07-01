.PHONY: up down restart build exec migrate tests cs pint larastan install up-prod fresh

up:
	docker-compose up -d
up-prod:
	docker-compose -f docker-compose.prod.yml up -d
down:
	docker-compose down
restart:
	docker-compose down
	docker-compose up -d
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
install:
	docker-compose exec khp-back composer install
	docker-compose exec khp-back npm install
fresh:
	docker-compose exec khp-back php artisan migrate:fresh --seed
