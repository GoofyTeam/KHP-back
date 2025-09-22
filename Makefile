# ================================================
# KHP-Back - Makefile de développement
# ================================================

# Variables
DC = docker-compose
APP = khp-back
ARTISAN = $(DC) exec $(APP) php artisan
COMPOSER = $(DC) exec $(APP) composer
NPM = $(DC) exec $(APP) npm
VENDOR_BIN = $(DC) exec $(APP) vendor/bin

# Définition des cibles qui ne sont pas des fichiers
.PHONY: help up down restart build exec shell status logs \
	        migrate migrate-fresh migrate-status \
	        seed demo-seed lyonnaise-seed rollback test tests coverage \
		cs pint larastan analyse \
		install composer-update npm-update \
		cache-clear optimize fresh reset-minio \
		routes clean erd \
	buildkhp-back-image

# Cible par défaut
.DEFAULT_GOAL := help

# Aide
help:
	@echo "KHP-Back - Commandes disponibles :"
	@echo "--------------------------------"
	@echo "Gestion des conteneurs :"
	@echo "  up           : Démarrer les conteneurs"
	@echo "  down         : Arrêter les conteneurs"
	@echo "  restart      : Redémarrer les conteneurs"
	@echo "  build        : Construire les images"
	@echo "  exec, shell  : Ouvrir un shell dans le conteneur"
	@echo "  status       : Vérifier l'état des conteneurs"
	@echo "  logs         : Afficher les logs des conteneurs"
	@echo "--------------------------------"
	@echo "Base de données & Migrations :"
	@echo "  migrate       : Exécuter les migrations"
	@echo "  migrate-fresh : Rafraîchir la base de données"
	@echo "  migrate-status: Vérifier le statut des migrations"
	@echo "  seed          : Peupler la base de données"
	@echo "  demo-seed     : Réinitialiser puis lancer le DemoSeeder"
	@echo "  lyonnaise-seed: Ajouter l'entreprise La Table des Canuts et ses données métiers"
	@echo "  rollback      : Annuler la dernière migration"
	@echo "--------------------------------"
	@echo "Tests et Qualité de code :"
	@echo "  test, tests  : Exécuter les tests"
	@echo "  coverage     : Générer un rapport de couverture"
	@echo "  cs, pint     : Corriger le style du code"
	@echo "  larastan, analyse : Analyser le code statiquement"
	@echo "--------------------------------"
	@echo "Développement :"
	@echo "  install      : Installer les dépendances"
	@echo "  composer-update : Mettre à jour les dépendances PHP"
	@echo "  npm-update   : Mettre à jour les dépendances JS"
	@echo "  cache-clear  : Nettoyer les caches de l'application"
	@echo "  optimize     : Optimiser l'application"
	@echo "  fresh        : Réinitialiser la BDD et MinIO"
	@echo "  reset-minio  : Réinitialiser le bucket MinIO"
	@echo "  routes       : Lister les routes"

# Gestion des conteneurs
up:
	$(DC) up -d
	@echo "Serveur disponible sur http://localhost:8000"

up-prod:
	$(DC) -f docker-compose.prod.yml up -d

down:
	$(DC) down

restart: down up

build:
	$(DC) build

buildkhp-back-image:
	docker build --no-cache -t khp-back-builded:v0.0.1 -f docker/php/Dockerfile.production .

exec shell:
	$(DC) exec $(APP) bash

status:
	$(DC) ps

logs:
	$(DC) logs -f

# Base de données & Migrations
migrate:
	$(ARTISAN) migrate

migrate-fresh:
	$(ARTISAN) migrate:fresh

migrate-status:
	$(ARTISAN) migrate:status

seed:
	$(ARTISAN) db:seed

demo-seed:
	$(MAKE) reset-minio
	$(ARTISAN) migrate:fresh
	$(ARTISAN) db:seed --class=DemoSeeder
	@echo "🍽️ Données de démonstration installées sur une base fraîche."

lyonnaise-seed:
	$(ARTISAN) db:seed --class=LyonnaiseCompanySeeder
	@echo "🥖 Données Lyonnaises prêtes à l'emploi."

rollback:
	$(ARTISAN) migrate:rollback

# Tests et Qualité de code
test tests:
	$(ARTISAN) test

coverage:
	$(DC) exec -e XDEBUG_MODE=coverage $(APP) php artisan test --coverage

cs pint:
	$(VENDOR_BIN)/pint

larastan analyse:
	$(VENDOR_BIN)/phpstan analyse --memory-limit=2G

# Développement
install:
	$(COMPOSER) install
	$(NPM) install

composer-update:
	$(COMPOSER) update

npm-update:
	$(NPM) update

cache-clear:
	$(ARTISAN) cache:clear
	$(ARTISAN) config:clear
	$(ARTISAN) route:clear
	$(ARTISAN) view:clear

optimize:
	$(ARTISAN) optimize

routes:
	$(ARTISAN) route:list

# Réinitialisation complète
fresh: reset-minio
	$(ARTISAN) migrate:fresh --seed
#	$(ARTISAN) db:seed --class=LyonnaiseCompanySeeder
	@echo "🚀 Environnement frais et prêt !"

# Réinitialise le bucket developp dans MinIO
reset-minio:
	$(DC) exec $(APP) bash -c '\
		mc alias set myminio http://khp-minio:9000 root password && \
		if mc ls myminio | grep -q developp; then \
			echo "🗑️ Suppression du bucket developp..." && \
			mc rb --force myminio/developp; \
		fi && \
		echo "🆕 Création du bucket developp..." && \
		mc mb myminio/developp && \
		echo "✅ Bucket developp réinitialisé avec succès."'

# Nettoyage
clean: down
	$(DC) rm -f
	@echo "🧹 Environnement nettoyé"

erd:
	$(ARTISAN) erd:generate
	@echo "ERD diagram generated at localhost:8000/laravel-erd"
