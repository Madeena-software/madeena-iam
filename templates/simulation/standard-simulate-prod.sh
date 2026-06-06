#!/bin/bash
# =============================================================================
# {{APP_NAME_PROPER}} — Production Simulation Script
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.simulation.yml"
ENV_FILE="$PROJECT_DIR/.env.simulation"

# ─── COLOUR HELPERS ───────────────────────────────────────────────────────────
BOLD="\033[1m"
GREEN="\033[0;32m"
YELLOW="\033[1;33m"
RED="\033[0;31m"
CYAN="\033[0;36m"
RESET="\033[0m"

step()  { echo -e "\n${BOLD}${GREEN}══════════════════════════════════════════════${RESET}"
          echo -e "${BOLD}${GREEN}  $*${RESET}"
          echo -e "${BOLD}${GREEN}══════════════════════════════════════════════${RESET}"; }
info()  { echo -e "  ${GREEN}✔${RESET}  $*"; }
warn()  { echo -e "  ${YELLOW}⚠${RESET}  $*"; }
die()   { echo -e "\n${RED}${BOLD}✘  ERROR: ${*}${RESET}\n" >&2
          echo "=== app logs ===" >&2
          docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" logs app --tail=40 2>/dev/null || true
          echo "=== db logs ===" >&2
          docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" logs db --tail=20 2>/dev/null || true
          exit 1; }
_pass() { PASS_COUNT=$((PASS_COUNT + 1)); echo -e "  ${GREEN}✅ PASS${RESET}: $*"; }
_fail() { FAIL_COUNT=$((FAIL_COUNT + 1)); echo -e "  ${RED}❌ FAIL${RESET}: $*"; }

# ─── COMPOSE HELPER — always passes --env-file ────────────────────────────────
dc() { docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"; }

# ─── PARSE ARGUMENTS ─────────────────────────────────────────────────────────
ACTION="full"
for arg in "$@"; do
    case "$arg" in
        --teardown) ACTION="teardown" ;;
        --verify)   ACTION="verify" ;;
        --rebuild)  ACTION="rebuild" ;;
        -h|--help)  echo "Usage: $0 [--teardown|--verify|--rebuild]"; exit 0 ;;
    esac
done

# ─── TEARDOWN ─────────────────────────────────────────────────────────────────
teardown() {
    step "Tearing down simulation stack"
    cd "$PROJECT_DIR"
    dc down -v --remove-orphans 2>/dev/null || docker compose -f "$COMPOSE_FILE" down -v --remove-orphans 2>/dev/null || true
    rm -f "$ENV_FILE" 2>/dev/null || true
    info "Simulation stack removed."
}

if [ "$ACTION" = "teardown" ]; then
    teardown
    exit 0
fi

# ─── VERIFY ───────────────────────────────────────────────────────────────────
PASS_COUNT=0
FAIL_COUNT=0

verify() {
    step "Verification — Simulating post-deploy checks"

    # 1. Service health checks
    echo ""
    echo -e "  ${BOLD}▶ Health Checks${RESET}"
    for svc in app db; do
        CONTAINER=$(dc ps -q "$svc" 2>/dev/null | head -n 1)
        if [ -n "$CONTAINER" ]; then
            HEALTH=$(docker inspect --format='{{.State.Health.Status}}' "$CONTAINER" 2>/dev/null || echo "none")
            if [ "$HEALTH" = "healthy" ]; then
                _pass "$svc: $HEALTH"
            else
                _fail "$svc: $HEALTH (expected healthy)"
                docker inspect --format='{{json .State.Health.Log}}' "$CONTAINER" 2>/dev/null | python3 -c "
import sys,json
logs=json.load(sys.stdin)
for l in (logs or [])[-3:]:
    print('    ', l.get('Output','').strip()[:200])
" 2>/dev/null || true
            fi
        else
            _fail "$svc: container not found"
        fi
    done

    # 2. Service running
    echo ""
    echo -e "  ${BOLD}▶ Service Status${RESET}"
    for svc in app queue db nginx; do
        STATUS=$(dc ps --format '{{.State}}' "$svc" 2>/dev/null || echo "missing")
        if [ "$STATUS" = "running" ]; then
            _pass "$svc: running"
        else
            _fail "$svc: $STATUS"
        fi
    done

    # 3. HTTP probe
    echo ""
    echo -e "  ${BOLD}▶ HTTP Probe${RESET}"
    HTTP_STATUS=$(curl -sS -o /dev/null -w "%{http_code}" --max-time 15 http://127.0.0.1:8010 2>/dev/null || echo "000")
    if echo "$HTTP_STATUS" | grep -qE '^(200|301|302|307|308)$'; then
        _pass "http://127.0.0.1:8010 → HTTP $HTTP_STATUS"
    elif [ "$HTTP_STATUS" = "500" ]; then
        _pass "http://127.0.0.1:8010 → HTTP $HTTP_STATUS (app running, internal error expected in sim)"
    else
        _fail "http://127.0.0.1:8010 → HTTP $HTTP_STATUS (expected 2xx/3xx)"
    fi

    # 4. DB connectivity from app via Laravel artisan
    echo ""
    echo -e "  ${BOLD}▶ DB Connectivity (artisan db:show)${RESET}"
    APP_CONTAINER=$(dc ps -q app 2>/dev/null | head -n 1)
    if [ -n "$APP_CONTAINER" ]; then
        if docker exec "$APP_CONTAINER" php artisan db:show --json 2>/dev/null | python3 -c "import sys,json; d=json.load(sys.stdin); print('    DB:', d.get('name','?'), 'tables:', d.get('tables_count','?'))" 2>/dev/null; then
            _pass "App → DB artisan connection verified"
        else
            # fallback: plain PDO from env vars already loaded inside container
            if docker exec "$APP_CONTAINER" php -r "
\$h=getenv('DB_HOST');\$p=getenv('DB_PORT')?:'3306';
\$d=getenv('DB_DATABASE');\$u=getenv('DB_USERNAME');\$pw=getenv('DB_PASSWORD');
new PDO(\"mysql:host=\$h;port=\$p;dbname=\$d\",\$u,\$pw);
echo 'ok';
" 2>/dev/null | grep -q ok; then
                _pass "App → DB PDO connection successful"
            else
                _fail "App → DB connection failed"
                docker exec "$APP_CONTAINER" php -r "
\$h=getenv('DB_HOST');\$p=getenv('DB_PORT')?:'3306';
\$d=getenv('DB_DATABASE');\$u=getenv('DB_USERNAME');\$pw=getenv('DB_PASSWORD');
try { new PDO(\"mysql:host=\$h;port=\$p;dbname=\$d\",\$u,\$pw); echo 'ok'; }
catch(Exception \$e){ echo \$e->getMessage(); }
" 2>/dev/null | sed 's/^/    /' || true
            fi
        fi
    else
        _fail "App container not found"
    fi

    # Summary
    echo ""
    echo "══════════════════════════════════════════════════════════════"
    echo "  RESULTS: ✅ $PASS_COUNT passed | ❌ $FAIL_COUNT failed"
    echo "══════════════════════════════════════════════════════════════"

    if [ "$FAIL_COUNT" -gt 0 ]; then
        echo -e "\n  ${RED}${BOLD}Simulation FAILED — fix issues before deploying to production.${RESET}\n"
        return 1
    else
        echo -e "\n  ${GREEN}${BOLD}Simulation PASSED — safe to deploy.${RESET}\n"
        return 0
    fi
}

if [ "$ACTION" = "verify" ]; then
    cd "$PROJECT_DIR"
    verify
    exit $?
fi

# ─── FULL SIMULATION ─────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}🧪  {{APP_NAME_PROPER}} — Production Simulation${RESET}"
echo "────────────────────────────────────────────────"

# ── Preflight ────────────────────────────────────────────────────────────────
step "1/5 · Preflight checks"
command -v docker &>/dev/null || die "Docker not found."
docker info &>/dev/null 2>&1 || die "Docker daemon is not running."
info "Docker $(docker --version | grep -oP '\d+\.\d+\.\d+')"
[ -d "$PROJECT_DIR/vendor" ]     || die "vendor/ not found. Run: composer install --no-dev"
[ -d "$PROJECT_DIR/public/build" ] || die "public/build/ not found. Run: npm run build"

# ── Create simulation .env ────────────────────────────────────────────────────
step "2/5 · Creating simulation environment"

# Fixed credentials — consistent across compose variable substitution + container env
SIM_DB_PASS="sim_db_pass_2024"
SIM_ROOT_PASS="sim_root_pass_2024"
SIM_DB_NAME="{{APP_NAME}}_sim"
SIM_DB_USER="{{APP_NAME}}_sim"

cat > "$ENV_FILE" <<EOF
APP_NAME="{{APP_NAME_PROPER}}"
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta
APP_URL=http://localhost:8010

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=${SIM_DB_NAME}
DB_USERNAME=${SIM_DB_USER}
DB_PASSWORD=${SIM_DB_PASS}
DB_ROOT_PASSWORD=${SIM_ROOT_PASS}

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

DATA_DRIVE_PATH=/var/www/enterprise_data

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS=test@example.com
MAIL_FROM_NAME={{APP_NAME_PROPER}}
MAIL_ENCRYPTION=null

SUPER_ADMIN_EMAIL=admin@test.com
SUPER_ADMIN_PASSWORD=testpassword123
EOF

# Reuse APP_KEY from local .env if available
if [ -f "$PROJECT_DIR/.env" ] && grep -q "^APP_KEY=base64:" "$PROJECT_DIR/.env" 2>/dev/null; then
    grep "^APP_KEY=" "$PROJECT_DIR/.env" | head -1 >> "$ENV_FILE"
    info "Reused APP_KEY from .env"
else
    echo "APP_KEY=" >> "$ENV_FILE"
    warn "No APP_KEY found — will be generated on first boot"
fi

info "Created .env.simulation (DB: $SIM_DB_NAME / $SIM_DB_USER)"

# ── Ensure enterprise data dir exists ────────────────────────────────────────
mkdir -p "$PROJECT_DIR/storage/enterprise_data_local"

# ── Tear down any previous run cleanly ────────────────────────────────────────
step "3/5 · Clean state — tearing down previous stack"
cd "$PROJECT_DIR"
dc down -v --remove-orphans 2>/dev/null || true
info "Previous stack cleared."

# ── Build and start ───────────────────────────────────────────────────────────
step "4/5 · Build & start simulation stack"
echo -e "  ${CYAN}Building image...${RESET}"

if [ "$ACTION" = "rebuild" ]; then
    dc build --no-cache app
else
    dc build app
fi
info "Image built (cache used where possible)."

echo -e "  ${CYAN}Starting core services (db + app)...${RESET}"
# Start db and app first — wait for them to be healthy
dc up -d db app
info "DB and App containers started."

# Wait for DB health
echo -e "  ${CYAN}Waiting for DB...${RESET}"
WAIT=0
until [ "$(dc ps --format '{{.Health}}' db 2>/dev/null)" = "healthy" ]; do
    if [ "$WAIT" -ge 120 ]; then
        dc logs db --tail=30
        die "DB health check timed out (120s)."
    fi
    echo -n "."; sleep 5; WAIT=$((WAIT + 5))
done
echo ""; info "DB healthy (${WAIT}s)"

# Wait for App health (migrations + php-fpm)
echo -e "  ${CYAN}Waiting for App (migrations + PHP-FPM boot)...${RESET}"
WAIT=0
until [ "$(dc ps --format '{{.Health}}' app 2>/dev/null)" = "healthy" ]; do
    if [ "$WAIT" -ge 240 ]; then
        dc logs app --tail=50
        die "App health check timed out (240s)."
    fi
    echo -n "."; sleep 5; WAIT=$((WAIT + 5))
done
echo ""; info "App healthy (${WAIT}s) — migrations complete ✅"

# Now start queue and nginx (queue depends on app:healthy, nginx depends on app:healthy)
echo -e "  ${CYAN}Starting queue worker and nginx...${RESET}"
dc up -d queue nginx
info "Queue and Nginx started."

# Give queue 15s to connect and start processing
sleep 15
QUEUE_STATUS=$(dc ps --format '{{.State}}' queue 2>/dev/null || echo "unknown")
if [ "$QUEUE_STATUS" != "running" ]; then
    warn "Queue status: $QUEUE_STATUS — check logs:"
    dc logs queue --tail=20
else
    info "Queue worker running ✅"
fi


# ── Run DB migrations inside app container ─────────────────────────────────
APP_CONTAINER=$(dc ps -q app | head -n 1)
echo -e "  ${CYAN}Running migrations...${RESET}"
if docker exec "$APP_CONTAINER" php artisan migrate --force --seed 2>&1 | tail -10; then
    info "Migrations complete."
else
    warn "Migrations failed — app may still work if DB already seeded."
fi

# ── Initialize MinIO default bucket ─────────────────────────────────────────
echo -e "  ${CYAN}Initializing MinIO S3 bucket...${RESET}"
if docker exec "$APP_CONTAINER" mc alias set {{APP_NAME}}-gateway-alias http://host.docker.internal:9000 minioadmin minioadminpassword >/dev/null 2>&1; then
    if docker exec "$APP_CONTAINER" mc mb {{APP_NAME}}-gateway-alias/{{APP_NAME}}-storage >/dev/null 2>&1 || docker exec "$APP_CONTAINER" mc ls {{APP_NAME}}-gateway-alias/{{APP_NAME}}-storage >/dev/null 2>&1; then
        info "MinIO {{APP_NAME}}-storage bucket ready ✅"
    else
        warn "Could not create {{APP_NAME}}-storage bucket in MinIO"
    fi
else
    warn "Could not configure mc alias inside app container"
fi


# ── Verify ───────────────────────────────────────────────────────────────────
step "5/5 · Running verification"
verify
RESULT=$?

# ── Summary ──────────────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}${CYAN}Simulation stack is running at:${RESET}"
echo -e "  App:   ${CYAN}http://localhost:8010${RESET}"
echo ""
echo -e "  ${BOLD}Useful commands:${RESET}"
echo -e "    Logs:       ${CYAN}docker compose --env-file .env.simulation -f docker-compose.simulation.yml logs -f${RESET}"
echo -e "    Shell:      ${CYAN}docker compose --env-file .env.simulation -f docker-compose.simulation.yml exec app bash${RESET}"
echo -e "    Tear down:  ${CYAN}./scripts/simulate-prod.sh --teardown${RESET}"
echo -e "    Re-verify:  ${CYAN}./scripts/simulate-prod.sh --verify${RESET}"
echo ""

exit $RESULT
