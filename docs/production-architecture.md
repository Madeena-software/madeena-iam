---
title: Madeena IAM Production Architecture & Operational Invariants
document_id: PROD-ARCH-001
version: 1.1
status: draft-reference
language: en-US
last_updated: 2026-08-21
scope:
  - production runtime topology
  - swarm service specifications
  - host-port binding and rollout invariants
  - network boundaries and storage configuration
  - deployment control path and verification procedures
authority_note: This document describes the observed production architecture and operational invariants for Madeena IAM as reference material pending any designated human approval.
---

# Madeena IAM — Production Architecture & Operational Invariants

## 1. Purpose & Scope

This document specifies the production runtime topology, container orchestration configuration, networking model, and deployment workflows for `madeena-iam`.

Its objectives are:
- Providing an unambiguous reference for production service dependencies and configuration.
- Explaining the architectural rationale behind critical operational invariants (notably the `stop-first` update policy for the host-mode reverse proxy).
- Documenting the automated CI/CD control path and post-deployment verification procedures.

---

## 2. Deployment & Control Path

Production operations for Madeena IAM follow an automated, workflow-mediated pipeline:

```text
┌──────────────────────────────┐
│  Developer / Agent Operator  │
└──────────────┬───────────────┘
               │ Triggers workflow_dispatch via GitHub UI or gh CLI
               ▼
┌──────────────────────────────┐
│  GitHub Actions Orchestrator │
│ (.github/workflows/deploy-   │
│  swarm.yml)                  │
└──────────────┬───────────────┘
               │ Dispatches job to target runner
               ▼
┌──────────────────────────────┐
│ Self-Hosted Production Runner│ (runs-on: self-hosted)
│ 1. Builds madeena-iam:latest │
│ 2. Prepares host directories │
│ 3. Deploys Docker Swarm stack│
│ 4. Executes health checks    │
└──────────────┬───────────────┘
               │ Deploys & manages stack
               ▼
┌──────────────────────────────┐
│  Docker Swarm Manager Engine │
└──────────────────────────────┘
```

### Operational Policy Boundary
- **Workflow-Mediated Control Path**: Production deployments, image builds, and configuration updates are executed via GitHub Actions workflows ([`.github/workflows/deploy-swarm.yml`](../.github/workflows/deploy-swarm.yml)) running on the self-hosted production runner.
- **Image Identity Note**: The deployment workflow currently builds and deploys the mutable tag `madeena-iam:latest`. Transitioning to immutable image digests (SHA-pinned tags) remains a future release-hardening opportunity.
- **Access Boundary**: Direct remote administration via SSH, SCP, SFTP, remote Docker contexts (`DOCKER_HOST`), or interactive production shell sessions is not part of the standard deployment path.

---

## 3. Production Runtime Topology

The following diagram illustrates how inbound traffic routes through the host reverse proxy, containerized ingress, application worker, and storage layers:

```mermaid
flowchart TD
    Client["External HTTPS Client / Web Browser"]
    UpstreamProxy["Upstream Host Reverse Proxy (SSL Termination)"]

    subgraph Host ["Production Host System (Manager Node)"]
        HostPort["Host Port :8012"]

        subgraph Swarm ["Docker Swarm: 'madeena-iam' Stack"]
            subgraph Ingress ["Ingress Service"]
                NginxSvc["madeena-iam_nginx (nginx:alpine)\nTarget: 80 | Published: 8012 (mode: host)"]
            end

            subgraph Overlay ["Stack Overlay Network: 'madeena-iam_madeena-iam_network'"]
                AppSvc["madeena-iam_app (madeena-iam:latest)\nPHP-FPM Port: 9000 (order: start-first)"]
                QueueSvc["madeena-iam_queue (madeena-iam:latest)\nphp artisan queue:work (order: stop-first)"]
                DbSvc["madeena-iam_db (mysql:8.4)\nMySQL Port: 3306 (order: stop-first)"]
            end
        end

        subgraph HostStorage ["Host Bind Mounts"]
            StorageApp["/var/lib/madeena-iam/storage/app/private (OAuth Keys)"]
            StorageLogs["/var/lib/madeena-iam/logs (Laravel Logs)"]
            MysqlData["/var/lib/madeena-iam/mysql (MySQL Database Data)"]
        end
    end

    subgraph ObjectStorage ["MinIO / S3 Object Storage"]
        S3Public["Public Media Assets (Disk: public)"]
        S3Backup["Enterprise Backups (Disk: enterprise_backups)"]
    end

    Client -->|HTTPS :443| UpstreamProxy
    UpstreamProxy -->|HTTP :8012| HostPort
    HostPort --> NginxSvc
    NginxSvc -->|FastCGI :9000| AppSvc
    AppSvc -->|TCP :3306| DbSvc
    QueueSvc -->|TCP :3306| DbSvc
    AppSvc --> StorageApp
    AppSvc --> StorageLogs
    QueueSvc --> StorageLogs
    DbSvc --> MysqlData
    AppSvc -.->|S3 API| S3Public
    AppSvc -.->|S3 API| S3Backup
```

---

## 4. Swarm Services Specification

The `madeena-iam` stack is defined in [`docker-compose.prod.yml`](../docker-compose.prod.yml) and consists of 4 distinct services:

| Service Name | Container Image | Replicas | Placement | Update Order | Rollback Order | Healthcheck Target |
|---|---|---|---|---|---|---|
| `madeena-iam_nginx` | `nginx:alpine` | 1 | `node.role == manager` | `stop-first` | `stop-first` | `wget -q --spider http://127.0.0.1/up` |
| `madeena-iam_app` | `madeena-iam:latest` | 1 | `node.role == manager` | `start-first` | `start-first` | FastCGI socket probe `127.0.0.1:9000` |
| `madeena-iam_queue` | `madeena-iam:latest` | 1 | `node.role == manager` | `stop-first` | `stop-first` | Storage directory writability check |
| `madeena-iam_db` | `mysql:8.4` | 1 | `node.role == manager` | `stop-first` | `stop-first` | `mysqladmin status` |

---

## 5. Critical Invariant: Nginx Host Port & `stop-first` Policy

### The Single-Eligible-Node Host-Port Problem
The production `nginx` service publishes port `8012` using Docker Swarm's host publication mode:
```yaml
ports:
  - target: 80
    published: 8012
    protocol: tcp
    mode: host
```

When `mode: host` is configured:
1. The container bypasses Docker Swarm's routing mesh (ingress network) and binds directly to port `8012` on the host network interface.
2. In the current production environment, the stack is placed exclusively on the single manager node (`node.role == manager`).
3. If `update_config.order` is set to `start-first`:
   - Swarm attempts to schedule the replacement task before terminating the old task.
   - Because port `8012` is already allocated to the running task on that sole node, Swarm cannot place the replacement container on any node.
   - The replacement task remains `Pending` with the error `no suitable node (host-mode port already in use on 1 node)`, preventing deployment convergence.

### The Invariant Solution: `stop-first`
To allow task replacement on a single-node host-mode topology, [`docker-compose.prod.yml`](../docker-compose.prod.yml) enforces:
```yaml
update_config:
  parallelism: 1
  delay: 5s
  order: stop-first
  failure_action: rollback
  monitor: 10s
rollback_config:
  parallelism: 1
  order: stop-first
```

### Operational Trade-off
- **Interruption Characteristic**: When updating the Nginx configuration or container, `stop-first` stops the existing Nginx container before initializing the new container. This introduces a brief (typically sub-second to a few seconds) connection gap during the transition window.
- **Architectural Assessment**: This minor interruption is expected and necessary for a single-node host-mode port binding topology.

---

## 6. Network Architecture

- **Stack Overlay Network**: Inter-service communication occurs across a stack-scoped overlay network.
  - Compose logical network key: `madeena-iam_network` (`driver: overlay`, `attachable: true`).
  - Observed Swarm runtime network name: `madeena-iam_madeena-iam_network`.
- **Internal DNS Resolution**: Nginx dynamically resolves the PHP-FPM service using Docker's internal DNS daemon (`127.0.0.11`) configured with `set $php_upstream madeena-iam_app; fastcgi_pass $php_upstream:9000;`.
- **Database & Queue Isolation**: The MySQL database (`madeena-iam_db`) is isolated within the stack overlay network. It does not publish ports to the host interface.

---

## 7. Storage & Persistent Data Architecture

Madeena IAM implements strict data separation between host-bound persistent data and cloud-based object storage:

1. **Host Bind Mounts**:
   - `storage/app/private`: Holds cryptographically sensitive Passport OAuth keys (`oauth-private.key`, `oauth-public.key`). Permissions are restricted to `600` / `www-data`.
   - `storage/logs`: Host directory holding `laravel.log` for operational troubleshooting.
   - `var/lib/mysql`: Host directory bound to `madeena-iam_db` for MySQL data persistence.

2. **Isolated Ephemeral Framework Views**:
   - `storage/framework/views` and `storage/framework/cache` remain local to the container to prevent cache corruption between concurrent tasks.

3. **Object Storage (S3 / MinIO)**:
   - User profile images, client application logos, and system backups utilize Laravel S3 storage drivers (`public` and `enterprise_backups` disks).

---

## 8. Verification & Diagnostic Procedures

### Automated Post-Deployment Verification (8 Gates)
The production deployment workflow ([`.github/workflows/deploy-swarm.yml`](../.github/workflows/deploy-swarm.yml)) automatically executes 8 sequential verification checks before concluding a deployment:
1. **Service Replicas**: Verifies all 4 services report `1/1` active replicas.
2. **Container Healthchecks**: Confirms `app` and `db` health statuses report `healthy`.
3. **Update & Rollback Policy**: Validates `app` uses `start-first` and `nginx`, `db`, `queue` use `stop-first`.
4. **Resource Limits**: Checks that memory limits and reservations are configured.
5. **Queue Worker**: Verifies `queue:work` command execution and graceful shutdown periods.
6. **Storage Mounts**: Asserts that `storage/app` is mounted, `storage/framework/views` is writable and local, and root `/var/www/html/storage` is not mounted as a single block.
7. **S3 Storage Connectivity**: Invokes `php artisan storage:check` to verify S3 bucket read/write operations.
8. **Media Streaming Route**: Uploads a test probe to the `public` disk and verifies retrieval through `http://127.0.0.1:8012/storage/{probe}`.

### Production Incident Diagnostics (`diagnose-502.yml`)
The read-only diagnostic workflow ([`.github/workflows/diagnose-502.yml`](../.github/workflows/diagnose-502.yml)) allows inspecting:
- Host port `8012` connectivity (`/up` and `/admin` endpoints).
- Swarm manager state, stack tasks, and container exit codes.
- Nginx and PHP-FPM service logs.
- Network routing, overlay subnet allocations, and container IP assignments.
- Internal DNS resolution (`getent hosts app` and `nslookup madeena-iam_app 127.0.0.11`).
- FastCGI TCP connectivity from inside Nginx to PHP-FPM (`nc -z -v -w 3 madeena-iam_app 9000`).

---

## 9. Incident Remediation Context & Lessons Learned

- **Rollout Convergence Defect**: During remediation of the 502 incident, diagnostics confirmed a separate/current Swarm rollout defect: nginx `start-first` replacement cannot converge when a single eligible node already owns the fixed host-mode port 8012 (Swarm reports `no suitable node (host-mode port already in use on 1 node)` and the replacement task remains Pending).
- **Evidence Scope**: Available diagnostic evidence does not establish that this rollout defect was necessarily the sole original cause of every earlier HTTP 502 observation.
- **Remediation**: Setting `update_config.order: stop-first` in [`docker-compose.prod.yml`](../docker-compose.prod.yml) resolved the confirmed rollout-convergence defect.
