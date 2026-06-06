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

## ADR-09 — Convenção explícita para extração de comandos Markdown

### Contexto
O CommandSphere precisa ingerir documentação real sem transformar todo documento em fonte ambígua de comandos. A visão define duas formas aceitas: frontmatter `commands:` ou blocos com heading `## /<comando>` e metadados (`syntax`, `aliases`, `params`). Documentos fora da convenção ainda são conteúdo válido e não podem ser descartados silenciosamente.

### Decisão
Implementar o parser com `league/commonmark` para HTML e `symfony/yaml` para frontmatter/metadados. Um documento gera `Document` sempre que puder ser lido; comandos só são extraídos quando declaram `syntax` pela convenção suportada. Documentos sem comandos válidos ou com metadados incompletos adicionam warning ao `IngestionRun`.

### Consequências
A extração fica previsível, testável com fixtures Markdown reais e extensível sem heurísticas frágeis. O custo é exigir disciplina nos repositórios de plugins: documentação fora da convenção aparece no viewer, mas não vira comando pesquisável até ser corrigida.

## ADR-10 — Idempotência por chave natural e reconciliação no re-sync

### Contexto
Reprocessar o mesmo repositório não pode duplicar documentos ou comandos. Além disso, quando um comando some da documentação, manter o registro antigo criaria busca e analytics inconsistentes.

### Decisão
Persistir documentos por `plugin_version_id + path` e comandos por `plugin_version_id + slug` usando upsert. A cada documento processado, o pipeline reconcilia os comandos daquele documento e remove os slugs que não apareceram no parse atual. O serviço evita sincronização Scout durante ingestão para não indexar dados antes da fase de busca.

### Consequências
O re-sync passa a ser seguro e repetível, com teste funcional cobrindo duas execuções iguais e remoção de comando ausente. A reconciliação depende de slugs estáveis; renomear slug intencionalmente é tratado como remoção do comando antigo e criação de um novo.

## ADR-11 — ETag e requisições condicionais na GitHub API

### Contexto
O pipeline consulta repositórios GitHub e pode rodar manualmente, por webhook ou agendamento. Baixar conteúdo inalterado aumenta latência e pressiona rate limit, especialmente em plugins com muitos documentos.

### Decisão
O `GitHubClient` HTTP lista arquivos `.md` por árvore recursiva e busca o conteúdo via Contents API. Para cada arquivo, salva o ETag em cache e envia `If-None-Match` nos próximos syncs. Respostas `304` são tratadas como conteúdo inalterado; `403` e `404` viram erros de domínio claros no `IngestionRun`.

### Consequências
Execuções repetidas reduzem tráfego e risco de rate limit sem depender de estado persistente extra. Testes usam `FixtureGitHubClient` local para não depender da rede; o comportamento de rate limit é validado com fake que falha antes de mutar dados.

## ADR-12 — Scout e Meilisearch para descoberta de comandos

### Contexto
A busca do CommandSphere precisa responder rápido para uso com debounce no frontend, suportar typo-tolerance e retornar facets por plugin/categoria. Também precisa impedir que resultados de comunidades fora do escopo do usuário apareçam.

### Decisão
Indexar `Command` via Laravel Scout/Meilisearch com payload enxuto e campos de filtro estáveis: `community`, `plugin`, `category` e `plugin_version`. O campo `views` é ordenável para ranking futuro. O endpoint `/api/v1/search` sempre adiciona filtro de comunidades acessíveis ao usuário e permite filtros adicionais por community/plugin/category.

### Consequências
A busca usa o motor correto para typo-tolerance e facets sem varrer MySQL por request. A contrapartida é manter settings do índice sincronizados (`scout:sync-index-settings`) e provar a presença real de documentos no Meilisearch nos smokes.

## ADR-13 — Cache de leitura invalidado por versão na ingestão

### Contexto
Catálogo de comunidades, plugins, versões e documentos é leitura frequente e muda principalmente após ingestões. Invalidar item a item aumenta acoplamento entre pipeline e endpoints.

### Decisão
Usar cache Redis por chave com versão global de descoberta. As respostas de catálogo incluem a versão atual na chave e o `IngestionService` incrementa a versão quando documentos foram persistidos. Isso invalida as leituras antigas sem precisar listar todas as chaves.

### Consequências
O cache permanece simples, previsível e barato. Leituras antigas expiram naturalmente, enquanto novas requisições passam a usar a versão atual logo após uma ingestão com mudanças. O trade-off é invalidar todo o catálogo de leitura em vez de uma única chave específica.

## ADR-14 — Deduplicação de views por janela curta

### Contexto
Analytics de comandos mais vistos pode ser inflado por refresh, duplo clique ou navegação repetida no mesmo comando em poucos segundos. O MVP precisa de uma regra simples e auditável antes de realtime/agendamentos.

### Decisão
Registrar `CommandView` no endpoint `/api/v1/commands/{slug}/view`, mas ignorar nova view do mesmo usuário para o mesmo comando dentro da janela configurada por `COMMANDSPHERE_VIEW_DEDUPE_MINUTES` (10 minutos por padrão). Quando uma view é gravada, o comando é reenviado ao Scout para atualizar o campo `views`.

### Consequências
Os rankings ficam menos suscetíveis a inflação acidental e a regra é coberta por teste funcional. O trade-off é que sessões legítimas repetidas dentro da janela curta contam como uma única view.

### Nota de contrato — Discovery API para telas de domínio
Durante o pré-check da Fase 4B, o contrato previsto para telas de domínio foi completado sem nova decisão arquitetural: `POST /api/v1/plugins` cria plugins sem disparar sync automático e exige `plugins.manage` no escopo da comunidade; `/api/v1/search` passa a aceitar filtro `community` por slug ou id e retorna facet `community` junto de `plugin` e `category`, preservando o filtro obrigatório de comunidades acessíveis ao usuário.

## ADR-15 — Token bearer em memória no frontend

### Contexto
O frontend Angular SSR precisa consumir a API Sanctum sem persistir credenciais em superfícies que aumentem exposição a XSS. Também não pode depender de cookies de sessão server-side nesta fase, pois o backend emite bearer tokens para SPA/SSR.

### Decisão
Manter o token Sanctum apenas em memória dentro do `AuthService` por signals. O login Discord ou dev-login popula o estado no browser, `/auth/me` hidrata usuário/comunidades/permissões, e refresh de página exige novo login. Não usamos `localStorage`, `sessionStorage` nem serialização do token no `TransferState`.

### Consequências
A estratégia reduz persistência indevida de segredo e evita vazamento no HTML SSR. O custo é uma UX menos persistente até uma decisão futura sobre cookie httpOnly/BFF ou renovação segura de sessão.

## ADR-16 — SSR sem vazamento de estado por request

### Contexto
O shell SSR renderiza conteúdo público e convive com auth client-side. Serviços singleton globais ou caches compartilhados poderiam contaminar uma request com dados de outra, especialmente quando o Node SSR atende usuários diferentes.

### Decisão
Usar providers Angular por request no SSR padrão (`bootstrapApplication` com `BootstrapContext`) e manter estado de usuário apenas em serviços injetáveis da aplicação, sem variáveis de módulo para sessão. `TransferState` é usado somente para dados públicos do shell; token, usuário e permissões nunca são transferidos para o HTML.

### Consequências
Duas requests SSR com identificadores diferentes geram HTML público equivalente e sem dados de usuário. Dados autenticados entram só após login no browser, via API bearer. O trade-off é que SSR não pré-renderiza conteúdo personalizado nesta fase.

## ADR-17 — Estado frontend com Angular Signals, sem NgRx

### Contexto
A Fase 4A precisa de estado local para auth, loading, toasts, idioma e command palette. O domínio ainda não exige workflows complexos, normalização pesada ou time travel debugging.

### Decisão
Usar Angular Signals em serviços focados (`AuthService`, `LoadingService`, `ToastService`, `CommandPaletteService`) e rotas lazy standalone. NgRx não é adotado nesta fase; permissões continuam centralizadas no `AuthService` para evitar duplicação em guards e componentes.

### Consequências
O estado fica simples, tipado e compatível com SSR/hydration sem adicionar store global prematuro. Se fases futuras introduzirem cache complexo, colaboração realtime ou múltiplas fontes concorrentes de estado, a decisão pode ser revisitada com uma necessidade concreta.

## ADR-18 — Markdown renderer customizado com sanitização

### Contexto
O doc viewer precisa renderizar `content_html` ingerido do backend, montar índice lateral por headings e realçar code blocks sem abrir superfície de XSS. Usar HTML direto no template ou depender apenas de CSS no conteúdo persistido colocaria segurança e consistência visual em risco.

### Decisão
Criar um `MarkdownRendererService` frontend que primeiro sanitiza o HTML com `DomSanitizer.sanitize(SecurityContext.HTML)`, depois aplica transformações controladas: ids estáveis em headings `h2/h3`, coleta do índice lateral e realce leve de sintaxe de comandos em blocos de código. O HTML final só é marcado como `SafeHtml` após essa sanitização e as transformações internas.

### Consequências
O viewer ganha navegação estrutural, code blocks consistentes com o design system e teste Jest cobrindo remoção de script malicioso. O trade-off é manter o renderer deliberadamente pequeno; se o domínio exigir Markdown interativo complexo, a decisão deve ser reavaliada com uma biblioteca sanitizável e compatível com SSR.

## ADR-19 — Facets e filtros de busca como estado na URL

### Contexto
A tela `/search` precisa suportar busca em tempo real, facets por comunidade/plugin/categoria, navegação por teclado e compartilhamento de estado. Manter filtros apenas em estado local quebraria deep links e dificultaria retorno do browser.

### Decisão
Representar `q`, `community`, `plugin` e `category` em query params e centralizar a conversão no `SearchUrlStateService`. A tela observa os params, aplica debounce antes de chamar `/api/v1/search` e atualiza a URL ao trocar termo ou facet, reutilizando o mesmo `SearchService` da command palette.

### Consequências
Resultados e filtros ficam reproduzíveis por URL, compatíveis com SSR/hydration e mais fáceis de testar. O custo é disciplina para toda nova facet passar pelo mesmo serviço, evitando estado paralelo em componentes.
