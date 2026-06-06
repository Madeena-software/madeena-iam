# Simama Standard - Production Simulation Templates

Reusable templates for the production simulation stack:

- Docker Compose file for the simulation services
- Environment template for `.env.simulation`
- Orchestration script for `scripts/simulate-prod.sh`

## What To Copy

| Template | Purpose | Copy To |
|----------|---------|---------|
| `standard-simulate-prod.sh` | Simulation bootstrap, verification, and teardown script | `scripts/simulate-prod.sh` |
| `standard-env-simulation` | Simulation environment variables | `.env.simulation` |
| `standard-docker-compose-simulation.yml` | Simulation stack definition | `docker-compose.simulation.yml` |

## Edit Once

Replace these placeholders after copying the templates:

| Placeholder | Used In | Meaning |
|-------------|---------|---------|
| `{{APP_NAME}}` | script, env | Application name or slug |
| `{{APP_HTTP_PORT}}` | script, env, compose | Host port exposed by Nginx |
| `{{APP_URL}}` | env | Public URL used by Laravel |
| `{{SIM_DB_NAME}}` | script, env | Simulation database name |
| `{{SIM_DB_USER}}` | script, env | Simulation database user |
| `{{SIM_DB_PASSWORD}}` | script, env | Simulation database password |
| `{{SIM_ROOT_PASSWORD}}` | script, env | MySQL root password |

## Setup Flow

1. Copy the templates into the target repository root.
2. Replace the placeholders listed above.
3. Make `scripts/simulate-prod.sh` executable.
4. Run the simulation script once to bootstrap the stack.

Example:

```bash
cp templates/simulation/standard-simulate-prod.sh scripts/simulate-prod.sh
cp templates/simulation/standard-env-simulation .env.simulation
cp templates/simulation/standard-docker-compose-simulation.yml docker-compose.simulation.yml
chmod +x scripts/simulate-prod.sh
./scripts/simulate-prod.sh
```

## Common Edits Per Repo

| File | Typical Edits |
|------|---------------|
| `scripts/simulate-prod.sh` | Port, health-check timings, verification commands |
| `.env.simulation` | app name, URL, database credentials, admin seed values |
| `docker-compose.simulation.yml` | port mapping, volume names, extra services |

## Notes

- The simulation stack is intentionally production-like, but it should stay isolated from your real production environment.
- Keep the exposed host port unique if you run multiple repositories on the same machine.
