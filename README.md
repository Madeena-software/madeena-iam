# Madeena IAM (Identity & Access Management)

[![Laravel Version](https://img.shields.io/badge/Laravel-13.x-red.svg)](https://laravel.com)
[![Filament Version](https://img.shields.io/badge/Filament-5.x-orange.svg)](https://filamentphp.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)

Madeena IAM is a centralized Identity & Access Management (IAM) and Single Sign-On (SSO) server. It serves as the single source of truth for user credentials, profiles, device sessions, and access permissions across the Madeena ecosystem (including `simama` portal, `madeena-workspace`, `madeena-erp`, and `madeena-it`).

---

## 🚀 Key Capabilities

* **Centralized Single Sign-On (SSO):** OAuth2 Authorization Code grant flow allowing seamless access to authorized client applications without duplicate user credentials.
* **Silent Session Synchronization (`prompt=none`):** Redirect-based session validation allowing client applications to silently check for an active central session and obtain authorization codes without prompting the user.
* **Direct API Authentication (No Redirects):**
  * `POST /api/v1/auth/login`: Direct server-to-server validation (validates client secret, user credentials, and client authorization status).
  * `POST /api/v1/auth/register`: Endpoint for client applications to forward new user registrations to IAM in a `pending_approval` state.
* **Granular Client Access Mappings:** Per-user application access provisioning via the `client_user` mapping table, blocking unauthorized users at the gateway.
* **Device Session Management & Remote Revocation:** Inspect active device sessions (IP address, user-agent, last active timestamp) and revoke them remotely.
* **Security Audit Trail:** Comprehensive authentication logs tracking logins, logouts, IPs, user agents, accessed client applications, and status classifications (`success`, `failed_password`, `blocked_app`, `invalid_client`).
* **Central Admin Dashboard:** Filament v5 dashboard for client application management, user approval workflows, access provisioning, and activity monitoring.

---

## 🛠️ Technology Stack

* **Backend Engine:** PHP 8.4+
* **Framework:** Laravel 13 (`laravel/framework: ^13.8`)
* **OAuth2 Authentication Engine:** Laravel Passport 13 (`laravel/passport: ^13.0`, OAuth2 server issuing signed JWT access tokens)
* **Admin Dashboard:** Filament v5 (`filament/filament: ^5.3.5`) with Spatie Permission & Shield
* **Audit Logging:** Spatie Laravel Activitylog (`spatie/laravel-activitylog: ^5.0`)
* **Frontend Assets:** Alpine.js, Tailwind CSS v4, and Vite 8 (`@tailwindcss/vite: ^4.0.0`)
* **Database Engine:** MySQL 8.4 (Isolated DB Instance)
* **Testing Suite:** PHPUnit 12.5.x (using Mockery 1.6 & Faker 1.23)

> [!IMPORTANT]
> **Database Isolation Rule:** To maintain absolute security, security audits, and architectural independence, `madeena-iam` **MUST** run on a completely separate database server instance, sharing zero tables or credentials with client applications.

---

## 📚 Documentation Reference

* **Product Requirements & Specifications:** [`docs/madeena_iam_prd.md`](file:///var/www/madeena-iam/docs/madeena_iam_prd.md)
* **Production Architecture & Operational Invariants:** [`docs/production-architecture.md`](file:///var/www/madeena-iam/docs/production-architecture.md)
* **Repository AI Delivery Contract:** [`.agents/AGENTS.md`](file:///var/www/madeena-iam/.agents/AGENTS.md)
* **Software Delivery Lifecycle Protocol:** [`.agents/software-workflow.md`](file:///var/www/madeena-iam/.agents/software-workflow.md)
* **Repository Context Map:** [`.agents/context/project.md`](file:///var/www/madeena-iam/.agents/context/project.md)

---

## 💻 Local Development Setup

Madeena IAM employs a **hybrid development architecture** to optimize performance inside WSL (Windows Subsystem for Linux):
* PHP, Composer, and NPM packages run directly on the host WSL system.
* MySQL containerized infrastructure runs isolated via Docker Compose.

### Prerequisites
Ensure the following are installed:
* PHP 8.4+
* Composer
* Node.js & NPM
* Docker & Docker Compose

### 📥 1. Clone & Set Up Environment
Run the automated initialization script:
```bash
# Provision application, copy environment variables, build assets, and run migrations
./deploy-local.sh
```

To perform a fresh reset of the database, run:
```bash
./deploy-local.sh --fresh
```

To configure the workspace without starting the development server immediately:
```bash
./deploy-local.sh --no-start
```

### 🏃‍♂️ 2. Developer Commands
* **Start Dev Server (server, queue, logs, and Vite concurrently):**
  ```bash
  composer dev
  ```
* **Run Tests (PHPUnit):**
  ```bash
  composer test
  ```
* **Linting & Code Style Formatting:**
  ```bash
  ./vendor/bin/pint
  ```
* **Stop Infrastructure Container:**
  ```bash
  docker compose -f docker-compose.local.yml down
  ```

---

## 🤖 Repository AI Delivery & Governance

This repository utilizes the canonical `.agents/` software delivery framework.
* Read [`.agents/AGENTS.md`](file:///var/www/madeena-iam/.agents/AGENTS.md) for role boundaries (Planner, Executor, Reviewer) and delivery governance.
* Follow [`.agents/software-workflow.md`](file:///var/www/madeena-iam/.agents/software-workflow.md) for quality gates (B0–G10), traceability, and immutable baseline rules.
* Orientation context is maintained at [`.agents/context/project.md`](file:///var/www/madeena-iam/.agents/context/project.md).
