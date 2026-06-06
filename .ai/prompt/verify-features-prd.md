# Task Prompt: Verify Implementation against the PRD

Use this prompt to perform a systematic audit of the repository's source code against the Product Requirements Document (PRD) to identify implementation gaps, schema mismatches, and test coverage deficiencies.

---

## 1. Context & Setup
- **PRD Location**: [docs/madeena_iam_prd.md](file:///var/www/madeena-iam/docs/madeena_iam_prd.md)
- **Active ERD Rules**: [project-context.md](file:///var/www/madeena-iam/.ai/rules/project-context.md)
- **Testing Standard**: [testing-pyramid.md](file:///var/www/madeena-iam/.ai/rules/testing-pyramid.md)

---

## 2. Objective
Identify the gap between the functional requirements defined in the PRD and what is actually implemented and verified in the codebase. Generate an audit report listing fully met, partially met, and unmet requirements, along with a remediation backlog.

---

## 3. Step-by-Step Instructions

### Step 1: Extract Requirements from the PRD
Read [docs/madeena_iam_prd.md](file:///var/www/madeena-iam/docs/madeena_iam_prd.md) and extract all checklist items from:
- **Section 4**: Sequence Diagrams & Flows (Login flow, Registration flow, SSO silent session check flow, Admin configuration flow).
- **Section 5**: Functional Requirements (Authentication, Application & Access Management, Security, Sessions & Audit Logs).
- **Section 7**: Non-Functional Requirements (Database isolation, response targets < 100ms, premium aesthetics).

### Step 2: Audit the Codebase
Verify each requirement against the following areas:
- **Routing**: Check `routes/web.php` and `routes/api.php`.
- **Controllers**: Verify endpoints in `app/Http/Controllers/Api/V1/` and other controllers.
- **Middleware**: Verify guard clauses in `app/Http/Middleware/`.
- **Database Schema**: Inspect migrations in `database/migrations/` and actual columns.
- **Filament Resources**: Inspect resources, forms, tables, and relation managers in `app/Filament/`.
- **Tests**: Check test coverage in `tests/Feature/` and `tests/Unit/`.

### Step 3: Generate the Verification Matrix
Produce a Markdown table listing each requirement:
- **Category**: (e.g., SSO, Access Control, Auditing, UI)
- **Requirement**: (Brief description)
- **Implemented?**: (Yes / No / Partial)
- **Tested?**: (Yes / No)
- **Evidence/File Paths**: (Clickable links to the routes, controllers, or tests demonstrating implementation)
- **Gaps Identified**: (What is missing or incorrect)

### Step 4: Identify Schema Mismatches
Specifically cross-reference the schema section of the PRD with active migrations and column lists to ensure:
- Model properties match migration fields.
- Relationship pivot tables (`client_user`) hold status enums and audit dates correctly.
- Audit logging (`authentication_logs`) records required relational data (e.g. `client_id` if defined).

---

## 4. Deliverable Format

Structure the output as a Markdown artifact (`audit_results.md` in the artifacts folder) containing:
1. **Executive Summary**: Brief narrative on the project health and completeness percentage.
2. **Verification Matrix Table**: The detailed requirement mapping table.
3. **Identified Mismatches & Gaps**: Bulleted list of logic flaws, missing routes, or missing schema fields.
4. **Actionable Remediation Backlog**: Prioritized roadmap of development steps (e.g. "Task 1: Implement prompt=none endpoint redirect logic") to reach 100% PRD compliance.
