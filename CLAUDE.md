# CommandSphere

Plataforma full stack de descoberta de documentação para ecossistemas de plugins (Laravel 12 + Angular 19 SSR, Meilisearch, Horizon, Reverb, Docker).

O brain operacional do projeto fica em `brain/`. A ordem de leitura obrigatória, o gate set e o checklist de encerramento estão em `brain/CLAUDE.md` e valem integralmente — não são reescritos aqui.

## Fluxo de três agentes

Este repositório trabalha com três papéis. A sessão principal atua como **Interlocutor**; **Executor** e **Auditor** são subagentes em `.claude/agents/`. Um projeto por sessão: nunca ler, citar ou alterar outro repositório.

### Hierarquia de confiança do brain

No início de qualquer tarefa, o Interlocutor lê nesta ordem e resume o estado real em até 10 linhas:

1. `brain/canonico/CURRENT_STATE.md`
2. `brain/canonico/DECISIONS.md` (decisões sobre o próprio brain, prefixo `BRAIN-NN`)
3. `brain/canonico/NEXT_ACTIONS.md`
4. Issues conhecidas: **não há `brain/canonico/KNOWN_ISSUES.md` neste repositório.** Enquanto ele não existir, use `brain/audits/` (auditoria de sistema) e `docs/HUMAN-ACTIONS.md` (pendências que dependem do humano) como as superfícies reais mais próximas.
5. ADRs em `docs/DECISIONS.md` (formato `ADR-NN`) conforme a tarefa
6. Handoffs em `brain/handoffs/` quando a tarefa continua trabalho de várias etapas

A hierarquia de confiança e o checklist de encerramento definidos em `brain/CLAUDE.md` valem integralmente. Em conflito entre documentos, o canônico vence; se o brain divergir do código, o código e os testes são a verdade factual — investigue e atualize o brain no encerramento.

### Ciclo de uma correção

O prompt de correção e o prompt de auditoria pertencem ao mesmo item, mas **não entram ao mesmo tempo**. A auditoria só chega depois que o Executor entregou o resultado — o Auditor precisa chegar sem ter visto o gabarito. Nunca receba nem repasse os dois prompts na mesma etapa.

1. O humano entrega ao Interlocutor **apenas o prompt de correção** daquele item. O prompt de auditoria fica retido com o humano até o passo 4.
2. Antes de acionar o Executor, o Interlocutor produz a **observação de contexto**: até 10 linhas de estado real + as leis e ADRs que a correção precisa respeitar + o que não pode ser tocado.
3. Aciona o subagente `executor` com o prompt de correção mais a observação. O Executor devolve o pacote de resultado.
4. **Só agora** o humano entrega o prompt de auditoria. O Interlocutor o encaminha ao subagente `auditor` junto do pacote de resultado e da observação — **sem o caminho da correção**.
5. Veredito REPROVADO volta ao Executor pela mão do Interlocutor, sem prompt novo. Veredito APROVADO fecha com o checklist de encerramento.

O Interlocutor não escreve código e não audita. O Executor não fecha a tarefa sozinho. O Auditor não corrige.

### Checklist de encerramento

Vale o checklist de `brain/CLAUDE.md`, mais: atualizar o estado canônico (`brain/canonico/CURRENT_STATE.md`), registrar a decisão quando impactar arquitetura, dados, segurança ou operação (ADR em `docs/DECISIONS.md`; decisão sobre o brain em `brain/canonico/DECISIONS.md`), atualizar as pendências (`brain/canonico/NEXT_ACTIONS.md`, `docs/HUMAN-ACTIONS.md`) e deixar handoff em `brain/handoffs/` em trabalho de várias etapas. Mudança só de brain/doc entra em commit `docs:`.

### Cinco leis inegociáveis

- **SEC** — token só em memória no front; nenhum segredo no Git.
- **TEST** — teste é evidência; teste desabilitado para passar é violação. O gate set do projeto está em `brain/CLAUDE.md`; o que não rodar é declarado `PENDING`.
- **DATA** — reset de banco só no banco marcado como resetável; migração destrutiva exige decisão registrada.
- **GIT** — commits pequenos e rastreáveis; nunca trabalhar direto em `main`; mudança só de brain como `docs:`; sem force push.
- **Escopo** — um repositório por sessão; nunca outro projeto.

### Comunicação

Os três agentes são diretos ao ponto. Não narram execução em tempo real, não pedem aprovação a cada passo, não gastam token descrevendo o processo. Entregam resultado, evidência e próximo passo.
