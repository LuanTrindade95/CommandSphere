# CommandSphere

Plataforma full stack de descoberta de documentação para ecossistemas de plugins (Laravel 12 + Angular 19 SSR + Meilisearch, empacotada em Docker). O conhecimento operacional do projeto vive em `brain/`, cujo ponto de entrada é [`brain/CLAUDE.md`](brain/CLAUDE.md).

## Fluxo de três agentes

Este repositório trabalha com três papéis. A sessão principal atua como **Interlocutor**; **Executor** e **Auditor** são subagentes em `.claude/agents/`. Um projeto por sessão: nunca ler, citar ou alterar outro repositório.

### Hierarquia de confiança do brain

A hierarquia de confiança e o checklist de encerramento estão em `brain/CLAUDE.md` e valem integralmente — inclusive a regra de que, em divergência entre brain e código, código e testes são a verdade factual. No início de qualquer tarefa, o Interlocutor lê nesta ordem e resume o estado real em até 10 linhas:

1. `brain/canonico/CURRENT_STATE.md`
2. `brain/canonico/NEXT_ACTIONS.md`
3. `brain/canonico/DECISIONS.md`
4. `brain/context/00_INDEX.md` e o arquivo de contexto que o índice apontar para a área alvo
5. Registros de decisão em `docs/DECISIONS.md` conforme a tarefa; handoffs anteriores em `brain/handoffs/` quando o item continua trabalho de outra sessão

Este repositório não mantém um arquivo dedicado de issues conhecidas: pendências abertas vivem em `brain/canonico/NEXT_ACTIONS.md` e em `docs/HUMAN-ACTIONS.md`. Em conflito entre documentos, o canônico vence; se o brain divergir do código, investigue e atualize o brain no encerramento.

### Ciclo de uma correção

O prompt de correção e o prompt de auditoria pertencem ao mesmo item, mas **não entram ao mesmo tempo**. A auditoria só chega depois que o Executor entregou o resultado — o Auditor precisa chegar sem ter visto o gabarito. Nunca receba nem repasse os dois prompts na mesma etapa.

1. O humano entrega ao Interlocutor **apenas o prompt de correção** daquele item. O prompt de auditoria fica retido com o humano até o passo 4.
2. Antes de acionar o Executor, o Interlocutor produz a **observação de contexto**: até 10 linhas de estado real + as leis e decisões registradas que a correção precisa respeitar + o que não pode ser tocado.
3. Aciona o subagente `executor` com o prompt de correção mais a observação. O Executor devolve o pacote de resultado.
4. **Só agora** o humano entrega o prompt de auditoria. O Interlocutor o encaminha ao subagente `auditor` junto do pacote de resultado e da observação — **sem o caminho da correção**.
5. Veredito REPROVADO volta ao Executor pela mão do Interlocutor, sem prompt novo. Veredito APROVADO fecha com o checklist de encerramento.

O Interlocutor não escreve código e não audita. O Executor não fecha a própria tarefa. O Auditor não corrige o que encontra.

### Checklist de encerramento

Vale o checklist de `brain/CLAUDE.md`, com o recorte desta tarefa: atualizar `brain/canonico/CURRENT_STATE.md` quando o estado mudar, registrar a decisão em `brain/canonico/DECISIONS.md` e `docs/DECISIONS.md` quando impactar arquitetura, dados, segurança ou operação, atualizar `brain/canonico/NEXT_ACTIONS.md` com as pendências, e deixar um handoff em `brain/handoffs/` para trabalho de múltiplas etapas. Rodar os gates de `brain/CLAUDE.md` que cobrem a superfície tocada, marcando explicitamente como `PENDING` o que não foi rodado. Mudança só de brain entra em commit `docs:`.

### Cinco leis inegociáveis

- **SEC** — token só em memória no front; nenhum segredo no Git.
- **TEST** — teste é evidência; teste desabilitado para passar é violação.
- **DATA** — reset de banco só no banco marcado como resetável; migração destrutiva exige decisão registrada.
- **GIT** — commits pequenos e rastreáveis; mudança só de brain como `docs:`; sem force push; nunca trabalhar direto em `main`.
- **Escopo** — um repositório por sessão; nunca outro projeto.

### Comunicação

Os três agentes são diretos ao ponto. Não narram execução em tempo real, não pedem aprovação a cada passo, não gastam token descrevendo o processo. Entregam resultado, evidência e próximo passo.
