#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="${APP_DIR:-/opt/topscolaire}"
FRONTEND_DIR="${FRONTEND_DIR:-${APP_DIR}/frontend}"
ENV_FILE="${ENV_FILE:-${APP_DIR}/.env.production}"

# Robustesse : si ENV_FILE vient de l'environnement serveur mais pointe vers
# un fichier introuvable, basculer sur .env.production (présent sur prod).
if [[ ! -f "$ENV_FILE" && -f "${APP_DIR}/.env.production" ]]; then
    ENV_FILE="${APP_DIR}/.env.production"
fi
BACKUP_DIR="${BACKUP_DIR:-/opt/backups/topscolaire}"
EXPECTED_BACKEND_SHA="${EXPECTED_BACKEND_SHA:-}"
EXPECTED_FRONTEND_SHA="${EXPECTED_FRONTEND_SHA:-}"
AUTO_ROLLBACK="${AUTO_ROLLBACK:-true}"
ROLLBACK_ONLY="${ROLLBACK_ONLY:-false}"
ROLLBACK_IMAGE_TAG="${ROLLBACK_IMAGE_TAG:-}"

BACKEND_SHA="unknown"
FRONTEND_SHA="unknown"
IMAGE_TAG="unknown"
PREVIOUS_IMAGE_TAG=""
BACKUP_TEMP=""
LOCAL_RELEASE_HTML=""
PUBLIC_RELEASE_HTML=""
ROLLBACK_ARMED=false

log() {
    printf '[deploy] %s\n' "$*"
}

log_error() {
    printf '[deploy] ERROR: %s\n' "$*" >&2
}

cleanup() {
    if [[ -n "$BACKUP_TEMP" && -f "$BACKUP_TEMP" ]]; then
        rm -f -- "$BACKUP_TEMP"
    fi

    if [[ -n "$LOCAL_RELEASE_HTML" && -f "$LOCAL_RELEASE_HTML" ]]; then
        rm -f -- "$LOCAL_RELEASE_HTML"
    fi

    if [[ -n "$PUBLIC_RELEASE_HTML" && -f "$PUBLIC_RELEASE_HTML" ]]; then
        rm -f -- "$PUBLIC_RELEASE_HTML"
    fi
}

trap cleanup EXIT

compose() {
    docker compose --env-file "$ENV_FILE" "$@"
}

is_valid_sha() {
    [[ "$1" =~ ^[0-9a-f]{40}$ ]]
}

is_valid_image_tag() {
    [[ "$1" =~ ^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$ ]]
}

check_prerequisites() {
    log 'Running pre-flight checks...'

    [[ -d "$APP_DIR/.git" ]] || { log_error "Backend repository not found at $APP_DIR"; exit 1; }
    [[ -d "$FRONTEND_DIR/.git" ]] || { log_error "Frontend repository not found at $FRONTEND_DIR"; exit 1; }
    [[ -f "$ENV_FILE" ]] || { log_error "Environment file not found: $ENV_FILE"; exit 1; }

    local command_name
    for command_name in curl docker flock git gzip sed; do
        command -v "$command_name" >/dev/null 2>&1 \
            || { log_error "Required command not found: $command_name"; exit 1; }
    done

    docker compose version >/dev/null

    local required_variable
    for required_variable in APP_KEY DB_DATABASE DB_USERNAME DB_PASSWORD; do
        grep -Eq "^${required_variable}=.+" "$ENV_FILE" \
            || { log_error "Missing or empty variable in $ENV_FILE: $required_variable"; exit 1; }
    done

    install -d -m 700 "$BACKUP_DIR"
    [[ -w "$BACKUP_DIR" ]] || { log_error "Backup directory is not writable: $BACKUP_DIR"; exit 1; }

    log 'Pre-flight checks passed.'
}

acquire_lock() {
    exec 9>/tmp/topscolaire-production-deploy.lock

    if ! flock -n 9; then
        log_error 'Another TopScolaire deployment is already running.'
        exit 1
    fi

    log 'Deployment lock acquired.'
}

validate_compose_project() {
    compose config --quiet

    local project_name
    project_name="$(compose config --format json \
        | sed -n 's/.*"name":[[:space:]]*"\([^"]*\)".*/\1/p' \
        | head -n 1)"

    if [[ "$project_name" != 'topscolaire' ]]; then
        log_error "Compose project is '$project_name'; expected 'topscolaire'."
        exit 1
    fi
}

update_repository() {
    local repository="$1"
    local expected_sha="$2"
    local target='origin/main'

    git -C "$repository" fetch origin main --prune

    if [[ -n "$expected_sha" ]]; then
        is_valid_sha "$expected_sha" \
            || { log_error "Invalid Git SHA: $expected_sha"; exit 1; }

        git -C "$repository" cat-file -e "${expected_sha}^{commit}" 2>/dev/null \
            || { log_error "Revision $expected_sha is unavailable in $repository"; exit 1; }

        git -C "$repository" merge-base --is-ancestor "$expected_sha" origin/main \
            || { log_error "Revision $expected_sha is not on origin/main in $repository"; exit 1; }

        target="$expected_sha"
    fi

    git -C "$repository" checkout -B main "$target" --quiet
    log "Updated $(basename "$repository") to $(git -C "$repository" rev-parse --short HEAD)."
}

current_frontend_image_tag() {
    local container_id
    local image_reference

    container_id="$(compose ps -q frontend 2>/dev/null || true)"
    [[ -n "$container_id" ]] || return 1

    image_reference="$(docker inspect --format '{{.Config.Image}}' "$container_id" 2>/dev/null || true)"
    [[ "$image_reference" == bulletinlab/frontend:* ]] || return 1

    printf '%s\n' "${image_reference#bulletinlab/frontend:}"
}

image_pair_exists() {
    local image_tag="$1"

    docker image inspect "bulletinlab/api:${image_tag}" >/dev/null 2>&1 \
        && docker image inspect "bulletinlab/frontend:${image_tag}" >/dev/null 2>&1
}

previous_image_tag() {
    local current_tag="$1"
    local candidate

    while IFS= read -r candidate; do
        [[ -n "$candidate" && "$candidate" != '<none>' && "$candidate" != "$current_tag" ]] || continue

        if is_valid_image_tag "$candidate" && image_pair_exists "$candidate"; then
            printf '%s\n' "$candidate"
            return 0
        fi
    done < <(docker images bulletinlab/frontend --format '{{.Tag}}')

    return 1
}

health_check() {
    local name="$1"
    local url="$2"
    local expected="${3:-}"
    local attempt
    local response

    for attempt in $(seq 1 12); do
        if response="$(curl --fail --silent --show-error --max-time 10 "$url" 2>/dev/null)"; then
            if [[ -z "$expected" ]] || grep -q "$expected" <<<"$response"; then
                log "$name is healthy."
                return 0
            fi
        fi

        log "Waiting for $name ($attempt/12)..."
        sleep 5
    done

    log_error "$name health check failed: $url"
    return 1
}

verify_frontend_release() {
    LOCAL_RELEASE_HTML="$(mktemp)"
    PUBLIC_RELEASE_HTML="$(mktemp)"

    curl --fail --silent --show-error --retry 5 --retry-connrefused --retry-delay 3 \
        -H 'Cache-Control: no-cache' \
        "http://127.0.0.1:8080/?_release=${IMAGE_TAG}" \
        --output "$LOCAL_RELEASE_HTML"

    local attempt
    for attempt in $(seq 1 15); do
        if curl --fail --silent --show-error --max-time 15 \
            -H 'Cache-Control: no-cache' \
            "https://topscolaire.com/?_release=${IMAGE_TAG}&attempt=${attempt}" \
            --output "$PUBLIC_RELEASE_HTML" \
            && cmp --silent "$LOCAL_RELEASE_HTML" "$PUBLIC_RELEASE_HTML"; then
            log 'Public frontend matches the local deployed release.'
            return 0
        fi

        log "Waiting for public frontend propagation ($attempt/15)..."
        sleep 5
    done

    log_error 'Public frontend does not match the local deployed release.'
    return 1
}

verify_application() {
    health_check 'Local API' 'http://127.0.0.1:8081/up' 'Application up'
    health_check 'Local frontend' 'http://127.0.0.1:8080/'
    health_check 'Public API' 'https://topscolaire.com/up' 'Application up'
    verify_frontend_release
}

restore_image_pair() {
    local image_tag="$1"

    is_valid_image_tag "$image_tag" \
        || { log_error "Invalid rollback image tag: $image_tag"; return 1; }
    image_pair_exists "$image_tag" \
        || { log_error "Complete API/frontend image pair not found for tag: $image_tag"; return 1; }

    log "Restoring Docker image pair: $image_tag"
    export IMAGE_TAG="$image_tag"
    compose up -d --wait --wait-timeout 300
    verify_application
    log "Rollback completed with image tag: $image_tag"
}

automatic_rollback() {
    if [[ "$ROLLBACK_ARMED" != true ]]; then
        log 'Failure occurred before the application rollout; current containers were left unchanged.'
        return 0
    fi

    if [[ "$AUTO_ROLLBACK" != true || -z "$PREVIOUS_IMAGE_TAG" ]]; then
        log_error 'Automatic rollback is unavailable; manual verification is required.'
        return 0
    fi

    log "Attempting automatic rollback to $PREVIOUS_IMAGE_TAG..."
    restore_image_pair "$PREVIOUS_IMAGE_TAG" \
        || log_error 'Automatic rollback failed; manual intervention is required.'
}

on_error() {
    local exit_code="$1"
    local line="$2"
    local command="$3"

    trap - ERR
    log_error "Command failed with exit code $exit_code at line $line: $command"
    automatic_rollback
    exit "$exit_code"
}

trap 'on_error $? ${LINENO} "$BASH_COMMAND"' ERR

wait_for_postgres() {
    local attempt

    for attempt in $(seq 1 15); do
        if compose exec -T db sh -c 'pg_isready -U "$POSTGRES_USER" -d "$POSTGRES_DB"' >/dev/null 2>&1; then
            log 'PostgreSQL accepts connections.'
            return 0
        fi

        log "Waiting for PostgreSQL ($attempt/15)..."
        sleep 3
    done

    log_error 'PostgreSQL did not become ready.'
    return 1
}

backup_database() {
    local backup_timestamp
    local backup_file
    local attempt

    backup_timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
    backup_file="${BACKUP_DIR}/topscolaire-${backup_timestamp}-$$.sql.gz"
    BACKUP_TEMP="${backup_file}.tmp"
    umask 077

    for attempt in 1 2 3; do
        log "Creating PostgreSQL backup ($attempt/3)..."
        rm -f -- "$BACKUP_TEMP"

        if compose exec -T db \
            sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB" --no-owner --no-privileges' \
            | gzip > "$BACKUP_TEMP" \
            && [[ -s "$BACKUP_TEMP" ]] \
            && gzip -t "$BACKUP_TEMP"; then
            mv "$BACKUP_TEMP" "$backup_file"
            BACKUP_TEMP=""
            chmod 600 "$backup_file"
            log "Backup created: $backup_file"
            return 0
        fi

        sleep 5
    done

    log_error 'Unable to create a verified PostgreSQL backup; deployment stopped.'
    return 1
}

run_migrations() {
    log 'Current migration status:'
    compose --profile maintenance run --rm migrate php artisan migrate:status || true

    log 'Applying migrations...'
    compose --profile maintenance run --rm migrate php artisan migrate --force
}

sync_production_reference_data() {
    log 'Synchronizing required production reference data...'
    compose --profile maintenance run --rm migrate \
        php artisan db:seed --class=Database\\Seeders\\ProductionReferenceSeeder --force
    log 'Production reference data is complete.'
}

prune_old_artifacts() {
    find "$BACKUP_DIR" -name 'topscolaire-*.sql.gz' -type f \
        | sort -r \
        | tail -n +11 \
        | xargs -r rm -f

    docker image prune -f >/dev/null 2>&1 || true
}

manual_rollback() {
    local current_tag
    local target_tag="$ROLLBACK_IMAGE_TAG"

    current_tag="$(current_frontend_image_tag || true)"

    if [[ -z "$target_tag" ]]; then
        target_tag="$(previous_image_tag "$current_tag" || true)"
    fi

    [[ -n "$target_tag" ]] \
        || { log_error 'No previous complete image pair is available for rollback.'; exit 1; }

    restore_image_pair "$target_tag"
}

deploy_release() {
    local start_time
    local current_tag
    local duration

    start_time="$(date +%s)"

    update_repository "$APP_DIR" "$EXPECTED_BACKEND_SHA"
    update_repository "$FRONTEND_DIR" "$EXPECTED_FRONTEND_SHA"

    # Validate the Compose file from the requested backend revision, not the stale server checkout.
    validate_compose_project

    BACKEND_SHA="$(git -C "$APP_DIR" rev-parse HEAD)"
    FRONTEND_SHA="$(git -C "$FRONTEND_DIR" rev-parse HEAD)"

    if [[ -n "$EXPECTED_BACKEND_SHA" && "$BACKEND_SHA" != "$EXPECTED_BACKEND_SHA" ]]; then
        log_error 'Backend SHA mismatch after checkout.'
        exit 1
    fi

    if [[ -n "$EXPECTED_FRONTEND_SHA" && "$FRONTEND_SHA" != "$EXPECTED_FRONTEND_SHA" ]]; then
        log_error 'Frontend SHA mismatch after checkout.'
        exit 1
    fi

    export IMAGE_TAG="main-${BACKEND_SHA:0:12}-${FRONTEND_SHA:0:12}"
    current_tag="$(current_frontend_image_tag || true)"

    if [[ -n "$current_tag" ]] && image_pair_exists "$current_tag"; then
        PREVIOUS_IMAGE_TAG="$current_tag"
        log "Current image pair retained for rollback: $PREVIOUS_IMAGE_TAG"
    fi

    log "Building image pair: $IMAGE_TAG"
    compose build --pull api frontend
    image_pair_exists "$IMAGE_TAG" \
        || { log_error "Built image pair is incomplete for tag: $IMAGE_TAG"; exit 1; }

    log 'Starting PostgreSQL and Redis...'
    compose up -d --wait --wait-timeout 180 db redis
    wait_for_postgres

    backup_database
    run_migrations
    sync_production_reference_data

    log 'Rolling out the application stack...'
    ROLLBACK_ARMED=true
    compose up -d --wait --wait-timeout 300
    verify_application
    ROLLBACK_ARMED=false

    prune_old_artifacts

    duration=$(( $(date +%s) - start_time ))
    log "Deployment completed in ${duration}s."
    log "Backend SHA: $BACKEND_SHA"
    log "Frontend SHA: $FRONTEND_SHA"
    log "Image tag: $IMAGE_TAG"
}

main() {
    log '=== TopScolaire production operation started ==='
    check_prerequisites
    acquire_lock
    cd "$APP_DIR"

    if [[ "$ROLLBACK_ONLY" == true ]]; then
        validate_compose_project
        manual_rollback
    else
        deploy_release
    fi
}

main "$@"
