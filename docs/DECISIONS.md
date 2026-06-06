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

## ADR-05 — Chaves naturais para idempotência de documentos e comandos

### Contexto
O pipeline de ingestão futuro precisará reprocessar documentação Markdown sem criar duplicatas a cada execução. As entidades extraídas do repositório têm identificadores naturais no escopo correto: documento por caminho dentro de uma versão de plugin e comando por slug dentro de uma versão de plugin.

### Decisão
Modelar unicidade em `documents(plugin_version_id, path)` e `commands(plugin_version_id, slug)`. Plugins também usam `plugins(community_id, slug)` para permitir o mesmo slug em comunidades distintas sem conflito global.

### Consequências
As próximas fases poderão fazer upsert idempotente durante ingestão, reduzindo lógica compensatória e risco de duplicidade. A escolha exige que o parser preserve paths e slugs estáveis; alterações intencionais nesses identificadores serão tratadas como novos registros ou exigirão reconciliação explícita no pipeline.

## ADR-06 — Permissões escopadas por comunidade

### Contexto
CommandSphere é multi-community: o mesmo usuário pode administrar uma comunidade e ser apenas membro em outra. Permissões globais não representam esse isolamento e criariam risco de vazamento de autorização entre comunidades.

### Decisão
Habilitar o modo teams do `spatie/laravel-permission` usando `community_id` como chave de escopo. O pivot `community_user` mantém o papel de domínio visível (`community-admin`, `maintainer`, `member`), enquanto roles e permissions do Spatie fazem a autorização efetiva. O helper `User::hasCommunityPermission()` troca temporariamente o escopo antes da checagem.

### Consequências
A autorização fica preparada para multi-tenancy por comunidade sem inventar um sistema paralelo. O custo é disciplina para sempre definir o escopo antes de atribuir ou checar roles, especialmente em jobs e fluxos assíncronos futuros.

## ADR-07 — Discord OAuth2 com Sanctum para autenticação da API

### Contexto
O MVP define Discord OAuth2 como fluxo real de login e Sanctum como mecanismo de token para SPA/SSR. A API precisa criar ou atualizar o usuário com dados do Discord e devolver um token bearer sem expor segredos.

### Decisão
Implementar `/api/v1/auth/discord/redirect` e `/api/v1/auth/discord/callback` com Socialite em modo stateless. O callback persiste `discord_id`, `username` e `avatar`, associa o usuário à comunidade default como `member` no MVP e emite token Sanctum. O mapeamento automático por guild fica como extensão futura, pois depende de permissões e configuração operacional no Discord.

### Consequências
O backend fica pronto para o fluxo OAuth real sem depender de sessão server-side. Tokens são revogáveis via `/api/v1/auth/logout`. Arthur deve configurar `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET` e `DISCORD_REDIRECT_URI` no `.env`; esses valores não entram no repositório.

## ADR-08 — Dev-login local/testing para desbloquear pipeline

### Contexto
Credenciais Discord são uma ação humana e não devem bloquear testes, CI ou desenvolvimento backend. Também não é aceitável criar secrets fake hardcoded para simular OAuth real.

### Decisão
Adicionar `/api/v1/auth/dev-login`, habilitado somente quando `APP_ENV` é `local` ou `testing`. A rota autentica um usuário seed por email e emite token Sanctum. Fora desses ambientes, retorna `403` com erro JSON padrão.

### Consequências
O pipeline consegue testar autenticação real de API com Sanctum sem integração externa. O trade-off é manter disciplina para nunca habilitar a rota em produção; a checagem de ambiente é coberta por teste de integração.
