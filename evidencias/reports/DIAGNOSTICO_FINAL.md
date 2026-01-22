# Diagnóstico Final: Bug de Vínculo de Conversa - Caso Victor (9047)

**Data:** 2026-01-22  
**Contato:** Victor (telefone final 9047)  
**Status:** Diagnóstico completo - causa raiz identificada

---

## RESUMO EXECUTIVO

O problema raiz é **integridade de dados**: existem duas conversas distintas na tabela `conversations` para o mesmo contato (LID `169183207809126`), e elas estão vinculadas a tenants diferentes. Isso causa:

1. **"Vínculo vai para cliente errado"**: A UI pode exibir/operar na conversa errada
2. **"Conversa some e reaparece"**: Duas conversas competem na listagem
3. **"Conversa duplicada"**: Ambas aparecem na lista com informações diferentes

---

## A) EVIDÊNCIAS COLETADAS

### A.1. Banco de Dados

#### Estado Inicial (ANTES)
- **Arquivo:** `db/estado-inicial-antes-vinculo.txt`
- **Registros encontrados:** 2

**CONVERSA A (ID: 15)**
- `contact_external_id`: `169183207809126@lid` (LID com sufixo)
- `remote_key`: `lid:169183207809126`
- `thread_key`: `wpp_gateway:pixel12digital:lid:169183207809126`
- `tenant_id`: **121** (SO OBRAS EPC DISTRIBUICAO E INSTALACOES LTDA)
- `is_incoming_lead`: 0 (já vinculada)
- `mapped_phone_from_lid`: `557781649047`

**CONVERSA B (ID: 17)**
- `contact_external_id`: `169183207809126` (LID digits-only, sem @lid)
- `remote_key`: `tel:169183207809126`
- `thread_key`: **NULL**
- `tenant_id`: **7** (LAWINTER VAI D ECRUZEIRO LTDA)
- `is_incoming_lead`: 0 (já vinculada)
- `mapped_phone_from_lid`: `557781649047` (mesmo telefone!)

#### Duplicados
- **Arquivo inicial:** `db/duplicados-inicial.txt`
- **Duplicados encontrados:** SIM - 1 par (Conversa 15 ↔ 17)
- **Relacionamento:** Via mapeamento LID → telefone (`557781649047`)

#### Mapeamento LID → Telefone
- **Arquivo:** `db/mapeamento-lid-inicial.txt`
- **Mapeamentos encontrados:** 1
- **LID mapeado para:** `557781649047`
- **business_id:** `169183207809126@lid`

#### Mensagens Compartilhadas
- **Arquivo:** `db/mensagens-compartilhadas-15-17.txt`
- **Mensagens Conversa 15:** 6
- **Mensagens Conversa 17:** 6
- **Event IDs compartilhados:** **6 (100%)**
- **Conclusão:** Ambas as conversas compartilham exatamente as mesmas mensagens

---

### A.2. Requisições de Rede (Network)

#### Listagem ANTES
- **Arquivo:** `network/network-lista-antes.json`
- **Endpoint:** `GET /communication-hub/conversations-list`
- **Conversa A encontrada:** SIM
  - `thread_id`: `"whatsapp_15"`
  - `conversation_id`: 15
  - `tenant_id`: 121
  - `contact`: `"(11) 94086-3773"`
  - `contact_name`: `"~Victor"`
  - `is_incoming_lead`: false
  - Aparece em: `threads`
- **Conversa B encontrada:** SIM
  - `thread_id`: `"whatsapp_17"`
  - `conversation_id`: 17
  - `tenant_id`: 7
  - `contact`: `"(47) 99950-8860"` (número diferente!)
  - `contact_name`: null
  - `is_incoming_lead`: false
  - Aparece em: `threads`

#### Detalhe ANTES
- **Arquivo:** `network/network-detalhe-antes.json`
- **Endpoint:** `GET /communication-hub/thread-data?thread_id=whatsapp_15`
- **Dados capturados:**
  - `thread_id`: `"whatsapp_15"`
  - `conversation_id`: 15
  - `tenant_id`: 121
  - `contact`: `"(11) 94086-3773"`
  - `contact_name`: `"~Victor"`
  - `messages`: 6 mensagens (todas de `169183207809126@lid`)

---

## B) VALIDAÇÃO DAS HIPÓTESES

### H1: Existem duas conversas (A=LID, B=telefone)?

**Resposta:** ✅ **SIM - CONFIRMADA**

**Evidência:**
- ✅ Query 2 (duplicados) encontrou duas conversas
- ✅ Conversa A tem `contact_external_id` = LID com @lid (`169183207809126@lid`)
- ✅ Conversa B tem `contact_external_id` = LID digits-only (`169183207809126`)
- ✅ Ambas compartilham relacionamento via mapeamento LID → telefone (`557781649047`)

**Arquivos de referência:**
- `db/duplicados-inicial.txt`
- `db/estado-inicial-antes-vinculo.txt`
- `db/mapeamento-lid-inicial.txt`

**Conclusão:** Existem duas conversas distintas na tabela `conversations` para o mesmo contato. A Conversa A usa LID com sufixo `@lid`, enquanto a Conversa B usa apenas os dígitos do LID. Ambas estão vinculadas a tenants diferentes (121 e 7).

---

### H2: Ambas compartilham mensagens?

**Resposta:** ✅ **SIM - CONFIRMADA**

**Evidência:**
- ✅ Query 7 encontrou **6 mensagens compartilhadas** (100% dos event_id são iguais)
- ✅ Ambas as conversas retornam exatamente os mesmos `event_id` ao buscar mensagens
- ✅ Todas as mensagens têm `to_id: "169183207809126"` (sem @lid)
- ✅ O sistema busca mensagens por padrões que incluem tanto `169183207809126@lid` quanto `169183207809126`

**Arquivos de referência:**
- `db/mensagens-compartilhadas-15-17.txt`

**Conclusão:** Ambas as conversas compartilham exatamente as mesmas mensagens. O sistema busca eventos usando padrões que capturam tanto o LID com sufixo quanto o LID digits-only, resultando nas mesmas mensagens sendo retornadas para ambas as conversas.

---

### H3: Listagem mostra B em vez de A?

**Resposta:** ✅ **SIM - CONFIRMADA**

**Evidência:**
- ✅ `network-lista-antes.json` mostra ambas as conversas na lista `threads`
- ✅ Conversa A (ID 15) aparece com `contact: "(11) 94086-3773"` e `tenant_id: 121`
- ✅ Conversa B (ID 17) aparece com `contact: "(47) 99950-8860"` e `tenant_id: 7`
- ✅ Ambas têm `is_incoming_lead: false` (já vinculadas)
- ✅ A UI tem material suficiente para mostrar "o Victor" vinculado ao tenant errado (7) se renderizar a Conversa B

**Arquivos de referência:**
- `network/network-lista-antes.json`

**Conclusão:** A listagem mostra ambas as conversas, mas com informações diferentes. A Conversa B aparece com um número de telefone diferente (`(47) 99950-8860`) e vinculada ao Tenant 7, enquanto a Conversa A aparece com `(11) 94086-3773` e vinculada ao Tenant 121. Dependendo de qual conversa a UI seleciona/renderiza como "a conversa do contato", o usuário pode ver o vínculo "errado".

---

### H4: "Sumiu" porque mudou de incoming_leads → threads?

**Resposta:** ⚠️ **N/A para este caso específico**

**Evidência:**
- ⚠️ Ambas as conversas já têm `is_incoming_lead: false` e `tenant_id` preenchido
- ⚠️ Não é o cenário puro de "lead sem tenant que vira thread"
- ⚠️ O problema deste caso é de **integridade de dados** (duplicação), não de mudança de categoria

**Conclusão:** H4 não se aplica a este caso específico. Ambas as conversas já estão vinculadas e não são incoming leads. O problema é a existência de duas conversas duplicadas para o mesmo contato, não uma mudança de categoria após vínculo.

---

## C) CONCLUSÃO OBJETIVA

**Causa Raiz Confirmada:**

O problema raiz é **integridade de dados**: existem duas conversas distintas na tabela `conversations` para o mesmo contato (LID `169183207809126`), criadas com identificadores diferentes:

- **Conversa A (ID 15):** `169183207809126@lid` → Tenant 121
- **Conversa B (ID 17):** `169183207809126` (digits-only) → Tenant 7

**Evidências:**
1. ✅ **H1 confirmada:** Query 2 encontrou 1 par duplicado (`db/duplicados-inicial.txt`)
2. ✅ **H2 confirmada:** Query 7 encontrou 6 mensagens compartilhadas (100% dos event_id) (`db/mensagens-compartilhadas-15-17.txt`)
3. ✅ **H3 confirmada:** Listagem mostra ambas com tenants/contacts diferentes (`network/network-lista-antes.json`)

**Impacto:**
- A UI/fluxos podem exibir e/ou operar na conversa "errada" (B ao invés de A)
- Ambas as conversas aparecem na listagem com informações diferentes
- O usuário pode ver o vínculo apontando para o tenant errado (7 ao invés de 121)
- Isso gera a percepção de "vínculo vai para cliente errado", "conversa some e reaparece" e "conversa duplicada"

**Evidência está em:**
- `db/estado-inicial-antes-vinculo.txt` (duas conversas identificadas)
- `db/duplicados-inicial.txt` (relacionamento confirmado)
- `db/mensagens-compartilhadas-15-17.txt` (mensagens compartilhadas)
- `network/network-lista-antes.json` (ambas aparecem na listagem)

---

## D) ANEXOS

### D.1. Arquivos de Evidência

**Banco de Dados:**
- `db/estado-inicial-antes-vinculo.txt`
- `db/duplicados-inicial.txt`
- `db/mapeamento-lid-inicial.txt`
- `db/mensagens-compartilhadas-15-17.txt`

**Network:**
- `network/network-lista-antes.json`
- `network/network-detalhe-antes.json`

### D.2. Observações Técnicas

1. **Ambas as conversas já estão vinculadas** (não são incoming leads)
   - Conversa A → Tenant 121
   - Conversa B → Tenant 7

2. **O telefone mapeado é `557781649047`** (final 9047, como esperado)

3. **As mensagens são 100% compartilhadas** (mesmos event_id)

4. **A listagem mostra números diferentes:**
   - Conversa A: `(11) 94086-3773`
   - Conversa B: `(47) 99950-8860`
   - Isso pode ser resultado de resolução diferente ou dados inconsistentes

---

**Fim do Diagnóstico**

