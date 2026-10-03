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
docs/          Technical and domain documentation
engineering/   Engineering operating docs and skills
```

## Documentation model

- `.ai-orchestration/` — local AI workflow, temporary tasks, and short project docs
- `repo` — code, technical documentation, and engineering rules

## What is kept here

- engineering skills and lightweight workflow guidance
- temporary local AI tasks in `.ai-orchestration/local-tasks/`
- short project docs in `.ai-orchestration/project-docs/`

## Key docs

- [PROJECT_CONTEXT.md](PROJECT_CONTEXT.md)
- [docs/architecture/README.md](docs/architecture/README.md)
- [docs/architecture/modules-and-events.md](docs/architecture/modules-and-events.md)
- [docs/architecture/engineering-principles.md](docs/architecture/engineering-principles.md)
- [engineering/README.md](engineering/README.md)
- [.ai-orchestration/README.md](.ai-orchestration/README.md)
- [Technical due diligence](docs/operations/technical-due-diligence.md)

## Local setup

1. Start containers:

```bash
docker compose --env-file config/docker.env up -d
```

2. Install and prepare the Laravel app inside `src/`.

3. Run migrations and frontend/build commands as needed.

If you want, this README can be expanded later with exact day-to-day local commands for this project specifically.
