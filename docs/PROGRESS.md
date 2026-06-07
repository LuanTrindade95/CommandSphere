# Progresso

| Fase | Status | Evidência |
|---|---|---|
| Fase 1 — Fundação (scaffold do monorepo) | ✓ Concluída | `9045e88` backend · `f8eb667` frontend · `87aebf3`, `fa0a242`, `57a2c89` Docker · `700e1c0` CI · `12ef725` README · validações locais/Docker verdes |
| Fase 2 — Domínio e Dados | ✓ Concluída | `a7c8f78` DTO package · `88c2453` migrations · `96ff1f5` models/DTOs/Scout · `55d6452` seeders/factories/permissões · validações MySQL Docker verdes |
| Fase 3A — Backend: Auth Discord + Permissões | ✓ Concluída | `8ba0d69` auth schema · `8ee693d` Discord/dev-login/`me`/logout · `06d21c4` policies/middleware/testes · validações Pest/Pint/smoke HTTP verdes |
| Fase 3B — Backend: Pipeline de Ingestão & Parser | ✓ Concluída | `fcba9f5` GitHub client/ETag · `e3861b0` parser/fixtures · `0fae62c` ingestão idempotente/job/testes · `e1d67e2` endpoints · Pest/Pint/smoke Docker verdes |
| Fase 3C — Backend: Busca, Catálogo, Favoritos & Analytics | ✓ Concluída | `90dc43f` indexação/Scout · `4889e51` search · `1390ba0` catálogo/cache · `4d9f2db` favoritos · `eb7c844` analytics/testes · `a23c8a9` env Meilisearch · Pest/Pint/smoke Meilisearch verdes |
| Fase 4A — Frontend: Fundação SSR, auth, i18n e command palette | ✓ Concluída | `4c7c8a0` Transloco SSR · `2b17347` UI kit/palette · `0667506` auth/interceptors/guards · `62d0023` shell SSR · tsc/lint/Jest/build SSR/smoke Docker verdes |
| Fase 4B — Frontend: Catálogo, Doc Viewer & Busca | ✓ Concluída | `763e1d3` clientes/API/renderer · `de4cdc0` telas/rotas/i18n · `11785ce` Jest F4B · tsc/lint/Jest/build SSR/smoke Docker/browser verdes |
| Fase 5 — Sincronização Automática + Realtime | ✓ Concluída | `49a32e0` scheduler/webhook/broadcasts/canais/Docker · `07f345c` Echo/Reverb e status ao vivo no admin · Pest/Pint/tsc/lint/build SSR verdes · smoke Reverb+Horizon em duas sessões validado |
| Fase 6 — Polish & Vitrine | ✓ Concluída | `4e2bafb` testes/cobertura/E2E · `bc74d2b` discovery público/hardening · `61705e5` landing/SEO SSR · `347549d` Docker prod/README/ADRs · projeto v1 completo |

## Correções complementares

- Fase 3C / bloqueador da Fase 4B: contrato Discovery completado em `143fc47` com `POST /api/v1/plugins`, facet/filtro `community` em `/api/v1/search`, testes funcionais Pest e smoke HTTP/Meilisearch.
