#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

# ============================================
# TOPSCOLAIRE — DEPLOIEMENT PRODUCTION
# Laravel 13 / React / PostgreSQL 18
# ============================================

ROOT="/opt/topscolaire"
ENV_FILE="${ENV_FILE:-$ROOT/.env.production}"
BACKUP_DIR="/opt/backups/topscolaire/deployments"
STATE_DIR="$ROOT/.deploy-state"
LOCK_FILE="/tmp/topscolaire-deploy.lock"

EXPECTED_VOLUME="topscolaire_topscolaire_pg_data"

APPROVE_MIGRATIONS="${APPROVE_MIGRATIONS:-false}"
BOOTSTRAP_DATABASE_IDENTITY="${BOOTSTRAP_DATABASE_IDENTITY:-false}"
REDEPLOY_API_NGINX="${REDEPLOY_API_NGINX:-false}"
ROLLBACK_ONLY="${ROLLBACK_ONLY:-false}"
EXPECTED_DATABASE_UUID="${DEPLOYMENT_DATABASE_UUID:-}"
BACKUP_TEMP=""

log() {
  printf '[TOPSCOLAIRE] %s\n' "$*"
}

fail() {
  printf '[ERREUR] %s\n' "$*" >&2
  exit 1
}

on_error() {
  local status=$?
  printf '[ERREUR] Déploiement interrompu (code %s).\n' "$status" >&2
  printf '[IMPORTANT] Les migrations déjà appliquées ne sont pas annulées.\n' >&2
  exit "$status"
}

trap on_error ERR

cleanup() {
  if [[ -n "$BACKUP_TEMP" && -f "$BACKUP_TEMP" ]]; then
    rm -f -- "$BACKUP_TEMP"
  fi
}

trap cleanup EXIT

is_valid_uuid() {
  [[ "$1" =~ ^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$ ]]
}

is_boolean() {
  [[ "$1" == "true" || "$1" == "false" ]]
}

write_state() {
  local name="$1"
  local value="$2"
  local temporary="$STATE_DIR/.${name}.tmp.$$"

  printf '%s\n' "$value" > "$temporary"
  mv -f -- "$temporary" "$STATE_DIR/$name"
}

# ============================================
# 1. PREREQUIS ET VERROUILLAGE
# ============================================

for cmd in docker git flock curl python3; do
  command -v "$cmd" >/dev/null ||
    fail "Commande indisponible : $cmd"
done

[[ -f "$ROOT/docker-compose.yml" ]] ||
  fail "docker-compose.yml introuvable"

[[ -s "$ENV_FILE" ]] ||
  fail "Fichier .env.production introuvable"

[[ -d "$ROOT/.git" ]] ||
  fail "Dépôt backend Git introuvable"

[[ -d "$ROOT/frontend/.git" ]] ||
  fail "Dépôt frontend Git introuvable"

mkdir -p "$BACKUP_DIR" "$STATE_DIR"
chmod 700 "$BACKUP_DIR" "$STATE_DIR"

[[ -w "$BACKUP_DIR" ]] ||
  fail "Répertoire de sauvegarde non accessible en écriture : $BACKUP_DIR"

[[ -w "$STATE_DIR" ]] ||
  fail "Répertoire d'état non accessible en écriture : $STATE_DIR"

[[ -z "$(git -C "$ROOT" ls-files -- '.deploy-state/**')" ]] ||
  fail ".deploy-state ne doit contenir aucun fichier suivi par Git"

exec 9>"$LOCK_FILE"

flock -n 9 ||
  fail "Un déploiement est déjà en cours"

cd "$ROOT"

compose() {
  ENV_FILE="$ENV_FILE" \
    docker compose --env-file "$ENV_FILE" "$@"
}

compose config --quiet

if [[ -z "$EXPECTED_DATABASE_UUID" && -s "$STATE_DIR/database-uuid" ]]; then
  EXPECTED_DATABASE_UUID="$(cat "$STATE_DIR/database-uuid")"
fi

EXPECTED_DATABASE_UUID="${EXPECTED_DATABASE_UUID,,}"

if [[ -n "$EXPECTED_DATABASE_UUID" ]]; then
  is_valid_uuid "$EXPECTED_DATABASE_UUID" ||
    fail "UUID d'identité PostgreSQL invalide"
fi

is_boolean "$APPROVE_MIGRATIONS" ||
  fail "APPROVE_MIGRATIONS doit valoir true ou false"

is_boolean "$BOOTSTRAP_DATABASE_IDENTITY" ||
  fail "BOOTSTRAP_DATABASE_IDENTITY doit valoir true ou false"

is_boolean "$REDEPLOY_API_NGINX" ||
  fail "REDEPLOY_API_NGINX doit valoir true ou false"

is_boolean "$ROLLBACK_ONLY" ||
  fail "ROLLBACK_ONLY doit valoir true ou false"

# ============================================
# 2. VERIFICATION POSTGRESQL
# ============================================

database_container_id() {
  compose ps -q db 2>/dev/null
}

db_psql() {
  local container_id

  container_id="$(database_container_id)"
  [[ -n "$container_id" ]] || fail "Conteneur PostgreSQL introuvable"

  docker exec "$container_id" sh -c \
    'exec psql -X -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" "$@"' \
    sh "$@"
}

db_pg_dump() {
  local container_id

  container_id="$(database_container_id)"
  [[ -n "$container_id" ]] || fail "Conteneur PostgreSQL introuvable"

  docker exec "$container_id" sh -c \
    'exec pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" "$@"' \
    sh "$@"
}

db_pg_restore() {
  local container_id

  container_id="$(database_container_id)"
  [[ -n "$container_id" ]] || fail "Conteneur PostgreSQL introuvable"

  docker exec -i "$container_id" sh -c \
    'exec pg_restore "$@"' \
    sh "$@"
}

check_database_infrastructure() {
  local container_id
  local mounted_volume
  local configured_volume
  local database_name

  container_id="$(database_container_id)"
  [[ -n "$container_id" ]] || fail "Conteneur PostgreSQL introuvable"

  [[ "$(docker inspect -f '{{.State.Running}}' "$container_id")" == "true" ]] ||
    fail "PostgreSQL n'est pas démarré"

  mounted_volume="$(
    docker inspect "$container_id" \
      --format '{{range .Mounts}}{{if eq .Destination "/var/lib/postgresql"}}{{.Name}}{{end}}{{end}}'
  )"

  [[ "$mounted_volume" == "$EXPECTED_VOLUME" ]] ||
    fail "Volume PostgreSQL incorrect : $mounted_volume"

  configured_volume="$(
    compose config --format json |
      python3 -c \
      'import sys,json; print(json.load(sys.stdin)["volumes"]["topscolaire_pg_data"]["name"])'
  )"

  [[ "$configured_volume" == "$EXPECTED_VOLUME" ]] ||
    fail "Le volume déclaré dans Compose ne correspond pas au volume de production"

  database_name="$(db_psql -Atqc 'SELECT current_database()')"

  [[ -n "$database_name" ]] ||
    fail "Connexion à une base inattendue"
}

check_database() {
  local actual_uuid
  local identity_count

  check_database_infrastructure

  if [[ "$BOOTSTRAP_DATABASE_IDENTITY" == "true" && -z "$EXPECTED_DATABASE_UUID" ]]; then
    log "Identité PostgreSQL à initialiser après la migration additive"
    return 0
  fi

  [[ -n "$EXPECTED_DATABASE_UUID" ]] ||
    fail "Identité PostgreSQL absente : effectuer le bootstrap explicite avant de redéployer"

  actual_uuid="$(
    db_psql -Atqc \
      'SELECT uuid FROM public.deployment_identity WHERE id = 1'
  )" || fail "Table deployment_identity absente ou illisible"

  identity_count="$(
    db_psql -Atqc \
      'SELECT COUNT(*) FROM public.deployment_identity'
  )"

  [[ "$identity_count" == "1" ]] && is_valid_uuid "$actual_uuid" ||
    fail "Identité PostgreSQL invalide"

  [[ "$actual_uuid" == "$EXPECTED_DATABASE_UUID" ]] ||
    fail "L'identité PostgreSQL ne correspond pas à celle enregistrée sur le VPS"

  log "PostgreSQL, volume et identité de production vérifiés"
}

if [[ "$ROLLBACK_ONLY" == "true" && -z "$EXPECTED_DATABASE_UUID" ]]; then
  check_database_infrastructure
  log "Rollback d'urgence avant bootstrap : volume PostgreSQL vérifié, identité encore absente"
else
  check_database
fi

# ============================================
# 3. SAUVEGARDE POSTGRESQL
# ============================================

backup_database() {
  local backup

  backup="$BACKUP_DIR/predeploy-$(date -u +%Y%m%dT%H%M%SZ).dump"
  BACKUP_TEMP="${backup}.tmp.$$"

  log "Sauvegarde PostgreSQL : $backup"

  if ! db_pg_dump \
    --format=custom \
    --no-owner \
    --no-privileges > "$BACKUP_TEMP"; then
    fail "Échec de la sauvegarde PostgreSQL"
  fi

  [[ -s "$BACKUP_TEMP" ]] ||
    fail "Sauvegarde vide"

  db_pg_restore --list < "$BACKUP_TEMP" > /dev/null ||
    fail "Sauvegarde illisible"

  mv -- "$BACKUP_TEMP" "$backup"
  chmod 600 "$backup"
  BACKUP_TEMP=""

  log "Sauvegarde créée et catalogue vérifié"
}

initialize_database_identity() {
  local actual_uuid
  local identity_count

  [[ "$BOOTSTRAP_DATABASE_IDENTITY" == "true" && -z "$EXPECTED_DATABASE_UUID" ]] ||
    return 0

  actual_uuid="$(
    db_psql -Atqc \
      "INSERT INTO public.deployment_identity (id, uuid, created_at)
       VALUES (1, gen_random_uuid(), NOW())
       ON CONFLICT (id) DO NOTHING;
       SELECT uuid FROM public.deployment_identity WHERE id = 1"
  )" || fail "Impossible d'initialiser l'identité PostgreSQL"

  identity_count="$(
    db_psql -Atqc \
      'SELECT COUNT(*) FROM public.deployment_identity'
  )"

  [[ "$identity_count" == "1" ]] && is_valid_uuid "$actual_uuid" ||
    fail "Identité PostgreSQL initialisée de manière invalide"

  EXPECTED_DATABASE_UUID="$actual_uuid"
  write_state database-uuid "$actual_uuid"

  log "Identité PostgreSQL permanente enregistrée dans $STATE_DIR/database-uuid"
}

# ============================================
# 4. VERIFICATION ET DEPLOIEMENT DES IMAGES
# ============================================

verify_seeder_class() {
  log "Vérification de ProductionReferenceSeeder dans la nouvelle image"

  compose run --interactive=false -T --rm --no-deps api php -r '
    require "vendor/autoload.php";

    if (! class_exists("Database\\Seeders\\ProductionReferenceSeeder")) {
        fwrite(STDERR, "ProductionReferenceSeeder absent de l’image.\n");
        exit(1);
    }
  '
}

deploy_images() {
  local tag="$1"

  export IMAGE_TAG="$tag"

  log "Déploiement IMAGE_TAG=$tag"

  # Ne touche ni à db ni à redis. api-nginx reste une opération d'infrastructure explicite.
  compose up -d --no-deps --no-build --pull never api

  compose up -d --no-deps --no-build --pull never \
    queue scheduler

  compose up -d --no-deps --no-build --pull never \
    frontend

  if [[ "$REDEPLOY_API_NGINX" == "true" ]]; then
    compose up -d --no-deps --no-build --pull never api-nginx
  fi
}

# ============================================
# 5. VERIFICATION DES CONTENEURS, HTTP ET POSTGRESQL
# ============================================

check_application_containers() {
  local service
  local container_id
  local expected_image
  local actual_image
  local health

  for service in api queue scheduler frontend api-nginx; do
    container_id="$(compose ps -q "$service" 2>/dev/null)"
    [[ -n "$container_id" ]] || return 1

    [[ "$(docker inspect -f '{{.State.Running}}' "$container_id" 2>/dev/null)" == "true" ]] ||
      return 1

    case "$service" in
      api|queue|scheduler)
        expected_image="bulletinlab/api:$IMAGE_TAG"
        ;;
      frontend)
        expected_image="bulletinlab/frontend:$IMAGE_TAG"
        ;;
      *)
        expected_image=""
        ;;
    esac

    if [[ -n "$expected_image" ]]; then
      actual_image="$(docker inspect -f '{{.Config.Image}}' "$container_id" 2>/dev/null)"
      [[ "$actual_image" == "$expected_image" ]] || return 1
    fi

    if [[ "$service" == "api" || "$service" == "api-nginx" ]]; then
      health="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$container_id" 2>/dev/null)"
      [[ "$health" == "healthy" ]] || return 1
    fi
  done
}

check_health() {
  local attempt
  local local_api_healthy=false

  for attempt in $(seq 1 30); do
    if curl -fsS --max-time 5 http://127.0.0.1:8081/up |
      grep -q 'Application up' &&
      check_application_containers; then
      local_api_healthy=true
      break
    fi

    sleep 3
  done

  [[ "$local_api_healthy" == "true" ]] ||
    fail "API locale, conteneurs applicatifs, images ou healthchecks incorrects"

  for attempt in $(seq 1 12); do
    if curl -fsS --max-time 15 https://topscolaire.com/up |
      grep -q 'Application up' &&
      curl -fsSI --max-time 15 https://topscolaire.com >/dev/null; then

      check_database

      log "Conteneurs, images, API locale et endpoints HTTPS vérifiés"
      return 0
    fi

    sleep 5
  done

  fail "Application ou endpoints HTTPS inaccessibles après déploiement"
}

# ============================================
# 6. ROLLBACK MANUEL DES IMAGES UNIQUEMENT
# ============================================

if [[ "$ROLLBACK_ONLY" == "true" ]]; then

  tag="${ROLLBACK_IMAGE_TAG:-}"
  active_tag=""

  if [[ -s "$STATE_DIR/current-tag" ]]; then
    active_tag="$(cat "$STATE_DIR/current-tag")"
  fi

  if [[ -z "$tag" ]]; then
    [[ -s "$STATE_DIR/previous-tag" ]] ||
      fail "Aucun tag précédent enregistré"

    tag="$(cat "$STATE_DIR/previous-tag")"
  fi

  [[ "$tag" =~ ^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$ ]] ||
    fail "Tag invalide"

  docker image inspect "bulletinlab/api:$tag" >/dev/null ||
    fail "Image backend absente"

  docker image inspect "bulletinlab/frontend:$tag" >/dev/null ||
    fail "Image frontend absente"

  deploy_images "$tag"
  check_health

  if [[ -n "$active_tag" && "$active_tag" != "$tag" ]]; then
    write_state previous-tag "$active_tag"
  fi

  write_state current-tag "$tag"

  log "Retour aux anciennes images terminé"
  log "PostgreSQL n'a pas été restauré ni modifié"

  exit 0
fi

# ============================================
# 7. IDENTIFICATION ET PROPRETE DES DEPOTS
# ============================================

BACKEND_SHA="${EXPECTED_BACKEND_SHA:-}"
FRONTEND_SHA="${EXPECTED_FRONTEND_SHA:-}"

[[ "$BACKEND_SHA" =~ ^[0-9a-f]{40}$ ]] ||
  fail "SHA backend invalide"

[[ "$FRONTEND_SHA" =~ ^[0-9a-f]{40}$ ]] ||
  fail "SHA frontend invalide"

NEW_TAG="main-${BACKEND_SHA:0:12}-${FRONTEND_SHA:0:12}"
OLD_TAG=""

if [[ -s "$STATE_DIR/current-tag" ]]; then
  OLD_TAG="$(cat "$STATE_DIR/current-tag")"
fi

log "Backend : $BACKEND_SHA"
log "Frontend : $FRONTEND_SHA"

git -C "$ROOT" fetch origin main --prune
git -C "$ROOT/frontend" fetch origin main --prune

git -C "$ROOT" cat-file -e "${BACKEND_SHA}^{commit}" 2>/dev/null ||
  fail "Commit backend inaccessible"

git -C "$ROOT/frontend" cat-file -e "${FRONTEND_SHA}^{commit}" 2>/dev/null ||
  fail "Commit frontend inaccessible"

git -C "$ROOT" merge-base --is-ancestor "$BACKEND_SHA" origin/main ||
  fail "Le commit backend demandé n'appartient pas à origin/main"

git -C "$ROOT/frontend" merge-base --is-ancestor "$FRONTEND_SHA" origin/main ||
  fail "Le commit frontend demandé n'appartient pas à origin/main"

[[ -z "$(git -C "$ROOT" status --porcelain --untracked-files=all -- . ':(exclude).deploy-state/**')" ]] ||
  fail "Le dépôt backend contient des modifications locales"

[[ -z "$(git -C "$ROOT/frontend" status --porcelain --untracked-files=all)" ]] ||
  fail "Le dépôt frontend contient des modifications locales"

git -C "$ROOT" checkout --detach "$BACKEND_SHA"
git -C "$ROOT/frontend" checkout --detach "$FRONTEND_SHA"

compose config --quiet

# ============================================
# 8. CONTROLE STRICT DES CHANGEMENTS DE MIGRATIONS
# ============================================

[[ -s "$STATE_DIR/backend-sha" ]] ||
  fail "Premier déploiement non initialisé : renseigner .deploy-state/backend-sha après audit manuel"

OLD_BACKEND_SHA="$(cat "$STATE_DIR/backend-sha")"

[[ "$OLD_BACKEND_SHA" =~ ^[0-9a-f]{40}$ ]] ||
  fail "Ancien SHA backend invalide"

git cat-file -e "${OLD_BACKEND_SHA}^{commit}" 2>/dev/null ||
  fail "Ancien commit introuvable : contrôle des migrations impossible"

git merge-base --is-ancestor "$OLD_BACKEND_SHA" "$BACKEND_SHA" ||
  fail "L'ancien SHA n'est pas un ancêtre du déploiement demandé"

NEW_MIGRATIONS=()

migration_changes="$(
  git diff --name-status --find-renames \
    "$OLD_BACKEND_SHA" "$BACKEND_SHA" -- database/migrations
)" || fail "Impossible de contrôler les changements de migrations"

while IFS=$'\t' read -r status path extra_path; do
  [[ -n "$status" ]] || continue

  case "$status" in
    A)
      [[ "$path" == database/migrations/*.php && -z "$extra_path" ]] ||
        fail "Seules les nouvelles migrations PHP additives sont acceptées : $status $path"
      NEW_MIGRATIONS+=("$path")
      ;;
    *)
      fail "Modification, suppression ou renommage d'une migration existante interdit : $status $path"
      ;;
  esac
done <<< "$migration_changes"

if (( ${#NEW_MIGRATIONS[@]} > 0 )); then
  printf '[TOPSCOLAIRE] Nouvelle migration : %s\n' "${NEW_MIGRATIONS[@]}"

  [[ "$APPROVE_MIGRATIONS" == "true" ]] ||
    fail "Nouvelles migrations détectées : revue et approbation manuelle obligatoires après tests PostgreSQL 18"

  log "Nouvelles migrations explicitement approuvées ; aucune migration existante modifiée"
fi

# ============================================
# 9. CONSTRUCTION DES IMAGES
# ============================================

export IMAGE_TAG="$NEW_TAG"

compose config --quiet

log "Construction des images"

compose build api frontend

docker image inspect "bulletinlab/api:$NEW_TAG" >/dev/null ||
  fail "Image backend construite introuvable"

docker image inspect "bulletinlab/frontend:$NEW_TAG" >/dev/null ||
  fail "Image frontend construite introuvable"

check_database
verify_seeder_class

# ============================================
# 10. SAUVEGARDE ET MIGRATIONS
# ============================================

backup_database

log "Application des migrations en attente"

compose --profile maintenance \
  run --interactive=false -T --rm --no-deps migrate

check_database_infrastructure
initialize_database_identity
check_database

# ============================================
# 11. SEEDER DE PRODUCTION IDEMPOTENT
# ============================================

# Seeders désactivés par défaut
RUN_REFERENCE_SEEDER="${RUN_REFERENCE_SEEDER:-false}"

if [[ "$RUN_REFERENCE_SEEDER" == "true" ]]; then
  log "Synchronisation des référentiels de production"

  compose run --interactive=false -T --rm --no-deps api \
    php artisan db:seed \
    --class='Database\Seeders\ProductionReferenceSeeder' \
    --force \
    --no-interaction

  check_database
else
  log "Aucun seeder exécuté pour ce déploiement"
fi

# ============================================
# 12. MISE EN SERVICE DES NOUVELLES IMAGES
# ============================================

# En cas d'échec du rollout, le rollback manuel cible la dernière image active.
if [[ -n "$OLD_TAG" && "$OLD_TAG" != "$NEW_TAG" ]]; then
  write_state previous-tag "$OLD_TAG"
fi

deploy_images "$NEW_TAG"
check_health

# ============================================
# 13. MEMORISATION DES VERSIONS
# ============================================

write_state current-tag "$NEW_TAG"
write_state backend-sha "$BACKEND_SHA"
write_state frontend-sha "$FRONTEND_SHA"
# Vérification finale obligatoire
check_database

[[ -s "$STATE_DIR/database-uuid" ]] ||
  fail "Déploiement incomplet : UUID PostgreSQL non enregistré"

log "Déploiement réussi : $NEW_TAG"
