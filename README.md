# Learning App

Learning App is a language-learning product built around content:
- users learn through videos, texts, grammar and extracted lexemes;
- admins and editors manage content and processing flows;
- the system combines catalog, study flow, progress tracking, SRS, and AI features.

## Stack

- Backend: Laravel
- Admin panel: Filament
- Frontend: Vue SPA
- Infrastructure: Docker, Postgres, Redis

## Project structure

```text
docker/        Docker configs
config/        Docker env files
src/           Laravel application + SPA
docs/          Architecture, product, operations and agent docs
engineering/   Coding and naming standards
.agents/       Agent skills (mattpocock/skills + project skills)
```

## Key docs

- [AGENTS.md](AGENTS.md) — principles, stop-lines and which skill to use
- [PROJECT_CONTEXT.md](PROJECT_CONTEXT.md) — product, modules, where things live
- [docs/architecture/README.md](docs/architecture/README.md)
- [docs/architecture/modules-and-events.md](docs/architecture/modules-and-events.md)
- [engineering/coding-standards.md](engineering/coding-standards.md)
- [docs/agents/issue-tracker.md](docs/agents/issue-tracker.md) — Linear workflow
- [Technical due diligence](docs/operations/technical-due-diligence.md)

## Local setup

1. Start containers:

```bash
docker compose --env-file config/docker.env up -d
```

2. Install and prepare the Laravel app inside `src/`.

3. Run migrations and frontend/build commands as needed.

If you want, this README can be expanded later with exact day-to-day local commands for this project specifically.
