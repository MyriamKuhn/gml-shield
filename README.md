# GML-SHIELD

**Application de gestion sécurisée des missions de transport — GreenMove Logistics**

GML-SHIELD est une application web full-stack permettant aux équipes logistiques de créer, attribuer et suivre des missions de transport, tout en protégeant les informations sensibles et en assurant la traçabilité des opérations.

Le projet est développé dans le cadre du **Mastère CTO & Tech Lead — Studi**.

L'application est fonctionnelle, conteneurisée et déployée sur un serveur K8 avec HTTPS et communications WebSocket sécurisées.

**Application de production :** https://gml-shield.mkcodecreations.dev

## 1. Fonctionnalités principales

### Gestion des utilisateurs

- Authentification par session Laravel Sanctum.
- Trois rôles : `ADMIN`, `DISPATCHER` et `DRIVER`.
- Gestion des comptes chauffeurs et répartiteurs par l'administrateur.
- Activation et désactivation des comptes.
- Contrôles d'accès côté backend selon les autorisations métier.

### Gestion des missions

- Création, consultation et modification des missions.
- Attribution des missions aux chauffeurs.
- Suivi des statuts : `PENDING`, `IN_PROGRESS`, `COMPLETED`, `CANCELLED`.
- Démarrage, clôture et annulation selon les droits de l'utilisateur.
- Affichage des missions autorisées pour chaque rôle.

### Sécurité et traçabilité

- Chiffrement AES-256-GCM des codes secrets des missions.
- Révélation des codes secrets à la demande, selon les autorisations.
- Stockage privé des justificatifs de livraison.
- Journal d'audit des opérations sensibles.
- Protection CSRF, limitation des connexions et vérification des comptes actifs.

### Documents et temps réel

- Téléversement de justificatifs PDF, JPEG ou PNG (10 Mo maximum).
- Téléchargement sécurisé des justificatifs par les utilisateurs autorisés.
- Notifications temps réel via Laravel Reverb et WebSocket.
- Actualisation de l'interface à la réception des événements.

## 2. Stack technique

| Composant | Technologies |
|---|---|
| Frontend | React 19, TypeScript 6, Vite 8, Material UI 9 |
| Backend | PHP 8.5, Laravel 13 |
| API | API REST, documentation OpenAPI 3.1 |
| Authentification | Laravel Sanctum |
| Base de données | PostgreSQL 18 |
| Temps réel | Laravel Reverb, Laravel Echo |
| Files d'attente | Laravel Queues, pilote `database` |
| Conteneurisation | Docker, Docker Compose |
| Intégration continue | GitHub Actions |
| Production | Docker Compose, Nginx, reverse proxy HTTPS/WSS |

L'environnement local est composé de six services Docker : `front`, `api`, `db`, `reverb`, `queue-worker` et `broadcast-worker`.

## 3. Structure du dépôt

```text
gml-shield/
├── .github/
│   └── workflows/
│       └── ci.yml
├── backend/
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── routes/
│   ├── tests/
│   └── README.md
├── frontend/
│   ├── public/
│   ├── src/
│   └── README.md
├── deploy/
│   ├── Dockerfile.api
│   ├── Dockerfile.front
│   ├── Caddyfile.gml-shield
│   ├── backend.env.example
│   └── README-K8.md
├── compose.yml
├── compose.prod.yml
├── deploy-prod.sh
├── .env.example
└── README.md
```

## 4. Installation locale

### Prérequis

- Git.
- Docker Desktop ou Docker Engine.
- Docker Compose V2.
- Un terminal compatible avec Docker.

Les dépendances PHP, Composer, Node.js et PostgreSQL sont fournies par les conteneurs de développement.

### Étape 1 — Récupérer le projet

```bash
git clone https://github.com/MyriamKuhn/gml-shield.git
cd gml-shield
git switch dev
```

La branche `dev` est utilisée pour l'intégration des développements.

### Étape 2 — Configurer les variables d'environnement

Créer les fichiers de configuration locaux à partir des exemples :

```bash
cp .env.example .env
cp backend/.env.example backend/.env
```

Configurer dans les deux fichiers le même mot de passe PostgreSQL (`DB_PASSWORD`).

Dans `backend/.env`, renseigner également les paramètres Laravel, la clé de chiffrement métier `SECRET_CODE_KEY` et les variables nécessaires à Laravel Reverb.

`SECRET_CODE_KEY` doit contenir une clé aléatoire de 32 octets encodée en Base64, distincte de `APP_KEY`.

Exemple de génération :

```bash
openssl rand -base64 32
```

**Important :** la configuration Reverb doit être terminée avant de tester les notifications temps réel. Se référer à [backend/README.md](backend/README.md) et [frontend/README.md](frontend/README.md) pour les valeurs et les paramètres à vérifier.

Ne jamais versionner les fichiers `.env` réels.

### Étape 3 — Construire et démarrer les conteneurs

```bash
docker compose up -d --build
```

Contrôler les services :

```bash
docker compose ps
```

### Étape 4 — Initialiser Laravel et PostgreSQL

Générer la clé Laravel si `APP_KEY` est vide :

```bash
docker compose exec api php artisan key:generate
```

Exécuter les migrations :

```bash
docker compose exec api php artisan migrate
```

### Étape 5 — Créer obligatoirement le premier administrateur

**Une installation neuve de GML-SHIELD nécessite impérativement la création d'un premier compte `ADMIN` actif.**

Les migrations initialisent la structure de la base de données, mais ne créent pas automatiquement un administrateur utilisable.

Ce premier compte est indispensable pour accéder aux fonctionnalités d'administration et créer les utilisateurs `DISPATCHER` et `DRIVER`.

La procédure sécurisée d'initialisation du premier administrateur est décrite dans :

[backend/README.md — Initialisation du premier administrateur](backend/README.md#initialisation-du-premier-administrateur)

Ne jamais utiliser de mot de passe prédéfini ou publié dans le dépôt.

### Étape 6 — Accéder à l'application

| Service | Adresse locale |
|---|---|
| Frontend React | http://localhost:5173 |
| Backend Laravel | http://localhost:8000 |
| Laravel Reverb | localhost:8080 |
| Documentation OpenAPI | http://localhost:8000/docs/api |

Après connexion avec le compte administrateur, créer les comptes répartiteurs et chauffeurs nécessaires aux tests métier.

Aucun utilisateur de démonstration complet n'est provisionné automatiquement par cette procédure.

## 5. Documentation automatique de l'API

GML-SHIELD utilise **Dedoc Scramble** pour générer automatiquement sa documentation REST au format OpenAPI 3.1.

Cette solution permet d'explorer les routes, les paramètres et les structures de l'API à partir du code Laravel.

| Ressource | URL locale |
|---|---|
| Interface interactive | http://localhost:8000/docs/api |
| Spécification OpenAPI JSON | http://localhost:8000/docs/api.json |

**L'accès aux deux endpoints est réservé aux administrateurs authentifiés et actifs.**

Des tests automatisés contrôlent le refus d'accès aux visiteurs non authentifiés, chauffeurs, répartiteurs et administrateurs désactivés.

La documentation technique détaillée se trouve dans [backend/README.md](backend/README.md).

## 6. Tests et assurance qualité

GML-SHIELD dispose de tests automatisés backend et de contrôles de qualité frontend.

### Tests backend

```bash
docker compose exec api php artisan test
```

Les tests couvrent notamment :

- L'authentification et les autorisations.
- La gestion des utilisateurs et des rôles.
- Les missions et leurs transitions d'état.
- Le chiffrement des codes secrets.
- Les justificatifs de livraison et leur accès.
- Le journal d'audit et les événements sensibles.
- Les canaux de diffusion WebSocket.
- La sécurité d'accès à la documentation OpenAPI.

### Vérifications frontend

```bash
docker compose exec front npx tsc -b
docker compose exec front npm run lint
docker compose exec front npm run build
```

### Vérification de Docker Compose

```bash
docker compose config -q
```

### Intégration continue

Le workflow `.github/workflows/ci.yml` exécute :

- Les tests Laravel avec une base SQLite en mémoire.
- La vérification TypeScript.
- ESLint.
- La compilation du frontend.

La CI se déclenche sur les pushes vers `dev` et les Pull Requests ciblant `dev`.

### Tests fonctionnels

Des parcours de bout en bout ont également été vérifiés manuellement sur l'application déployée :

- Connexion et utilisation des interfaces par rôle.
- Création, attribution et suivi de missions.
- Chiffrement et révélation contrôlée des secrets.
- Téléversement et téléchargement de justificatifs PDF et image.
- Notifications WebSocket et rafraîchissement en direct.

Ces validations manuelles complètent les tests automatisés.

## 7. Sécurité

Les principales mesures appliquées au projet comprennent :

- Authentification Laravel Sanctum.
- Protection CSRF.
- Contrôles d'autorisation côté serveur.
- Chiffrement authentifié AES-256-GCM.
- Hachage des mots de passe.
- Stockage privé des documents.
- Validation des fichiers téléversés.
- Journalisation des actions sensibles.
- Isolation réseau Docker.
- Utilisation de HTTPS et WSS en production.
- Séparation des secrets et du code source.

Les mesures d'exploitation, de sauvegarde et de surveillance doivent être maintenues indépendamment des contrôles applicatifs.

## 8. Stratégie Git et intégration

Le développement utilise plusieurs catégories de branches :

| Branche | Rôle |
|---|---|
| `feature/*`, `docs/*`, `chore/*` | Travaux isolés |
| `dev` | Branche d'intégration |
| `main` | Version principale validée |
| `release/*` | Préparation des publications |
| `production` | Version déployable sur le serveur |

Les modifications sont intégrées au moyen de Pull Requests.

Le script `deploy-prod.sh` permet de préparer une publication sélective depuis `main` vers une branche intermédiaire destinée à `production`.

```bash
./deploy-prod.sh --dry-run
```

Puis, après validation :

```bash
./deploy-prod.sh --publish
```

Le script utilise le nom de branche configuré dans `RELEASE_BRANCH`. Ce nom doit être vérifié avant une nouvelle publication.

**Le script prépare une publication Git : il ne redémarre pas automatiquement les conteneurs du serveur.**

## 9. Production et exploitation

L'application est déployée à l'adresse :

https://gml-shield.mkcodecreations.dev

L'environnement de production utilise `compose.prod.yml`, des images Docker dédiées, un réseau privé et un réseau `proxy` externe.

Les fichiers `.env` et `deploy/backend.env` contiennent des configurations sensibles propres au serveur et ne doivent pas être publiés.

Les volumes Docker assurent la persistance des données PostgreSQL et des fichiers applicatifs.

**Attention :** ne jamais utiliser `docker compose down -v` sans décision explicite concernant la suppression des données et les sauvegardes.

La procédure de déploiement, les vérifications, les précautions de maintenance et les questions de restauration sont détaillées dans [deploy/README-K8.md](deploy/README-K8.md).

## 10. Maintenance locale

Vérifier l'état des services :

```bash
docker compose ps
```

Consulter les journaux de l'API :

```bash
docker compose logs --tail=100 api
```

Arrêter l'environnement local sans supprimer les volumes :

```bash
docker compose down
```

Les commandes de production doivent utiliser explicitement `compose.prod.yml` et suivre la procédure documentée pour le serveur concerné.

## 11. Documentation détaillée

| Fichier | Contenu |
|---|---|
| [backend/README.md](backend/README.md) | Installation Laravel, premier administrateur, API, OpenAPI, sécurité, tests, audit et Reverb |
| [frontend/README.md](frontend/README.md) | React, composants, navigation, authentification, WebSocket et qualité du code |
| [deploy/README-K8.md](deploy/README-K8.md) | Architecture de production, HTTPS/WSS, secrets, publication et exploitation |

Le **Dossier d'Architecture Technique (DAT)** complète ces documentations en présentant les décisions d'architecture, les arbitrages et les justifications liées au contexte de l'examen Studi.

## 12. Limites et responsabilités

GML-SHIELD est un projet pédagogique disposant d'une implémentation fonctionnelle et d'un déploiement réel.

Les validations réalisées ne constituent pas une certification de cybersécurité, un audit d'intrusion ou une garantie de haute disponibilité.

L'installation dans un nouvel environnement nécessite une configuration adaptée des secrets, du réseau et des utilisateurs.

Toute évolution doit être accompagnée de tests appropriés et d'une mise à jour de la documentation.

---

**Projet :** GML-SHIELD
**Contexte :** GreenMove Logistics
**Formation :** Mastère CTO & Tech Lead — Studi
**Nature :** Projet pédagogique de conception, développement et déploiement d'une application sécurisée.