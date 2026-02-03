# Proposta: Interrupção por outro tipo de bloco (ex.: Comercial durante Produção)

**Cenário:** Você está no bloco Produção 08:00–10:00, trabalhando no CFC. Surge reunião urgente do Comercial. Pausa CFC, faz a reunião, retorna ao CFC. O relatório e a agenda precisam mostrar essa “lacuna” Comercial dentro da janela de Produção.

**Restrição:** Nunca dois projetos/segmentos rodando ao mesmo tempo.

---

## Opção A: `tipo_id` no segmento (recomendada, menor impacto)

### Ideia
O segmento continua vinculado ao bloco atual (Produção), mas ganha um campo opcional `tipo_id` que indica o **tipo de trabalho** (Comercial, Suporte etc.). Quando preenchido, esse tipo é usado em relatórios e na agenda.

### Alterações

| Item | Ação |
|------|------|
| **agenda_block_segments** | Nova coluna `tipo_id` (INT NULL, FK agenda_block_types) |
| **startSegment** | Aceitar parâmetro opcional `tipo_id` |
| **UI** | Ao iniciar projeto, permitir escolher “Tipo de trabalho” (padrão = tipo do bloco) |
| **Relatório** | Agrupar por `COALESCE(segmento.tipo_id, bloco.tipo_id)` |
| **Agenda semanal** | No card do bloco, exibir “fatias” por segmento com tipo (ex.: CFC · Produção \| Reunião · Comercial \| CFC · Produção) |

### Fluxo
1. Bloco Produção 08–10, CFC rodando.
2. Reunião urgente → Pausar CFC.
3. Clicar “Iniciar trabalho de outro tipo” → escolher Comercial + projeto/tarefa.
4. Segmento criado com `block_id` = Produção, `tipo_id` = Comercial, `project_id` = projeto da reunião.
5. Ao terminar → Pausar segmento Comercial → Retomar CFC.
6. Relatório: Produção (CFC + PixelHub) e Comercial (reunião) separados.
7. Agenda: no card Produção 08–10, exibir as fatias com tipo.

### Vantagens
- Não exige blocos sobrepostos.
- Mantém regra de 1 bloco ongoing.
- Reaproveita estrutura atual.
- Relatório e agenda passam a refletir o tipo real do trabalho.

---

## Opção B: Bloco “avulso” sobreposto

### Ideia
Criar um bloco Comercial 08:45–09:15 sobreposto ao Produção. Exige permitir blocos com horários sobrepostos e mais de um bloco ongoing.

### Desvantagens
- Mudanças maiores (validações, modelo de dados).
- Risco de conflitos e duplicidade.
- Impacto alto no código atual.

---

## Recomendação: Opção A

Implementar `tipo_id` em `agenda_block_segments` e ajustar:

1. **Migration** – adicionar coluna `tipo_id` em `agenda_block_segments`.
2. **startSegment** – aceitar `tipo_id` opcional; se vazio, usar `tipo_id` do bloco.
3. **UI** – na tela do bloco, ao iniciar projeto, permitir selecionar “Tipo de trabalho” (padrão = tipo do bloco).
4. **Relatório** – usar `COALESCE(s.tipo_id, b.tipo_id)` para agrupar por tipo.
5. **Agenda semanal** – no card do bloco, listar segmentos com tipo e projeto.

### Critérios de aceite
- Pausar CFC → Iniciar Comercial (reunião) → Pausar → Retomar CFC.
- Relatório: tempo de Produção e de Comercial separados.
- Agenda: fatias Comercial visíveis dentro do card Produção 08–10.
- Regra mantida: nunca dois segmentos `running` ao mesmo tempo.
