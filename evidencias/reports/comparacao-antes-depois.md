# Comparação: Estado ANTES vs DEPOIS do Vínculo

**Data do Diagnóstico:** [PREENCHER]  
**Contato:** Victor (telefone final 9047)  
**Tenant Selecionado:** [PREENCHER - ID e Nome]

---

## A) TABELA DE COMPARAÇÃO

| Campo | Antes | Depois | Mudou? | Observações |
|-------|-------|--------|--------|-------------|
| **CONVERSA A (LID com @lid)** | | | | |
| conversation_id_A | 15 | [PREENCHER] | [SIM/NÃO] | |
| contact_external_id_A | 169183207809126@lid | [PREENCHER] | [SIM/NÃO] | |
| remote_key_A | lid:169183207809126 | [PREENCHER] | [SIM/NÃO] | |
| thread_key_A | wpp_gateway:pixel12digital:lid:169183207809126 | [PREENCHER] | [SIM/NÃO] | |
| tenant_id_A | 121 | [PREENCHER] | [SIM/NÃO] | |
| tenant_name_A | SO OBRAS EPC... | [PREENCHER] | [SIM/NÃO] | |
| is_incoming_lead_A | 0 | [PREENCHER] | [SIM/NÃO] | |
| updated_at_A | 2026-01-22 14:25:29 | [PREENCHER] | [SIM/NÃO] | |
| **CONVERSA B (LID digits-only)** | | | | |
| Existe conversa B? | SIM (ID: 17) | [SIM/NÃO] | [SIM/NÃO] | |
| conversation_id_B | 17 | [PREENCHER/N/A] | [SIM/NÃO] | |
| contact_external_id_B | 169183207809126 | [PREENCHER/N/A] | [SIM/NÃO] | |
| remote_key_B | tel:169183207809126 | [PREENCHER/N/A] | [SIM/NÃO] | |
| thread_key_B | NULL | [PREENCHER/N/A] | [SIM/NÃO] | |
| tenant_id_B | 7 | [PREENCHER/N/A] | [SIM/NÃO] | |
| tenant_name_B | LAWINTER VAI D... | [PREENCHER/N/A] | [SIM/NÃO] | |
| is_incoming_lead_B | 0 | [PREENCHER/N/A] | [SIM/NÃO] | |
| updated_at_B | 2026-01-22 14:23:11 | [PREENCHER/N/A] | [SIM/NÃO] | |

---

## B) EVIDÊNCIAS COLETADAS

### B.1. Banco de Dados

#### Estado Inicial (ANTES)
- **Arquivo:** `db/estado-inicial-antes-vinculo.txt`
- **Registros encontrados:** 2
- **Conversa A identificada:** SIM
  - conversation_id: 15
  - contact_external_id: 169183207809126@lid
  - remote_key: lid:169183207809126
  - tenant_id: 121

#### Estado Final (DEPOIS)
- **Arquivo:** `db/estado-final-depois-vinculo.txt`
- **Registros encontrados:** [PREENCHER]
- **Conversa A após vínculo:**
  - conversation_id: [PREENCHER]
  - tenant_id: [PREENCHER]
  - is_incoming_lead: [0/1]
  - updated_at: [PREENCHER]

#### Duplicados
- **Arquivo inicial:** `db/duplicados-inicial.txt`
- **Arquivo final:** `db/duplicados-final.txt`
- **Duplicados encontrados ANTES:** SIM - 1 par (Conversa 15 ↔ 17)
- **Duplicados encontrados DEPOIS:** [SIM/NÃO] - [PREENCHER quantidade]
- **Relacionamento:** Via mapeamento LID → telefone (557781649047)

#### Conflitos de Vínculo
- **Arquivo:** `db/conflitos-vinculo.txt`
- **Conflitos encontrados:** [SIM/NÃO]
- **Detalhes:** [PREENCHER se houver conflitos]

#### Mapeamento LID → Telefone
- **Arquivo:** `db/mapeamento-lid-inicial.txt`
- **Mapeamentos encontrados:** 1
- **LID mapeado para:** 557781649047

#### Histórico de Atualizações
- **Arquivo:** `db/historico-atualizacoes.txt`
- **Conversas atualizadas nas últimas 24h:** [PREENCHER quantidade]
- **Conversa A aparece no histórico?** [SIM/NÃO]

---

### B.2. Requisições de Rede (Network)

#### Listagem ANTES
- **Arquivo:** `network/network-lista-antes.json`
- **Endpoint:** `GET /communication-hub/conversations-list`
- **Conversa A encontrada:** [SIM/NÃO]
  - thread_id: [PREENCHER]
  - conversation_id: [PREENCHER]
  - tenant_id: [PREENCHER]
  - is_incoming_lead: [true/false]
  - Aparece em: [threads/incoming_leads]

#### Detalhe ANTES
- **Arquivo:** `network/network-detalhe-antes.json`
- **Endpoint:** `GET /communication-hub/thread-info?thread_id=whatsapp_[ID]`
- **Dados capturados:** [PREENCHER resumo]

#### Requisição de Vínculo
- **Request:** `network/network-vinculo-request.json`
  - conversation_id: [PREENCHER]
  - tenant_id: [PREENCHER]
- **Response:** `network/network-vinculo-response.json`
  - success: [true/false]
  - tenant_id: [PREENCHER]
  - conversation_id: [PREENCHER]
  - message: [PREENCHER]

#### Listagem DEPOIS
- **Arquivo:** `network/network-lista-depois.json`
- **Endpoint:** `GET /communication-hub/conversations-list`
- **Conversa A encontrada:** [SIM/NÃO]
  - thread_id: [PREENCHER]
  - conversation_id: [PREENCHER]
  - tenant_id: [PREENCHER]
  - is_incoming_lead: [true/false]
  - Aparece em: [threads/incoming_leads]
- **Conversa B encontrada:** [SIM/NÃO]
  - thread_id: [PREENCHER]
  - conversation_id: [PREENCHER]
  - tenant_id: [PREENCHER]

---

## C) VALIDAÇÃO DAS HIPÓTESES

### H1: Existem duas conversas (A=LID, B=telefone)?

**Resposta:** [SIM/NÃO]

**Evidência:**
- [ ] Query 2 (duplicados) encontrou duas conversas
- [ ] Conversa A tem `contact_external_id` = LID com @lid (`169183207809126@lid`)
- [ ] Conversa B tem `contact_external_id` = LID digits-only (`169183207809126`)
- [ ] Ambas compartilham relacionamento via mapeamento LID → telefone (`557781649047`)

**Arquivos de referência:**
- `db/duplicados-inicial.txt` ou `db/duplicados-final.txt`
- `db/mapeamento-lid-inicial.txt`

**Conclusão:** [PREENCHER - 1 parágrafo explicando]

---

### H2: Ambas compartilham mensagens?

**Resposta:** [SIM/NÃO]

**Evidência:**
- [ ] Ambas têm `mapped_phone_from_lid` = `557781649047`
- [ ] Ambas compartilham relacionamento via `whatsapp_business_ids`
- [ ] O endpoint de mensagens busca por `contact_external_id` e variações (incluindo @lid mapeado)

**Arquivos de referência:**
- `db/estado-inicial-antes-vinculo.txt` (ambas têm mesmo `mapped_phone_from_lid`)
- Análise do código: `CommunicationHubController::getWhatsAppMessagesFromConversation()`

**Conclusão:** [PREENCHER - 1 parágrafo explicando]

---

### H3: Listagem mostra B em vez de A?

**Resposta:** [SIM/NÃO]

**Evidência:**
- [ ] `network-lista-depois.json` mostra conversa B com `tenant_id` diferente do selecionado?
- [ ] Conversa B tem número visível (E.164), Conversa A tem LID
- [ ] A listagem prioriza exibição por número resolvido?
- [ ] O `thread_id` mostrado na UI corresponde à Conversa B, não à A?

**Arquivos de referência:**
- `network/network-lista-depois.json`
- `db/estado-final-depois-vinculo.txt`

**Conclusão:** [PREENCHER - 1 parágrafo explicando]

---

### H4: "Sumiu" porque mudou de incoming_leads → threads?

**Resposta:** [SIM/NÃO]

**Evidência:**
- [ ] `network-lista-antes.json` mostra conversa A em `incoming_leads`?
- [ ] `network-lista-depois.json` mostra conversa A em `threads` (ou não aparece)?
- [ ] `is_incoming_lead` mudou de 1 para 0?
- [ ] A conversa não "sumiu", apenas mudou de categoria?

**Arquivos de referência:**
- `network/network-lista-antes.json`
- `network/network-lista-depois.json`
- `db/estado-final-depois-vinculo.txt`

**Conclusão:** [PREENCHER - 1 parágrafo explicando]

---

## D) CONCLUSÃO OBJETIVA

[PREENCHER - 1 parágrafo resumindo a causa raiz confirmada com dados]

**Estrutura sugerida:**
- O vínculo atualiza a Conversa A corretamente (tenant_id, is_incoming_lead)
- Porém, [explicar o problema identificado]
- Isso causa [explicar o comportamento observado]
- A evidência está em [referenciar arquivos específicos]

**Exemplo baseado no que já sabemos:**
- Existem duas conversas (A=LID com @lid, B=LID digits-only) que compartilham o mesmo telefone mapeado (`557781649047`)
- Ambas já estão vinculadas a tenants diferentes (A→121, B→7)
- O vínculo atualiza apenas a Conversa A, mas a Conversa B continua vinculada ao Tenant 7
- A listagem pode mostrar a Conversa B (por ter número visível) ao invés da Conversa A
- Isso causa a percepção de "vínculo incorreto" e "conversa duplicada"

---

**Fim do Relatório**

