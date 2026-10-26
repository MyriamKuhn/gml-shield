# GML-SHIELD — Déploiement et exploitation sur K8

**Documentation de production — GreenMove Logistics**

Ce document décrit l'architecture Docker de production, les configurations nécessaires, la stratégie de publication, les procédures de déploiement et les précautions d'exploitation de GML-SHIELD sur le serveur K8.

L'application est déployée à l'adresse :

**https://gml-shield.mkcodecreations.dev**

Le serveur héberge également d'autres applications. Toutes les opérations décrites ici doivent donc être strictement limitées à GML-SHIELD.

Pour la présentation du projet et son installation locale, consulter le [README principal](../README.md).

## 1. État de l'environnement

GML-SHIELD est déployé et fonctionnel sur K8.

Les éléments suivants ont été vérifiés lors de la mise en production :

- Accès à l'interface en HTTPS.
- Communication entre le frontend React et l'API Laravel.
- Authentification et utilisation des rôles applicatifs.
- Création, attribution et suivi des missions.
- Chiffrement et révélation contrôlée des codes secrets.
- Téléversement et téléchargement de justificatifs de livraison.
- Notifications WebSocket via WSS et Laravel Reverb.
- Actualisation en direct des données de mission.
- Publication du frontend depuis la branche Git `production`.

Ces contrôles fonctionnels ne constituent pas un audit de sécurité ou une validation de haute disponibilité.

## 2. Architecture de production

L'environnement est défini dans `compose.prod.yml` à la racine du dépôt.

Le nom du projet Docker Compose est :

```text
gml-shield
```

### Services

| Service | Image ou technologie | Fonction |
|---|---|---|
| `front` | Nginx non privilégié | Interface React compilée |
| `api` | PHP 8.5 / Apache / Laravel 13 | API REST et logique métier |
| `db` | PostgreSQL 18 | Persistance relationnelle |
| `reverb` | Laravel Reverb | Serveur WebSocket |
| `queue-worker` | Laravel Queues | Traitement de l'audit |
| `broadcast-worker` | Laravel Queues | Diffusion des événements |

### Réseaux Docker

Deux réseaux sont utilisés :

**`private`**

Réseau interne propre au projet. Il est configuré avec `internal: true`.

Il permet aux composants backend de communiquer sans exposition directe vers Internet.

**`proxy`**

Réseau Docker externe préexistant, partagé avec le reverse proxy du serveur.

Les services suivants y sont connectés :

- `front`
- `api`
- `reverb`

PostgreSQL et les deux workers ne rejoignent pas le réseau `proxy`.

### Isolation des applications

La configuration ne publie pas de ports directement sur l'hôte K8.

Les communications publiques passent par le reverse proxy existant, sans modifier les ports exposés par les autres applications.

**Ne pas supprimer ni recréer le réseau Docker externe `proxy` lors d'une maintenance GML-SHIELD.**

## 3. Organisation des fichiers de production

```text
gml-shield/
├── backend/
├── frontend/
├── deploy/
│   ├── Dockerfile.api
│   ├── Dockerfile.front
│   ├── Caddyfile.gml-shield
│   ├── backend.env.example
│   ├── backend.env
│   └── README-K8.md
├── compose.prod.yml
├── deploy-prod.sh
├── .env
└── README.md
```

Sur K8, le dépôt est installé dans :

```text
/srv/docker/gml-shield
```

Les fichiers `.env` et `deploy/backend.env` sont propres au serveur et ne doivent pas être publiés dans Git.

Le fichier `deploy/Caddyfile.gml-shield` contient la configuration de routage prévue pour GML-SHIELD. Son intégration au Caddyfile actif du serveur doit être contrôlée séparément.

## 4. Prérequis de production

L'environnement de déploiement nécessite :

- Un serveur Linux avec Docker Engine et Docker Compose V2.
- Un accès SSH autorisé.
- Un clone du dépôt GML-SHIELD.
- Le réseau Docker externe `proxy`.
- Un reverse proxy Caddy opérationnel.
- Un domaine configuré vers le serveur.
- Un certificat TLS fonctionnel.
- Les fichiers d'environnement de production.
- Une stratégie de sauvegarde adaptée aux données et fichiers persistants.

Le serveur K8 dispose déjà d'une configuration opérationnelle.

Les instructions d'installation initiale dans un nouvel environnement ne doivent pas être exécutées directement sur le serveur existant sans vérification préalable.

## 5. Variables et secrets de production

### 5.1. Fichier `.env` racine

Le fichier suivant est utilisé par Docker Compose :

```text
/srv/docker/gml-shield/.env
```

Il contient notamment :

- `DB_PASSWORD`
- `VITE_REVERB_APP_KEY`

`DB_PASSWORD` alimente la configuration du conteneur PostgreSQL.

`VITE_REVERB_APP_KEY` est transmise à Vite lors de la compilation du frontend.

Cette clé Reverb est un identifiant applicatif public ; elle ne doit pas être confondue avec `REVERB_APP_SECRET`.

### 5.2. Fichier `deploy/backend.env`

Le fichier suivant fournit les paramètres Laravel aux conteneurs backend :

```text
/srv/docker/gml-shield/deploy/backend.env
```

Il contient notamment :

- `APP_KEY`
- `APP_ENV`
- `APP_DEBUG`
- `APP_URL`
- `DB_PASSWORD`
- `SECRET_CODE_KEY`
- `REVERB_APP_ID`
- `REVERB_APP_KEY`
- `REVERB_APP_SECRET`
- Les paramètres de sessions et de cookies.
- Les paramètres de files d'attente.
- Les paramètres de sécurité du reverse proxy.

Un modèle sans secrets est disponible dans `deploy/backend.env.example`.

### 5.3. Règles de sécurité

- Ne jamais committer de secrets.
- Ne jamais copier les fichiers d'environnement dans un ticket public.
- Ne pas afficher les valeurs secrètes dans les captures d'écran.
- Utiliser des clés différentes selon les environnements.
- Ne pas modifier `APP_KEY` sans évaluer les effets sur les données et sessions existantes.
- Ne jamais modifier `SECRET_CODE_KEY` sans procédure de conservation ou de migration des secrets chiffrés.
- Ne pas changer le mot de passe PostgreSQL uniquement dans les fichiers d'environnement sans procédure coordonnée de modification des identifiants de la base.

### 5.4. Particularité de Reverb

Sur K8, Laravel communique avec Reverb par le réseau Docker :

```dotenv
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http
```

Le navigateur utilise le domaine public en WSS sur le port `443`.

La configuration d'origine autorisée doit correspondre aux domaines du navigateur.

Pour la configuration de production validée sur K8 :

```dotenv
REVERB_ALLOWED_ORIGINS=gml-shield.mkcodecreations.dev
```

Le domaine est renseigné sans préfixe `https://`.

Le fichier d'exemple du dépôt devra rester cohérent avec la configuration opérationnelle.

## 6. Routage HTTPS et WSS

Le reverse proxy Caddy assure l'accès public au domaine GML-SHIELD.

Le fichier de référence est :

```text
deploy/Caddyfile.gml-shield
```

### Routage

| Requête | Service cible |
|---|---|
| `/` et ressources frontend | `gml-shield-front:8080` |
| `/api/*` | `gml-shield-api:80` |
| `/sanctum/*` | `gml-shield-api:80` |
| `/docs/api` | `gml-shield-api:80` |
| `/docs/api.json` | `gml-shield-api:80` |
| `/up` | `gml-shield-api:80` |
| `/app`, `/app/*`, `/apps`, `/apps/*` | `gml-shield-reverb:8080` |

Caddy termine les connexions HTTPS et relaie les requêtes vers les services Docker.

Les connexions WebSocket publiques utilisent WSS et sont transmises à Reverb.

### Précautions

Le reverse proxy est partagé avec d'autres applications du serveur.

Toute modification de sa configuration doit être vérifiée avant son application.

Ne pas remplacer intégralement le Caddyfile du serveur par le seul fichier GML-SHIELD.

Une intervention sur Caddy peut affecter plusieurs applications et doit être traitée comme une opération d'infrastructure distincte.

## 7. Stockage et persistance

Trois volumes Docker nommés sont déclarés dans `compose.prod.yml`.

| Volume Compose | Contenu |
|---|---|
| `postgres_data` | Données PostgreSQL |
| `laravel_storage` | Fichiers applicatifs, sessions et justificatifs |
| `laravel_cache` | Cache de bootstrap Laravel |

Le projet Compose s'appelle `gml-shield`. Les volumes créés par Docker portent généralement un nom préfixé par ce projet.

### Données à préserver

La conservation des données nécessite de protéger :

- La base PostgreSQL.
- Les justificatifs de livraison stockés dans Laravel.
- Les clés permettant le déchiffrement des codes secrets.
- Les paramètres de configuration indispensables à la restauration.

**La présence de volumes persistants ne constitue pas une sauvegarde.**

Les volumes survivent aux recréations ordinaires des conteneurs, mais ils ne protègent pas contre toutes les pertes, corruptions ou erreurs administratives.

### Commandes interdites sans procédure explicite

Ne pas exécuter :

```bash
docker compose -f compose.prod.yml down -v
```

Ne pas supprimer les volumes Docker associés à GML-SHIELD.

Ne pas lancer de commandes de réinitialisation destructives de la base, telles que `migrate:fresh` ou `db:wipe`.

Ne pas effectuer de nettoyage global Docker sans analyse préalable des autres applications hébergées.

## 8. Sauvegardes

### Périmètre minimal

Une stratégie de sauvegarde complète doit couvrir :

1. Une sauvegarde cohérente de PostgreSQL.
2. Une copie des justificatifs et fichiers persistants.
3. Une conservation sécurisée des secrets de restauration.
4. Une conservation hors du serveur d'origine, selon la politique retenue.
5. Des tests de restauration sur un environnement isolé.

### État de la documentation

La configuration Docker confirme la persistance des données, mais les fichiers du dépôt ne prouvent pas l'existence d'une sauvegarde automatisée ni d'une restauration effectivement testée.

Ces opérations doivent donc être considérées comme des **procédures d'exploitation à formaliser et à valider**, et non comme des mécanismes déjà certifiés.

### Avant une intervention à risque

Vérifier l'existence et la fraîcheur des sauvegardes avant :

- Toute migration de données sensible.
- Une mise à niveau majeure de PostgreSQL.
- Une modification des volumes.
- Une modification des clés de chiffrement.
- Une intervention susceptible d'affecter les fichiers persistants.

Aucune commande de restauration ne doit être appliquée directement à la production sans avoir été préparée et validée dans un environnement isolé.

## 9. Stratégie Git de publication

Les développements suivent le parcours :

```text
Branche de travail
       |
       v
      dev
       |
       v
      main
       |
       v
Publication sélective
       |
       v
   release/*
       |
       v
   production
       |
       v
Déploiement sur K8
```

### Rôles des branches

- `dev` : intégration des développements et exécution de la CI.
- `main` : référence validée.
- `release/*` : branche intermédiaire de publication.
- `production` : référence des fichiers utilisés pour le déploiement.

Les changements passent par des Pull Requests selon les protections GitHub configurées.

### Script de publication

Le script à la racine du dépôt est :

```text
deploy-prod.sh
```

Il prépare un instantané des composants autorisés depuis `main` sur une branche de release construite à partir de `production`.

Il n'effectue pas un merge direct de l'intégralité du dépôt.

Il exclut notamment du périmètre de publication les tests, le Compose local et les secrets.

### Prévisualisation

Sur le poste de développement, à la racine du dépôt :

```bash
./deploy-prod.sh --dry-run
```

Cette commande énumère les fichiers autorisés, mais ne constitue pas à elle seule une comparaison des fichiers modifiés.

### Publication

Après validation et synchronisation de `main` :

```bash
./deploy-prod.sh --publish
```

Le script prépare un commit sur la branche définie par `RELEASE_BRANCH`.

**Attention :** le script utilise actuellement un nom de branche fixe. Il faut vérifier et adapter ce nom pour une nouvelle publication si la branche précédente a déjà été utilisée.

Le script ne force pas l'écrasement d'une branche distante.

### Pull Request vers `production`

Avant fusion :

- Vérifier la branche source et la cible `production`.
- Examiner les fichiers modifiés.
- Vérifier qu'aucun secret n'est présent.
- Contrôler le périmètre exact de la publication.
- Examiner les contrôles de qualité applicables.
- Valider la Pull Request selon les règles du dépôt.

**Le script ne modifie pas directement les conteneurs K8.**

La fusion Git et le déploiement sur le serveur constituent deux opérations distinctes.

## 10. Mise à jour du dépôt sur K8

Toutes les commandes ci-dessous concernent uniquement le dépôt GML-SHIELD.

Se connecter au serveur avec un compte SSH autorisé.

Se placer dans le répertoire du projet :

```bash
cd /srv/docker/gml-shield
```

Vérifier l'état du dépôt :

```bash
git status -sb
```

La branche attendue est :

```text
production
```

Un fichier `.gitignore` local non versionné peut apparaître comme fichier non suivi.

Sur le serveur K8 existant, ce fichier contient notamment :

```gitignore
.env
deploy/backend.env
```

**Ne pas supprimer ce fichier sans vérification.**

Les fichiers d'environnement doivent être préservés.

Récupérer les changements validés :

```bash
git pull --ff-only origin production
```

L'option `--ff-only` évite la création involontaire d'un commit de fusion sur le serveur.

Avant toute reconstruction, contrôler les fichiers réellement modifiés et déterminer quels services doivent être mis à jour.

## 11. Déployer uniquement le frontend

Cette procédure a été utilisée pour la publication de la nouvelle interface GML-SHIELD.

### 11.1. Vérification

Depuis :

```text
/srv/docker/gml-shield
```

Vérifier les services :

```bash
docker compose -f compose.prod.yml ps
```

### 11.2. Reconstruire l'image frontend

```bash
docker compose -f compose.prod.yml build front
```

Cette commande construit une nouvelle image `gml-shield-front:prod` sans redémarrer immédiatement les conteneurs.

### 11.3. Remplacer le conteneur frontend

```bash
docker compose -f compose.prod.yml up -d --no-deps --force-recreate front
```

L'option `--no-deps` évite de recréer les services dépendants.

Cette commande limite le remplacement au service `front`.

Elle ne déclenche pas de migration de base de données.

### 11.4. Vérifier le conteneur

```bash
docker compose -f compose.prod.yml ps front
```

Consulter les logs :

```bash
docker compose -f compose.prod.yml logs --tail=100 front
```

### 11.5. Vérification fonctionnelle

Ouvrir :

https://gml-shield.mkcodecreations.dev

Vérifier :

- Le chargement de l'application.
- Le nouveau design.
- Le favicon.
- La connexion.
- La récupération des missions.
- Les interactions avec l'API.
- Les notifications WebSocket.

Si l'interface ancienne apparaît, vérifier d'abord le cache du navigateur et l'état du conteneur.

## 12. Mise à jour du backend

Une mise à jour backend présente davantage de risques qu'une modification visuelle.

Elle peut concerner :

- L'image PHP/Laravel.
- Les migrations PostgreSQL.
- Les workers.
- Reverb.
- Les volumes persistants.
- Les permissions de fichiers.
- La compatibilité avec le frontend.

Elle doit être préparée séparément.

### Avant l'intervention

- Vérifier les différences Git.
- Confirmer l'existence d'une sauvegarde exploitable.
- Examiner les nouvelles migrations.
- Vérifier les variables d'environnement nécessaires.
- Prévoir un plan de retour arrière.
- Identifier les services à reconstruire et les éventuelles interruptions.

### Principe de reconstruction

L'image backend est construite depuis :

```text
deploy/Dockerfile.api
```

Elle est partagée par les services `api`, `queue-worker`, `broadcast-worker` et `reverb`.

La mise à jour d'un seul de ces conteneurs ne garantit donc pas la cohérence de tous les services Laravel.

La procédure exacte de remplacement doit être définie selon les changements concernés.

**Ne pas utiliser de commande globale `up -d --build` en production sans avoir vérifié son périmètre et les effets sur les autres services GML-SHIELD.**

## 13. Vérifications d'exploitation

Depuis la racine du projet sur K8 :

### État de tous les services GML-SHIELD

```bash
docker compose -f compose.prod.yml ps
```

### Logs API

```bash
docker compose -f compose.prod.yml logs --tail=100 api
```

### Logs Reverb

```bash
docker compose -f compose.prod.yml logs --tail=100 reverb
```

### Logs du worker d'audit

```bash
docker compose -f compose.prod.yml logs --tail=100 queue-worker
```

### Logs du worker de diffusion

```bash
docker compose -f compose.prod.yml logs --tail=100 broadcast-worker
```

### Logs frontend

```bash
docker compose -f compose.prod.yml logs --tail=100 front
```

### État de PostgreSQL

```bash
docker compose -f compose.prod.yml ps db
```

### Validation de la configuration Compose

```bash
docker compose -f compose.prod.yml config -q
```

Attention : certaines commandes de diagnostic Docker peuvent afficher des éléments de configuration. Ne pas publier leurs sorties sans vérifier l'absence de secrets.

## 14. Vérifications fonctionnelles après déploiement

Une vérification de production doit couvrir les parcours concernés par la modification.

### Authentification

- Accès à la page de connexion.
- Connexion d'un utilisateur actif.
- Refus des accès non autorisés.
- Déconnexion.

### Missions

- Consultation des missions.
- Création par un utilisateur autorisé.
- Attribution à un chauffeur.
- Démarrage et changement de statut.
- Clôture après transmission d'un justificatif.

### Justificatifs

- Téléversement d'un PDF ou d'une image autorisée.
- Enregistrement du document.
- Téléchargement par le dispatcher propriétaire.
- Ouverture et vérification de l'intégrité du fichier récupéré.

### WebSocket

- Connexion du chauffeur à son canal privé.
- Attribution d'une mission depuis une autre session.
- Réception de la notification.
- Mise à jour de l'affichage sans rechargement manuel.

### Documentation API

- Accès à `/docs/api` par un administrateur actif.
- Refus d'accès pour les rôles non autorisés.

Les tests fonctionnels doivent utiliser des comptes et données de test appropriés, sans modifier inutilement les données métier conservées.

## 15. Rollback et retour arrière

Un retour arrière doit être préparé avant une publication risquée.

### Frontend

Le remplacement du frontend ne modifie pas directement les données PostgreSQL.

Toutefois, reconstruire une ancienne version nécessite de disposer d'une référence Git ou d'une image Docker antérieure identifiée.

Il ne faut pas supposer que Docker conserve systématiquement une image précédente exploitable.

### Backend

Le retour à une ancienne version du code ne garantit pas la compatibilité avec une base de données ayant subi de nouvelles migrations.

Les changements de schéma, les fichiers persistants et les clés de chiffrement doivent être pris en compte.

### Précautions

- Identifier précisément la version précédente.
- Conserver ou préparer les images nécessaires.
- Vérifier les données avant restauration.
- Ne jamais restaurer une base de production sans procédure contrôlée.
- Vérifier la compatibilité entre frontend, backend et base de données.

Une procédure de restauration automatisée et testée reste à formaliser dans le cadre de l'exploitation.

## 16. Points connus de maintenance

### Permissions du favicon

Lors du déploiement du nouveau frontend, le fichier `favicon.svg` a initialement renvoyé une erreur HTTP `403 Forbidden`.

L'analyse a montré des permissions trop restrictives dans le conteneur Nginx.

Une correction locale des permissions a permis de rendre le fichier accessible.

Cette correction directe dans le conteneur **n'est pas garantie persistante après une reconstruction**.

Une évolution du processus de build frontend devra garantir des permissions de lecture compatibles avec l'utilisateur Nginx non privilégié.

### Configuration de l'origine Reverb

Le domaine autorisé dans la configuration de production opérationnelle est renseigné sans schéma `https://`.

L'exemple versionné doit être maintenu en cohérence avec cette configuration.

### Sauvegardes et restauration

Les volumes Docker assurent la persistance, mais la documentation ne permet pas encore de confirmer une stratégie automatisée et testée de sauvegarde/restauration.

Ces procédures restent à formaliser et à valider.

### Nom de la branche de publication

La variable `RELEASE_BRANCH` du script `deploy-prod.sh` doit être vérifiée lors d'une nouvelle publication pour éviter les conflits avec une ancienne branche de release.

## 17. Sécurité opérationnelle

L'exploitation du serveur doit respecter les principes suivants :

- Limitation des accès SSH aux personnes autorisées.
- Conservation des secrets hors Git.
- HTTPS et WSS pour les accès publics.
- Isolation des services internes.
- Absence d'exposition directe de PostgreSQL.
- Contrôle des permissions des fichiers persistants.
- Surveillance des erreurs et des services.
- Mises à jour de sécurité planifiées.
- Sauvegardes et tests de restauration.
- Traçabilité des modifications.
- Vérification des impacts sur les autres applications du serveur.

Les procédures de sécurité du serveur hôte et du reverse proxy relèvent également de l'exploitation générale de K8.

## 18. Documentation associée

- [README principal](../README.md) : présentation du projet et installation locale.
- [README backend](../backend/README.md) : Laravel, API, sécurité, audit, tests et Reverb.
- [README frontend](../frontend/README.md) : React, WebSocket, composants et qualité.
- [Configuration Compose](../compose.prod.yml) : définition de la stack.
- [Dockerfile API](Dockerfile.api) : construction Laravel.
- [Dockerfile frontend](Dockerfile.front) : compilation React et serveur Nginx.
- [Configuration Caddy](Caddyfile.gml-shield) : routage HTTPS/WSS.
- [Exemple backend](backend.env.example) : variables d'environnement de production.

Le Dossier d'Architecture Technique (DAT) complète cette documentation avec les choix d'architecture, les décisions de sécurité et les justifications relatives au projet Studi.

---

**Projet :** GML-SHIELD
**Composant :** Déploiement et exploitation K8
**Environnement :** Production Docker Compose
**Contexte :** GreenMove Logistics — Mastère CTO & Tech Lead, Studi