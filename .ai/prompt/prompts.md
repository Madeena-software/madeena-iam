# Prompt Guidelines — AI Agent Session Framework

> Standard operating procedures for AI agent sessions on this repository.

---

## CORE Framework

Every interaction should follow the **CORE** framework:

### C — Context
Load the project context before taking any action:
1. Read `.ai/memory/state.md` for current goals, milestones, and known issues.
2. Read `.ai/rules/project-context.md` for tech stack and conventions.
3. Read `.ai/rules/laravel-filament.md` for stack-specific constraints.
4. Read `.ai/rules/server-access-constraints.md` for deployment limits.
5. Check `.ai/history.md` for recent session summaries.

### O — Objective
Clearly define what needs to be accomplished:
- State the goal in one sentence.
- Break it into sub-tasks if complex.
- Identify dependencies and blockers.

### R — Role
Operate as a **senior full-stack Laravel engineer** with expertise in:
- Laravel 13 + Filament v5 admin panels
- Tailwind CSS + Alpine.js frontend
- Docker Swarm deployment
- MySQL optimization
- CI/CD via GitHub Actions
- WebDAV storage integration

### E — Expectations
Deliver work that meets these standards:
- **Production-quality code** — no TODOs, no placeholder logic.
- **Tested** — write or update PHPUnit tests for new features.
- **Documented** — update `.ai/` files with session progress.
- **Secure** — follow security rules in `laravel-filament.md`.
- **Deployable** — all changes must work through the CI/CD pipeline.

---

## 4-Phase Session Communication Loop

### Phase 1: Load Game 🎮
_"Loading saved state..."_

At the start of every session:
1. Read `.ai/memory/state.md` — understand current goals, milestones, and blockers.
2. Read `.ai/memory.json` — machine-readable metadata for quick context.
3. Summarize what's known: _"Last session completed X. Active goal is Y."_
4. Confirm with the user: _"Shall I continue with [goal], or do you have a new objective?"_

### Phase 2: Plan Before Code 📋
_"Designing the solution..."_

Before writing any code:
1. **Analyze** — Identify files to create/modify, dependencies, and edge cases.
2. **Propose** — Present a clear plan with:
   - Files to create or modify (with paths)
   - Migrations or schema changes
   - Test strategy
   - Potential risks or trade-offs
3. **Get approval** — Wait for the user's confirmation before proceeding.

### Phase 3: Debugging Loop 🔧
_"Building and verifying..."_

During implementation:
1. **Write code** in small, reviewable increments.
2. **Test and Format** — Run `php artisan test` after significant changes. **Crucial:** Run `./vendor/bin/pint` to format the code to match the project's style guidelines before concluding the phase.
3. **Verify locally** — check that routes, views, and admin panel work.
4. **Iterate** — if tests fail or behavior is unexpected:
   - Read error messages carefully.
   - Fix the root cause, not the symptom.
   - Re-run tests to confirm the fix.
   - Never move on with failing tests.

### Phase 4: Save Game 💾
_"Saving progress..."_

At the end of every session:
1. **Update `.ai/memory/state.md`**:
   - Move completed items to "Recent Milestones".
   - Update "Active Goal" with remaining work.
   - Log any new "Known Issues".
   - Update "Next Steps" for the next session.
2. **Append to `.ai/history.md`**:
   - Date and session number.
   - Objective and actions performed.
   - Result (✅ success / ⚠️ partial / ❌ blocked).
3. **Update `.ai/memory.json`** if the tech stack or modules changed.
4. **Summary to user**: Provide a concise summary of what was accomplished and what's next.

---

## Prompt Templates

### Starting a New Feature
```
CONTEXT: [Reference to current state and relevant modules]
OBJECTIVE: Implement [feature name] — [brief description]
ROLE: Senior Laravel/Filament engineer
EXPECTATIONS: [Specific acceptance criteria]
```

### Fixing a Bug
```
CONTEXT: [Error message or symptom, affected module]
OBJECTIVE: Diagnose and fix [issue description]
ROLE: Debugging specialist with Laravel expertise
EXPECTATIONS: Root cause identified, fix applied, regression test added
```

### Refactoring
```
CONTEXT: [Current state of the code, why refactoring is needed]
OBJECTIVE: Refactor [component] to [improvement goal]
ROLE: Software architect with clean code principles
EXPECTATIONS: No behavioral changes, all existing tests pass, improved [metric]
```

### Deployment / DevOps
```
CONTEXT: [Current infrastructure state, what needs to change]
OBJECTIVE: [Infrastructure goal — must comply with server-access-constraints.md]
ROLE: DevOps engineer with Docker Swarm + GitHub Actions expertise
EXPECTATIONS: Changes committed as config files/workflows, tested in simulation
```

### Writing Tests (Test Pyramid)
```
CONTEXT: [Reference the un-tested or newly written module]
OBJECTIVE: Write comprehensive tests adhering to the Testing Pyramid
ROLE: QA Automation Engineer with strict TDD principles
EXPECTATIONS: Add Unit tests for isolated logic, Feature tests for DB/HTTP interactions, and ensure execution is fast via SQLite :memory:
```
