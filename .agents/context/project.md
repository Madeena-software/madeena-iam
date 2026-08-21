---
title: Madeena IAM Repository Context Map
document_id: CONTEXT-MAP-001
version: 2.1
status: draft-context
language: en-US
last_updated: 2026-08-21
scope:
  - repository orientation and navigation
  - technology summary and architecture boundaries
  - authoritative artifact routing
  - current production operational invariants
  - delivery baseline and known gaps
authority_note: This document provides supporting orientation context for AI agents and developers. It is not primary product or delivery authority and does not constitute approved policy. Refer to canonical repository specifications and observed repository evidence for primary truth.
---

# Madeena IAM — Project Context Map

## 1. Repository Purpose

`madeena-iam` is the centralized Identity and Access Management (IAM) and Single Sign-On (SSO) service for the Madeena application ecosystem (including `simama`, `madeena-workspace`, `madeena-erp`, and `madeena-it`).

Its core responsibilities are:
- Providing centralized identity credentials, password verification, and user profiles.
- Authenticating users and issuing cryptographically signed OAuth2 tokens via Laravel Passport.
- Enforcing per-client access authorization through client-user approval workflows (`client_user` mapping).
- Managing active web and device sessions with remote termination capabilities.
- Maintaining security audit trails (`authentication_logs`) for all authentication events across client applications.
- Offering administrative control through a Filament dashboard for user approvals, client registration, and access provisioning.

---

## 2. Verified Technology Stack

| Layer | Component | Version / Detail |
|---|---|---|
| Runtime | PHP | `^8.3` (Container runs PHP 8.4+) |
| Framework | Laravel Framework | `13.x` (`laravel/framework: ^13.8`) |
| Auth Server | Laravel Passport | `13.x` (`laravel/passport: ^13.0`, OAuth2 server) |
| Admin Panel | Filament | `v5.x` (`filament/filament: ^5.3.5`) |
| Authorization | Spatie Laravel Permission & Shield | `spatie/laravel-permission: ^7.4`, `filament-shield: ^4.2` |
| Audit Logging | Spatie Laravel Activitylog | `spatie/laravel-activitylog: ^5.0` |
| Frontend Assets | Alpine.js, Tailwind CSS, Vite | Tailwind CSS v4, Vite 8 (`@tailwindcss/vite: ^4.0.0`) |
| Primary Database | MySQL | 8.4 (Dedicated, isolated instance) |
| Asynchronous Queue | Laravel Queue | Database driver (`queue:work` worker service) |
| Object Storage | S3 / MinIO | `league/flysystem-aws-s3-v3: ^3.34` |
| Production Platform | Docker Swarm | Single-node manager placement |

---

## 3. High-Level System Architecture

Madeena IAM operates as a standalone monolithic Laravel application serving three primary interface surfaces:

1. **OAuth2 Authorization & SSO Surface (`/oauth/*`, `/login`, `/register`)**:
   - Implements the OAuth2 Authorization Code grant flow.
   - Handles custom silent session sync (`GET /oauth/authorize?prompt=none`) and forced login (`prompt=login`).
   - Intercepts requests with `CheckClientAccess` middleware to enforce per-application user approval status before token issuance.

2. **Direct API Surface (`/api/v1/*`)**:
   - `POST /api/v1/auth/login`: Direct server-to-server validation (validates client secret, user credentials, client access mapping, and issues Passport Personal Access Tokens).
   - `POST /api/v1/auth/register`: Public registration endpoint forwarding new accounts into a `pending_approval` state.
   - `GET /api/v1/user`: Authenticated endpoint returning user profile and per-client status.
   - `POST /api/v1/auth/logout`: Revokes Passport access/refresh tokens and invalidates central session cookies.
   - `GET /api/v1/sessions` & `DELETE /api/v1/sessions/{id}`: Device session inspection and remote revocation.
   - `PATCH /api/v1/client-user/link`: Links external client app user identifiers to IAM accounts.

3. **Filament Admin Surface (`/admin/*`)**:
   - Accessible strictly by administrators with the `super_admin` role.
   - Provides user approvals, application client management, permission toggles, active session monitoring, and audit log inspection.

---

## 4. Authoritative Source Map

To avoid documentation drift and maintain clear governance, agents and developers must consult the appropriate authority:

| Concern | Authoritative Document | Role / Boundary |
|---|---|---|
| Product Requirements & Behavior | [`docs/madeena_iam_prd.md`](../../docs/madeena_iam_prd.md) | Defines product intent, user personas, functional requirements, and application flows. |
| AI Delivery Contract & Governance | [`.agents/AGENTS.md`](../AGENTS.md) | Defines agent roles (Planner, Executor, Reviewer), task boundaries, and execution rules. |
| Software Delivery Lifecycle | [`.agents/software-workflow.md`](../software-workflow.md) | Defines the formal delivery protocol, quality gates (B0–G10), and traceability requirements. |
| Production Topology & Invariants | [`docs/production-architecture.md`](../../docs/production-architecture.md) | Documents observed Swarm services, network topology, port bindings, and operational constraints. |
| Implementation Reality | Source code, configuration, migrations, tests | Source of truth for current implementation behavior at the inspected Git revision. |

> [!NOTE]
> Context files under `.agents/context/` are refreshable orientation aids. They do not override primary product authority or observed repository implementation evidence.

---

## 5. Critical Production Invariants

The following operational invariants are observed in production:

1. **Nginx Host-Mode Published Port (`8012`)**:
   - The production `nginx` service binds target port 80 to host port `8012` using Swarm host mode (`mode: host`).
   - Placement is constrained to the manager node (`node.role == manager`).

2. **Nginx Update & Rollback Order (`stop-first`)**:
   - The `nginx` service is configured with `update_config.order: stop-first` and `rollback_config.order: stop-first`.
   - **Reason**: In a single-node manager topology with a fixed host-mode port, `start-first` causes the replacement task to remain `Pending` because Swarm cannot schedule a second container binding host port 8012 on the same node (`no suitable node (host-mode port already in use on 1 node)`). `stop-first` allows the old task to stop so the replacement task can bind the port.
   - **Operational Trade-off**: `stop-first` introduces a brief, momentary connection gap during container replacement, which is the expected trade-off for single-node host-mode port binding.

3. **App Service Update Order (`start-first`)**:
   - The `app` (PHP-FPM) service runs within the stack-scoped overlay network without host-port binding, enabling `update_config.order: start-first` for zero-downtime application worker transitions.

4. **Overlay Network Scope & Naming**:
   - Compose logical network key: `madeena-iam_network` (`driver: overlay`, `attachable: true`).
   - Observed Swarm runtime network: `madeena-iam_madeena-iam_network`. Nginx proxies FastCGI requests to `madeena-iam_app:9000`.

5. **Deployment Control Path**:
   - Production deployments are automated via GitHub Actions (`.github/workflows/deploy-swarm.yml`) running on the self-hosted production runner, building and deploying `madeena-iam:latest`.
   - Direct manual operations via SSH, SCP, SFTP, remote Docker contexts, or direct host shells are prohibited by repository delivery policy.

---

## 6. Current Delivery State & Incident History

- **Last Accepted Implementation Baseline**: `be39763b73ca7ebbc59fecc4ab0be0aad78bc4a2` (`fix(swarm): set nginx update and rollback order to stop-first for host-mode port`).
- **Documentation Remediation Lineage**: `94d22541d3d6330e6c7156e49f0bb2e90df0df55` → `8253878f2e48347322faa0e145809d2cf162b86c`.
- **Current Documentation Review State**: Review Required (Awaiting Reviewer evaluation and acceptance).
- **Incident Remediation Context**: During remediation of the 502 incident, diagnostics confirmed a separate/current Swarm rollout defect: nginx `start-first` replacement cannot converge when a single eligible node already owns the fixed host-mode port 8012 (Swarm reports `no suitable node (host-mode port already in use on 1 node)` and the replacement task remains Pending). Available diagnostic evidence does not establish that this rollout defect was necessarily the sole original cause of every earlier HTTP 502 observation.
- **Resolution**: Setting `update_config.order: stop-first` resolved the confirmed deployment-convergence defect. Post-deploy verification (run `32376272592`) and diagnostic probing (run `32376637733`) confirmed healthy service convergence and `/up` endpoint availability.

---

## 7. Known Gaps & Uncertainties

1. **OAuth2 vs Full OIDC Provider Status**:
   - Madeena IAM is currently an **OAuth2-based Single Sign-On server** issuing Passport JWT access tokens.
   - Full OpenID Connect (OIDC) provider specifications (such as OIDC Discovery at `/.well-known/openid-configuration`, `id_token` issuance, standard UserInfo endpoint schemas, and JWKS endpoints) are NOT established in the current codebase.
   - Silent session sync uses custom handling of `prompt=none` over OAuth2 rather than full OIDC specification conformance.

2. **Social Logins**:
   - Third-party social identity providers (Google, GitHub, Microsoft) are currently deferred.

3. **GeoIP Location Resolution**:
   - Location tracking in `AuthenticationLog` utilizes a placeholder/basic resolver (`GeoIPService`) pending full IP database integration.
