# Painel de Diagnóstico (Debug) - WhatsApp Gateway

**Localização:** Configurações → WhatsApp Gateway → **Diagnóstico (Debug)**

**URL:** `/settings/whatsapp-gateway/diagnostic`

---

## Objetivo

Centralizar testes, capturas e evidências para diagnóstico de mensagens do WhatsApp Gateway, eliminando a dependência de comandos soltos em terminal.

---

## Funcionalidades

### 1. Estado Atual do Gateway

**O que mostra:**
- URL do webhook configurado no PixelHub (ex: `/api/whatsapp/webhook`)
- Horário do servidor e timezone (para eliminar confusão de timestamps)
- Canais configurados e seus tenants associados

**Utilidade:**
- Verificar configuração atual sem acessar `.env`
- Confirmar timezone do servidor (importante para timestamps)
- Listar canais disponíveis para testes

---

### 2. Simulador de Webhook (POST)

**Funcionalidade:**
- Envia payload de webhook para o endpoint real do sistema (`/api/whatsapp/webhook`)
- Permite testar ingestão sem depender do gateway externo

**Templates disponíveis:**
- **Mensagem Recebida (inbound):** Simula mensagem recebida do WhatsApp
- **Mensagem Enviada (outbound):** Simula mensagem enviada
- **Status/ACK (message.ack):** Simula confirmação de entrega

**Campos editáveis:**
- `from` (obrigatório): Telefone de origem (ex: 554796164699)
- `to` (opcional): Telefone de destino
- `body`: Texto da mensagem
- `event_id` (opcional): ID do evento (gerado automaticamente se vazio)
- `channel_id`: Canal do gateway (padrão: Pixel12 Digital)

**Resultado:**
- Mostra resposta HTTP (status e body)
- Registra execução (se tabela `whatsapp_debug_executions` existir)

**Exemplo de uso:**
1. Selecione template "Mensagem Recebida"
2. Preencha `from`: `554796164699`
3. Preencha `body`: `Teste de diagnóstico`
4. Clique em "Enviar Webhook Simulado"
5. Verifique resultado e mensagem criada no banco

---

### 3. Últimas Mensagens e Threads (Consulta Rápida)

**Funcionalidade:**
- Consulta mensagens do banco sem usar terminal
- Filtros rápidos por telefone, thread_id e intervalo de tempo

**Filtros disponíveis:**
- **Telefone (contains):** Busca parcial (ex: `4699` encontra `554796164699`)
- **Thread ID:** Busca exata (ex: `whatsapp_34`, `whatsapp_35`)
- **Intervalo:** Últimos 15 min / 1 hora / 24 horas

**Colunas exibidas:**
- `ID`: ID numérico (PK) da mensagem
- `Created`: Data/hora de criação
- `Direction`: `inbound` ou `outbound`
- `Thread ID`: Thread associada (ex: `whatsapp_35`)
- `From`: Telefone de origem
- `To`: Telefone de destino
- `Event ID`: UUID do evento
- `Tenant`: ID do tenant

**Ações:**
- **Recarregar:** Atualiza lista com filtros aplicados
- **Copiar JSON:** Copia resultados em formato JSON para colar em auditoria/issue

**Exemplo de uso:**
1. Preencha `Telefone`: `4699`
2. Selecione `Intervalo`: `Últimos 15 min`
3. Clique em "Recarregar"
4. Verifique mensagens encontradas
5. Clique em "Copiar JSON" para documentar

---

### 4. Checklist de Teste

**Funcionalidade:**
- Fluxo guiado para verificar estado completo após envio de mensagem
- Gera relatório de evidência em Markdown

**Como usar:**
1. Envie uma mensagem no WhatsApp
2. Preencha `Telefone` (ex: `554796474223`)
3. Preencha `Thread ID` (opcional, ex: `whatsapp_34`)
4. Clique em "Capturar Agora"

**O que verifica (identifica exatamente onde está o problema):**

1. ✅ **Webhook recebido:** Se mensagem chegou no webhook (últimos 30s)
   - **Se FAIL:** Webhook não chegou → Problema no gateway (não enviou webhook ou falhou)
   
2. ✅ **Inserido:** Se mensagem foi inserida no banco
   - **Se FAIL:** Webhook chegou mas não inseriu → Problema na ingestão (EventIngestionService::ingest())
   
3. ✅ **Thread ID:** Se thread_id está correto
   - **Se FAIL:** Mensagem inserida mas thread_id errado → Problema de roteamento/mapeamento
   
4. ✅ **Conversation atualizada:** Se `last_message_at` e `unread_count` foram atualizados
   - **Se FAIL:** 
     - `last_message_at` não atualizado → ConversationService::updateConversationMetadata() não foi chamado
     - `unread_count` não incrementado → Badge não aparece
     - **Impacto:** Conversation não sobe ao topo da lista
   
5. ✅ **Check detecta:** Se `/messages/check` encontra a mensagem
   - **Se FAIL:** Frontend não sabe que há mensagens novas → Polling não funciona
   - **Causas possíveis:** Normalização de contato diferente, filtros incorretos
   
6. ✅ **New retorna:** Se `/messages/new` retorna a mensagem
   - **Se FAIL:** Mensagens não são carregadas no frontend → Thread não atualiza
   - **Causas possíveis:** Normalização de contato diferente, filtros incorretos
   
7. ✅ **Lista ordenada:** Se conversation está no topo da lista
   - **Se WARNING:** Conversation não está no topo → `last_message_at` não foi atualizado ou há conversations mais recentes
   - **Impacto:** Conversation não aparece primeiro na lista do frontend

**Relatório gerado:**
- Formato Markdown copiável
- Inclui timestamp, telefone, thread_id
- Status de cada check (OK/FAIL)
- IDs e contadores relevantes

**Exemplo de relatório:**
```markdown
# Relatório de Diagnóstico WhatsApp Gateway

**Timestamp:** 2026-01-14 15:44:38
**Telefone:** 554796474223
**Thread ID:** whatsapp_34

## Resultados dos Checks

### WEBHOOK_RECEIVED
- **Status:** ✅ OK
- **message_id:** 4663
- **event_id:** xxx-xxx-xxx
- **created_at:** 2026-01-14 15:44:38

### INSERTED
- **Status:** ✅ OK
- **id:** 4663

### THREAD_ID
- **Status:** ✅ OK
- **expected:** whatsapp_34
- **resolved:** whatsapp_34

### CHECK_DETECTS
- **Status:** ✅ OK
- **has_new:** true
- **count:** 1

### NEW_RETURNS
- **Status:** ✅ OK
- **messages_count:** 1
```

---

## Exemplos de Teste

### Teste ServPro (554796474223)

**Cenário:** Verificar se mensagem do ServPro está entrando no banco

**Passos:**
1. Acesse: Configurações → WhatsApp Gateway → Diagnóstico (Debug)
2. No bloco "Últimas Mensagens":
   - Telefone: `4223` (ou `554796474223`)
   - Intervalo: `Últimos 15 min`
   - Clique "Recarregar"
3. **Resultado esperado:**
   - Se mensagem aparece: ✅ Webhook chegou e inseriu
   - Se não aparece: ❌ Verificar logs do gateway

**Se não aparece:**
1. Use "Checklist de Teste":
   - Telefone: `554796474223`
   - Thread ID: `whatsapp_34`
   - Clique "Capturar Agora"
2. Verifique relatório:
   - Se `webhook_received = FAIL`: Webhook não chegou (problema no gateway)
   - Se `webhook_received = OK` mas `inserted = FAIL`: Problema na ingestão
   - Se `thread_id` está errado: Problema de roteamento

---

### Teste Charles (554796164699)

**Cenário:** Verificar se mensagem do Charles aparece no thread correto

**Passos:**
1. Acesse: Configurações → WhatsApp Gateway → Diagnóstico (Debug)
2. No bloco "Últimas Mensagens":
   - Telefone: `4699` (ou `554796164699`)
   - Thread ID: `whatsapp_35`
   - Intervalo: `Últimos 15 min`
   - Clique "Recarregar"
3. **Resultado esperado:**
   - Mensagem aparece com `thread_id = whatsapp_35`
   - `direction = inbound`
   - `created_at` recente

**Se thread_id está errado:**
1. Verifique conversation no banco:
   - Busque por `contact_external_id` contendo `554796164699`
   - Verifique se `tenant_id` está correto
2. Use "Simulador de Webhook" para testar:
   - Template: "Mensagem Recebida"
   - From: `554796164699`
   - Channel ID: Verifique qual canal está configurado
   - Envie e verifique thread_id gerado

---

## Endpoints Internos

### GET `/settings/whatsapp-gateway/diagnostic/messages`

**Parâmetros:**
- `phone` (opcional): Filtro por telefone (contains)
- `thread_id` (opcional): Filtro por thread_id exato
- `interval` (opcional): `15min`, `1h`, `24h` (padrão: `15min`)

**Resposta:**
```json
{
  "success": true,
  "messages": [
    {
      "message_id": 4663,
      "event_id": "xxx-xxx-xxx",
      "created_at": "2026-01-14 15:44:38",
      "direction": "inbound",
      "thread_id": "whatsapp_34",
      "from_contact": "554796474223",
      "to_contact": "554797309525",
      "tenant_id": 2
    }
  ],
  "count": 1
}
```

### POST `/settings/whatsapp-gateway/diagnostic/simulate-webhook`

**Body (FormData):**
- `template`: `inbound`, `outbound`, `ack`
- `from`: Telefone de origem (obrigatório)
- `to`: Telefone de destino (opcional)
- `body`: Texto da mensagem
- `event_id`: ID do evento (opcional)
- `channel_id`: ID do canal

**Resposta:**
```json
{
  "success": true,
  "http_status": 200,
  "response": {
    "success": true,
    "event_id": "xxx-xxx-xxx"
  },
  "payload_sent": {...},
  "timestamp": "2026-01-14 15:44:38"
}
```

### POST `/settings/whatsapp-gateway/diagnostic/checklist-capture`

**Body (FormData):**
- `phone`: Telefone (obrigatório se thread_id não fornecido)
- `thread_id`: Thread ID (obrigatório se phone não fornecido)

**Resposta:**
```json
{
  "success": true,
  "report": {
    "timestamp": "2026-01-14 15:44:38",
    "phone": "554796474223",
    "thread_id": "whatsapp_34",
    "checks": {
      "webhook_received": {
        "status": "OK",
        "message_id": 4663,
        "event_id": "xxx-xxx-xxx"
      },
      "inserted": {
        "status": "OK",
        "id": 4663
      },
      "thread_id": {
        "status": "OK",
        "expected": "whatsapp_34",
        "resolved": "whatsapp_34"
      },
      "check_detects": {
        "status": "OK",
        "has_new": true,
        "count": 1
      },
      "new_returns": {
        "status": "OK",
        "messages_count": 1
      }
    }
  }
}
```

---

## Como Identificar Exatamente Onde Está o Problema

### Fluxo Completo e Pontos de Falha

Quando uma mensagem **não aparece na thread, não mostra badge e não sobe ao topo**, o checklist identifica exatamente onde está o problema:

#### 1. **Webhook não chegou** (`webhook_received = FAIL`)
- **Problema:** Gateway não enviou webhook ou falhou
- **Ação:** Verificar logs do gateway, status da requisição POST, retries

#### 2. **Webhook chegou mas não inseriu** (`inserted = FAIL`)
- **Problema:** EventIngestionService::ingest() falhou ou foi rejeitado
- **Ação:** Verificar logs do webhook (WhatsAppWebhookController), dedupe, validações

#### 3. **Inseriu mas thread_id errado** (`thread_id = FAIL`)
- **Problema:** Roteamento/mapeamento incorreto (tenant_id, channel_id, normalização de contato)
- **Ação:** Verificar resolução de tenant por channel_id, normalização de contato

#### 4. **Conversation não atualizada** (`conversation_updated = FAIL`)
- **Problema:** ConversationService::updateConversationMetadata() não foi chamado ou falhou
- **Sintomas:**
  - `last_message_at` não atualizado → **Conversation não sobe ao topo**
  - `unread_count` não incrementado → **Badge não aparece**
- **Ação:** Verificar se EventIngestionService chama ConversationService após ingestão

#### 5. **Check não detecta** (`check_detects = FAIL`)
- **Problema:** Endpoint `/messages/check` não encontra mensagem
- **Sintomas:** Frontend não sabe que há mensagens novas → **Polling não funciona**
- **Causas:** Normalização de contato diferente, filtros incorretos, after_timestamp muito recente
- **Ação:** Verificar normalização de contato (deve bater com `contact_external_id` da conversation)

#### 6. **New não retorna** (`new_returns = FAIL`)
- **Problema:** Endpoint `/messages/new` não retorna mensagem
- **Sintomas:** Mensagens não são carregadas no frontend → **Thread não atualiza**
- **Causas:** Normalização de contato diferente, filtros incorretos
- **Ação:** Verificar normalização de contato e filtros do endpoint

#### 7. **Lista não ordenada** (`list_ordering = WARNING`)
- **Problema:** Conversation não está no topo da lista
- **Sintomas:** Conversation não aparece primeiro na lista do frontend
- **Causas:** `last_message_at` não foi atualizado ou há conversations mais recentes
- **Ação:** Verificar se `conversation_updated` está OK

### Exemplo de Diagnóstico Completo

**Cenário:** Mensagem do ServPro não aparece na thread, não mostra badge e não sobe ao topo

**Resultado do Checklist:**
```
✅ webhook_received: OK (mensagem chegou)
✅ inserted: OK (mensagem inserida)
✅ thread_id: OK (thread_id correto: whatsapp_34)
❌ conversation_updated: FAIL
   - last_message_at não atualizado
   - unread_count não incrementado
   - Diagnóstico: ConversationService::updateConversationMetadata() não foi chamado
✅ check_detects: OK
✅ new_returns: OK
⚠️ list_ordering: WARNING (posição 15 de 20)
```

**Conclusão:** O problema está em **ConversationService::updateConversationMetadata()** não ser chamado após a ingestão. Isso causa:
- ❌ Conversation não sobe ao topo (last_message_at não atualizado)
- ❌ Badge não aparece (unread_count não incrementado)
- ✅ Mensagens aparecem na thread (check e new funcionam)

**Ação:** Verificar se EventIngestionService chama ConversationService após ingestão bem-sucedida.

---

## Critério de Aceite

**Em 1 minuto, sem terminal, consigo provar:**

1. ✅ **"A msg chegou no webhook?"**
   - Use "Checklist de Teste" → Verifica `webhook_received`

2. ✅ **"Inseriu no banco?"**
   - Use "Últimas Mensagens" → Filtre por telefone
   - Ou use "Checklist de Teste" → Verifica `inserted`

3. ✅ **"Em qual thread caiu?"**
   - Use "Últimas Mensagens" → Coluna `Thread ID`
   - Ou use "Checklist de Teste" → Verifica `thread_id`

4. ✅ **"O /messages/check detecta?"**
   - Use "Checklist de Teste" → Verifica `check_detects`

5. ✅ **"O /messages/new retorna?"**
   - Use "Checklist de Teste" → Verifica `new_returns`

6. ✅ **"Conversation foi atualizada?"**
   - Use "Checklist de Teste" → Verifica `conversation_updated`
   - **Identifica se badge aparece e se sobe ao topo**

7. ✅ **"Conversation está no topo?"**
   - Use "Checklist de Teste" → Verifica `list_ordering`

---

## Próximos Passos (Futuro)

- [ ] Bloco "Correlação de eventos (timeline)" - visão cronológica
- [ ] Bloco "Logs instrumentados (viewer)" - viewer de logs do webhook
- [ ] Tabela `whatsapp_debug_logs` para persistir logs instrumentados
- [ ] Tabela `whatsapp_debug_executions` para histórico de simulações
- [ ] Export de relatórios em PDF/CSV

---

## Notas Técnicas

- **Read-only:** Todas as consultas são read-only (não alteram dados)
- **Simulador:** Única ação "ativa" - envia webhook real para o endpoint
- **Segurança:** Requer autenticação interna (`Auth::requireInternal()`)
- **Performance:** Limites aplicados (50 mensagens, 30s para checklist)

