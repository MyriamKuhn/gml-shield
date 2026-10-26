# GML-SHIELD — Backend Laravel

**API REST sécurisée de gestion des missions de transport — GreenMove Logistics**

Le backend GML-SHIELD est développé avec Laravel 13 et PHP. Il centralise la logique métier, les autorisations, le stockage des données, le chiffrement, la gestion des justificatifs, l'audit et les événements temps réel.

Il constitue la source de vérité pour toutes les opérations métier. Les contrôles de sécurité sont appliqués côté serveur, indépendamment de l'interface React.

Pour une présentation générale du projet, consulter le [README principal](../README.md).

## 1. Technologies

| Domaine | Technologie |
|---|---|
| Framework | Laravel 13 |
| Langage | PHP 8.5 dans l'image Docker locale |
| Base de données | PostgreSQL 18 |
| ORM et migrations | Eloquent / Laravel Migrations |
| Authentification | Laravel Sanctum, sessions et CSRF |
| Chiffrement | OpenSSL AES-256-GCM |
| Documentation API | Dedoc Scramble, OpenAPI 3.1 |
| Temps réel | Laravel Reverb |
| Files d'attente | Laravel Queues, pilote `database` |
| Tests | PHPUnit / Laravel Test Framework |
| Serveur HTTP local | Apache dans Docker |

Les dépendances PHP sont définies dans `composer.json` et verrouillées dans `composer.lock`.

## 2. Organisation du backend

```text
backend/
├── app/
│   ├── Enums/
│   ├── Events/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Jobs/
│   ├── Models/
│   ├── Policies/
│   ├── Providers/
│   └── Services/
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── docker/
├── public/
├── routes/
│   ├── api.php
│   ├── channels.php
│   └── web.php
├── tests/
│   ├── Feature/
│   └── Unit/
├── .env.example
├── artisan
├── composer.json
├── composer.lock
├── Dockerfile
└── README.md
```

### Responsabilités principales

- `Controllers/Api` : traitement des requêtes HTTP.
- `Requests` : validation des données et autorisations associées.
- `Models` : représentation des entités persistées.
- `Policies` : règles d'autorisation métier.
- `Services` : traitements spécialisés, notamment le chiffrement.
- `Jobs` : opérations asynchrones.
- `Events` : événements métier diffusés en temps réel.
- `Middleware` : contrôles transversaux d'accès et d'audit.
- `database/migrations` : évolution versionnée du schéma PostgreSQL.
- `tests` : tests automatisés unitaires et fonctionnels.

## 3. Installation locale

### Prérequis

- Docker et Docker Compose V2.
- Git.
- Un terminal.
- Accès au dépôt GML-SHIELD.

L'installation standard repose sur le fichier `compose.yml` à la racine.

Les commandes suivantes doivent être exécutées depuis la **racine du dépôt**, sauf indication contraire.

### 3.1. Configurer PostgreSQL

Créer le fichier `.env` racine à partir de `.env.example` :

```bash
cp .env.example .env
```

Définir un mot de passe robuste :

```dotenv
DB_PASSWORD=<mot-de-passe-local>
```

### 3.2. Configurer Laravel

```bash
cp backend/.env.example backend/.env
```

Adapter les paramètres essentiels :

```dotenv
APP_NAME=GML-SHIELD
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=gml_shield
DB_USERNAME=gml_shield
DB_PASSWORD=<meme-mot-de-passe>
```

`APP_DEBUG=true` est réservé au développement local. Il doit être désactivé en production.

### 3.3. Générer la clé de chiffrement métier

Le backend utilise une clé dédiée pour chiffrer les codes secrets des missions.

Générer une valeur aléatoire :

```bash
openssl rand -base64 32
```

Reporter le résultat dans `backend/.env` :

```dotenv
SECRET_CODE_KEY=<cle-base64-generee>
```

Cette clé doit contenir 32 octets aléatoires avant encodage Base64. Elle ne doit pas être identique à `APP_KEY`.

**Ne jamais remplacer ou perdre cette clé lorsque des missions chiffrées existent déjà : leurs codes secrets pourraient devenir illisibles.**

### 3.4. Configurer Laravel Reverb

Pour une installation locale Docker, préparer les variables suivantes dans `backend/.env` :

```dotenv
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database

REVERB_APP_ID=100001
REVERB_APP_KEY=<identifiant-reverb-local>
REVERB_APP_SECRET=<secret-reverb-local>

REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http

REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=localhost

REVERB_APP_ACCEPT_CLIENT_EVENTS_FROM=none
```

`REVERB_APP_ID`, `REVERB_APP_KEY` et `REVERB_APP_SECRET` doivent être cohérents entre les différents services.

Pour une installation locale neuve, générer des valeurs aléatoires distinctes pour la clé et le secret Reverb :

```bash
openssl rand -hex 16
openssl rand -hex 32
```

Ne pas publier ces valeurs.

Les variables `VITE_REVERB_*` utilisées par React doivent être compatibles avec cette configuration. Le navigateur se connecte via `localhost:8080`, tandis que Laravel utilise le nom de service Docker `reverb`.

Consulter également [frontend/README.md](../frontend/README.md).

### 3.5. Démarrer les services

```bash
docker compose up -d --build
```

Vérifier les conteneurs :

```bash
docker compose ps
```

Les services attendus sont :

- `api`
- `db`
- `front`
- `reverb`
- `queue-worker`
- `broadcast-worker`

### 3.6. Initialiser Laravel

Si `APP_KEY` est vide :

```bash
docker compose exec api php artisan key:generate
```

Exécuter les migrations :

```bash
docker compose exec api php artisan migrate
```

Ne pas exécuter les commandes destructives `migrate:fresh`, `db:wipe` ou des suppressions de volumes sur une base contenant des données à conserver.

## 4. Initialisation du premier administrateur

### Pourquoi cette étape est obligatoire

**Une nouvelle installation de GML-SHIELD ne crée pas automatiquement un administrateur opérationnel.**

Les migrations initialisent les tables, mais ne provisionnent pas de compte `ADMIN`.

Le contrôleur de gestion des utilisateurs permet à un administrateur actif de créer des comptes `DISPATCHER` et `DRIVER`, mais il ne permet pas de créer un autre administrateur.

Il est donc nécessaire d'initialiser le premier compte administrateur par une procédure contrôlée.

### Procédure locale avec Laravel Tinker

Depuis la racine du dépôt :

```bash
docker compose exec api php artisan tinker
```

Dans la console interactive Tinker, saisir les instructions suivantes, une par une :

```php
use App\Models\User;
use App\Enums\UserRole;
```

Renseigner un nom et une adresse électronique propres à l'installation :

```php
$name = 'Administrateur GML-SHIELD';
$email = 'admin@example.test';
```

Saisir le mot de passe de manière interactive, sans l'inscrire dans le dépôt :

```php
$password = \Laravel\Prompts\password('Mot de passe administrateur');
```

Créer le compte :

```php
$user = new User();
$user->name = $name;
$user->email = $email;
$user->password = $password;
$user->role = UserRole::ADMIN;
$user->is_active = true;
$user->save();
```

Le modèle `User` utilise le cast Laravel `hashed` pour le mot de passe. Il est donc haché lors de son affectation.

Le rôle et l'état d'activation sont définis explicitement, conformément à la structure du modèle.

Pour vérifier la création du compte sans afficher son mot de passe :

```php
$user->only(['id', 'name', 'email', 'role', 'is_active']);
```

Quitter Tinker :

```php
exit
```

**Précautions :**

- Utiliser un mot de passe robuste et unique, d'au moins 12 caractères par convention de sécurité du projet.
- Vérifier que l'adresse électronique ne correspond pas à un compte déjà existant.
- Ne pas utiliser cette procédure pour créer des administrateurs supplémentaires sans validation.
- Ne pas afficher ni partager le mot de passe dans les journaux ou les captures d'écran.
- Ne pas utiliser de compte ou de mot de passe de démonstration en production.

Après cette étape, ouvrir `http://localhost:5173` et se connecter avec le compte administrateur.

L'administrateur pourra ensuite créer les comptes chauffeurs et répartiteurs nécessaires.

### Remarque sur les seeders

Le `DatabaseSeeder` fourni dans le dépôt est un exemple Laravel de génération de données. Il ne constitue pas une procédure de provisionnement du premier administrateur.

Ne pas supposer qu'un simple `db:seed` initialise une installation GML-SHIELD utilisable.

## 5. Authentification et gestion des accès

### Authentification

L'application utilise Laravel Sanctum en mode SPA, avec authentification basée sur les sessions.

Le frontend récupère d'abord le cookie CSRF via :

```text
GET /sanctum/csrf-cookie
```

Il effectue ensuite la connexion avec :

```text
POST /api/auth/login
```

Les principales routes d'authentification sont :

| Méthode | Route | Fonction |
|---|---|---|
| POST | `/api/auth/login` | Connexion |
| GET | `/api/user` | Utilisateur connecté |
| POST | `/api/logout` | Déconnexion |

La route de connexion est limitée à cinq tentatives par minute par le middleware Laravel.

La session est régénérée après authentification et invalidée lors de la déconnexion.

Les utilisateurs désactivés ne peuvent pas se connecter.

### Rôles métier

| Rôle | Responsabilités |
|---|---|
| `ADMIN` | Supervision, gestion des utilisateurs, consultation de l'audit et de la documentation API |
| `DISPATCHER` | Création, attribution et suivi de ses missions, consultation de leurs justificatifs |
| `DRIVER` | Consultation de ses missions attribuées, démarrage, transmission d'un justificatif et clôture |

### Autorisations

Les politiques Laravel appliquent notamment les règles suivantes :

- Un dispatcher consulte uniquement les missions dont il est le créateur.
- Un chauffeur consulte les missions qui lui sont attribuées.
- Un administrateur peut superviser les missions.
- L'attribution ou la modification d'une mission concerne une mission en attente et un utilisateur autorisé.
- Le démarrage est réservé au chauffeur affecté.
- La clôture est réservée au chauffeur affecté et nécessite au moins un justificatif enregistré.
- Le téléchargement des justificatifs est réservé au dispatcher créateur de la mission.
- Les missions ne peuvent pas être supprimées via la politique métier, afin de préserver la traçabilité.

Les décisions d'autorisation sont prises dans le backend, indépendamment des éléments affichés par l'interface.

## 6. Gestion des missions

Les missions suivent quatre statuts :

| Statut | Signification |
|---|---|
| `PENDING` | Mission en attente |
| `IN_PROGRESS` | Mission démarrée |
| `COMPLETED` | Mission terminée |
| `CANCELLED` | Mission annulée |

Le cycle de vie est encadré par les autorisations et les règles de transition.

Les missions peuvent être créées, affectées, modifiées lorsqu'elles sont encore en attente, démarrées par leur chauffeur, puis clôturées après transmission d'un justificatif.

Le code métier empêche notamment la clôture d'une mission dépourvue de preuve de livraison.

### Routes principales

| Méthode | Route |
|---|---|
| GET | `/api/missions` |
| POST | `/api/missions` |
| GET | `/api/missions/{mission}` |
| PATCH | `/api/missions/{mission}` |
| PATCH | `/api/missions/{mission}/assign` |
| PATCH | `/api/missions/{mission}/status` |
| PATCH | `/api/missions/{mission}/cancel` |
| GET | `/api/missions/{mission}/secret-code` |

Pour consulter les routes effectivement enregistrées :

```bash
docker compose exec api php artisan route:list --path=api
```

## 7. Chiffrement des codes secrets

Les codes secrets des missions sont chiffrés par `SecretCodeEncryptionService`.

Le service utilise :

- AES-256-GCM ;
- une clé dédiée de 256 bits ;
- un nonce aléatoire de 12 octets ;
- un tag d'authentification de 16 octets ;
- un format de stockage versionné.

La clé secrète n'est jamais stockée avec les données chiffrées.

### Révélation du secret

La valeur en clair n'est pas renvoyée dans les réponses ordinaires de consultation des missions.

Elle est révélée à la demande via :

```text
GET /api/missions/{mission}/secret-code
```

Cette opération est réservée au chauffeur effectivement affecté à une mission au statut `IN_PROGRESS`.

L'administrateur, le dispatcher et les autres chauffeurs ne disposent pas de cette autorisation.

Cette décision privilégie la confidentialité des informations sensibles. Elle constitue un choix d'implémentation explicité dans le Dossier d'Architecture Technique (DAT), notamment concernant la différence avec la formulation de certaines annexes Studi.

### Points de vigilance

- Conserver `SECRET_CODE_KEY` en lieu sûr.
- Ne jamais exposer les secrets dans les logs.
- Ne pas changer la clé sans procédure de migration des données chiffrées.
- Éviter toute mise en cache des réponses contenant des secrets.

## 8. Justificatifs de livraison

Le backend accepte les documents suivants :

| Format | MIME |
|---|---|
| PDF | `application/pdf` |
| JPEG | `image/jpeg` |
| PNG | `image/png` |

La taille maximale est fixée à 10 Mo.

La validation utilise le type réel du fichier, et non uniquement son extension déclarée par le navigateur.

### Téléversement

```text
POST /api/missions/{mission}/documents
```

L'envoi est réservé au chauffeur affecté, lorsque la mission est au statut `IN_PROGRESS`.

Le fichier est conservé sur le disque Laravel `local`, dans un espace de stockage privé.

Les métadonnées du document sont enregistrées en base.

### Consultation et téléchargement

```text
GET /api/missions/{mission}/documents
GET /api/missions/{mission}/documents/{document}
```

Ces opérations sont réservées au dispatcher créateur de la mission.

Le backend vérifie notamment :

- Les autorisations de l'utilisateur.
- L'appartenance du document à la mission.
- La validité du chemin de stockage.
- L'existence du fichier.
- La cohérence du type MIME.

Les chemins internes de stockage ne sont pas exposés dans les réponses ordinaires.

Une journalisation d'audit accompagne le téléchargement autorisé.

### Persistance

Les documents de production sont stockés dans un volume Docker persistant.

La sauvegarde du seul PostgreSQL ne suffit donc pas à préserver l'ensemble des justificatifs : le stockage des fichiers doit aussi être pris en compte.

## 9. Notifications temps réel

GML-SHIELD utilise Laravel Reverb pour les communications WebSocket.

Le backend émet des événements métier, notamment lors de l'attribution de missions, et les diffuse vers les clients autorisés.

Le système utilise :

- Laravel Broadcasting ;
- Laravel Reverb ;
- des canaux authentifiés ;
- un worker de diffusion asynchrone ;
- Laravel Echo côté frontend.

### Services concernés

| Service | Fonction |
|---|---|
| `api` | Enregistrement des opérations métier |
| `db` | Stockage des données et des tâches |
| `reverb` | Serveur WebSocket |
| `broadcast-worker` | Traitement de la file `broadcasts` |

### Contrôle des services

```bash
docker compose ps
```

Consulter les journaux Reverb :

```bash
docker compose logs --tail=100 reverb
```

Consulter les journaux du worker :

```bash
docker compose logs --tail=100 broadcast-worker
```

En développement local, les connexions WebSocket utilisent le port `8080`.

En production, elles transitent par le reverse proxy en WSS.

## 10. Journal d'audit

Le système dispose d'un journal des événements sensibles.

La collecte intervient notamment lors de requêtes API, de tentatives d'authentification et de certaines opérations métier.

Les événements sont enregistrés par un traitement asynchrone dédié.

Le service `queue-worker` consomme la file `audit`.

L'API de consultation est :

```text
GET /api/audit-logs
```

L'accès est réservé aux administrateurs actifs.

Les événements d'audit ne doivent pas contenir de mots de passe, de clés secrètes ni de contenus sensibles inutiles.

### Vérifications

```bash
docker compose logs --tail=100 queue-worker
```

Les tests backend couvrent également les comportements lors d'échecs de journalisation ou de traitement.

## 11. Documentation automatique de l'API — Scramble

GML-SHIELD utilise **Dedoc Scramble** pour générer une documentation OpenAPI 3.1 depuis le code Laravel.

Il n'est pas nécessaire de maintenir manuellement un fichier Swagger décrivant toutes les routes.

### Endpoints

| Ressource | URL locale |
|---|---|
| Documentation interactive | `http://localhost:8000/docs/api` |
| Spécification OpenAPI JSON | `http://localhost:8000/docs/api.json` |

### Sécurité

Les deux endpoints sont protégés par :

- Le middleware web.
- Laravel Sanctum.
- La permission `viewApiDocs`.

Cette permission est accordée uniquement aux administrateurs actifs.

Le test `ApiDocumentationAccessTest.php` vérifie notamment :

- Le refus des utilisateurs non authentifiés.
- Le refus des chauffeurs.
- Le refus des dispatchers.
- Le refus des administrateurs désactivés.
- L'accès autorisé pour un administrateur actif.

### Configuration

La documentation est configurée dans :

```text
config/scramble.php
```

Le projet utilise le moteur de rendu Elements et une spécification OpenAPI 3.1.

La documentation est automatiquement dérivée du code, mais son contenu et ses descriptions doivent rester vérifiés lors des évolutions de l'API.

## 12. Tests automatisés

Les tests Laravel se trouvent dans :

```text
backend/tests/
```

Ils sont organisés en tests `Feature` et `Unit`.

### Lancer les tests

Depuis la racine :

```bash
docker compose exec api php artisan test
```

Depuis un environnement PHP configuré directement dans le répertoire backend :

```bash
php artisan test
```

### Périmètre couvert

Les fichiers de tests couvrent notamment :

| Domaine | Exemples |
|---|---|
| Authentification | Connexion, déconnexion, limitation des tentatives |
| Utilisateurs | Rôles, activation, désactivation, gestion des comptes |
| Missions | Création, attribution, statuts, politiques |
| Chiffrement | Service AES-GCM, confidentialité, altération des données |
| Documents | Téléversement, validation, accès, téléchargement |
| Audit | Enregistrement, accès, gestion des échecs |
| WebSocket | Autorisation des canaux et événements |
| Documentation API | Accès aux endpoints OpenAPI |

### CI GitHub Actions

Le workflow `.github/workflows/ci.yml` exécute les tests backend lors des événements configurés sur `dev`.

Les tests CI utilisent SQLite en mémoire, ce qui évite d'interagir avec la base PostgreSQL locale ou de production.

### Tests fonctionnels manuels

En complément, les parcours suivants ont été vérifiés sur le déploiement :

- Connexion par rôle.
- Création et attribution d'une mission.
- Démarrage et clôture.
- Révélation contrôlée des codes secrets.
- Téléversement et récupération de justificatifs PDF et PNG.
- Notifications WebSocket et affichage des mises à jour en direct.

Les tests manuels ne remplacent pas les tests automatisés.

## 13. Commandes de diagnostic

Afficher les services :

```bash
docker compose ps
```

Afficher les routes API :

```bash
docker compose exec api php artisan route:list --path=api
```

Vérifier les migrations :

```bash
docker compose exec api php artisan migrate:status
```

Consulter les logs de l'API :

```bash
docker compose logs --tail=100 api
```

Consulter les logs du worker d'audit :

```bash
docker compose logs --tail=100 queue-worker
```

Consulter les logs Reverb :

```bash
docker compose logs --tail=100 reverb
```

Vérifier les tests :

```bash
docker compose exec api php artisan test
```

Ces commandes concernent l'environnement local `compose.yml`.

En production, se référer exclusivement à [deploy/README-K8.md](../deploy/README-K8.md).

## 14. Sécurité et bonnes pratiques

- Ne jamais versionner les fichiers `.env`.
- Utiliser des clés et des mots de passe distincts selon l'environnement.
- Ne pas exposer directement PostgreSQL.
- Appliquer les autorisations côté backend.
- Ne jamais faire confiance uniquement aux contrôles du frontend.
- Valider les données entrantes.
- Ne pas exposer les chemins des fichiers privés.
- Préserver les journaux d'audit.
- Conserver les volumes persistants.
- Tester les migrations et les procédures de restauration avant toute opération sensible.
- Ne jamais exécuter `docker compose down -v` sur une installation contenant des données à conserver.

La création d'un compte administrateur supplémentaire ou la récupération d'un accès administrateur doit faire l'objet d'une procédure contrôlée.

## 15. Documentation complémentaire

- [README principal](../README.md) : présentation et démarrage général.
- [README frontend](../frontend/README.md) : interface React, authentification et notifications.
- [Documentation K8](../deploy/README-K8.md) : environnement et exploitation de production.
- Dossier d'Architecture Technique (DAT) : choix structurants, modèles, arbitrages et justification des écarts avec les annexes Studi.

---

**Projet :** GML-SHIELD
**Composant :** Backend Laravel
**Contexte :** GreenMove Logistics — Mastère CTO & Tech Lead, Studi
