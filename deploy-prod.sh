#!/usr/bin/env bash
set -euo pipefail

# GML-SHIELD: publication selective de main vers production.
# Premier essai: ./deploy-prod.sh --dry-run
# Publication:  ./deploy-prod.sh --publish

SOURCE_BRANCH="main"
TARGET_BRANCH="production"
RELEASE_BRANCH="release/production-deployment"
MODE="${1:---dry-run}"

if [[ "$MODE" != "--dry-run" && "$MODE" != "--publish" ]]; then
  echo "Usage: ./deploy-prod.sh [--dry-run|--publish]" >&2
  exit 2
fi

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

# Liste explicite des composants utiles. Aucune copie de tests, de .env,
# de node_modules, de vendor ou du Compose de developpement.
PATHS=(
  backend/.dockerignore
  backend/Dockerfile
  backend/docker
  backend/app
  backend/bootstrap
  backend/config
  backend/database/migrations
  backend/public
  backend/resources
  backend/routes
  backend/artisan
  backend/composer.json
  backend/composer.lock
  frontend/.dockerignore
  frontend/Dockerfile
  frontend/index.html
  frontend/package.json
  frontend/package-lock.json
  frontend/tsconfig.json
  frontend/tsconfig.app.json
  frontend/tsconfig.node.json
  frontend/vite.config.ts
  frontend/public
  frontend/src
  compose.prod.yml
  deploy/backend.env.example
  deploy/Caddyfile.gml-shield
  deploy/Dockerfile.api
  deploy/Dockerfile.front
  deploy/README-K8.md
)

if [[ "$MODE" == "--publish" && -n "$(git status --porcelain)" ]]; then
  echo "ERREUR : depot non propre. Commiter ou annuler les modifications." >&2
  exit 1
fi

CURRENT_BRANCH="$(git branch --show-current)"
if [[ "$MODE" == "--publish" && "$CURRENT_BRANCH" != "$SOURCE_BRANCH" ]]; then
  echo "ERREUR : publication autorisee uniquement depuis $SOURCE_BRANCH." >&2
  exit 1
fi

if [[ "$MODE" == "--dry-run" ]]; then
  echo "=== Apercu uniquement (aucun fichier modifie, aucun push) ==="
  echo "Branche actuelle : $CURRENT_BRANCH"
  echo "Source de l'apercu : HEAD ($(git rev-parse --short HEAD))"
  PRESENT=()
  for p in "${PATHS[@]}"; do
    if git cat-file -e "HEAD:$p" 2>/dev/null; then
      PRESENT+=("$p")
    else
      echo "NON PRESENT pour l'instant : $p"
    fi
  done
  echo "=== Fichiers qui seraient inclus ==="
  git ls-tree -r --name-only HEAD -- "${PRESENT[@]}"
  echo "=== Total ==="
  git ls-tree -r --name-only HEAD -- "${PRESENT[@]}" | wc -l
  echo "Aucune modification effectuee."
  exit 0
fi

# Publication : aucun changement distant n'est ecrase.
git fetch origin "$SOURCE_BRANCH" "$TARGET_BRANCH"
if [[ "$(git rev-parse "$SOURCE_BRANCH")" != "$(git rev-parse "origin/$SOURCE_BRANCH")" ]]; then
  echo "ERREUR : main local n'est pas synchronise avec origin/main." >&2
  exit 1
fi
if ! git show-ref --verify --quiet "refs/remotes/origin/$TARGET_BRANCH"; then
  echo "ERREUR : origin/production introuvable." >&2
  exit 1
fi

PRESENT=()
for p in "${PATHS[@]}"; do
  if git cat-file -e "$SOURCE_BRANCH:$p" 2>/dev/null; then
    PRESENT+=("$p")
  fi
done

TMPROOT="$(mktemp -d "${TMPDIR:-/tmp}/gml-shield-prod.XXXXXXXX")"
WORKTREE="$TMPROOT/worktree"
ADDED=0
cleanup() {
  if [[ "$ADDED" == "1" ]]; then
    git worktree remove --force "$WORKTREE" >/dev/null 2>&1 || true
  fi
  rmdir "$TMPROOT" >/dev/null 2>&1 || true
}
trap cleanup EXIT

git worktree add --detach "$WORKTREE" "origin/$TARGET_BRANCH"
ADDED=1
# Nettoyage strictement limite au worktree temporaire cree par ce script.
find "$WORKTREE" -mindepth 1 -maxdepth 1 ! -name .git -exec rm -rf -- {} +
git archive "$SOURCE_BRANCH" "${PRESENT[@]}" | tar -x -C "$WORKTREE"

# Verifier les changements avant de publier.
git -C "$WORKTREE" add -A
if git -C "$WORKTREE" diff --cached --quiet; then
  echo "Aucun changement a publier."
  exit 0
fi

git -C "$WORKTREE" diff --cached --stat
git -C "$WORKTREE" commit -m "Build production from $(git rev-parse --short "$SOURCE_BRANCH")"

# Publier sur une branche intermediaire, jamais directement sur production.
# En cas de branche distante existante, le push normal refusera
# toute mise a jour non fast-forward.
git -C "$WORKTREE" push origin "HEAD:refs/heads/$RELEASE_BRANCH"

echo "Branche $RELEASE_BRANCH publiee."
echo "Creer une Pull Request de $RELEASE_BRANCH vers $TARGET_BRANCH."
echo "Aucun conteneur K8 modifie."
