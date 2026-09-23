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
Implementar `/api/v1/auth/discord/redirect` e `/api/v1/auth/discord/callback` com um provider Discord explícito baseado em Socialite. O fluxo permanece sem sessão Laravel nas rotas API, mas usa cookie httpOnly de curta duração com `state` assinado para proteger o callback contra CSRF/login injection. O callback persiste `discord_id`, `username` e `avatar`, associa o usuário à comunidade default como `member` no MVP e emite token Sanctum. O mapeamento automático por guild fica como extensão futura, pois depende de permissões e configuração operacional no Discord.

### Consequências
O backend fica pronto para o fluxo OAuth real sem depender de sessão server-side. Tokens são revogáveis via `/api/v1/auth/logout`. A proteção de `state` adiciona uma dependência de cookie same-site/httpOnly entre redirect e callback, então ambiente frontend/backend precisa preservar domínio e HTTPS corretos. O operador do ambiente deve configurar `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET` e `DISCORD_REDIRECT_URI` no `.env`; esses valores não entram no repositório.

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

## ADR-20 — Webhook GitHub com verificação HMAC

### Contexto
A ingestão precisa reagir a pushes no repositório de documentação sem depender apenas de polling. Um endpoint público de webhook não pode aceitar payloads sem autenticação, porque isso permitiria disparo arbitrário de jobs de ingestão.

### Decisão
Expor `POST /api/v1/webhooks/github` e validar `X-Hub-Signature-256` com HMAC SHA-256 usando `GITHUB_WEBHOOK_SECRET`. Apenas eventos `push` na branch configurada do plugin disparam ingestão da versão latest correspondente. Assinatura ausente ou inválida retorna `401` com erro JSON padronizado.

### Consequências
O backend pode reagir a mudanças reais no GitHub com baixa latência e sem expor execução anônima. A ação humana obrigatória é configurar o secret no `.env` e no webhook do GitHub com o mesmo valor.

## ADR-21 — Sync agendado respeitando ETag

### Contexto
Webhooks podem falhar, ser desabilitados ou não existir em todos os repositórios. O sistema precisa de uma rotina periódica de reconciliação sem reprocessar conteúdo inalterado nem pressionar desnecessariamente a API do GitHub.

### Decisão
Adicionar o comando `commandsphere:sync-scheduled` ao scheduler Laravel, com cron configurável por `COMMANDSPHERE_SYNC_SCHEDULE`. O comando enfileira ingestões das versões latest e o `GitHubClient` mantém o uso de ETag/`If-None-Match` por arquivo para pular conteúdo sem alteração.

### Consequências
A ingestão fica resiliente a perda de webhook e preserva idempotência. O trade-off é que a cadência precisa ser calibrada com rate limit do GitHub e tamanho dos repositórios monitorados.

## ADR-22 — Canais Reverb privados por comunidade

### Contexto
Status de ingestão e atualização de índice são eventos operacionais por comunidade. Canais globais poderiam vazar informação entre comunidades ou exigir filtragem client-side frágil.

### Decisão
Broadcastar eventos em `private-community.{slug}` via Reverb, autorizado por Sanctum no endpoint `/api/broadcasting/auth`. A autorização exige que o usuário pertença à comunidade e tenha `ingestion.run` ou `analytics.view`. O frontend usa Echo de forma SSR-safe e assina apenas comunidades presentes no estado autenticado.

### Consequências
O admin de ingestões recebe atualizações ao vivo sem refresh e sem expor eventos de outras comunidades. O custo é manter Reverb, Horizon e credenciais de broadcast alinhados no Docker e nos ambientes futuros.

## ADR-23 — SEO/SSR com discovery público de leitura

### Contexto
O CommandSphere precisa funcionar como produto e também como peça de portfólio indexável. Páginas de landing, busca, catálogo, plugins, documentos e comandos devem renderizar conteúdo real no servidor para SEO, previews sociais e avaliação sem login. Ao mesmo tempo, dados operacionais, favoritos, analytics e ações administrativas continuam exigindo usuário autenticado e permissões por comunidade.

### Decisão
Expor endpoints de leitura do discovery como públicos quando a requisição é anônima, mantendo escopo por bearer token quando ele existe. O frontend SSR usa uma URL interna de API por ambiente (`COMMANDSPHERE_API_INTERNAL_URL`) para renderizar conteúdo público no servidor. Landing, plugin e comando atualizam `title`, description, canonical, Open Graph e JSON-LD; `robots.txt` e `sitemap.xml` ficam no build público.

### Consequências
As páginas públicas ficam indexáveis e auditáveis por Lighthouse sem depender de sessão. O trade-off é separar explicitamente leitura pública de ações autenticadas, para não confundir catálogo indexável com dados privados. Requisições com header de autenticação inválido não são promovidas a escopo público.

## ADR-24 — Hardening de superfície pública

### Contexto
A Fase 6 amplia a superfície pública com SSR, landing, busca indexável e screenshots. Isso exige revisar XSS, rate limiting, headers e dependências para que o projeto não pareça apenas funcional, mas operável.

### Decisão
Manter sanitização de Markdown antes de `SafeHtml`, aplicar headers de segurança no Laravel e no servidor SSR Node, limitar rotas de busca/sync/webhook, validar webhook GitHub com HMAC SHA-256, manter token Sanctum apenas em memória e auditar dependências com `composer audit` e `npm audit --audit-level=critical`.

### Consequências
O projeto reduz riscos comuns de portfólio público: HTML inseguro, endpoints de sync abusáveis, webhook forjável e secrets em código. O npm ainda reporta vulnerabilidades moderadas/altas em dependências de tooling Angular sem correção não quebrável; a regra de release é bloquear vulnerabilidade crítica e registrar o risco até atualização segura da toolchain.

## ADR-25 — Build de produção multi-stage

### Contexto
O Docker de desenvolvimento privilegia feedback rápido e ferramentas de teste. Para avaliação de deploy, o projeto precisa demonstrar imagens separadas, menor superfície e composição próxima de produção.

### Decisão
Adicionar Dockerfiles multi-stage para backend PHP-FPM e frontend Node SSR, além de `docker-compose.prod.yml` com Nginx para API, MySQL, Redis, Meilisearch, Horizon e Reverb. O build de produção consome variáveis de ambiente documentadas e não embute secrets.

### Consequências
A stack pode ser validada localmente com `docker compose -f docker-compose.prod.yml up -d --build`, preservando separação entre runtime PHP-FPM, SSR Node e serviços de dados. O trade-off é manter dois caminhos Docker: dev com DX/testes e prod com empacotamento mais fiel ao deploy.

## ADR-26 — Override de `pacote` para fechar vulnerabilidade crítica sem upgrade de major

### Contexto
O CI da `main` ficou vermelho desde o merge do PR #1 por auditoria de dependências. No frontend, a única vulnerabilidade `critical` era `tar <=7.5.20` (path traversal por hardlink/symlink), alcançada de forma transitiva. `@angular/cli@19.2.27` fixa `pacote` em `20.0.0` exato, e `pacote@20.0.0` depende de `tar ^6.1.11` — uma linha que nunca recebeu patch de segurança. Sem tocar nessa cadeia, a critical só fecharia com upgrade de major da toolchain Angular, fora do escopo da correção.

### Decisão
Declarar em `frontend/package.json` os overrides `"pacote": "20.0.1"` e `"tar": "^7.5.21"`. A versão forçada de `pacote` está fora do pin exato declarado pelo `@angular/cli`, e isso é aceito conscientemente como exceção, com base nestes fatos verificados: a única diferença de dependências entre `pacote@20.0.0` e `20.0.1` é `tar: ^6.1.11 -> ^7.5.10`, com `engines` e `bin` idênticos; `pacote` é usado apenas por `ng update` e `ng add`, nunca em build, teste, SSR ou runtime; com `tar ^7.5.21`, os requisitos de `cacache` (`^7.4.3`), `node-gyp` (`^7.4.3`) e `pacote@20.0.1` (`^7.5.10`) são satisfeitos pela mesma versão resolvida, e `npm ls` não reporta `invalid`.

### Consequências
`npm audit --audit-level=critical` volta a sair com código 0 sem afrouxar o gate, sem `npm audit fix --force` e sem upgrade de major. O custo é um ponto de manutenção: no upgrade da toolchain Angular os dois overrides devem ser reavaliados e removidos assim que a cadeia oficial trouxer `tar` 7.x. Vulnerabilidades `high` e `moderate` remanescentes seguem a política do ADR-24: registradas como risco, nunca declaradas corrigidas.

## ADR-27 — Client GitHub fail-closed com taxonomia de códigos de falha

### Contexto
`HttpGitHubClient::request()` mapeava apenas 403 para rate limit e 404 para repositório inexistente. Todo o restante — 401, 409, 422, 429, 5xx, falha de conexão — retornava uma `Response` normal, e `markdownFiles()` a consumia com `json('tree', [])`. O efeito era fail-open: corpo não-JSON, corpo sem `tree` ou erro de servidor viravam lista vazia, e a ingestão terminava `success` sem nenhum documento, indistinguível de um repositório legitimamente sem documentação. `base64_decode(...) ?: ''` transformava conteúdo inválido em documento vazio persistido em silêncio, e uma árvore com `truncated: true` era tratada como completa. Havia ainda uma segunda perna, não descrita na F-006: `IngestionService::run()` capturava apenas as duas exceções existentes, de modo que qualquer outra escapava do método e deixava o `IngestionRun` preso em `running`, sem `finished_at` e sem log.

### Decisão
Introduzir a hierarquia `App\Exceptions\GitHubClientException`, abstrata, com o método `failureCode()` devolvendo um código estável e seguro para log. O client passa a falhar explicitamente em toda resposta que não seja sucesso e em todo payload que não satisfaça o formato esperado, e `IngestionService::run()` captura a hierarquia inteira, encerrando o run por `fail()`. As categorias e seus códigos:

- rate limit → `git_hub_rate_limit_exception` (403 com sinal de rate limit, e 429)
- autenticação ou permissão → `github_authentication_failed` (401, e 403 sem sinal de rate limit)
- repositório ou ref inexistente → `git_hub_repository_not_found_exception` (404)
- conflito ou validação → `github_validation_failed` (409, 422)
- erro transitório → `github_transient_error` (qualquer status `>= 500`, falha de conexão, timeout)
- payload malformado → `github_malformed_response` (corpo não-JSON, corpo sem `tree`, base64 inválido, encoding não suportado)
- árvore truncada → `github_tree_truncated`

Os dois códigos herdados mantêm o formato derivado do nome da classe porque há asserção literal em teste existente, e alterar expectativa de teste para acomodar estética seria degradar evidência. Códigos novos usam literais explícitos. A distinção entre 403 de rate limit e 403 de permissão é feita pelo cabeçalho de rate limit, não pelo status isolado.

Decisões de escopo tomadas junto: árvore truncada encerra como `failed`, e não como `partial`, porque um inventário incompleto ingerido parcialmente acionaria a reconciliação do ADR-10 e apagaria comandos que apenas não vieram na resposta; a falha de um único arquivo também derruba o run inteiro, preservando o comportamento de abortar que já existia. Retry e backoff ficam fora, na Prioridade 8.

### Consequências
Nenhuma resposta de erro do GitHub pode mais terminar como sucesso vazio, e todo run que falha carrega um código estável em `IngestionRun.log`, na chave `code` que já existia. A assinatura de `IngestionService::fail()` e a estrutura das entradas de `log` não mudaram, então consumidores a jusante — telemetria e correlação de eventos — leem a taxonomia sem migração. O teste de transitório usa `>= 500` em vez de lista de status, de modo que qualquer 5xx futuro é coberto sem alteração. O caminho 304/ETag do ADR-11 retorna antes de qualquer verificação de erro e segue inalterado: o run permanece `success` e registra `document_not_modified`. O trade-off é rigidez deliberada — uma indisponibilidade momentânea do GitHub agora reprova o run inteiro em vez de ingerir o que deu, e é exatamente por isso que a ação de retry da Prioridade 8 se torna mais necessária. Mensagens de exceção carregam apenas repositório, caminho e status; nunca o token, o cabeçalho `Authorization` ou o corpo da resposta.

## ADR-28 — Sanitização de HTML no servidor em duas camadas

### Contexto
`content_html` é derivado de Markdown de terceiros vindo do GitHub. Até então a conversão usava `GithubFlavoredMarkdownConverter` sem configuração, o que mantém os padrões `html_input=allow` e links inseguros permitidos, e a sanitização existia apenas no Angular (`MarkdownRendererService`, ADR-18). Qualquer outro consumidor da API — export, integração, client alternativo, view administrativa — recebia HTML executável. Documentos ingeridos antes da correção já estavam gravados com esse HTML.

### Decisão
Sanitizar no backend em duas camadas, com um allowlist próprio de tags e atributos sobre `DOMDocument` (`App\Services\Markdown\HtmlSanitizer`), sem dependência nova:

1. Na ingestão, o conversor passa a usar `html_input=escape` e `allow_unsafe_links=false`, e a saída é sanitizada antes de persistir.
2. Na leitura, um accessor no model `Document` sanitiza `content_html` a cada acesso, sem reescrever a coluna.

`href`/`src` aceitam apenas `http`, `https`, `mailto` e caminhos relativos, com remoção de bytes de controle antes da checagem de esquema. A sanitização do Angular permanece como defesa em profundidade.

### Consequências
Documentos antigos ficam cobertos sem migração nem reprocessamento: a coluna armazenada permanece como está e a sanitização acontece na saída, o que satisfaz a lei DATA sem escrita em dado existente. A proteção não depende do filtro interno do `league/commonmark`, o que neutraliza a classe de bypass por bytes de controle do `CVE-2026-71478` (versão instalada 2.8.2) independentemente da atualização daquele pacote — complementar ao ADR-26, que trata a política de advisories por override de dependência. O custo é sanitizar a cada leitura; a operação é idempotente e o resultado de discovery já é cacheado por `DiscoveryCache`. Entradas cacheadas antes do deploy continuam cruas até expirar (300s), então o deploy desta mudança exige invalidar o cache de discovery.

## ADR-29 — Correlation ID e telemetria estruturada no fluxo de ingestão

### Contexto
Não havia como seguir um incidente de ponta a ponta — request do webhook, run de ingestão, job na fila, falha do GitHub. Não existia nenhuma chamada a `Log::` em `backend/app/`, o canal padrão era `stack` → `single` em texto livre, e as entradas de `IngestionRun.log` não carregavam identificador de correlação (F-011).

### Decisão
- O middleware `AssignCorrelationId`, em prepend na stack `api`, aceita o `X-Request-Id` recebido apenas se casar `^[A-Za-z0-9._:-]{1,128}$` com o modificador `D`; caso contrário gera um UUID. O ID é devolvido no cabeçalho da resposta. Sem o `D`, o `$` do PCRE casa antes de um newline final e o valor cru seria ecoado no cabeçalho e persistido — o modificador é obrigatório e está coberto por teste de regressão para `\n`, `\r` e `\r\n` finais.
- A coluna aditiva `ingestion_runs.correlation_id`, nullable e indexada, é gravada na criação do run. O job não carrega o ID: recarrega o run pelo `ingestionRunId` e lê a coluna.
- Um run reaproveitado por `IngestionService::start()` mantém o ID de quem o criou. A chamada que o reaproveitou registra o próprio ID apenas no evento `ingestion.enqueued`, com `reused: true`. Histórico de run não é reescrito.
- Cada entrada de `IngestionRun.log` ganha o campo `correlation_id`. Nenhuma entrada nova é acrescentada, o que preserva asserções existentes por índice.
- Um canal `telemetry` aditivo (Monolog com `JsonFormatter`, arquivo `storage/logs/telemetry.log`) recebe os eventos por `App\Support\Telemetry::event()`: `ingestion.enqueued`, `ingestion.started`, `ingestion.completed`, `ingestion.failed`, `webhook.github.accepted` e `webhook.github.rejected`. O canal `default` não muda. `ingestion.failed` lê o código da chave `code` já persistida, sem recomputar nem criar vocabulário paralelo (ADR-27, BRAIN-007).
- O sync agendado gera um ID por run, não compartilhado no lote.

### Consequências
Um incidente pode ser seguido por um único ID da requisição ao run, ao job, a cada entrada do log do run e aos eventos estruturados, sem dependência nova nem infraestrutura externa. Runs anteriores à migration ficam com `correlation_id` nulo e continuam legíveis na API e no admin. O contexto de telemetria é montado apenas com identificadores, contagens, status e códigos: token, cabeçalho `Authorization`, assinatura do webhook, segredo e conteúdo Markdown não entram no log. Limites atuais: a chamada ao GitHub, a indexação no Meilisearch e o evento `CommandIndexUpdated` não carregam o ID, e o broadcast `IngestionRunStatusChanged` o leva apenas indiretamente, dentro das entradas de `log`. Os demais eventos da taxonomia da auditoria (`plugin.created`, `search.executed`, `realtime.broadcast.failed` e outros) e as métricas de duração e latência ficam fora. O `telemetry.log` usa `StreamHandler` sem rotação e cresce sem limite até que uma política de retenção seja definida.

## ADR-30 — Content Security Policy estrita com nonce por requisição

### Contexto
Nem a API Laravel nem o SSR Node enviavam `Content-Security-Policy` (F-008). O app renderiza HTML derivado de Markdown de terceiros; a sanitização no Angular (ADR-18) e no servidor (ADR-28) é a primeira barreira, e faltava a segunda, aplicada pelo browser. Três pontos impediam uma política estrita sem `unsafe-inline`: o `CommonEngine` do Angular SSR injetava CSS crítico inline, com handler `onload`, nas rotas renderizadas dinamicamente; componentes usavam bindings `[style.*]`, que o SSR serializa como atributo `style=""`; e o `express.static` respondia os HTML prerrenderizados antes do render, sem cabeçalho nenhum e com cache de um ano.

### Decisão
- O SSR aplica a CSP por requisição: `default-src 'self'`, `script-src 'self'`, `style-src 'self' 'nonce-<por requisição>'`, `img-src 'self' data: https:`, `font-src 'self'`, `object-src 'none'`, `base-uri 'self'`, `form-action 'self'` e `frame-ancestors 'none'`. O nonce chega ao Angular pelo atributo `ngCspNonce` do `app-root`.
- `script-src` dispensa nonce porque nenhum script inline executa: o transfer state e o JSON-LD são blocos `application/json` e `application/ld+json`.
- `connect-src` é montado pela mesma função que gera o `/runtime-config.js` (`resolveRuntimeBrowserConfig`, a partir de `COMMANDSPHERE_API_PUBLIC_URL` e `COMMANDSPHERE_REVERB_PUBLIC_*`), então a política e a configuração que o browser recebe não podem divergir. Nenhum host é fixo. A origem da API só entra quando difere da origem do próprio SSR, e o esquema do Reverb define `ws:` ou `wss:`.
- O HTML sai com `Cache-Control: no-store`, porque um nonce cacheado deixa de ser nonce.
- Requisições `*.html`, sem diferenciar caixa, não passam pelo `express.static`: caem no render e recebem a mesma política. `/runtime-config.js` recebe `default-src 'none'`.
- A inlining de CSS crítico fica desligada no build (`angular.json`) e no `CommonEngine.render`.
- Valores de estilo enumeráveis viram classes Tailwind estáticas. O único valor contínuo, a barra de analytics, virou atributo de geometria SVG, que `style-src` não rege.
- A API, que serve apenas JSON, envia `default-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'` em toda resposta, inclusive nas de erro. O `SecurityHeaders` fica no stack global de middleware, de modo que nenhum grupo de rota o contorna.
- A política é aplicada, não report-only. Os cabeçalhos anteriores (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`) permanecem.

### Consequências
A política não tem `unsafe-inline` nem `unsafe-eval`, e não há curinga em `script-src` nem em `connect-src`. `img-src https:` é a única abertura, deliberada, para não quebrar as imagens da documentação ingerida; o custo é expor o acesso a hosts de imagem arbitrários, atenuado pelo `Referrer-Policy`. Um proxy ou CDN colocado na frente do SSR não pode cachear HTML nem reescrever o cabeçalho. Componente novo que precise de estilo dinâmico com valor não enumerável usa atributo ou geometria SVG; `style-src-attr 'unsafe-inline'` só entra com decisão registrada (BRAIN-009). Esta é a camada acima do ADR-28, não um substituto dele.

## ADR-31 — Resolução de usuário opcional dos endpoints públicos em um único serviço

### Contexto
`CatalogController::currentUser()` e `SearchController::currentUser()` continham a mesma lógica de resolução de usuário opcional, byte a byte (486 caracteres normalizados), usada oito vezes no catálogo e uma na busca (F-013). Os endpoints públicos de descoberta precisam ser legíveis anonimamente, mas uma requisição com aparência de autenticada e token inválido não pode ser promovida ao escopo público (ADR-23). Duas cópias da mesma regra de escopo são um ponto de divergência futura: corrigir uma e esquecer a outra abriria o catálogo público para quem mandou um token inválido.

### Decisão
Extrair a lógica, sem nenhuma alteração de comportamento, para `App\Services\Auth\OptionalBearerUserResolver`, injetado por construtor nos dois controllers, e remover os dois métodos privados. O serviço é uma função pura de `Request`, sem estado, e o contrato caracterizado é:

- usuário já autenticado pelo guard → o próprio usuário;
- bearer não vazio resolvido por `PersonalAccessToken::findToken` → dono do token;
- bearer não vazio que não resolve (inválido, revogado) → `new User` não persistido, que `DiscoveryAccess` traduz em escopo vazio, nunca o público;
- ausência de bearer — sem cabeçalho, cabeçalho de outro esquema como `Basic`, ou `Bearer` vazio → `null`, escopo público anônimo.

O comportamento foi fixado por `backend/tests/Feature/OptionalBearerUserResolutionTest.php` antes da extração, em commit próprio, e a mesma suíte, sem uma linha alterada, passou depois dela.

### Consequências
A regra de escopo dos endpoints públicos passa a ter um único ponto de manutenção, e qualquer mudança futura nela é necessariamente uma mudança deliberada de contrato, coberta por teste. Duas divergências entre o texto da F-013 e o código real ficam registradas como comportamento vigente, não corrigidas aqui para não misturar mudança funcional a um refactor:

1. `Authorization: Basic ...` e `Bearer` vazio caem no escopo público, e não no escopo vazio. O guard clause devolve `null` assim que `bearerToken()` é nulo ou vazio, então o escopo vazio só existe para bearer não vazio que falha ao resolver.
2. `findToken` não verifica `expires_at`, e `config('sanctum.expiration')` é `null`. Um token expirado continua aceito como seu dono nestes endpoints públicos, porque eles resolvem o token fora do guard `auth:sanctum`. Os endpoints privados não são afetados: ali quem valida é o guard. A lacuna é pré-existente, está travada por teste nomeado como tal e documentada no docblock do serviço; fechá-la é tarefa própria, com decisão registrada, porque muda resposta de endpoint.
