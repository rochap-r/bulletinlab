#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/opt/topscolaire}"
FRONTEND_DIR="${APP_DIR}/frontend"
ENV_FILE="${APP_DIR}/.env.production"
BACKUP_DIR="${BACKUP_DIR:-/opt/backups/topscolaire}"

if [[ ! -d "$APP_DIR/.git" || ! -d "$FRONTEND_DIR/.git" ]]; then
    echo "ERREUR : les dépôts backend et frontend doivent exister dans $APP_DIR." >&2
    exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
    echo "ERREUR : fichier d'environnement absent : $ENV_FILE." >&2
    exit 1
fi

if ! command -v flock >/dev/null 2>&1; then
    echo "ERREUR : flock est requis pour sérialiser les déploiements." >&2
    exit 1
fi

exec 9>/tmp/topscolaire-production-deploy.lock
if ! flock -n 9; then
    echo "ERREUR : un autre déploiement TopScolaire est déjà en cours." >&2
    exit 1
fi

update_main_branch() {
    local repository="$1"

    git -C "$repository" fetch origin main
    git -C "$repository" checkout -B main origin/main
}

echo "[deploy] Mise à jour du backend depuis origin/main..."
update_main_branch "$APP_DIR"

echo "[deploy] Mise à jour du frontend depuis origin/main..."
update_main_branch "$FRONTEND_DIR"

BACKEND_SHA="$(git -C "$APP_DIR" rev-parse HEAD)"
FRONTEND_SHA="$(git -C "$FRONTEND_DIR" rev-parse HEAD)"
export IMAGE_TAG="main-${BACKEND_SHA:0:12}-${FRONTEND_SHA:0:12}"

cd "$APP_DIR"
COMPOSE=(docker compose --env-file "$ENV_FILE")

echo "[deploy] Vérification de la configuration Compose..."
"${COMPOSE[@]}" config --quiet
COMPOSE_PROJECT="$("${COMPOSE[@]}" config --format json \
    | sed -n 's/.*"name":[[:space:]]*"\([^"]*\)".*/\1/p' \
    | head -n 1)"
if [[ "$COMPOSE_PROJECT" != "topscolaire" ]]; then
    echo "ERREUR : le projet Compose résolu est '$COMPOSE_PROJECT', attendu : topscolaire." >&2
    exit 1
fi

echo "[deploy] Construction des images $IMAGE_TAG..."
"${COMPOSE[@]}" build --pull

echo "[deploy] Démarrage et vérification de PostgreSQL et Redis..."
"${COMPOSE[@]}" up -d --wait --wait-timeout 180 db redis

install -d -m 700 "$BACKUP_DIR"
umask 077
BACKUP_TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP_FILE="${BACKUP_DIR}/topscolaire-${BACKUP_TIMESTAMP}-$$.sql.gz"
BACKUP_TEMP="${BACKUP_FILE}.tmp"
trap 'if [[ -n "${BACKUP_TEMP:-}" ]]; then rm -f -- "$BACKUP_TEMP"; fi' EXIT

echo "[deploy] Sauvegarde PostgreSQL avant les migrations..."
"${COMPOSE[@]}" exec -T db \
    sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' \
    | gzip > "$BACKUP_TEMP"

gzip -t "$BACKUP_TEMP"
mv "$BACKUP_TEMP" "$BACKUP_FILE"
BACKUP_TEMP=""
chmod 600 "$BACKUP_FILE"
echo "[deploy] Sauvegarde créée : $BACKUP_FILE"

echo "[deploy] Exécution des migrations..."
"${COMPOSE[@]}" --profile maintenance run --rm migrate

echo "[deploy] Démarrage de la version $IMAGE_TAG..."
"${COMPOSE[@]}" up -d --wait --wait-timeout 300

echo "[deploy] Vérification des endpoints locaux..."
curl --fail --silent --show-error --retry 10 --retry-connrefused --retry-delay 3 \
    http://127.0.0.1:8081/up | grep -q "Application up"
curl --fail --silent --show-error --retry 10 --retry-connrefused --retry-delay 3 \
    --output /dev/null http://127.0.0.1:8080/

echo "[deploy] Vérification HTTPS publique..."
curl --fail --silent --show-error --retry 10 --retry-connrefused --retry-delay 3 \
    --output /dev/null https://topscolaire.com/
curl --fail --silent --show-error --retry 10 --retry-connrefused --retry-delay 3 \
    https://topscolaire.com/up | grep -q "Application up"

echo "[deploy] Version déployée : backend=$BACKEND_SHA frontend=$FRONTEND_SHA"
