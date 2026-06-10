# GitHub Secrets Configuration — Madeena IAM

The following secrets must be configured in your GitHub Repository Settings (`Settings` -> `Secrets and variables` -> `Actions`) before running the server setup or deployment workflows.

## Required Secrets

| Secret | Description / How to Get |
|---|---|
| `APP_KEY` | The Laravel application encryption key. Generate it using `php artisan key:generate --show`. |
| `APP_DOMAIN` | The production domain name for the application (e.g., `sso.mhcsgo.cloud`). |
| `REMOTE_PATH` | The dedicated deployment directory on the server host (e.g., `/var/www/madeena-iam`). |
| `SSH_USER` | The system username on the target server running the self-hosted runner. |
| `DB_HOST` | Must be configured to `db` to resolve the isolated Swarm database service container. |
| `DB_DATABASE` | The name of the MySQL production database (e.g., `madeena_iam`). |
| `DB_USERNAME` | The MySQL username for application connections (e.g., `madeena_iam`). |
| `DB_PASSWORD` | The password for the application's MySQL user account. |
| `DB_ROOT_PASSWORD` | The root password for the MySQL database container. |
| `MINIO_ACCESS_KEY_ID` | Access Key ID for MinIO S3-compatible cloud storage. |
| `MINIO_SECRET_ACCESS_KEY` | Secret Access Key for MinIO S3-compatible cloud storage. |
| `MINIO_BUCKET` | The name of the S3 bucket used for backups and public file uploads. |
| `MINIO_ENDPOINT` | The absolute URL of the MinIO storage server endpoint (e.g., `https://s3.mhcsgo.cloud`). |

## Optional Secrets

| Secret | Description / Purpose |
|---|---|
| `SUDO_PASSWORD` | The system password for `SSH_USER` on the host. **Required only once** during the execution of `server-setup-deploy.yml` to set up passwordless sudo. |
| `MAIL_USERNAME` | The SMTP server username for outgoing emails. |
| `MAIL_PASSWORD` | The SMTP server password for outgoing emails. |
| `SUPER_ADMIN_EMAIL` | Optional email address to bootstrap a default super administrator account in production. |
| `SUPER_ADMIN_PASSWORD` | Optional password for the bootstrapped super administrator account. |
