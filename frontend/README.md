# GML-SHIELD — Frontend React

**Interface web de gestion sécurisée des missions de transport — GreenMove Logistics**

Le frontend GML-SHIELD est une application React et TypeScript permettant aux utilisateurs de gérer leurs missions et d'accéder aux fonctionnalités correspondant à leur rôle.

Il communique avec l'API Laravel par HTTPS/HTTP et reçoit les notifications temps réel via Laravel Reverb.

La logique métier, les droits d'accès et la protection des données sensibles sont assurés par le backend.

Pour découvrir le projet et sa procédure d'installation générale, consulter le [README principal](../README.md).

## 1. Stack technique

| Composant | Technologie |
|---|---|
| Bibliothèque UI | React 19 |
| Langage | TypeScript 6 |
| Build et développement | Vite 8 |
| Composants visuels | Material UI 9 |
| Client HTTP | Axios |
| Notifications | Laravel Echo et Pusher JS |
| WebSocket | Laravel Reverb |
| Qualité du code | ESLint et TypeScript |
| Conteneurisation | Docker |

Les versions des dépendances sont définies dans `package.json` et verrouillées dans `package-lock.json`.

## 2. Architecture du frontend

Le frontend est organisé par responsabilités : interface, appels API, temps réel et composants métier.

```text
frontend/
├── public/
│   └── favicon.svg
├── src/
│   ├── api/
│   │   ├── auditLogs.ts
│   │   ├── auth.ts
│   │   ├── client.ts
│   │   ├── documents.ts
│   │   ├── drivers.ts
│   │   ├── missions.ts
│   │   └── users.ts
│   ├── assets/
│   ├── components/
│   │   ├── admin/
│   │   │   ├── AdminDashboard.tsx
│   │   │   ├── AuditLogList.tsx
│   │   │   └── UserManagement.tsx
│   │   ├── dispatcher/
│   │   │   └── DispatcherDashboard.tsx
│   │   └── missions/
│   │       ├── MissionAssignForm.tsx
│   │       ├── MissionCreateForm.tsx
│   │       ├── MissionDocumentUpload.tsx
│   │       ├── MissionDocumentsList.tsx
│   │       ├── MissionEditForm.tsx
│   │       ├── MissionList.tsx
│   │       └── MissionSecretCode.tsx
│   ├── lib/
│   │   └── echo.ts
│   ├── App.tsx
│   ├── main.tsx
│   └── theme.ts
├── Dockerfile
├── eslint.config.js
├── index.html
├── package.json
├── package-lock.json
├── vite.config.ts
└── README.md
```

### Responsabilités des principaux fichiers

- `App.tsx` : connexion, restauration de session, déconnexion, sélection de l'interface selon le rôle et réception des notifications.
- `main.tsx` : initialisation de React, du thème Material UI et des styles globaux.
- `theme.ts` : identité visuelle et configuration du thème.
- `api/client.ts` : client HTTP Axios commun.
- `api/auth.ts` : connexion, déconnexion et récupération de l'utilisateur authentifié.
- `api/missions.ts` : opérations relatives aux missions.
- `api/documents.ts` : téléversement et téléchargement des justificatifs.
- `api/users.ts` : gestion des comptes.
- `api/auditLogs.ts` : consultation du journal d'audit.
- `lib/echo.ts` : configuration des connexions WebSocket et de l'authentification des canaux.

## 3. Interfaces et rôles

GML-SHIELD distingue trois rôles applicatifs.

### ADMIN — Administration

L'administrateur dispose notamment :

- D'un tableau de bord de supervision.
- De la consultation des missions.
- De la gestion des comptes chauffeurs et répartiteurs.
- De l'activation et de la désactivation des utilisateurs.
- De la consultation du journal d'audit.
- De l'accès autorisé à la documentation OpenAPI du backend.

Les composants principaux sont situés dans `src/components/admin/`.

### DISPATCHER — Répartition

Le répartiteur peut notamment :

- Créer ses missions.
- Consulter les missions dont il est le créateur.
- Modifier et attribuer les missions lorsque leur état le permet.
- Suivre les missions et leurs statuts.
- Consulter et télécharger les justificatifs des missions qu'il a créées.

L'interface dédiée est implémentée dans `DispatcherDashboard.tsx`.

### DRIVER — Chauffeur

Le chauffeur peut notamment :

- Consulter les missions qui lui sont attribuées.
- Démarrer une mission en attente.
- Révéler à la demande le code secret d'une mission en cours.
- Transmettre un justificatif de livraison.
- Terminer une mission lorsque les conditions métier sont respectées.
- Recevoir une notification en direct lors d'une nouvelle attribution.

Le frontend adapte les actions affichées au rôle et à l'état de la mission.

**Important :** cette adaptation visuelle ne constitue pas une mesure d'autorisation suffisante. Les contrôles sont effectués dans Laravel.

## 4. Installation et démarrage

### 4.1. Prérequis

L'installation recommandée utilise Docker Compose depuis la racine du dépôt.

Prérequis :

- Git.
- Docker avec moteur Linux.
- Docker Compose V2.
- Un navigateur récent.

Pour une installation sans Docker, il faut disposer d'un environnement Node.js compatible avec les versions de Vite et des dépendances du projet.

La CI utilise Node.js 22 pour les contrôles frontend, tandis que le Dockerfile local utilise l'image `node:24-alpine`.

### 4.2. Installation avec Docker

Depuis la racine de GML-SHIELD :

```bash
docker compose up -d --build
```

Vérifier le service frontend :

```bash
docker compose ps front
```

Ouvrir :

http://localhost:5173

Le serveur Vite est configuré pour écouter sur toutes les interfaces du conteneur, tandis que Docker Compose publie le port uniquement sur `127.0.0.1` du poste hôte.

### 4.3. Installation directe sans Docker

Depuis le répertoire `frontend/` :

```bash
npm ci
```

Démarrer le serveur Vite :

```bash
npm run dev
```

Le backend Laravel et les services nécessaires doivent être configurés et démarrés séparément.

Une installation directe du frontend ne démarre pas PostgreSQL, Laravel ou Reverb.

## 5. Variables d'environnement

Les variables du frontend utilisent le préfixe `VITE_`.

Elles sont lues à travers `import.meta.env`.

### Configuration locale

Le code utilise les valeurs suivantes :

| Variable | Fonction | Valeur locale |
|---|---|---|
| `VITE_API_URL` | Adresse HTTP de l'API Laravel | `http://localhost:8000` |
| `VITE_REVERB_APP_KEY` | Clé publique de l'application Reverb | Même valeur que `REVERB_APP_KEY` |
| `VITE_REVERB_HOST` | Hôte WebSocket accessible au navigateur | `localhost` |
| `VITE_REVERB_PORT` | Port WebSocket | `8080` |
| `VITE_REVERB_SCHEME` | Schéma de connexion | `http` |

Pour un démarrage local, créer si nécessaire un fichier `frontend/.env.local` non versionné :

```dotenv
VITE_API_URL=http://localhost:8000
VITE_REVERB_APP_KEY=<meme-valeur-que-REVERB_APP_KEY>
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

Les variables doivent être disponibles dans l'environnement d'exécution ou de compilation Vite.

**Attention :** les variables `VITE_` sont destinées au navigateur et ne constituent pas un stockage de secrets.

Ne jamais y placer :

- `APP_KEY` ;
- `SECRET_CODE_KEY` ;
- `REVERB_APP_SECRET` ;
- les mots de passe PostgreSQL ;
- des jetons privés d'administration.

La clé `VITE_REVERB_APP_KEY` est un identifiant applicatif public utilisé pour établir la connexion WebSocket, et non le secret Reverb.

### Configuration de production

En production, les paramètres Vite sont transmis à la construction de l'image frontend via `compose.prod.yml`.

Le navigateur utilise :

- L'origine HTTPS de GML-SHIELD pour l'API.
- Le nom de domaine public pour Reverb.
- Le port `443`.
- Le schéma `https`, permettant une connexion WSS.

Ces paramètres sont intégrés lors du build. Leur modification nécessite une nouvelle construction de l'image frontend.

Voir [deploy/README-K8.md](../deploy/README-K8.md).

## 6. Communication avec l'API

Le client Axios partagé est défini dans :

```text
src/api/client.ts
```

Il configure notamment :

- L'URL de base de l'API.
- L'envoi des cookies d'authentification avec `withCredentials`.
- La gestion du jeton CSRF avec `withXSRFToken`.
- L'en-tête `Accept: application/json`.

### Authentification Laravel Sanctum

Le parcours de connexion suit les étapes suivantes :

1. Appel de `/sanctum/csrf-cookie`.
2. Envoi des identifiants à `/api/auth/login`.
3. Récupération du profil authentifié.
4. Affichage de l'interface correspondant au rôle.

Lors du chargement de l'application, un appel à `/api/user` permet de restaurer une session déjà valide.

La déconnexion utilise `/api/logout`.

Le frontend n'enregistre pas de mot de passe ou de jeton d'authentification permanent dans le stockage local du navigateur.

### Traitement des erreurs

Les composants présentent des messages adaptés aux réponses HTTP du backend, notamment :

| Code | Signification courante |
|---|---|
| `401` | Session absente ou expirée |
| `403` | Action non autorisée |
| `409` | Conflit avec l'état actuel de la mission |
| `413` | Fichier trop volumineux |
| `422` | Données invalides |
| `429` | Trop de tentatives de connexion |

La réponse exacte dépend de l'endpoint concerné.

## 7. Gestion des missions

Les composants du répertoire `src/components/missions/` couvrent :

- La création.
- La consultation.
- La modification.
- L'attribution.
- Le suivi des statuts.
- La révélation du code secret.
- Les justificatifs de livraison.

Les états métier sont :

- `PENDING` : en attente.
- `IN_PROGRESS` : en cours.
- `COMPLETED` : terminée.
- `CANCELLED` : annulée.

Le frontend ne décide pas seul des transitions autorisées. Il s'appuie sur les réponses de l'API et sur les politiques Laravel.

### Actualisation de la liste

La liste des missions conserve ses données pendant les actualisations de fond afin d'éviter de démonter puis recréer l'ensemble de l'interface à chaque requête.

Lorsqu'une opération métier renvoie une mission actualisée, le composant peut remplacer directement la mission correspondante dans son état local.

Le mécanisme permet de limiter les effets visuels de rechargement et de préserver la lisibilité du tableau de bord.

## 8. Justificatifs de livraison

Le composant `MissionDocumentUpload.tsx` permet au chauffeur autorisé de sélectionner puis transmettre un justificatif.

Formats acceptés :

| Format | Extension |
|---|---|
| PDF | `.pdf` |
| JPEG | `.jpg`, `.jpeg` |
| PNG | `.png` |

La taille maximale est de 10 Mo.

Le navigateur transmet le fichier au backend avec `FormData`.

Les composants concernés sont :

- `MissionDocumentUpload.tsx` : sélection et envoi.
- `MissionDocumentsList.tsx` : consultation des documents autorisés.
- `api/documents.ts` : appels HTTP et téléchargement.

Le téléchargement récupère le fichier sous forme de données binaires (`Blob`), puis déclenche son enregistrement dans le navigateur.

Le backend génère le nom de téléchargement et vérifie les droits d'accès.

Les chemins du stockage privé ne sont pas exposés dans le frontend.

### Parcours fonctionnel de vérification

1. Un dispatcher crée une mission.
2. Il affecte la mission à un chauffeur.
3. Le chauffeur démarre la mission.
4. Il téléverse un justificatif PDF ou image.
5. Il termine la mission.
6. Le dispatcher télécharge le justificatif.
7. Le fichier téléchargé doit être lisible et correspondre au fichier transmis.

Ce parcours a été vérifié manuellement sur l'environnement déployé.

## 9. Notifications WebSocket

Les notifications temps réel sont configurées dans :

```text
src/lib/echo.ts
```

Le frontend utilise :

- Laravel Echo.
- Pusher JS, selon le protocole compatible avec Reverb.
- Laravel Reverb côté serveur.
- L'API Laravel pour autoriser les abonnements aux canaux privés.

### Connexion et autorisation

La connexion WebSocket utilise les variables `VITE_REVERB_*`.

L'autorisation des canaux privés passe par :

```text
POST /api/broadcasting/auth
```

Cet appel utilise le client Axios authentifié, afin de réutiliser les cookies et la protection CSRF.

### Notification de nouvelle mission

Après connexion, un chauffeur s'abonne au canal privé :

```text
drivers.{id}
```

L'interface écoute l'événement :

```text
.NEW_MISSION
```

À la réception de l'événement :

- Une notification est affichée au chauffeur.
- Un rafraîchissement de la liste des missions est déclenché.
- Les données de mission sont récupérées depuis l'API Laravel.

L'abonnement est quitté lorsque le composant n'en a plus besoin, notamment lorsque l'utilisateur change.

### Tests temps réel

Pour valider ce comportement :

1. Se connecter comme chauffeur dans un navigateur.
2. Se connecter comme dispatcher dans une autre session.
3. Attribuer une nouvelle mission au chauffeur.
4. Vérifier l'apparition de la notification.
5. Vérifier l'actualisation de la liste sans recharger manuellement la page.

Le bon fonctionnement exige que Reverb et `broadcast-worker` soient démarrés.

## 10. Interface et identité visuelle

Le frontend utilise Material UI avec un thème partagé, défini dans :

```text
src/theme.ts
```

L'application comprend notamment :

- Une interface de connexion personnalisée.
- Une navigation adaptée au rôle.
- Des tableaux de bord administrateur et dispatcher.
- Des vues de missions adaptées aux chauffeurs.
- Des cartes et formulaires harmonisés.
- Des indicateurs visuels de statut.
- Des messages de confirmation et d'erreur.
- Une mise en page responsive.

Les styles sont principalement définis avec les composants Material UI et la propriété `sx`.

### Ressources statiques

Les ressources publiques, dont le favicon, sont placées dans `public/`.

Le fichier `index.html` définit le point d'entrée HTML de l'application et ses métadonnées.

Les éléments statiques du frontend doivent rester accessibles après la compilation et le déploiement.

## 11. Qualité du code et compilation

### Vérification TypeScript

Depuis le répertoire `frontend/` :

```bash
npx tsc -b
```

### Analyse ESLint

```bash
npm run lint
```

### Compilation de production

```bash
npm run build
```

Le script de compilation exécute la vérification TypeScript puis Vite.

### Prévisualisation locale de la compilation

Après un build :

```bash
npm run preview
```

Cette commande permet d'examiner les fichiers compilés localement. Elle ne remplace pas un environnement de production configuré avec son reverse proxy.

### Exécution dans Docker

Depuis la racine du dépôt :

```bash
docker compose exec front npx tsc -b
docker compose exec front npm run lint
docker compose exec front npm run build
```

### Intégration continue

Le workflow GitHub Actions exécute les vérifications frontend suivantes :

- Installation reproductible avec `npm ci`.
- Vérification TypeScript.
- ESLint.
- Compilation React/Vite.

Il est déclenché sur les pushes vers `dev` et les Pull Requests ciblant `dev`.

Le dépôt ne contient pas actuellement de suite autonome de tests de composants React. Les parcours de l'interface sont complétés par des vérifications fonctionnelles manuelles.

## 12. Déploiement

Le développement local utilise le serveur Vite dans un conteneur Docker.

La production utilise une compilation optimisée et une image Docker dédiée définie dans :

```text
deploy/Dockerfile.front
```

L'image est construite à partir de la configuration :

```text
compose.prod.yml
```

Le frontend compilé est servi par Nginx et accessible via le reverse proxy HTTPS du serveur K8.

### Publication Git

Le processus de publication distingue :

- `dev` : intégration et contrôles qualité.
- `main` : version validée.
- `release/*` : préparation d'un instantané de publication.
- `production` : branche des fichiers déployables.

Le script `deploy-prod.sh` prépare la publication sélective ; il ne déploie pas automatiquement les conteneurs.

La procédure de déploiement est détaillée dans [deploy/README-K8.md](../deploy/README-K8.md).

## 13. Diagnostic et dépannage

### L'interface ne s'affiche pas

Vérifier le conteneur :

```bash
docker compose ps front
```

Consulter les journaux :

```bash
docker compose logs --tail=100 front
```

Vérifier l'adresse `http://localhost:5173`.

### Les missions ne se chargent pas

Contrôler :

- Le démarrage de l'API Laravel.
- La valeur de `VITE_API_URL`.
- L'état de la session.
- Les cookies et les requêtes réseau dans les outils de développement du navigateur.

### La connexion échoue

Vérifier :

- L'existence d'un utilisateur actif.
- Le mot de passe.
- Les domaines autorisés par Sanctum.
- Le fonctionnement de `/sanctum/csrf-cookie`.
- Les erreurs HTTP dans l'onglet Réseau du navigateur.

Pour l'initialisation du premier administrateur, consulter [backend/README.md](../backend/README.md#initialisation-du-premier-administrateur).

### Les notifications n'arrivent pas

Vérifier :

- Les variables `VITE_REVERB_*`.
- Le service `reverb`.
- Le service `broadcast-worker`.
- L'autorisation du canal privé.
- La connexion WebSocket dans le navigateur.

Depuis la racine :

```bash
docker compose logs --tail=100 reverb
docker compose logs --tail=100 broadcast-worker
```

### L'envoi d'un document échoue

Vérifier :

- Que la mission est en cours.
- Que le chauffeur est bien affecté.
- Que le format est PDF, JPEG ou PNG.
- Que le fichier ne dépasse pas 10 Mo.
- La réponse HTTP du backend.

Le serveur Laravel reste responsable de la validation finale.

## 14. Conventions et maintenance

- Utiliser TypeScript pour les composants et les échanges API.
- Maintenir les types des réponses backend.
- Centraliser les appels HTTP dans `src/api/`.
- Réutiliser le client Axios commun.
- Regrouper les composants selon leur domaine métier.
- Conserver les vérifications d'autorisation côté Laravel.
- Ne jamais placer de secrets dans les variables `VITE_`.
- Respecter les conventions de nommage existantes.
- Vérifier TypeScript, ESLint et le build avant une Pull Request.
- Mettre à jour la documentation lors de toute évolution fonctionnelle.

## 15. Documentation complémentaire

- [README principal](../README.md) : présentation et installation générale.
- [README backend](../backend/README.md) : API Laravel, rôles, chiffrement, documents, audit et tests.
- [Documentation K8](../deploy/README-K8.md) : construction, publication et exploitation en production.

---

**Projet :** GML-SHIELD
**Composant :** Frontend React / TypeScript
**Contexte :** GreenMove Logistics — Mastère CTO & Tech Lead, Studi