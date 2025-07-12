<div align="center">

# 🚀 KHP Backend

### API Backend moderne construite avec Laravel & GraphQL

[![Laravel](https://img.shields.io/badge/Laravel-12.x-red.svg?style=flat&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg?style=flat&logo=php)](https://php.net)
[![GraphQL](https://img.shields.io/badge/GraphQL-Lighthouse-E10098.svg?style=flat&logo=graphql)](https://lighthouse-php.com)
[![Docker](https://img.shields.io/badge/Docker-Containerized-2496ED.svg?style=flat&logo=docker)](https://docker.com)

_Une API robuste et scalable avec authentification, GraphQL et architecture containerisée_

</div>

---

## 📦 Installation Détaillée

### Prérequis

-   🐳 [Docker](https://www.docker.com/) & [Docker Compose](https://docs.docker.com/compose/)
-   🛠 GNU `make`
-   📦 [Node.js](https://nodejs.org/) (pour Lefthook)

### Étapes d'installation

1. **Récupération du code source**

    ```bash
    git clone https://github.com/votre-utilisateur/khp-backend.git
    cd khp-backend
    ```

2. **Démarrage des services Docker**

    ```bash
    make up
    # Équivalent à: docker-compose up -d
    ```

3. **Installation des dépendances**

    ```bash
    make install
    # Installe les packages PHP et JavaScript
    ```

4. **Configuration de la base de données**

    ```bash
    make migrate
    # Exécute les migrations Laravel
    ```

5. **Configuration des hooks Git** ⚠️ **Important**
    ```bash
    npm install
    # Installe Lefthook pour les hooks pre-commit
    ```

---

## 🐳 Commandes Docker

| Commande       | Description                       |
| -------------- | --------------------------------- |
| `make up`      | Démarrer tous les conteneurs      |
| `make down`    | Arrêter tous les conteneurs       |
| `make restart` | Redémarrer les conteneurs         |
| `make build`   | Reconstruire les images           |
| `make exec`    | Ouvrir un shell dans le conteneur |
| `make up-prod` | Démarrer en mode production       |

### Commandes de développement

| Commande                | Description                      |
| ----------------------- | -------------------------------- |
| `make install`          | Installer les dépendances PHP/JS |
| `make migrate`          | Exécuter les migrations          |
| `make fresh`            | Reset complet de la DB           |
| `make tests`            | Lancer tous les tests            |
| `make cs` / `make pint` | Formater le code                 |
| `make larastan`         | Analyse statique du code         |
| `make erd`              | Générer le diagramme ERD         |

---

## 🌐 Endpoints API

### 🔗 REST API

> 📚 **Documentation complète** : [KHP-API-DOCS](https://github.com/GoofyTeam/KHP-API-DOCS)

**Pour utiliser l'API REST :**

1. Clonez le repository de documentation :

    ```bash
    git clone https://github.com/GoofyTeam/KHP-API-DOCS.git
    ```

2. Suivez les instructions du repository pour configurer et tester l'API

### 📊 GraphQL

| Endpoint    | Description        | Authentification    |
| ----------- | ------------------ | ------------------- |
| `/graphql`  | API GraphQL        | ✅                  |
| `/graphiql` | Interface GraphiQL | ❌ (dev uniquement) |

---

### Qualité de code

Le projet utilise plusieurs outils pour maintenir la qualité :

-   **Laravel Pint** : Formatage automatique du code PHP
-   **Laravel Erd** : Génération de diagrammes ERD
-   **PHPStan + Larastan** : Analyse statique
-   **Lefthook** : Hooks Git pre-commit
-   **PHPUnit** : Tests automatisés

---

<div align="center">

**Fait avec ❤️ par l'équipe GoofyTeam**

</div>
