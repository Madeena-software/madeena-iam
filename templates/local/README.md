# Madeena Standard - Hybrid Local Development

Reusable templates for local development with the hybrid architecture:
PHP, Composer, and NPM run natively on WSL, while MySQL runs in Docker.

## What To Copy

| Template | Purpose | Copy To |
|----------|---------|---------|
| `standard-deploy-local.sh` | Local setup and orchestration script | `deploy-local.sh` |
| `standard-env-local` | Environment variable template | `.env.local` |
| `standard-docker-compose-local.yml` | Infrastructure-only Docker Compose | `docker-compose.local.yml` |

## Edit Once

Replace these placeholders after copying the templates:

| Placeholder | Used In | Meaning |
|-------------|---------|---------|
| `{{APP_NAME}}` | script, compose, env | Lowercase app slug used in container names and DB names |
| `{{REQUIRED_PHP}}` | script | PHP version you expect on the WSL host |
| `{{DB_PORT}}` | compose, env, script | Local MySQL port bound on `127.0.0.1` |

## Setup Flow

1. Copy the templates into the target repository root.
2. Replace the placeholders listed above.
3. Make `deploy-local.sh` executable.
4. Run `deploy-local.sh` once to bootstrap the local environment.

Example:

```bash
cp templates/local/standard-deploy-local.sh deploy-local.sh
cp templates/local/standard-env-local .env.local
cp templates/local/standard-docker-compose-local.yml docker-compose.local.yml
chmod +x deploy-local.sh
./deploy-local.sh
```

## Common Edits Per Repo

| File | Typical Edits |
|------|---------------|
| `deploy-local.sh` | PHP version, database port, bootstrap checks |
| `.env.local` | app slug, mail defaults, local URLs, seed defaults |
| `docker-compose.local.yml` | database port, volume name, container name |

## Prerequisites

| Tool | Version | Install |
|------|---------|---------|
| PHP | `{{REQUIRED_PHP}}` | Example: `sudo apt install php8.4 php8.4-{mysql,mbstring,xml,gd,zip,intl,bcmath,curl,redis,pcntl}` |
| Composer | 2.x | [getcomposer.org](https://getcomposer.org/download/) |
| Node.js | 18+ | `nvm install 18` or `sudo apt install nodejs` |
| NPM | 9+ | Included with Node.js |
| Docker Desktop | Latest | [docker.com](https://www.docker.com/products/docker-desktop/) |

> **WSL users:** Enable Docker Desktop WSL integration:
> `Docker Desktop -> Settings -> Resources -> WSL Integration -> Enable for your distro`

## Architecture

```
WSL host (PHP, Composer, NPM)
	-> 127.0.0.1:{{DB_PORT}}
	-> Docker MySQL 8.4
```

Only the database runs in Docker. The application layer stays on the WSL host for faster iteration.

## Multi-App Notes

If you run more than one local app, give each one a unique DB port and volume name. Update `{{DB_PORT}}` in `docker-compose.local.yml` and `DB_PORT` in `.env.local` to match.
