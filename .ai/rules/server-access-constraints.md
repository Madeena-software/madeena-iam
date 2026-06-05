# Server Access Constraints

> **CRITICAL**: Read this file before proposing any deployment, server configuration, or infrastructure changes.

---

## 🚫 Direct Server Access is PROHIBITED

The production server **cannot be accessed directly via SSH** due to:
- **CGNAT (Carrier-Grade NAT)** — The server sits behind an ISP-level NAT, making inbound SSH connections impossible without tunneling.
- **Firewall restrictions** — Only HTTP/HTTPS traffic (ports 80/443) is allowed inbound.
- **Network topology** — The server is hosted behind a consumer-grade network without a static public IP or port forwarding capabilities.

### Rules for AI Agents

1. **NEVER request SSH credentials** — You will not be provided with SSH keys, passwords, or tunnel configurations.
2. **NEVER suggest direct SSH commands** — `ssh user@server`, `scp`, `rsync` over SSH, or similar are all prohibited.
3. **NEVER propose manual server login** — All server-side operations must be automated.

---

## ✅ Allowed Deployment Methods

All deployment and server configuration changes **MUST** be performed via:

### 1. GitHub Actions Workflows (Primary)
- Deployment is handled by `.github/workflows/deploy-swarm.yml`.
- Server setup by `server-setup-db.yml` and `server-setup-deploy.yml`.
- Admin operations by `server-admin-reset.yml`.
- All workflows connect to the server using GitHub Secrets-configured credentials and tunneling.

### 2. Docker Stack / Swarm Commands (via CI/CD only)
- Production runs on Docker Swarm (`docker-compose.prod.yml`).
- `docker stack deploy` commands are issued exclusively through GitHub Actions.
- Never run Docker commands directly on the production server.

### 3. Configuration Files (Committed to Git)
- Nginx configs → `docker/nginx.conf`, `nginx/` directory
- PHP configs → `docker/php.ini`
- Supervisor → `docker/supervisord.conf`
- Docker Compose → `docker-compose.prod.yml`
- Environment template → `.env.example` (secrets injected by CI/CD)

---

## How to Make Server-Side Changes

| Change Type                  | Method                                                    |
|------------------------------|-----------------------------------------------------------|
| Deploy new code              | Push to `main` branch → `deploy-swarm.yml` triggers       |
| Change Nginx config          | Edit `docker/nginx.conf` or `nginx/` → commit → redeploy  |
| Change PHP settings          | Edit `docker/php.ini` → commit → redeploy                 |
| Add environment variables    | Add to `.env.example`, set in GitHub Secrets → redeploy    |
| Database migrations          | Committed migrations auto-run during container startup     |
| Run one-off Artisan commands | Add to `docker/entrypoint.sh` or create a GitHub workflow  |
| Server admin/reset           | Use `server-admin-reset.yml` workflow                      |
| Database setup               | Use `server-setup-db.yml` workflow                         |

---

## Secrets Management

- All production secrets (DB passwords, WebDAV credentials, app keys) are stored as **GitHub Secrets**.
- They are injected into the CI/CD pipeline at deploy time.
- See `GITHUB-SECRETS.md` for the complete list of required secrets.
- **NEVER commit secrets to the repository** — not in `.env`, not in code, not in comments.

---

## Emergency Access

In the rare case that direct server access is required (e.g., catastrophic failure):
- The human operator will use a local tunnel (e.g., Tailscale, Cloudflare Tunnel, or physical access) to connect.
- The AI agent should prepare scripts/commands to be run by the human, but **must not require interactive SSH sessions**.
- Document any emergency procedures as shell scripts in the `scripts/` directory.
