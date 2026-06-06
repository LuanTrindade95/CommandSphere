# CommandSphere

CommandSphere é um hub de documentação inteligente para ecossistemas de plugins. O MVP será construído como um monorepo com backend Laravel, frontend Angular SSR e serviços de infraestrutura para busca, filas e realtime.

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 12, PHP 8.3, Sanctum, Socialite, Scout, Horizon, Reverb |
| Frontend | Angular 19 standalone, SSR, Transloco, TailwindCSS, Jest |
| Banco e infra | MySQL 8, Redis 7, Meilisearch v1, Docker Compose |
| Qualidade | Pest, Pint, Jest, TypeScript build, GitHub Actions |

## Arquitetura

O repositório é organizado em três áreas principais:

```text
backend/   API Laravel, jobs, configurações de busca, filas e realtime
frontend/  Angular SSR com i18n pt-BR padrão e shell dark-first
docker/    Dockerfiles de desenvolvimento para PHP 8.3 e Node 22
docs/      Visão, ADRs e progresso faseado
```

Fluxo alvo do produto:

```text
GitHub Markdown -> Laravel ingestion jobs -> CommonMark parser -> MySQL -> Meilisearch -> Angular SSR/search UI
```

## Setup Local

Pré-requisitos:

- Docker Desktop
- Node 22, para execução local do frontend fora do Docker
- PHP/Composer, para execução local do backend fora do Docker

Subir a stack completa:

```bash
cp .env.example .env
docker compose up --build
```

Serviços:

| Serviço | URL |
|---|---|
| Frontend | http://localhost:4200 |
| Backend | http://localhost:8000 |
| Reverb | http://localhost:8080 |
| Meilisearch | http://localhost:7700 |
| MySQL | localhost:3306 |
| Redis | localhost:6379 |

## Comandos Úteis

Backend:

```bash
cd backend
composer install
./vendor/bin/pint --test
./vendor/bin/pest
php artisan about
```

Frontend:

```bash
cd frontend
npm ci
npm run lint
npm test -- --runInBand
npm run build
```

## Decisões Técnicas

As decisões arquiteturais são registradas em `docs/DECISIONS.md`. A visão canônica do produto está em `docs/VISION.md`, e o progresso faseado está em `docs/PROGRESS.md`.
