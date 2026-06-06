# Madeena Standard CI/CD Boilerplate

Reusable CI/CD templates for Laravel apps deployed with Docker Swarm.

## What To Copy

| Template | Purpose | Copy To |
|----------|---------|---------|
| `standard-deploy-swarm.yml` | GitHub Actions deployment workflow | `.github/workflows/deploy-swarm.yml` |
| `standard-server-setup.yml` | One-time server permissions setup | `.github/workflows/server-setup-deploy.yml` |
| `standard-server-setup-db.yml` | One-time automated DB backup setup | `.github/workflows/server-setup-db.yml` |
| `standard-docker-compose.yml` | Swarm stack definition | `docker-compose.prod.yml` |
| `standard-dockerfile` | Production Docker image | `Dockerfile` |
| `standard-dockerignore` | Docker build context filter | `.dockerignore` |

## Runtime Docker Files

These templates are copied into the `docker/` directory in the target repo:

| Template | Purpose | Copy To |
|----------|---------|---------|
| `standard-entrypoint.sh` | Container bootstrap and runtime orchestration | `docker/entrypoint.sh` |
| `standard-php.ini` | Custom PHP runtime settings | `docker/php.ini` |
| `standard-nginx.conf` | Nginx site configuration | `docker/nginx.conf` |
| `standard-supervisord.conf` | Supervisor process configuration | `docker/supervisord.conf` |

## Edit Once

Replace these values after copying the templates:

| Value | Used In | Meaning |
|------|---------|---------|
| `{{APP_NAME}}` | workflow, compose, server setup | Lowercase app slug for image tags, stack names, and host paths |
| `{{APP_PORT}}` | compose | Host port exposed by Nginx |
| `{{PHP_VERSION}}` | Dockerfile | PHP base image version |
| `{{NODE_VERSION}}` | workflow | Node.js version used for frontend builds |

Use a dedicated deployment root such as `/var/www/my-app`. Do not point `REMOTE_PATH` at a shared root like `/var/www`, `/srv`, `/opt`, or `/var`.

## Secrets To Set

Configure these in GitHub Secrets before running the workflows:

| Secret | Required | Notes |
|--------|----------|-------|
| `APP_KEY` | ✅ | Output of `php artisan key:generate --show` |
| `APP_DOMAIN` | ✅ | Public domain, for example `app.example.com` |
| `REMOTE_PATH` | ✅ | Dedicated deploy directory on the server |
| `SSH_USER` | ✅ | User running the self-hosted runner |
| `DB_DATABASE` | ✅ | MySQL database name |
| `DB_USERNAME` | ✅ | MySQL app user |
| `DB_PASSWORD` | ✅ | MySQL app password |
| `DB_ROOT_PASSWORD` | ✅ | MySQL root password |
| `MINIO_ACCESS_KEY_ID` | ✅ | MinIO access key for AWS S3 compatibility |
| `MINIO_SECRET_ACCESS_KEY` | ✅ | MinIO secret key |
| `MINIO_BUCKET` | ✅ | MinIO bucket name |
| `MINIO_ENDPOINT` | ✅ | MinIO endpoint URL |
| `SUDO_PASSWORD` | optional | Needed only until `server-setup-deploy.yml` has run |
| `MAIL_USERNAME` | optional | SMTP username |
| `MAIL_PASSWORD` | optional | SMTP password |
| `SUPER_ADMIN_EMAIL` | optional | Bootstrap admin email |
| `SUPER_ADMIN_PASSWORD` | optional | Bootstrap admin password |

## Setup Flow

1. Copy the templates into the target repository.
2. Replace the placeholders listed above.
3. Set the GitHub Secrets listed above.
4. Run `server-setup-deploy.yml` once on the target server.
5. Run `server-setup-db.yml` once to install automated DB backups.
6. Run `deploy-swarm.yml` for each production deployment.

## What Usually Changes Per Repo

| File | Typical Edits |
|------|---------------|
| `docker-compose.prod.yml` | app slug, host port, storage paths, resource limits, extra services |
| `Dockerfile` | PHP version, extensions, config paths, runtime image labels |
| `.github/workflows/deploy-swarm.yml` | secret names, release policy, deployment checks |
| `.github/workflows/server-setup-deploy.yml` | deploy root, directory ownership rules |

## Architecture

```
Internet -> Host Nginx (443/80) -> Docker Swarm Ingress -> app_nginx:{{APP_PORT}} -> simama_app:9000
```

Each application is a self-contained Swarm stack with:
- `app` - PHP-FPM (Laravel)
- `db` - MySQL 8.4
- `nginx` - Static file serving plus FastCGI proxy

Zero-downtime relies on Swarm `update_config.order: start-first`: the new container must become healthy before the old one is removed.
