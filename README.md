# GML-SHIELD

Application de gestion sécurisée des missions de transport pour GreenMove Logistics.

Projet réalisé dans le cadre du bloc optionnel du Mastère CTO & Tech Lead.

## Stack technique

- Backend : Laravel 13, PHP 8.5 et Apache
- Frontend : React, TypeScript, Vite et Material UI
- Base de données : PostgreSQL 18
- Conteneurisation : Docker et Docker Compose

## Prérequis

- Docker Desktop avec moteur Linux
- Docker Compose
- Git

PHP, Composer, Node.js et PostgreSQL sont fournis par les conteneurs Docker. Leur installation sur Windows n'est pas nécessaire pour exécuter l'application.

## Installation locale

### 1. Configurer Docker Compose

Copier `.env.example` vers `.env` à la racine du dépôt.

Définir un mot de passe local robuste dans `DB_PASSWORD`.

### 2. Configurer Laravel

Copier `backend/.env.example` vers `backend/.env`.

Configurer les paramètres de connexion :

```dotenv
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=gml_shield
DB_USERNAME=gml_shield
DB_PASSWORD=
```

Renseigner le même mot de passe PostgreSQL dans les deux fichiers `.env`.

### 3. Construire et démarrer

```bash
docker compose up -d --build
```

### 4. Générer la clé Laravel

Uniquement si `APP_KEY` est vide :

```bash
docker compose exec api php artisan key:generate
```

### 5. Initialiser la base de données

```bash
docker compose exec api php artisan migrate
```

## Accès local

- Frontend : http://localhost:5173
- Backend Laravel : http://localhost:8000

PostgreSQL n'est pas exposé sur l'hôte.

## Vérifications qualité

### Frontend

```bash
docker compose exec front npm run lint
docker compose exec front npm run build
```

### Backend

```bash
docker compose exec api php artisan test
```

### Configuration Docker

```bash
docker compose config -q
```

## Arrêter les conteneurs

```bash
docker compose down
```

Les volumes persistants, notamment celui de PostgreSQL, sont conservés.

Attention : `docker compose down -v` supprime également les volumes de la stack et peut entraîner la perte des données locales.

## Sécurité

- Les fichiers `.env` réels sont exclus de Git.
- Les mots de passe et clés d'application ne doivent jamais être publiés.
- PostgreSQL est accessible uniquement depuis le réseau Docker.
- Les ports de développement sont liés à `127.0.0.1`.
- Les images Docker sont analysées avec Docker Scout.
- Les dépendances sont verrouillées dans `composer.lock` et `package-lock.json`.

## État du projet

Le socle technique est initialisé.

Les fonctionnalités métier (authentification, missions, documents, audit et notifications) seront implémentées progressivement.

## Déploiement

L'environnement actuel est destiné au développement local.

Le déploiement de production fera l'objet d'une configuration Docker Compose adaptée, avec HTTPS, gestion des secrets, sauvegardes et contrôles de sécurité.
