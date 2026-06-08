# .ai/ Control Center

Welcome to the **.ai/ Control Center** for **Madeena IAM**. This directory serves as the single source of truth for all AI interactions, ensuring context retention, strict architectural compliance, and state tracking.

---

## 📂 Directory Index

```
.ai/
├── README.md                  # This file: Directory index and usage guide
├── history.md                 # Append-only session history log
├── memory.json                # Machine-readable metadata (tech stack, modules, etc.)
├── memory/
│   └── state.md               # Active session state, goals, and health status
├── prompt/
│   ├── bootstrap-new-repo.md  # Original bootstrapping system instruction
│   └── prompts.md             # CORE framework instructions and task templates
└── rules/
    ├── project-context.md     # Project overview, key features, and configuration
    ├── laravel-filament.md    # Coding standards for Laravel 13 + Filament v5
    ├── server-access-constraints.md # Infrastructure access policies (no direct SSH)
    ├── testing-pyramid.md     # Testing strategy (10% E2E, 30% Feature, 60% Unit)
    └── browser-agent-restrictions.md # Restrictions on using the browser sub-agent
```

---

## 🤖 Instructions for AI Agents

1. **Load Game First**: Always start the session by reading `.ai/memory/state.md` and `.ai/memory.json` to synchronize on current goals, active tasks, and environment health.
2. **Consult the Rules**: Before making changes, refer to the files in `.ai/rules/` to ensure full compliance with coding standards, access restrictions, and testing expectations.
3. **Plan Before Writing Code**: For any complex changes, propose an implementation plan and wait for human approval.
4. **Save Game Last**: At the end of every session, update `.ai/memory/state.md` and append a summary of actions/outcomes to `.ai/history.md`.

---

## 🧑‍💻 Instructions for Humans

1. **Keep it Updated**: When making manual updates (e.g., changes to environment variables, dependencies, or infrastructure), ensure the corresponding files in `.ai/rules/` and `.ai/memory.json` are updated.
2. **Review AI Proposals**: AI agents will present implementation plans before coding. Review them against the rules defined in `.ai/rules/`.
3. **Check the History**: Read `.ai/history.md` to see what changes were made in recent AI sessions.
