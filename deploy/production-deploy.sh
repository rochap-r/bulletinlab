#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/opt/topscolaire}"
FRONTEND_DIR="${APP_DIR}/frontend"
ENV_FILE="${APP_DIR}/.env.production"
BACKUP_DIR="${BACKUP_DIR:-/opt/backups/topscolaire}"
EXPECTED_BACKEND_SHA="${EXPECTED_BACKEND_SHA:-}"
EXPECTED_FRONTEND_SHA="${EXPECTED_FRONTEND_SHA:-}"

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
    local expected_sha="${2:-}"
    local target="origin/main"

    git -C "$repository" fetch origin main

    if [[ -n "$expected_sha" ]]; then
        if ! git -C "$repository" cat-file -e "${expected_sha}^{commit}"; then
            echo "ERREUR : la révision $expected_sha est absente de $repository." >&2
            exit 1
        fi

        if ! git -C "$repository" merge-base --is-ancestor "$expected_sha" origin/main; then
            echo "ERREUR : la révision $expected_sha n'appartient pas à origin/main de $repository." >&2
            exit 1
        fi

        target="$expected_sha"
    fi

    git -C "$repository" checkout -B main "$target"
}

echo "[deploy] Mise à jour du backend depuis origin/main..."
update_main_branch "$APP_DIR" "$EXPECTED_BACKEND_SHA"

echo "[deploy] Mise à jour du frontend depuis origin/main..."
update_main_branch "$FRONTEND_DIR" "$EXPECTED_FRONTEND_SHA"

BACKEND_SHA="$(git -C "$APP_DIR" rev-parse HEAD)"
FRONTEND_SHA="$(git -C "$FRONTEND_DIR" rev-parse HEAD)"

if [[ -n "$EXPECTED_BACKEND_SHA" && "$BACKEND_SHA" != "$EXPECTED_BACKEND_SHA" ]]; then
    echo "ERREUR : backend récupéré $BACKEND_SHA, révision attendue $EXPECTED_BACKEND_SHA." >&2
    exit 1
fi

if [[ -n "$EXPECTED_FRONTEND_SHA" && "$FRONTEND_SHA" != "$EXPECTED_FRONTEND_SHA" ]]; then
    echo "ERREUR : frontend récupéré $FRONTEND_SHA, révision attendue $EXPECTED_FRONTEND_SHA." >&2
    exit 1
fi

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
# Attendre que PostgreSQL soit vraiment prêt pour pg_dump (healthcheck ≠ ready pour dump)
for i in {1..10}; do
    if "${COMPOSE[@]}" exec -T db pg_isready -U "$POSTGRES_USER" -d "$POSTGRES_DB" >/dev/null 2>&1; then
        break
    fi
    echo "[deploy] Attente PostgreSQL ready pour pg_dump (essai $i/10)..."
    sleep 2
done

# Backup non-bloquant : on log l'erreur mais on continue le déploiement
if "${COMPOSE[@]}" exec -T db \
    sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' \
    | gzip > "$BACKUP_TEMP"; then
    gzip -t "$BACKUP_TEMP"
    mv "$BACKUP_TEMP" "$BACKUP_FILE"
    BACKUP_TEMP=""
    chmod 600 "$BACKUP_FILE"
    echo "[deploy] Sauvegarde créée : $BACKUP_FILE"
else
    echo "[deploy] ATTENTION: pg_dump a échoué, suppression du backup partiel, déploiement continué..." >&2
    rm -f -- "$BACKUP_TEMP"
    BACKUP_TEMP=""
fi

echo "[deploy] Exécution des migrations..."
"${COMPOSE[@]}" --profile maintenance run --rm migrate

echo "[deploy] Démarrage de la version $IMAGE_TAG..."
"${COMPOSE[@]}" up -d --wait --wait-timeout 300

echo "[deploy] Recréation explicite du frontend $FRONTEND_SHA..."
"${COMPOSE[@]}" up -d --wait --wait-timeout 180 --force-recreate --no-deps frontend

echo "[deploy] Vérification des endpoints locaux..."
curl --fail --silent --show-error --retry 10 --retry-connrefused --retry-delay 3 \
    http://127.0.0.1:8081/up | grep -q "Application up"
curl --fail --silent --show-error --retry 10 --retry-connrefused --retry-delay 3 \
    --output /dev/null http://127.0.0.1:8080/

LOCAL_RELEASE_HTML="$(mktemp)"
PUBLIC_RELEASE_HTML="$(mktemp)"
trap 'rm -f -- "${BACKUP_TEMP:-}" "${LOCAL_RELEASE_HTML:-}" "${PUBLIC_RELEASE_HTML:-}"' EXIT
RELEASE_CHECK="_release=${IMAGE_TAG}"

curl --fail --silent --show-error --retry 10 --retry-connrefused --retry-delay 3 \
    -H 'Cache-Control: no-cache' \
    "http://127.0.0.1:8080/?${RELEASE_CHECK}" \
    --output "$LOCAL_RELEASE_HTML"

echo "[deploy] Vérification HTTPS publique..."
curl --fail --silent --show-error --retry 10 --retry-connrefused --retry-delay 3 \
    https://topscolaire.com/up | grep -q "Application up"

PUBLIC_FRONTEND_MATCHES=false
for attempt in {1..10}; do
    curl --fail --silent --show-error \
        -H 'Cache-Control: no-cache' \
        "https://topscolaire.com/?${RELEASE_CHECK}&attempt=${attempt}" \
        --output "$PUBLIC_RELEASE_HTML"

    if cmp --silent "$LOCAL_RELEASE_HTML" "$PUBLIC_RELEASE_HTML"; then
        PUBLIC_FRONTEND_MATCHES=true
        break
    fi

    sleep 3
done

if [[ "$PUBLIC_FRONTEND_MATCHES" != true ]]; then
    echo "ERREUR : topscolaire.com ne sert pas le frontend construit par ce déploiement." >&2
    echo "Empreinte locale  : $(sha256sum "$LOCAL_RELEASE_HTML" | cut -d ' ' -f 1)" >&2
    echo "Empreinte publique: $(sha256sum "$PUBLIC_RELEASE_HTML" | cut -d ' ' -f 1)" >&2
    exit 1
fi

rm -f -- "$LOCAL_RELEASE_HTML" "$PUBLIC_RELEASE_HTML"
LOCAL_RELEASE_HTML=""
PUBLIC_RELEASE_HTML=""

echo "[deploy] Version déployée : backend=$BACKEND_SHA frontend=$FRONTEND_SHA"
