# Progresso

| Fase | Status | Evidência |
|---|---|---|
| Fase 1 — Fundação (scaffold do monorepo) | ✓ Concluída | `9045e88` backend · `f8eb667` frontend · `87aebf3`, `fa0a242`, `57a2c89` Docker · `700e1c0` CI · `12ef725` README · validações locais/Docker verdes |
| Fase 2 — Domínio e Dados | ✓ Concluída | `a7c8f78` DTO package · `88c2453` migrations · `96ff1f5` models/DTOs/Scout · `55d6452` seeders/factories/permissões · validações MySQL Docker verdes |
| Fase 3A — Backend: Auth Discord + Permissões | ✓ Concluída | `8ba0d69` auth schema · `8ee693d` Discord/dev-login/`me`/logout · `06d21c4` policies/middleware/testes · validações Pest/Pint/smoke HTTP verdes |
| Fase 3B — Backend: Pipeline de Ingestão & Parser | ✓ Concluída | `fcba9f5` GitHub client/ETag · `e3861b0` parser/fixtures · `0fae62c` ingestão idempotente/job/testes · `e1d67e2` endpoints · Pest/Pint/smoke Docker verdes |
| Fase 3C — Backend: Busca, Catálogo, Favoritos & Analytics | ✓ Concluída | `90dc43f` indexação/Scout · `4889e51` search · `1390ba0` catálogo/cache · `4d9f2db` favoritos · `eb7c844` analytics/testes · `a23c8a9` env Meilisearch · Pest/Pint/smoke Meilisearch verdes |
| Fase 4A — Frontend: Fundação SSR, auth, i18n e command palette | ⏳ Pendente | — |
| Fase 4B — Frontend: Catálogo, Doc Viewer & Busca | ⏳ Pendente | — |
| Fase 5 — Sincronização Automática + Realtime | ⏳ Pendente | — |
| Fase 6 — Polish & Vitrine | ⏳ Pendente | — |
