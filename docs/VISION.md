# VISION — CommandSphere

## Visão Geral
Hub central de documentação inteligente para ecossistemas de plugins. Lê documentação em Markdown de repositórios GitHub, parseia em dados estruturados, extrai e indexa comandos, e oferece busca em tempo real, favoritos, i18n e analytics. Multi-plugin, multi-comunidade, dark-first (estilo IDE/Raycast/GitBook). Projeto de portfólio que evidencia engenharia de frontend escalável + pipeline de dados no backend.

## Problema
Documentação de plugins fica espalhada em READMEs de repositórios, difícil de buscar e comparar entre versões. CommandSphere centraliza, estrutura e torna a documentação pesquisável e viva, sincronizando automaticamente com o GitHub.

## Escopo do MVP
DENTRO: login Discord OAuth2; comunidades com permissões; cadastro de plugins apontando para repos GitHub; pipeline de ingestão (sync GitHub → parser markdown → documentos estruturados → extração de comandos → indexação); versionamento por plugin; busca em tempo real com facets; favoritos; analytics (comandos mais vistos); i18n; SSR das páginas públicas; dark-first.
FORA (v2): editor de docs no app, pagamento, marketplace de plugins, multi-fonte além de GitHub.

## Stack Técnica
| Camada | Tecnologia | Justificativa |
|---|---|---|
| Backend | Laravel 12 / PHP 8.3 | Pipeline, jobs, ecossistema |
| API | REST versionada /api/v1 + API Resources | Contrato estável p/ SPA + SSR |
| Auth | Discord OAuth2 (laravel/socialite) + Sanctum | Doc pede Discord; token p/ SPA |
| Permissões | spatie/laravel-permission escopado por comunidade | Permissões por comunidade |
| Busca | Laravel Scout + Meilisearch | Busca em tempo real com facets/typo-tolerance |
| Parser MD | league/commonmark + convenção de extração | Parsing confiável e extensível |
| Sync GitHub | GitHub API (token) + ETag/condicional + jobs | Respeita rate limit, idempotente |
| Banco | MySQL 8 | Relacional, versionamento |
| Cache/Filas | Redis + Laravel Queue/Horizon | Ingestão assíncrona, cache de leitura |
| Realtime | Laravel Reverb + Echo | Status de sync ao vivo |
| Frontend | Angular 19 (standalone, signals, SSR) | Busca reativa, SSR p/ SEO |
| i18n | Transloco | Locales em runtime, JSON por idioma |
| Estilização | TailwindCSS (dark-first) | Brand kit (abaixo) |
| Testes | Pest (back) · Jest + Playwright (front) | Funcional + E2E |
| Infra | Docker Compose | Sobe stack inteira (inclui Meilisearch) |

## Entidades de Domínio (schema mental)
- **User**: discord_id, username, avatar, email?. Roles por comunidade via spatie.
- **Community**: name, slug, discord_guild_id?, description, branding(json). Pivot community_user com role.
- **Plugin**: community_id, name, slug(unique por comunidade), description, github_repo (owner/repo), docs_path, default_branch.
- **PluginVersion**: plugin_id, version, git_ref(tag/commit), changelog?, published_at, is_latest(bool).
- **Document**: plugin_version_id, path, title, frontmatter(json), content_html, content_raw, order. (resultado do parser)
- **Command**: plugin_version_id, document_id?, name, slug, syntax, description, aliases(json), parameters(json), category_id?. (extraído dos docs) — Searchable (Scout).
- **Category**: name, slug (categorização de comandos).
- **Favorite**: user_id, favoritable (Command|Document) — polimórfico.
- **CommandView**: command_id, user_id?, viewed_at (base de analytics "mais vistos").
- **IngestionRun**: plugin_version_id, status(queued|running|success|partial|failed), stats(json: docs_parsed, commands_extracted, warnings), started_at, finished_at, log(json).

## Convenção de extração de comando (decisão de parser — registrar ADR)
Comandos são declarados nos `.md` por frontmatter de documento (lista `commands:`) OU por blocos com heading padrão `## /<comando>` seguido de bloco de metadados (syntax, aliases, params). Documento que não casa a convenção é ingerido como Document normal e gera **warning** no IngestionRun (NUNCA é descartado em silêncio).

## Pipeline de ingestão (núcleo)
Trigger (manual/agendado/webhook) → cria IngestionRun → busca arquivos `.md` do repo/branch via GitHub API (ETag p/ não re-baixar inalterado) → parser CommonMark → cria/atualiza Documents (idempotente por plugin_version+path) → extrai Commands (idempotente por plugin_version+slug, sem duplicar em re-sync) → indexa no Meilisearch → invalida cache → fecha IngestionRun com stats. Falha de parsing em um doc não derruba o run inteiro: marca partial + warning.

## Mapa de funcionalidades
Core: login Discord · comunidades+permissões · cadastrar plugin · ingestão+parser+extração · versionamento · busca em tempo real com facets · doc viewer · favoritos · analytics mais vistos · i18n · SSR público.
Importante: filtros persistentes na URL · command palette (Cmd/Ctrl+K) · status de sync realtime.
Nice-to-have: comparação entre versões · dark/light toggle · export de comando.

## Mapa de telas
/ (landing pública, SSR) · /login (Discord) · /c/:community (catálogo de plugins, SSR) · /c/:community/p/:plugin (doc viewer + versões, SSR) · /c/:community/p/:plugin/commands/:slug · /search (resultados + facets) · /favorites · /admin/plugins (cadastro + disparo de sync) · /admin/ingestions (runs + logs) · /analytics.

## Brand kit (frontend — DARK-FIRST)
Cores: Deep Space #020617, Surface Dark #111827, Electric Purple #7C3AED, Neon Cyan #22D3EE; Soft Purple #A78BFA, Neutral Gray #94A3B8; Success #22C55E, Warning #FACC15, Danger #F87171. Fontes: Sora (display), Inter (corpo). Estilo: dark moderno tecnológico, glow discreto, grids/partículas leves, command palette estilo Raycast, navegação ultra-rápida. Referências: Vercel, Docusaurus, Raycast, GitBook, Discord Developer Portal.

## Riscos técnicos
- Idempotência da ingestão (re-sync não pode duplicar comandos) — mitigar com upsert por chave natural + teste de re-sync.
- Rate limit / mudança da GitHub API — mitigar com token + ETag + tratamento de 403/409.
- Meilisearch em Docker (host/porta/master key) — mitigar com smoke de indexação+busca.
- SSR + auth/i18n (hidratação, vazamento de estado entre requests no server) — mitigar com TransferState e providers por request.
- Discord OAuth em dev (redirect URI, secrets) — AÇÃO HUMANA: operador do ambiente configura credenciais no .env; testes usam fake provider.

## Localização (pt-BR)
Idioma do produto: português (Brasil), com i18n via Transloco. LOCALE PADRÃO = pt-BR (en como secundário, troca em runtime).
- Todas as strings nos dicionários de tradução (sem texto hardcoded no template); pt-BR 100% preenchido, en como fallback.
- CÓDIGO EM INGLÊS: identificadores (variáveis, funções, classes, tabelas, colunas, rotas, eventos), nomes de arquivo e comentários em inglês. NÃO usar português em nomes de código nem de coluna.
- ENUMS em inglês no banco; rótulo via chave i18n na apresentação.
- FORMATAÇÃO: backend devolve dados neutros (ISO 8601, números crus); o frontend formata com locale pt-BR.
- Backend (Laravel): config/app.php locale 'pt_BR', fallback_locale 'en', faker_locale 'pt_BR', timezone 'America/Sao_Paulo'; instalar laravel-lang/lang (pt_BR); seeds com Faker pt_BR. Registrar ADR de localização.
