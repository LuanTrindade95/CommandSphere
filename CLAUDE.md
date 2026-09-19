# CommandSphere

Plataforma full-stack de descoberta de documentação (Laravel 12 + Angular 19 SSR + Meilisearch, empacotada em Docker). O conhecimento operacional do projeto vive em `brain/` — comece sempre por `brain/CLAUDE.md`.

## Fluxo de três agentes

Este repositório trabalha com três papéis. A sessão principal atua como **Interlocutor**; **Executor** e **Auditor** são subagentes em `.claude/agents/`. Um projeto por sessão: nunca ler, citar ou alterar outro repositório.

### Hierarquia de confiança do brain

No início de qualquer tarefa, o Interlocutor lê nesta ordem e resume o estado real em até 10 linhas:

1. `brain/canonico/CURRENT_STATE.md`
2. `brain/canonico/DECISIONS.md` (decisões sobre uso do brain e disciplina de trabalho)
3. `brain/canonico/NEXT_ACTIONS.md`
4. Issues conhecidas: **não existe `brain/canonico/KNOWN_ISSUES.md` neste repositório.** O registro vigente de problemas abertos é `brain/audits/2026-06-13-system-audit.md`, complementado pelos handoffs em `brain/handoffs/`.
5. ADRs em `docs/DECISIONS.md` (seções `## ADR-NN`) conforme a tarefa. O roteamento por área está em `brain/context/00_INDEX.md`, que aponta os ADRs relevantes para cada tipo de mudança.

A hierarquia de confiança e o checklist de encerramento definidos em `brain/CLAUDE.md` valem integralmente, incluindo a ordem de leitura completa, o Gate Set e a regra de que **código e testes são a verdade factual quando divergirem do brain**. Em conflito entre documentos, o canônico vence; se o brain divergir do código, investigue e atualize o brain no encerramento.

### Ciclo de uma correção

O prompt de correção e o prompt de auditoria pertencem ao mesmo item, mas **não entram ao mesmo tempo**. A auditoria só chega depois que o Executor entregou o resultado — o Auditor precisa chegar sem ter visto o gabarito. Nunca receba nem repasse os dois prompts na mesma etapa.

1. O humano entrega ao Interlocutor **apenas o prompt de correção** daquele item. O prompt de auditoria fica retido com o humano até o passo 4.
2. Antes de acionar o Executor, o Interlocutor produz a **observação de contexto**: até 10 linhas de estado real + as leis e ADRs que a correção precisa respeitar + o que não pode ser tocado.
3. Aciona o subagente `executor` com o prompt de correção mais a observação. O Executor devolve o pacote de resultado.
4. **Só agora** o humano entrega o prompt de auditoria. O Interlocutor o encaminha ao subagente `auditor` junto do pacote de resultado e da observação — **sem o caminho da correção**.
5. Veredito REPROVADO volta ao Executor pela mão do Interlocutor, sem prompt novo. Veredito APROVADO fecha com o checklist de encerramento.

O Interlocutor não escreve código nem audita: lê o brain, contextualiza, encaminha e fecha.

### Checklist de encerramento

Vale o checklist de `brain/CLAUDE.md`, mais: atualizar o estado canônico (`brain/canonico/CURRENT_STATE.md`), registrar a decisão (ADR novo em `docs/DECISIONS.md` e reflexo em `brain/canonico/DECISIONS.md` quando impactar arquitetura, dados, segurança ou operação), atualizar `brain/canonico/NEXT_ACTIONS.md` quando a fila mudar, registrar o que ficou aberto — em `brain/audits/` ou num handoff em `brain/handoffs/`, já que não há arquivo de issues conhecidas — e rodar os gates da superfície tocada, marcando como `PENDING` qualquer gate não executado. Mudança só de brain entra em commit `docs:`.

### Cinco leis inegociáveis

- **SEC** — token só em memória no front; nenhum segredo no Git.
- **TEST** — teste é evidência; teste desabilitado para passar é violação.
- **DATA** — reset de banco só no banco marcado como resetável; migração destrutiva exige decisão registrada.
- **GIT** — commits pequenos e rastreáveis; mudança só de brain como `docs:`; sem force push; nunca trabalhar direto em `main`.
- **Escopo** — um repositório por sessão; nunca outro projeto.

### Comunicação

Os três agentes são diretos ao ponto. Não narram execução em tempo real, não pedem aprovação a cada passo, não gastam token descrevendo o processo. Entregam resultado, evidência e próximo passo.
