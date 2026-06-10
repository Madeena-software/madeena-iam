#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════════
# Madeena Standard — Boilerplate Validation Utility
# ═══════════════════════════════════════════════════════════════════════════════
# Validates all boilerplate files in the repository before deployment/commit.
# Runs as many checks as possible without requiring actual Docker services.
#
# Usage:
#   ./.github/templates/validate-boilerplate.sh [--strict]
#
#   --strict    Fail on warnings (default: only fail on errors)
#
# Returns exit code 0 on success, 1 on failure.
# ═══════════════════════════════════════════════════════════════════════════════
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

# ─── COLOUR HELPERS ───────────────────────────────────────────────────────────
BOLD="\033[1m"
GREEN="\033[0;32m"
YELLOW="\033[1;33m"
RED="\033[0;31m"
CYAN="\033[0;36m"
RESET="\033[0m"

PASS_COUNT=0
FAIL_COUNT=0
WARN_COUNT=0
STRICT=false

for arg in "$@"; do
    case "$arg" in
        --strict) STRICT=true ;;
    esac
done

_pass() { PASS_COUNT=$((PASS_COUNT + 1)); echo -e "  ${GREEN}✅ PASS${RESET}: $*"; }
_fail() { FAIL_COUNT=$((FAIL_COUNT + 1)); echo -e "  ${RED}❌ FAIL${RESET}: $*"; }
_warn() { WARN_COUNT=$((WARN_COUNT + 1)); echo -e "  ${YELLOW}⚠  WARN${RESET}: $*"; }
_info() { echo -e "  ${CYAN}ℹ${RESET}  $*"; }

section() {
    echo ""
    echo -e "${BOLD}══════════════════════════════════════════════════════${RESET}"
    echo -e "${BOLD}  $*${RESET}"
    echo -e "${BOLD}══════════════════════════════════════════════════════${RESET}"
}

# ═══════════════════════════════════════════════════════════════════════════════
# CHECK 1: File existence
# ═══════════════════════════════════════════════════════════════════════════════
section "1/6 · Required files exist"

REQUIRED_FILES=(
    "Dockerfile"
    ".dockerignore"
    "docker-compose.prod.yml"
    "docker/entrypoint.sh"
    ".github/workflows/deploy-swarm.yml"
)

for f in "${REQUIRED_FILES[@]}"; do
    if [ -f "$PROJECT_ROOT/$f" ]; then
        _pass "$f"
    else
        _fail "$f — missing!"
    fi
done

OPTIONAL_FILES=(
    "docker-compose.local.yml"
    "docker-compose.simulation.yml"
    "deploy-local.sh"
    "scripts/simulate-prod.sh"
    ".env.local"
    ".github/workflows/server-setup-db.yml"
    ".github/workflows/download-backup.yml"
)

for f in "${OPTIONAL_FILES[@]}"; do
    if [ -f "$PROJECT_ROOT/$f" ]; then
        _pass "$f (optional)"
    else
        _warn "$f — not found (optional)"
    fi
done

# ═══════════════════════════════════════════════════════════════════════════════
# CHECK 2: YAML syntax validation
# ═══════════════════════════════════════════════════════════════════════════════
section "2/6 · YAML syntax validation"

if command -v docker &>/dev/null && docker info &>/dev/null 2>&1; then
    for yml_file in docker-compose.prod.yml docker-compose.local.yml docker-compose.simulation.yml; do
        FULL_PATH="$PROJECT_ROOT/$yml_file"
        if [ -f "$FULL_PATH" ]; then
            # Create a minimal .env so compose can interpolate variables
            TEMP_ENV=$(mktemp)
            {
                echo "DOCKERHUB_USERNAME=test"
                echo "IMAGE_TAG=test"
                echo "DB_ROOT_PASSWORD=test"
                echo "DB_DATABASE=test"
                echo "DB_USERNAME=test"
                echo "DB_PASSWORD=test"
                echo "APP_STORAGE_HOST_PATH=/tmp/test-app-storage"
                echo "APP_LOGS_HOST_PATH=/tmp/test-logs"
                echo "MYSQL_DATA_HOST_PATH=/tmp/test-mysql"
                echo "AWS_ACCESS_KEY_ID=test"
                echo "AWS_SECRET_ACCESS_KEY=test"
                echo "AWS_DEFAULT_REGION=us-east-1"
                echo "AWS_BUCKET=test"
                echo "AWS_USE_PATH_STYLE_ENDPOINT=true"
            } > "$TEMP_ENV"

            if docker compose --env-file "$TEMP_ENV" -f "$FULL_PATH" config &>/dev/null; then
                _pass "$yml_file — valid YAML and variable interpolation"
            else
                _fail "$yml_file — invalid syntax or missing variables"
                docker compose --env-file "$TEMP_ENV" -f "$FULL_PATH" config 2>&1 | head -5 | sed 's/^/    /'
            fi
            rm -f "$TEMP_ENV"
        fi
    done
else
    _warn "Docker not available — skipping YAML validation"
fi

# ═══════════════════════════════════════════════════════════════════════════════
# CHECK 3: Dockerfile validation
# ═══════════════════════════════════════════════════════════════════════════════
section "3/6 · Dockerfile validation"

DOCKERFILE="$PROJECT_ROOT/Dockerfile"
if [ -f "$DOCKERFILE" ]; then
    # Check for multi-stage build
    STAGE_COUNT=$(grep -cE "^FROM " "$DOCKERFILE" || true)
    if [ "$STAGE_COUNT" -ge 2 ]; then
        _pass "Multi-stage build detected ($STAGE_COUNT stages)"
    else
        _warn "Single-stage Dockerfile — consider multi-stage for build performance"
    fi

    # Check APP_VERSION placement (should be AFTER expensive layers)
    ARG_LINE=$(grep -n "^ARG APP_VERSION" "$DOCKERFILE" | head -1 | cut -d: -f1 || echo "0")
    EXT_LINE=$(grep -n "docker-php-ext-install" "$DOCKERFILE" | tail -1 | cut -d: -f1 || echo "0")

    if [ "$ARG_LINE" -gt 0 ] && [ "$EXT_LINE" -gt 0 ]; then
        if [ "$ARG_LINE" -gt "$EXT_LINE" ]; then
            _pass "ARG APP_VERSION is below extension install (good cache behavior)"
        else
            _fail "ARG APP_VERSION (line $ARG_LINE) is ABOVE extension install (line $EXT_LINE) — cache-busting!"
        fi
    fi

    # Check for redundant extensions
    if grep -q "docker-php-ext-install.*dom" "$DOCKERFILE"; then
        _warn "Dockerfile installs 'dom' — this is already in php:8.x-fpm base image"
    fi
    if grep -q "docker-php-ext-install.*xml" "$DOCKERFILE"; then
        _warn "Dockerfile installs 'xml' — this is already in php:8.x-fpm base image"
    fi

    # Check for HEALTHCHECK
    if grep -q "^HEALTHCHECK" "$DOCKERFILE"; then
        _pass "HEALTHCHECK directive present"
    else
        _warn "No HEALTHCHECK in Dockerfile"
    fi

    # Check for ENTRYPOINT
    if grep -q "^ENTRYPOINT" "$DOCKERFILE"; then
        _pass "ENTRYPOINT directive present"
    else
        _warn "No ENTRYPOINT in Dockerfile"
    fi
fi

# ═══════════════════════════════════════════════════════════════════════════════
# CHECK 4: Shell script validation
# ═══════════════════════════════════════════════════════════════════════════════
section "4/6 · Shell script validation"

SHELL_SCRIPTS=(
    "docker/entrypoint.sh"
    "deploy-local.sh"
    "scripts/simulate-prod.sh"
)

for script in "${SHELL_SCRIPTS[@]}"; do
    FULL_PATH="$PROJECT_ROOT/$script"
    if [ -f "$FULL_PATH" ]; then
        # Check shebang
        if head -1 "$FULL_PATH" | grep -q "^#!"; then
            _pass "$script — has shebang"
        else
            _fail "$script — missing shebang (#!/bin/bash)"
        fi

        # Check executable
        if [ -x "$FULL_PATH" ]; then
            _pass "$script — executable"
        else
            _warn "$script — not executable (chmod +x needed)"
        fi

        # Shellcheck if available
        if command -v shellcheck &>/dev/null; then
            ERRORS=$(shellcheck -S error "$FULL_PATH" 2>&1 | wc -l || true)
            if [ "$ERRORS" -eq 0 ]; then
                _pass "$script — shellcheck passed"
            else
                _warn "$script — shellcheck found $ERRORS issues"
            fi
        fi

        # Check for old-style health checks (mysqladmin ping with inline password)
        if grep -q "mysqladmin ping.*-p\\\$" "$FULL_PATH" || grep -q "mysqladmin ping.*-p\"" "$FULL_PATH"; then
            _fail "$script — uses insecure 'mysqladmin ping -p\$PASS' pattern (use MYSQL_PWD env var)"
        fi
    fi
done

# ═══════════════════════════════════════════════════════════════════════════════
# CHECK 5: DB Health check pattern validation
# ═══════════════════════════════════════════════════════════════════════════════
section "5/6 · Health check pattern validation"

for yml_file in docker-compose.prod.yml docker-compose.local.yml docker-compose.simulation.yml; do
    FULL_PATH="$PROJECT_ROOT/$yml_file"
    if [ -f "$FULL_PATH" ]; then
        # Check for old dangerous pattern
        if grep -q 'mysqladmin.*ping.*-p\${' "$FULL_PATH"; then
            _fail "$yml_file — uses insecure 'mysqladmin ping -p\${PASSWORD}' pattern"
        elif grep -q 'MYSQL_ROOT_PASSWORD' "$FULL_PATH" && grep -q 'mysqladmin status' "$FULL_PATH"; then
            _pass "$yml_file — uses secure MYSQL_ROOT_PASSWORD env var pattern"
        elif grep -q 'mysqladmin' "$FULL_PATH"; then
            _warn "$yml_file — uses mysqladmin but pattern couldn't be verified"
        fi
    fi
done

# ═══════════════════════════════════════════════════════════════════════════════
# CHECK 6: TODO markers in templates
# ═══════════════════════════════════════════════════════════════════════════════
section "6/9 · TODO markers audit"

TEMPLATE_DIR="$PROJECT_ROOT/.github/templates"
if [ -d "$TEMPLATE_DIR" ]; then
    TODO_COUNT=$(grep -rlc 'TODO:' "$TEMPLATE_DIR" 2>/dev/null | awk -F: '{sum+=$NF}END{print sum+0}')
    PLACEHOLDER_COUNT=$(grep -rc '{{' "$TEMPLATE_DIR" 2>/dev/null | awk -F: '{sum+=$NF}END{print sum+0}')

    _info "Templates contain $TODO_COUNT TODO markers and $PLACEHOLDER_COUNT {{placeholders}}"

    # These should exist in templates — it's a warning if they DON'T
    if [ "$PLACEHOLDER_COUNT" -gt 0 ]; then
        _pass "Templates have {{placeholders}} for customization"
    else
        _warn "Templates have NO {{placeholders}} — may be already customized"
    fi

    # Check that prod files DON'T have TODO markers
    for f in Dockerfile docker-compose.prod.yml docker/entrypoint.sh; do
        if [ -f "$PROJECT_ROOT/$f" ] && grep -q 'TODO:' "$PROJECT_ROOT/$f"; then
            _fail "$f contains TODO markers (should be resolved)"
        fi
    done
fi

# ═══════════════════════════════════════════════════════════════════════════════
# CHECK 7: Enterprise filesystem configuration
# ═══════════════════════════════════════════════════════════════════════════════
section "7/9 · AWS S3 configuration"

if [ -f "$PROJECT_ROOT/.env.example" ]; then
    grep -q 'AWS_ACCESS_KEY_ID=' "$PROJECT_ROOT/.env.example" \
        && _pass ".env.example has AWS_ACCESS_KEY_ID" \
        || _fail ".env.example missing AWS_ACCESS_KEY_ID"
fi

# Check filesystems.php has s3 disk
if [ -f "$PROJECT_ROOT/config/filesystems.php" ]; then
    if grep -q "'s3'" "$PROJECT_ROOT/config/filesystems.php"; then
        _pass "filesystems.php has s3 disk"
    else
        _fail "filesystems.php missing s3 disk — uploads will fail"
    fi
fi

# ═══════════════════════════════════════════════════════════════════════════════
# CHECK 8: Queue command consistency
# ═══════════════════════════════════════════════════════════════════════════════
section "8/9 · Queue command consistency"

for yml_file in docker-compose.prod.yml docker-compose.simulation.yml; do
    FULL_PATH="$PROJECT_ROOT/$yml_file"
    if [ -f "$FULL_PATH" ]; then
        # Check for the queue command format
        if grep -q 'queue:work' "$FULL_PATH"; then
            if grep -qE 'command:.*\[.*"php".*"artisan".*"queue:work"' "$FULL_PATH"; then
                _pass "$yml_file — queue uses standard direct array command"
            elif grep -qE 'fsockopen|until.*php' "$FULL_PATH"; then
                _pass "$yml_file — queue uses shell-based app probe for startup timing"
            else
                _warn "$yml_file — queue command format could not be verified"
            fi
        fi
    fi
done

# ═══════════════════════════════════════════════════════════════════════════════
# CHECK 9: Enterprise WebDAV storage in production
# ═══════════════════════════════════════════════════════════════════════════════
section "9/9 · AWS S3 Storage integration"

PROD_COMPOSE="$PROJECT_ROOT/docker-compose.prod.yml"
if [ -f "$PROD_COMPOSE" ]; then
    if grep -q '/var/www/enterprise_data\|/var/www/enterprise_backups' "$PROD_COMPOSE"; then
        _fail "Production compose still has old enterprise host-path mounts"
    else
        _pass "Production compose has no legacy enterprise host-path mounts"
    fi
fi

# ═══════════════════════════════════════════════════════════════════════════════
# SUMMARY
# ═══════════════════════════════════════════════════════════════════════════════
echo ""
echo "══════════════════════════════════════════════════════════════"
echo "  RESULTS: ✅ $PASS_COUNT passed | ⚠ $WARN_COUNT warnings | ❌ $FAIL_COUNT failed"
echo "══════════════════════════════════════════════════════════════"

if [ "$FAIL_COUNT" -gt 0 ]; then
    echo -e "\n  ${RED}${BOLD}VALIDATION FAILED — fix errors before deploying.${RESET}\n"
    exit 1
elif [ "$WARN_COUNT" -gt 0 ] && [ "$STRICT" = "true" ]; then
    echo -e "\n  ${YELLOW}${BOLD}VALIDATION FAILED (strict mode) — fix warnings.${RESET}\n"
    exit 1
else
    echo -e "\n  ${GREEN}${BOLD}VALIDATION PASSED — all checks green.${RESET}\n"
    exit 0
fi
