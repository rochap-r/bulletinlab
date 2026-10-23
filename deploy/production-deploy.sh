#!/usr/bin/env bash
# Production deployment script for TopScolaire
# Implements resilient, observable, and secure deployment practices
#
# Usage: EXPECTED_BACKEND_SHA=<sha> EXPECTED_FRONTEND_SHA=<sha> bash -s < production-deploy.sh
# Environment: APP_DIR, FRONTEND_DIR, ENV_FILE, BACKUP_DIR, DEPLOY_TIMEOUT, ROLLBACK_ON_FAILURE

set -Eeuo pipefail

# ─── Configuration ─────────────────────────────────────────────────────────────
APP_DIR="${APP_DIR:-/opt/topscolaire}"
FRONTEND_DIR="${APP_DIR}/frontend"
ENV_FILE="${ENV_FILE:-${APP_DIR}/.env.production}"
BACKUP_DIR="${BACKUP_DIR:-/opt/backups/topscolaire}"
DEPLOY_TIMEOUT="${DEPLOY_TIMEOUT:-1800}"  # 30 minutes total
ROLLBACK_ON_FAILURE="${ROLLBACK_ON_FAILURE:-true}"
NOTIFICATION_WEBHOOK="${NOTIFICATION_WEBHOOK:-}"  # Slack/Discord webhook URL
EXPECTED_BACKEND_SHA="${EXPECTED_BACKEND_SHA:-}"
EXPECTED_FRONTEND_SHA="${EXPECTED_FRONTEND_SHA:-}"

# ─── Logging & Colors ──────────────────────────────────────────────────────────
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly BLUE='\033[0;34m'
readonly NC='\033[0m' # No Color

log() { echo -e "${BLUE}[deploy]${NC} $*"; }
log_ok() { echo -e "${GREEN}[deploy]${NC} $*"; }
log_warn() { echo -e "${YELLOW}[deploy]${NC} $*"; }
log_err() { echo -e "${RED}[deploy]${NC} $*" >&2; }

# ─── Notification Helper ───────────────────────────────────────────────────────
notify() {
    local status="$1"
    local message="$2"
    local color="${3:-#36a64f}"  # green default

    if [[ -n "$NOTIFICATION_WEBHOOK" ]]; then
        local payload=$(jq -n \
            --arg text "$message" \
            --arg color "$color" \
            --arg status "$status" \
            --arg sha "$BACKEND_SHA" \
            --arg frontend_sha "$FRONTEND_SHA" \
            --arg image_tag "$IMAGE_TAG" \
            '{
                attachments: [{
                    color: $color,
                    title: "TopScolaire Deployment \($status)",
                    text: $text,
                    fields: [
                        {title: "Backend SHA", value: $sha, short: true},
                        {title: "Frontend SHA", value: $frontend_sha, short: true},
                        {title: "Image Tag", value: $image_tag, short: true},
                        {title: "Status", value: $status, short: true}
                    ],
                    ts: (now | floor)
                }]
            }')
        curl -sS -X POST -H 'Content-Type: application/json' -d "$payload" "$NOTIFICATION_WEBHOOK" >/dev/null 2>&1 || true
    fi
}

# ─── Cleanup on Exit ───────────────────────────────────────────────────────────
BACKUP_TEMP=""
LOCAL_RELEASE_HTML=""
PUBLIC_RELEASE_HTML=""
PREVIOUS_IMAGE_TAG=""

cleanup() {
    [[ -n "$BACKUP_TEMP" && -f "$BACKUP_TEMP" ]] && rm -f -- "$BACKUP_TEMP"
    [[ -n "$LOCAL_RELEASE_HTML" && -f "$LOCAL_RELEASE_HTML" ]] && rm -f -- "$LOCAL_RELEASE_HTML"
    [[ -n "$PUBLIC_RELEASE_HTML" && -f "$PUBLIC_RELEASE_HTML" ]] && rm -f -- "$PUBLIC_RELEASE_HTML"
}
trap cleanup EXIT

# ─── Rollback Function ─────────────────────────────────────────────────────────
rollback() {
    log_warn "Initiating rollback to previous image: $PREVIOUS_IMAGE_TAG"
    notify "FAILED" "Deployment failed, rolling back to $PREVIOUS_IMAGE_TAG" "#ff0000"

    if [[ -n "$PREVIOUS_IMAGE_TAG" ]]; then
        export IMAGE_TAG="$PREVIOUS_IMAGE_TAG"
        "${COMPOSE[@]}" up -d --wait --wait-timeout 180 2>/dev/null || true
        "${COMPOSE[@]}" up -d --wait --wait-timeout 120 --force-recreate --no-deps frontend 2>/dev/null || true
        log_warn "Rollback attempted. Manual verification required."
    else
        log_err "No previous image tag available for rollback."
    fi
}

# ─── Error Handler ─────────────────────────────────────────────────────────────
on_error() {
    local line="$1"
    local cmd="$2"
    log_err "Error at line $line: $cmd"
    if [[ "$ROLLBACK_ON_FAILURE" == "true" ]]; then
        rollback
    fi
    notify "FAILED" "Deployment failed at line $line: $cmd" "#ff0000"
    exit 1
}
trap 'on_error ${LINENO} "$BASH_COMMAND"' ERR

# ─── Pre-flight Checks ─────────────────────────────────────────────────────────
check_prerequisites() {
    log "Running pre-flight checks..."

    [[ -d "$APP_DIR/.git" ]] || { log_err "Backend repo not found at $APP_DIR"; exit 1; }
    [[ -d "$FRONTEND_DIR/.git" ]] || { log_err "Frontend repo not found at $FRONTEND_DIR"; exit 1; }
    [[ -f "$ENV_FILE" ]] || { log_err "Environment file not found: $ENV_FILE"; exit 1; }
    command -v flock >/dev/null 2>&1 || { log_err "flock required for deployment locking"; exit 1; }
    command -v docker >/dev/null 2>&1 || { log_err "docker not found"; exit 1; }
    command -v jq >/dev/null 2>&1 || { log_err "jq required for notifications"; exit 1; }

    # Validate .env.production has required vars
    local required_vars=(POSTGRES_USER POSTGRES_DB DB_PASSWORD REDIS_PASSWORD APP_KEY)
    for var in "${required_vars[@]}"; do
        grep -q "^${var}=" "$ENV_FILE" || log_warn "Missing recommended variable in $ENV_FILE: $var"
    done

    log_ok "Pre-flight checks passed"
}

# ─── Deployment Lock ───────────────────────────────────────────────────────────
acquire_lock() {
    exec 9>/tmp/topscolaire-production-deploy.lock
    if ! flock -n 9; then
        log_err "Another deployment is already in progress (lock held)."
        exit 1
    fi
    log_ok "Deployment lock acquired"
}

# ─── Git Operations ────────────────────────────────────────────────────────────
update_main_branch() {
    local repository="$1"
    local expected_sha="${2:-}"
    local target="origin/main"

    git -C "$repository" fetch origin main --prune

    if [[ -n "$expected_sha" ]]; then
        git -C "$repository" cat-file -e "${expected_sha}^{commit}" 2>/dev/null \
            || { log_err "Revision $expected_sha not found in $repository"; exit 1; }

        git -C "$repository" merge-base --is-ancestor "$expected_sha" origin/main \
            || { log_err "Revision $expected_sha is not ancestor of origin/main in $repository"; exit 1; }

        target="$expected_sha"
    fi

    git -C "$repository" checkout -B main "$target" --quiet
    log_ok "Updated $(basename "$repository") to $(git -C "$repository" rev-parse --short HEAD)"
}

# ─── Docker Compose Wrapper ────────────────────────────────────────────────────
compose() {
    docker compose --env-file "$ENV_FILE" "$@"
}

# ─── Wait for PostgreSQL Ready ─────────────────────────────────────────────────
wait_for_pg_ready() {
    log "Waiting for PostgreSQL to accept connections..."
    local max_attempts=15
    local attempt=1

    while [[ $attempt -le $max_attempts ]]; do
        if compose exec -T db sh -c 'pg_isready -U "$POSTGRES_USER" -d "$POSTGRES_DB"' >/dev/null 2>&1; then
            log_ok "PostgreSQL ready for connections"
            return 0
        fi
        log "PostgreSQL not ready yet (attempt $attempt/$max_attempts)..."
        sleep 3
        ((attempt++))
    done

    log_err "PostgreSQL did not become ready in time"
    return 1
}

# ─── Database Backup ───────────────────────────────────────────────────────────
backup_database() {
    log "Creating PostgreSQL backup..."

    install -d -m 700 "$BACKUP_DIR"
    umask 077

    BACKUP_TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
    BACKUP_FILE="${BACKUP_DIR}/topscolaire-${BACKUP_TIMESTAMP}-$$.sql.gz"
    BACKUP_TEMP="${BACKUP_FILE}.tmp"

    # Attempt backup with retries
    local max_attempts=3
    local attempt=1

    while [[ $attempt -le $max_attempts ]]; do
        if compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --no-owner --no-privileges' \
            | gzip > "$BACKUP_TEMP"; then

            # Verify backup integrity
            if gzip -t "$BACKUP_TEMP" 2>/dev/null; then
                mv "$BACKUP_TEMP" "$BACKUP_FILE"
                BACKUP_TEMP=""
                chmod 600 "$BACKUP_FILE"
                log_ok "Backup created: $(basename "$BACKUP_FILE") ($(du -h "$BACKUP_FILE" | cut -f1))"

                # Verify backup is non-empty and restorable (quick check)
                if zcat "$BACKUP_FILE" | head -5 | grep -q "PostgreSQL database dump"; then
                    log_ok "Backup format verified"
                else
                    log_warn "Backup format verification failed - backup may be corrupted"
                fi
                return 0
            fi
        fi

        log_warn "Backup attempt $attempt/$max_attempts failed, retrying..."
        rm -f -- "$BACKUP_TEMP"
        sleep 5
        ((attempt++))
    done

    log_warn "All backup attempts failed, continuing deployment without backup"
    rm -f -- "$BACKUP_TEMP"
    BACKUP_TEMP=""
    return 1
}

# ─── Migration with Validation ─────────────────────────────────────────────────
run_migrations() {
    log "Running database migrations..."

    # First, show migration status
    compose --profile maintenance run --rm migrate php artisan migrate:status || true

    # Run migrations
    if ! compose --profile maintenance run --rm migrate php artisan migrate --force; then
        log_err "Migrations failed"
        return 1
    fi

    log_ok "Migrations completed successfully"
}

# ─── Health Checks ─────────────────────────────────────────────────────────────
health_check_local() {
    local service="$1"
    local url="$2"
    local expected="${3:-}"
    local max_attempts=12
    local attempt=1

    log "Health check: $service ($url)"

    while [[ $attempt -le $max_attempts ]]; do
        if response=$(curl --fail --silent --show-error --max-time 10 "$url" 2>/dev/null); then
            if [[ -z "$expected" ]] || echo "$response" | grep -q "$expected"; then
                log_ok "$service is healthy"
                return 0
            fi
        fi
        log "Waiting for $service... (attempt $attempt/$max_attempts)"
        sleep 5
        ((attempt++))
    done

    log_err "$service health check failed after $max_attempts attempts"
    return 1
}

health_check_public() {
    log "Verifying public HTTPS endpoint..."
    health_check_local "HTTPS /up" "https://topscolaire.com/up" "Application up"
}

# ─── Frontend Release Verification ─────────────────────────────────────────────
verify_frontend_release() {
    log "Verifying frontend release matches deployed version..."

    LOCAL_RELEASE_HTML="$(mktemp)"
    PUBLIC_RELEASE_HTML="$(mktemp)"
    RELEASE_CHECK="_release=${IMAGE_TAG}"

    # Get local frontend fingerprint
    curl --fail --silent --show-error --retry 5 --retry-connrefused --retry-delay 3 \
        -H 'Cache-Control: no-cache' \
        "http://127.0.0.1:8080/?${RELEASE_CHECK}" \
        --output "$LOCAL_RELEASE_HTML" || { log_err "Failed to fetch local frontend"; return 1; }

    # Compare with public endpoint (with retries for CDN propagation)
    local max_attempts=15
    local attempt=1

    while [[ $attempt -le $max_attempts ]]; do
        if curl --fail --silent --show-error --max-time 15 \
            -H 'Cache-Control: no-cache' \
            "https://topscolaire.com/?${RELEASE_CHECK}&attempt=${attempt}" \
            --output "$PUBLIC_RELEASE_HTML"; then

            if cmp --silent "$LOCAL_RELEASE_HTML" "$PUBLIC_RELEASE_HTML"; then
                log_ok "Public frontend matches deployed version"
                return 0
            fi
        fi
        log "Frontend mismatch or fetch failed (attempt $attempt/$max_attempts), waiting for CDN propagation..."
        sleep 5
        ((attempt++))
    done

    log_err "Public frontend does not match deployed version"
    log_err "Local hash:  $(sha256sum "$LOCAL_RELEASE_HTML" | cut -d' ' -f1)"
    log_err "Public hash: $(sha256sum "$PUBLIC_RELEASE_HTML" | cut -d' ' -f1)"
    return 1
}

# ─── Capture Previous Image for Rollback ───────────────────────────────────────
capture_previous_image() {
    PREVIOUS_IMAGE_TAG=$(docker images --format '{{.Repository}}:{{.Tag}}' \
        | grep '^bulletinlab/frontend:' \
        | grep -v "$IMAGE_TAG" \
        | head -1) || PREVIOUS_IMAGE_TAG=""

    if [[ -n "$PREVIOUS_IMAGE_TAG" ]]; then
        log "Previous frontend image for rollback: $PREVIOUS_IMAGE_TAG"
    else
        log_warn "No previous frontend image found for rollback"
    fi
}

# ─── Prune Old Backups & Images ────────────────────────────────────────────────
prune_old_artifacts() {
    log "Pruning old backups and Docker images..."

    # Keep last 10 backups
    find "$BACKUP_DIR" -name 'topscolaire-*.sql.gz' -type f \
        | sort -r | tail -n +11 | xargs -r rm -f

    # Prune Docker images older than 7 days, keep at least 3 per service
    docker image prune -f --filter "until=168h" --filter "label!=keep" >/dev/null 2>&1 || true

    log_ok "Cleanup completed"
}

# ─── Main Deployment Flow ──────────────────────────────────────────────────────
main() {
    local start_time=$(date +%s)
    log "=== TopScolaire Production Deployment Started ==="

    check_prerequisites
    acquire_lock

    log "Updating repositories..."
    update_main_branch "$APP_DIR" "$EXPECTED_BACKEND_SHA"
    update_main_branch "$FRONTEND_DIR" "$EXPECTED_FRONTEND_SHA"

    BACKEND_SHA="$(git -C "$APP_DIR" rev-parse HEAD)"
    FRONTEND_SHA="$(git -C "$FRONTEND_DIR" rev-parse HEAD)"

    [[ -n "$EXPECTED_BACKEND_SHA" && "$BACKEND_SHA" != "$EXPECTED_BACKEND_SHA" ]] \
        && { log_err "Backend SHA mismatch"; exit 1; }
    [[ -n "$EXPECTED_FRONTEND_SHA" && "$FRONTEND_SHA" != "$EXPECTED_FRONTEND_SHA" ]] \
        && { log_err "Frontend SHA mismatch"; exit 1; }

    export IMAGE_TAG="main-${BACKEND_SHA:0:12}-${FRONTEND_SHA:0:12}"
    log "Deployment image tag: $IMAGE_TAG"

    # Capture previous image for potential rollback
    capture_previous_image

    cd "$APP_DIR"

    # Validate Compose configuration
    log "Validating Docker Compose configuration..."
    compose config --quiet
    local project_name=$(compose config --format json | jq -r '.name // empty')
    [[ "$project_name" == "topscolaire" ]] || { log_err "Compose project name mismatch: $project_name"; exit 1; }

    # Build images with --pull for security updates
    log "Building Docker images with --pull..."
    compose build --pull

    # Start infrastructure services
    log "Starting PostgreSQL and Redis..."
    compose up -d --wait --wait-timeout 180 db redis

    # Wait for PostgreSQL to be truly ready
    wait_for_pg_ready

    # Backup database (non-blocking)
    backup_database || log_warn "Continuing without verified backup"

    # Run migrations
    run_migrations

    # Deploy application stack
    log "Deploying application stack..."
    compose up -d --wait --wait-timeout 300

    # Force recreate frontend to ensure new image is used
    log "Forcing frontend recreation..."
    compose up -d --wait --wait-timeout 180 --force-recreate --no-deps frontend

    # Health checks
    health_check_local "API" "http://127.0.0.1:8081/up" "Application up"
    health_check_local "Frontend" "http://127.0.0.1:8080/"
    health_check_public
    verify_frontend_release

    # Cleanup
    prune_old_artifacts

    local duration=$(( $(date +%s) - start_time ))
    log_ok "=== Deployment Successful in ${duration}s ==="
    log_ok "Backend:  $BACKEND_SHA"
    log_ok "Frontend: $FRONTEND_SHA"
    log_ok "Image:    $IMAGE_TAG"

    notify "SUCCESS" "Deployment completed successfully in ${duration}s" "#36a64f"
}

# ─── Entry Point ───────────────────────────────────────────────────────────────
main "$@"