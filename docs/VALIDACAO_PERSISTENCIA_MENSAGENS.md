# Validação de Persistência e Mapeamento de Threads

**Data:** 2026-01-14  
**Objetivo:** Validar persistência de mensagens e corrigir mapeamento de threads

---

## Scripts Criados

### 1. `database/validate-messages-persistence.php`

**Objetivo:** Validar se as mensagens 4699 e 4223 (15:24–15:27) existem na tabela `communication_events`.

**Uso:**
```bash
php database/validate-messages-persistence.php
```

**O que faz:**
- Busca mensagens por event_id (4699, 4223)
- Busca mensagens no período 15:24-15:27
- Para cada mensagem encontrada, anota:
  - `thread_id` (derivado da conversation relacionada)
  - `created_at`
  - `event_id`
  - `contact_phone/from/to` (extraído do payload JSON)

**Saída esperada:**
- Lista de mensagens encontradas com todos os dados relevantes
- Thread ID calculado (whatsapp_{conversation_id})
- Se não encontrar, indica possíveis causas

---

### 2. `database/validate-thread-mapping.php`

**Objetivo:** Comparar thread_id retornado nas mensagens vs thread_id que o frontend está abrindo.

**Uso:**
```bash
php database/validate-thread-mapping.php
```

**O que faz:**
- Para cada thread_id do frontend (whatsapp_34, whatsapp_35):
  - Busca a conversation correspondente
  - Busca mensagens relacionadas a essa conversation
  - Verifica se há outras conversations com o mesmo contato (duplicatas)
  - Compara contact_external_id da conversation com os contatos das mensagens

**Saída esperada:**
- Dados da conversation (ID, contact_external_id, tenant_id)
- Lista de mensagens encontradas para essa conversation
- Alertas se houver conversations duplicadas
- Se divergir, identifica a causa raiz

---

### 3. `database/test-messages-check-endpoint.php`

**Objetivo:** Reproduzir localmente o endpoint `/messages/check` usando exatamente os params do console.

**Uso:**
```bash
php database/test-messages-check-endpoint.php
```

**O que faz:**
- Simula o método `checkNewMessages()` do backend
- Testa com diferentes combinações de parâmetros:
  - `thread_id=whatsapp_34`, `after_timestamp=2026-01-14 13:57:50`
  - `thread_id=whatsapp_35`, `after_timestamp=2026-01-14 13:57:50`
  - `thread_id=whatsapp_34`, `after_timestamp=2026-01-14 15:20:00` (período das mensagens)
- Mostra:
  - COUNT(*) total da query
  - Events encontrados
  - Condição exata aplicada

**Saída esperada:**
- COUNT(*) para cada teste
- Lista de eventos encontrados
- Se COUNT>0 e has_new=false, identifica o problema

---

## Instrumentação do Backend

### Arquivo: `src/Controllers/CommunicationHubController.php`

**Método:** `checkNewMessages()` (linhas ~1611-1624)

**Mudanças:**
- Adicionado log de COUNT(*) total antes de buscar eventos
- Adicionado log da condição exata aplicada (thread_id, after_timestamp, after_event_id, normalized_contact)
- Logs agora mostram:
  - `COUNT(*) TOTAL: X`
  - `CONDICAO EXATA: thread_id=..., after_timestamp=..., after_event_id=..., normalized_contact=...`
  - `QUERY RETORNOU: events_count=X, COUNT_TOTAL=Y`

**Como verificar:**
1. Acesse o painel de comunicação
2. Abra uma conversa (ex: whatsapp_34)
3. Verifique os logs do servidor (error_log)
4. Procure por `[LOG TEMPORARIO] CommunicationHub::checkNewMessages()`

**Exemplo de log esperado:**
```
[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - INICIADO: thread_id=whatsapp_34, after_timestamp=2026-01-14 13:57:50, after_event_id=NULL
[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - CONVERSA: conversation_id=34, contact_external_id=554796164699@c.us, tenant_id=2
[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - NORMALIZACAO: contact_external_id_original=554796164699@c.us, normalized=554796164699
[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - COUNT(*) TOTAL: 5
[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - CONDICAO EXATA: thread_id=whatsapp_34, after_timestamp=2026-01-14 13:57:50, after_event_id=NULL, normalized_contact=554796164699
[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - QUERY RETORNOU: events_count=5, COUNT_TOTAL=5
```

---

## Correções na UI

### Arquivo: `views/communication_hub/index.php`

**Função:** `loadConversation()` (linhas ~1327-1396)

**Mudanças:**
1. **Reset completo de estado:**
   - Limpa `ConversationState.messageIds` antes de carregar nova conversa
   - Reseta `lastTimestamp` e `lastEventId` para `null`
   - Adicionado log `[LOG TEMPORARIO]` para rastrear reset

2. **Inicialização de markers:**
   - `initializeConversationMarkers()` agora limpa `messageIds` antes de adicionar novos
   - Garante que markers sejam baseados no último item renderizado da conversa atual
   - Adicionado log para rastrear inicialização

**Comportamento garantido:**
- Ao clicar em conversa da lista:
  - ✅ Ativa thread_id do item clicado
  - ✅ Reseta markers (lastTimestamp/lastEventId) baseado no último item renderizado
  - ✅ Inicia polling nessa thread e para a anterior sem preservar estado errado

---

## Como Executar a Validação Completa

### Passo 1: Validar Persistência
```bash
php database/validate-messages-persistence.php
```

**Anote:**
- Se as mensagens 4699 e 4223 existem
- Thread_id de cada mensagem
- Contact/from/to de cada mensagem

### Passo 2: Validar Mapeamento de Thread
```bash
php database/validate-thread-mapping.php
```

**Anote:**
- Se o thread_id das mensagens diverge do thread_id do frontend
- Se há conversations duplicadas

### Passo 3: Testar Endpoint Check
```bash
php database/test-messages-check-endpoint.php
```

**Anote:**
- COUNT(*) para cada teste
- Se COUNT>0 mas has_new=false (problema na lógica)

### Passo 4: Verificar Logs do Backend

1. Acesse o painel de comunicação no navegador
2. Abra uma conversa (ex: whatsapp_34)
3. Verifique os logs do servidor:
   ```bash
   tail -f /caminho/para/logs/error.log | grep "LOG TEMPORARIO"
   ```

**Anote:**
- COUNT(*) retornado pelo backend
- Condição exata aplicada
- Se COUNT>0 e has_new=false, há problema na lógica

---

## Entregáveis Esperados

1. **Prints das queries (resultados):**
   - Resultado de `validate-messages-persistence.php`
   - Resultado de `validate-thread-mapping.php`
   - Resultado de `test-messages-check-endpoint.php`

2. **Trecho de log do backend com COUNT do check:**
   - Logs de `checkNewMessages()` para thread 34
   - Logs de `checkNewMessages()` para thread 35
   - Especialmente o COUNT(*) total

3. **Análise:**
   - Se as mensagens existem no banco
   - Se o thread_id está correto
   - Se COUNT>0 mas has_new=false, qual é a causa

---

## Próximos Passos (se necessário)

Se após executar os scripts e verificar os logs:

1. **Se COUNT>0 e has_new=false:**
   - Corrigir lógica de filtro em `checkNewMessages()`
   - Verificar normalização de contato
   - Verificar filtro por tenant_id

2. **Se thread_id diverge:**
   - Verificar mapeamento em `resolveThreadToConversation()`
   - Verificar se há conversations duplicadas
   - Corrigir criação/resolução de conversations

3. **Se mensagens não existem:**
   - Verificar ingestão de eventos
   - Verificar se eventos foram processados
   - Verificar se há filtros que excluem as mensagens

