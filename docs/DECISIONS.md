# Architecture Decision Records (ADR)

Cada decisão arquitetural relevante deve ser registrada neste arquivo usando o formato:

## ADR-NN — Título

### Contexto
Explique a necessidade, restrição ou risco que motivou a decisão.

### Decisão
Descreva a escolha feita de forma objetiva.

### Consequências
Liste impactos de manutenção, escalabilidade, performance, segurança e trade-offs conhecidos.

## ADR-01 — Monorepo Docker com MySQL, Redis e Meilisearch

### Contexto
CommandSphere precisa demonstrar arquitetura full stack com backend Laravel, frontend Angular SSR, busca indexada, filas e realtime. A fundação deve permitir que a stack suba de forma reprodutível sem depender de serviços externos.

### Decisão
Organizar o projeto em monorepo com `backend/`, `frontend/`, `docker/`, `docs/` e `docker-compose.yml`. O Docker Compose provisiona MySQL 8, Redis 7, Meilisearch v1, backend Laravel, Reverb, Horizon e frontend Angular. Os containers de aplicação usam PHP 8.3 e Node 22.

### Consequências
A arquitetura local fica próxima do ambiente real esperado para o MVP e reduz variação entre máquinas. O custo é um setup inicial maior e builds Docker mais pesados. Chaves locais de desenvolvimento ficam em `.env.example`/`.env`, não embutidas no código.

## ADR-02 — Angular SSR habilitado desde a fundação

### Contexto
As páginas públicas de catálogo, plugin e comando precisam ser indexáveis e renderizadas no servidor nas fases futuras. Adicionar SSR depois aumentaria risco de hidratação e retrabalho na arquitetura frontend.

### Decisão
Criar o frontend com Angular 19 standalone e SSR habilitado desde o scaffold. A casca inicial usa providers compatíveis com SSR e hydration.

### Consequências
O projeto nasce preparado para SEO e páginas públicas performáticas. A contrapartida é maior rigor com providers e estado por request nas fases seguintes, especialmente quando auth e dados públicos forem adicionados.

## ADR-03 — Transloco com pt-BR como locale padrão

### Contexto
CommandSphere é o único projeto do portfólio que exige i18n runtime. A visão define pt-BR como idioma padrão e inglês como secundário, mantendo código e identificadores em inglês.

### Decisão
Configurar Transloco no Angular com `pt-BR` como `defaultLang`, `en` como fallback e dicionários JSON em `public/i18n`. No backend, configurar `pt_BR`, fallback `en`, Faker `pt_BR` e timezone `America/Sao_Paulo`.

### Consequências
A localização fica testável desde a fundação e evita texto hardcoded nas próximas fases. O custo é disciplina contínua para manter chaves de tradução completas e impedir strings soltas nos templates.

## ADR-04 — Jest substitui Karma no frontend

### Contexto
O pacote de prompts fixa Jest no lugar de Karma. Angular 19 ainda scaffolda Karma por padrão, mas o projeto precisa de testes rápidos e adequados a CI.

### Decisão
Remover Karma/Jasmine do frontend e configurar Jest com `jest-preset-angular`. Foi fixado `jest-preset-angular@14.6.2` com Jest 29 para compatibilidade com Angular 19 e `@angular-devkit/build-angular`.

### Consequências
Os testes rodam de forma rápida e consistente no CI. A versão do preset precisou ser fixada para evitar conflito com Jest 30, cujo peer dependency não encaixa com o builder Angular 19 usado nesta fase.
