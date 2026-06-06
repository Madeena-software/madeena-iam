#!/bin/bash
# =============================================================================
# {{APP_NAME_PROPER}} — Hybrid Local Development Setup
# =============================================================================
# Orchestrates the local development environment using a hybrid architecture:
#   • Application Layer: PHP, Composer, NPM run natively on the WSL host
#   • Infrastructure Layer: MySQL {{REQUIRED_PHP}} runs inside Docker (port-forwarded)
#
# USAGE:
#   ./deploy-local.sh              # Standard setup (non-destructive)
#   ./deploy-local.sh --fresh      # Reset database + reseed from scratch
#   ./deploy-local.sh --no-start   # Setup only, don't auto-start dev server
#   ./deploy-local.sh --help       # Show usage
#
# PREREQUISITES:
#   PHP {{REQUIRED_PHP}}, Composer, Node.js 18+, NPM, Docker Desktop (WSL integration on)
# =============================================================================
set -euo pipefail

# ─── CONFIGURATION ────────────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_NAME="{{APP_NAME}}"
COMPOSE_FILE="${SCRIPT_DIR}/docker-compose.local.yml"
ENV_TEMPLATE="${SCRIPT_DIR}/.env.local"
ENV_FILE="${SCRIPT_DIR}/.env"
REQUIRED_PHP="{{REQUIRED_PHP}}"

# Docker MySQL settings (must match docker-compose.local.yml)
DB_CONTAINER="${APP_NAME}-mysql-local"
DB_ROOT_PASS="secret"
DB_PORT={{DB_PORT}}

# ─── PARSE ARGUMENTS ─────────────────────────────────────────────────────────
FRESH=false
NO_START=false

show_help() {
    cat <<'HELPTEXT'
{{APP_NAME_PROPER}} — Hybrid Local Development Setup

Usage:
  ./deploy-local.sh              Standard setup (non-destructive migrations)
  ./deploy-local.sh --fresh      Reset database and reseed from scratch
  ./deploy-local.sh --no-start   Setup only — don't auto-start dev server
  ./deploy-local.sh --help       Show this help

Architecture:
  PHP/Composer/NPM run natively on WSL for maximum performance.
  MySQL {{REQUIRED_PHP}} runs in Docker for isolation and easy teardown.
  Connection: PHP → 127.0.0.1:{{DB_PORT}} → Docker MySQL (port-forwarded)

Prerequisites:
  • PHP {{REQUIRED_PHP}} with extensions: pdo_mysql mbstring xml gd zip intl bcmath pcntl
  • Composer 2.x
  • Node.js 18+ and NPM
  • Docker Desktop with WSL integration enabled
HELPTEXT
}

for arg in "$@"; do
    case "$arg" in
        --fresh)    FRESH=true ;;
        --no-start) NO_START=true ;;
        -h|--help)  show_help; exit 0 ;;
        *)          echo "Unknown option: $arg"; show_help; exit 1 ;;
    esac
done

# ─── COLOUR HELPERS ───────────────────────────────────────────────────────────
BOLD="\033[1m"
GREEN="\033[0;32m"
YELLOW="\033[1;33m"
RED="\033[0;31m"
CYAN="\033[0;36m"
RESET="\033[0m"

step()  { echo -e "\n${BOLD}${GREEN}══════════════════════════════════════════════${RESET}"; \
          echo -e "${BOLD}${GREEN}  $*${RESET}"; \
          echo -e "${BOLD}${GREEN}══════════════════════════════════════════════${RESET}"; }
info()  { echo -e "  ${GREEN}✔${RESET}  $*"; }
warn()  { echo -e "  ${YELLOW}⚠${RESET}  $*"; }
die()   { echo -e "\n${RED}${BOLD}✘  ERROR: ${*}${RESET}\n" >&2; exit 1; }

require_cmd() {
    command -v "$1" &>/dev/null || die "Required command not found: '$1'. Please install it."
}

# Add or replace a KEY=VALUE line in an .env file.
# Uses the same proven pattern as the production deploy.sh.
set_env() {
    local key="$1" value="$2" file="${3:-${ENV_FILE}}"
    if grep -q "^${key}=" "${file}" 2>/dev/null; then
        sed -i "s|^${key}=.*|${key}=${value}|" "${file}"
    else
        echo "${key}=${value}" >> "${file}"
    fi
}

# ─── HEADER ───────────────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}🚀  {{APP_NAME_PROPER}} — Hybrid Local Development Setup${RESET}"
echo "────────────────────────────────────────────────"
if [[ "${FRESH}" == "true" ]]; then
    info "Mode: ${YELLOW}FRESH${RESET} (database will be reset and reseeded)"
else
    info "Mode: STANDARD (non-destructive migrations)"
fi

# ─── STEP 1: PREFLIGHT CHECKS ────────────────────────────────────────────────
step "1/8 · Preflight checks"

require_cmd php
require_cmd composer
require_cmd node
require_cmd npm

# Check PHP version
PHP_CURRENT=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
if [[ "${PHP_CURRENT}" != "${REQUIRED_PHP}" ]]; then
    warn "PHP ${PHP_CURRENT} detected. Production uses PHP ${REQUIRED_PHP}."
    warn "For exact version parity, install PHP ${REQUIRED_PHP}:"
    warn "  sudo add-apt-repository ppa:ondrej/php"
    warn "  sudo apt install php${REQUIRED_PHP} php${REQUIRED_PHP}-{mysql,mbstring,xml,gd,zip,intl,bcmath,curl,redis,pcntl}"
    echo ""
fi

# Check required PHP extensions
REQUIRED_EXTENSIONS=(pdo_mysql mbstring xml gd zip intl bcmath pcntl)
MISSING=()
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if ! php -m 2>/dev/null | grep -qi "^${ext}$"; then
        MISSING+=("${ext}")
    fi
done
if [[ ${#MISSING[@]} -gt 0 ]]; then
    die "Missing PHP extensions: ${MISSING[*]}
  Install with: sudo apt install php${PHP_CURRENT}-{$(IFS=,; echo "${MISSING[*]}")}"
fi
info "PHP ${PHP_CURRENT} — all required extensions present"

# Check Node.js version
NODE_MAJOR=$(node -v 2>/dev/null | sed 's/v\([0-9]*\).*/\1/')
if [[ "${NODE_MAJOR}" -lt 18 ]]; then
    die "Node.js 18+ required. Found: $(node -v)"
fi
info "Node.js $(node -v) / NPM $(npm -v)"

# Check Docker availability
if ! command -v docker &>/dev/null; then
    die "Docker not found.
  If Docker Desktop is installed on Windows, enable WSL integration:
  Docker Desktop → Settings → Resources → WSL Integration → Enable for your distro"
fi
if ! docker info &>/dev/null 2>&1; then
    die "Docker daemon is not running.
  Start Docker Desktop on Windows, then retry."
fi
info "Docker $(docker --version | grep -oP '\d+\.\d+\.\d+')"

info "All preflight checks passed."

# ─── STEP 2: DOCKER INFRASTRUCTURE ──────────────────────────────────────────
step "2/8 · Docker infrastructure (MySQL {{REQUIRED_PHP}})"

cd "${SCRIPT_DIR}"

# Pull image if not cached
if ! docker image inspect mysql:{{REQUIRED_PHP}} &>/dev/null; then
    info "Pulling mysql:{{REQUIRED_PHP}} image (first time only)…"
    docker pull mysql:{{REQUIRED_PHP}}
fi

docker compose -f "${COMPOSE_FILE}" up -d
info "MySQL container started."

# ─── STEP 3: WAIT FOR MYSQL ─────────────────────────────────────────────────
step "3/8 · Waiting for MySQL readiness"

RETRIES=30
until docker exec -e MYSQL_PWD="${DB_ROOT_PASS}" "${DB_CONTAINER}" mysqladmin status -u root --silent 2>/dev/null | grep -q Uptime; do
    sleep 2
    RETRIES=$((RETRIES - 1))
    if [[ ${RETRIES} -le 0 ]]; then
        die "MySQL did not become healthy within 60 seconds.
  Check: docker logs ${DB_CONTAINER}"
    fi
    echo -n "."
done
echo ""
info "MySQL is ready — 127.0.0.1:${DB_PORT}"

# ─── STEP 4: ENVIRONMENT ────────────────────────────────────────────────────
step "4/8 · Environment configuration"

if [[ ! -f "${ENV_FILE}" ]] || [[ "${FRESH}" == "true" ]]; then
    cp "${ENV_TEMPLATE}" "${ENV_FILE}"
    info "Generated .env from .env.local"
else
    info "Existing .env preserved (use --fresh to regenerate)"
fi

# Always ensure DATA_DRIVE_PATH is absolute (template uses relative for portability)
set_env "DATA_DRIVE_PATH" "${SCRIPT_DIR}/storage/enterprise_data_local"

# ─── STEP 5: PHP DEPENDENCIES ───────────────────────────────────────────────
step "5/8 · PHP dependencies (Composer)"

composer install --no-interaction --prefer-dist --optimize-autoloader
info "Composer dependencies installed (with dev)."

# ─── STEP 6: FRONTEND ASSETS ────────────────────────────────────────────────
step "6/8 · Frontend assets (NPM + Vite)"

npm ci 2>/dev/null || npm install
npm run build
info "Frontend assets compiled."

# ─── STEP 7: APPLICATION SETUP ───────────────────────────────────────────────
step "7/8 · Application setup"

# Generate app key if missing
if ! grep -q "^APP_KEY=base64:" "${ENV_FILE}" 2>/dev/null; then
    php artisan key:generate --force
    info "Application key generated."
else
    info "Application key already exists."
fi

# Run migrations
if [[ "${FRESH}" == "true" ]]; then
    warn "Running migrate:fresh --seed (this DROPS all tables)…"
    php artisan migrate:fresh --seed --force
    info "Database reset and seeded."
else
    php artisan migrate --force
    info "Migrations applied."
fi

# Storage symlink
php artisan storage:link --force 2>/dev/null || true
info "Storage symlink created."

# ─── STEP 8: PERMISSIONS & DIRECTORIES ──────────────────────────────────────
step "8/8 · Permissions"

# Create enterprise data directory (gitignored)
mkdir -p "${SCRIPT_DIR}/storage/enterprise_data_local"

# Set proper permissions (775, NOT 777)
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
info "Permissions set: storage & bootstrap/cache → 775"

# ─── SUMMARY ─────────────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}${GREEN}══════════════════════════════════════════════════════${RESET}"
echo -e "${BOLD}${GREEN}  ✅  {{APP_NAME_PROPER}} local environment ready!${RESET}"
echo -e "${BOLD}${GREEN}══════════════════════════════════════════════════════${RESET}"
echo ""
echo -e "  ${BOLD}App URL:${RESET}       http://localhost:8000"
echo -e "  ${BOLD}Admin:${RESET}         http://localhost:8000/admin"
echo -e "  ${BOLD}MySQL:${RESET}         127.0.0.1:${DB_PORT} (container: ${DB_CONTAINER})"
echo -e "  ${BOLD}PHP:${RESET}           ${PHP_CURRENT} (native WSL)"
echo -e "  ${BOLD}Node:${RESET}          $(node -v) (native WSL)"
echo ""
echo -e "  ${BOLD}Commands:${RESET}"
echo -e "    Start dev server:    ${CYAN}composer dev${RESET}"
echo -e "    Stop MySQL:          ${CYAN}docker compose -f docker-compose.local.yml down${RESET}"
echo -e "    Reset database:      ${CYAN}./deploy-local.sh --fresh${RESET}"
echo ""

if [[ "${NO_START}" == "true" ]]; then
    info "Setup complete. Run 'composer dev' to start the development server."
else
    echo -e "  ${BOLD}${CYAN}Starting development server…${RESET}"
    echo -e "  ${YELLOW}Press Ctrl+C to stop all services.${RESET}"
    echo ""
    exec composer dev
fi
