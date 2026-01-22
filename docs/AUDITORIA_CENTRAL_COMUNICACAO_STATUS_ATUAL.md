# Auditoria: Central de Comunicação — Status Atual

**Data:** 2026-01-09  
**Versão:** 1.0  
**Objetivo:** Mapear o estado atual do sistema de comunicação do PixelHub para identificar o que existe, o que funciona e onde estão as limitações.

---

## 1. Pontos de Entrada (Ingress)

### 1.1 Endpoints de Webhook

#### ✅ `/api/whatsapp/webhook` (POST)

**Controller:** `src/Controllers/WhatsAppWebhookController.php`  
**Rota:** Definida em `public/index.php:416`

**Evidências:**
```php
// public/index.php:416
$router->post('/api/whatsapp/webhook', 'WhatsAppWebhookController@handle');
```

**Headers Esperados:**
- `X-Webhook-Secret` ou `X-Gateway-Secret` (opcional, se `PIXELHUB_WHATSAPP_WEBHOOK_SECRET` configurado)
- `Content-Type: application/json`

**Validação de Autenticidade:**
```php
// WhatsAppWebhookController.php:34-46
$expectedSecret = Env::get('PIXELHUB_WHATSAPP_WEBHOOK_SECRET');
if (!empty($expectedSecret)) {
    $secretHeader = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? $_SERVER['HTTP_X_GATEWAY_SECRET'] ?? null;
    if ($secretHeader !== $expectedSecret) {
        http_response_code(403);
        // Retorna erro
    }
}
```

**Payload Esperado:**
```json
{
  "event": "message" | "message.ack" | "connection.update",
  "channel": "Pixel12 Digital",
  "message": { ... },
  "session": { ... }
}
```

**Mapeamento de Eventos:**
```php
// WhatsAppWebhookController.php:169-178
private function mapEventType(string $gatewayEventType): ?string
{
    $mapping = [
        'message' => 'whatsapp.inbound.message',
        'message.ack' => 'whatsapp.delivery.ack',
        'connection.update' => 'whatsapp.connection.update',
    ];
    return $mapping[$gatewayEventType] ?? null;
}
```

**Idempotência:**
- ✅ Implementada via `EventIngestionService::ingest()`
- Usa `idempotency_key` calculada a partir de `source_system + event_type + external_id` (ou hash do payload)
- Verifica duplicatas antes de inserir

**Retries do Gateway:**
- ⚠️ **GAP:** Não há tratamento explícito de retries do gateway
- O endpoint sempre retorna HTTP 200 para eventos não mapeados (evita retry infinito)
- Não há rate limiting ou throttling

**Resolução de Tenant:**
```php
// WhatsAppWebhookController.php:186-205
private function resolveTenantByChannel(?string $channelId): ?int
{
    // Busca em tenant_message_channels
    // Retorna tenant_id ou null
}
```

#### ✅ `/webhook/asaas` (POST)

**Controller:** `src/Controllers/AsaasWebhookController.php`  
**Rota:** Definida em `public/index.php:413`

**Validação:**
- Token via header `HTTP_ASAAS_ACCESS_TOKEN` ou `HTTP_X_ASAAS_ACCESS_TOKEN`
- Compara com `ASAAS_WEBHOOK_TOKEN` do `.env`

**Persistência:**
- Logs em `asaas_webhook_logs`
- Processa eventos de pagamento

**Status:** ✅ Funcional, mas não integrado com sistema de comunicação centralizado

---

### 1.2 Outros Pontos de Entrada

#### ⚠️ Endpoint de Ingestão de Eventos Internos

**Rota:** Não encontrada no código atual  
**GAP:** Não existe endpoint `/api/events` para sistemas internos emitirem eventos

**Evidência:** `EventIngestionService` existe, mas não há controller público para ingestão

---

## 2. Pipeline Interno

### 2.1 Fluxo de Recebimento (WhatsApp)

```
WhatsApp → WPP Gateway → POST /api/whatsapp/webhook
    ↓
WhatsAppWebhookController::handle()
    ↓
Valida secret (se configurado)
    ↓
Mapeia evento (message → whatsapp.inbound.message)
    ↓
Resolve tenant_id pelo channel_id
    ↓
EventIngestionService::ingest()
    ↓
Verifica idempotência (idempotency_key)
    ↓
Insere em communication_events (status: 'queued')
    ↓
Retorna HTTP 200 com event_id
```

**Evidências:**
- `WhatsAppWebhookController.php:22-161`
- `EventIngestionService.php:26-162`

### 2.2 Persistência

#### ✅ Tabela: `communication_events`

**Migration:** `database/migrations/20250201_create_communication_events_table.php`

**Estrutura:**
```sql
CREATE TABLE communication_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(36) NOT NULL UNIQUE,           -- UUID
    idempotency_key VARCHAR(255) NOT NULL UNIQUE,   -- Para dedupe
    event_type VARCHAR(100) NOT NULL,               -- whatsapp.inbound.message, etc.
    source_system VARCHAR(50) NOT NULL,             -- wpp_gateway, asaas, etc.
    tenant_id INT UNSIGNED NULL,                    -- FK para tenants
    trace_id VARCHAR(36) NOT NULL,                  -- UUID para rastreamento
    correlation_id VARCHAR(36) NULL,                -- UUID para agrupar eventos
    payload JSON NOT NULL,                          -- Payload completo
    metadata JSON NULL,                             -- Metadados adicionais
    status VARCHAR(20) DEFAULT 'queued',            -- queued|processing|processed|failed
    processed_at DATETIME NULL,
    error_message TEXT NULL,
    retry_count INT UNSIGNED DEFAULT 0,
    max_retries INT UNSIGNED DEFAULT 3,
    next_retry_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Índices e foreign keys
)
```

**Status Atual:**
- ✅ Tabela existe e está funcional
- ✅ Idempotência implementada
- ✅ Suporte a retry (campos existem, mas processamento não está implementado)
- ⚠️ **GAP:** Não há worker/processo que processa eventos em `queued`

### 2.3 Fila / Event Bus

**Status:** ❌ **NÃO EXISTE**

**Evidências:**
- Não há sistema de fila (Redis, RabbitMQ, etc.)
- Não há workers assíncronos
- Eventos ficam em `status = 'queued'` indefinidamente
- Não há processamento automático de eventos

**GAP Crítico:** Sistema recebe eventos mas não os processa automaticamente

### 2.4 Normalização

#### ✅ `EventNormalizationService`

**Arquivo:** `src/Services/EventNormalizationService.php`

**Status:** ✅ Existe, mas não está sendo usado no fluxo atual

**Evidência:** `WhatsAppWebhookController` não chama normalização antes de ingerir

**GAP:** Normalização existe mas não está integrada no pipeline

### 2.5 Roteamento

#### ✅ `EventRouterService`

**Arquivo:** `src/Services/EventRouterService.php`

**Funcionalidades:**
- Busca regras em `routing_rules`
- Roteia para WhatsApp, Chat, Email (parcial)
- Atualiza status do evento

**Status:** ✅ Existe, mas não está sendo chamado automaticamente

**GAP:** Roteamento existe mas não é executado automaticamente após ingestão

### 2.6 Correlação

**Status:** ⚠️ **PARCIAL**

**Campos Existentes:**
- ✅ `trace_id` — UUID para rastrear fluxo completo
- ✅ `correlation_id` — UUID para agrupar eventos relacionados
- ✅ `tenant_id` — Vincula ao tenant

**GAP:**
- ❌ Não há tabela `conversations` para agrupar mensagens
- ❌ Não há tabela `contacts` para identificar contatos
- ❌ Correlação é feita apenas por `tenant_id + from` (no código do CommunicationHubController)

---

## 3. Modelo de Dados

### 3.1 Tabelas Existentes

#### ✅ `communication_events`
**Status:** ✅ Implementada e funcional  
**Uso:** Armazena todos os eventos do sistema  
**GAP:** Não é otimizada para consultas de conversas (falta índices compostos)

#### ✅ `tenant_message_channels`
**Status:** ✅ Implementada  
**Uso:** Mapeia tenant → channel_id (WhatsApp)  
**Estrutura:**
```sql
CREATE TABLE tenant_message_channels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    provider VARCHAR(50) DEFAULT 'wpp_gateway',
    channel_id VARCHAR(100) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    webhook_configured BOOLEAN DEFAULT FALSE,
    metadata JSON NULL,
    -- UNIQUE (tenant_id, provider)
)
```

#### ✅ `tenants`
**Status:** ✅ Implementada  
**Uso:** Clientes da agência  
**GAP:** Não há separação entre "Contato" (pessoa que envia mensagem) e "Tenant" (cliente da agência)

#### ✅ `chat_threads` e `chat_messages`
**Status:** ⚠️ Tabelas existem (migrations encontradas)  
**Uso:** Chat interno (não integrado com WhatsApp)  
**GAP:** Não há integração entre chat_threads e communication_events

### 3.2 Tabelas Ausentes (Gaps)

#### ❌ `contacts`
**Necessidade:** Identificar contatos (pessoas) independentemente de tenant  
**GAP:** Não existe. Hoje usa-se `tenants.phone` ou extrai `from` do payload

#### ❌ `conversations`
**Necessidade:** Agrupar mensagens em threads de conversa  
**GAP:** Não existe. Hoje agrupa-se dinamicamente por `tenant_id + from` no PHP

#### ❌ `messages`
**Necessidade:** Tabela normalizada de mensagens (não eventos)  
**GAP:** Não existe. Hoje usa-se `communication_events` com payload JSON

#### ❌ `conversation_participants`
**Necessidade:** Atendentes, bots, participantes da conversa  
**GAP:** Não existe

#### ❌ `tickets`
**Status:** ⚠️ Existe módulo de tickets? (não encontrado na auditoria)  
**GAP:** Não há integração entre comunicação e tickets

#### ❌ `leads` / `deals`
**Status:** ❌ Não existe  
**GAP:** Não há CRM de vendas integrado

#### ❌ `attachments`
**Status:** ⚠️ Existe `task_attachments` mas não para mensagens  
**GAP:** Não há suporte a anexos em mensagens

#### ❌ `audit_logs`
**Status:** ❌ Não existe tabela dedicada  
**GAP:** Não há trilha de auditoria de ações (responder, transferir, encerrar)

---

## 4. UI/UX Atual

### 4.1 Tela de Inbox

#### ✅ `/communication-hub`

**Controller:** `src/Controllers/CommunicationHubController.php`  
**View:** `views/communication_hub/index.php`

**Funcionalidades Atuais:**
- ✅ Lista threads de conversa (WhatsApp + Chat)
- ✅ Filtros: canal, tenant, status
- ✅ Estatísticas: conversas ativas, não lidas
- ✅ Visualização de thread individual

**Limitações:**
- ⚠️ Threads são gerados dinamicamente (não há tabela `conversations`)
- ⚠️ Agrupamento por `tenant_id + from` (pode duplicar se mesmo contato em múltiplos tenants)
- ⚠️ Não há indicador de "não lida" real (sempre mostra 0)
- ⚠️ Não há ordenação por SLA ou prioridade
- ⚠️ Não há busca por texto da mensagem

**Evidências:**
```php
// CommunicationHubController.php:260-349
private function getWhatsAppThreads(PDO $db, ?int $tenantId, string $status): array
{
    // Busca eventos e agrupa em PHP
    // Thread key: "{$tenantId}_{$from}"
}
```

### 4.2 Visualização de Conversa

#### ✅ `/communication-hub/thread`

**View:** `views/communication_hub/thread.php`

**Funcionalidades:**
- ✅ Exibe mensagens em ordem cronológica
- ✅ Diferencia inbound/outbound
- ✅ Campo para enviar resposta

**Limitações:**
- ⚠️ Não há suporte a mídia (imagens, áudios, arquivos)
- ⚠️ Não há templates de resposta
- ⚠️ Não há notas internas
- ⚠️ Não há tags
- ⚠️ Não há histórico de ações (transferir, encerrar)

### 4.3 Ações Disponíveis

**Ações Implementadas:**
- ✅ Enviar mensagem (via POST `/communication-hub/send`)

**Ações Ausentes:**
- ❌ Transferir conversa
- ❌ Encerrar/pausar conversa
- ❌ Adicionar tags
- ❌ Notas internas
- ❌ Anexar arquivos
- ❌ Criar tarefa a partir da conversa
- ❌ Criar ticket a partir da conversa
- ❌ Vincular a lead/deal

### 4.4 Outras Telas

#### ✅ `/settings/communication-events`

**Controller:** `src/Controllers/CommunicationEventsController.php`  
**Uso:** Visualização técnica de eventos (debug/admin)

**Status:** ✅ Funcional, mas não é "Inbox" operacional

---

## 5. Integrações com Módulos

### 5.1 Vendas (Leads, Pipeline)

**Status:** ❌ **NÃO EXISTE**

**GAP:** Não há módulo de vendas/CRM integrado

### 5.2 Suporte (Tickets, SLAs)

**Status:** ⚠️ **NÃO ENCONTRADO**

**GAP:** Não foi encontrado módulo de tickets na auditoria. Se existir, não está integrado com comunicação.

### 5.3 Financeiro

**Status:** ✅ **PARCIALMENTE INTEGRADO**

**Integração Atual:**
- ✅ `EventRouterService` pode rotear eventos `billing.invoice.*` para WhatsApp
- ✅ Registra em `billing_notifications` quando envia
- ✅ `BillingCollectionsController` tem funcionalidade de cobrança via WhatsApp

**Evidências:**
```php
// EventRouterService.php:166-169
if (strpos($normalizedEvent['event_type'], 'billing.') === 0) {
    self::registerBillingNotification($tenantId, $toNormalized, $text, $normalizedEvent);
}
```

**GAP:**
- ⚠️ Não há vínculo bidirecional: mensagem recebida não cria evento de cobrança
- ⚠️ Não há detecção de intenção de pagamento em mensagens

### 5.4 Marketing

**Status:** ❌ **NÃO EXISTE**

**GAP:** Não há módulo de marketing, campanhas, UTM tracking

### 5.5 Projetos/Tarefas

**Status:** ✅ **EXISTE, MAS NÃO INTEGRADO**

**Módulo:** Existe `projects` e `tasks`  
**GAP:** Não há integração: não é possível criar tarefa a partir de conversa

### 5.6 Automação

**Status:** ⚠️ **PARCIAL**

**Existente:**
- ✅ `routing_rules` — Regras de roteamento baseadas em `event_type`
- ✅ `EventRouterService` — Executa roteamento

**GAP:**
- ❌ Não há bots/respostas automáticas
- ❌ Não há regras condicionais (horário, palavra-chave, status do cliente)
- ❌ Não há follow-up automático
- ❌ Não há triggers baseados em ações do atendente

---

## 6. Resumo: O Que Existe vs O Que Falta

### ✅ O Que Está Pronto

1. **Recebimento de Webhooks**
   - Endpoint `/api/whatsapp/webhook` funcional
   - Validação de secret
   - Idempotência

2. **Persistência de Eventos**
   - Tabela `communication_events` completa
   - Suporte a retry (estrutura, não processamento)

3. **Roteamento (Estrutura)**
   - `EventRouterService` implementado
   - `routing_rules` (tabela existe)
   - Normalização existe (não integrada)

4. **UI Básica**
   - Tela `/communication-hub` funcional
   - Visualização de threads
   - Envio de mensagens

5. **Integração Financeiro (Parcial)**
   - Roteamento de eventos de cobrança
   - Registro em `billing_notifications`

### ❌ O Que Está Faltando (Gaps Críticos)

1. **Processamento Automático**
   - ❌ Não há worker que processa eventos `queued`
   - ❌ Eventos ficam parados após ingestão

2. **Modelo de Dados Completo**
   - ❌ Falta `contacts` (identificação de contatos)
   - ❌ Falta `conversations` (threads persistentes)
   - ❌ Falta `messages` (tabela normalizada)
   - ❌ Falta `conversation_participants`
   - ❌ Falta `attachments` para mensagens
   - ❌ Falta `audit_logs`

3. **Funcionalidades de Inbox**
   - ❌ Transferir conversa
   - ❌ Encerrar/pausar
   - ❌ Tags e notas internas
   - ❌ Busca avançada
   - ❌ SLA e priorização
   - ❌ Indicador de "não lida" real

4. **Integrações**
   - ❌ Vendas/CRM (leads, deals)
   - ❌ Tickets de suporte
   - ❌ Marketing (campanhas, UTM)
   - ❌ Projetos/Tarefas (criar a partir de conversa)

5. **Automação**
   - ❌ Bots/respostas automáticas
   - ❌ Regras condicionais
   - ❌ Follow-up automático

6. **Observabilidade**
   - ❌ Métricas (mensagens/min, SLA, latência)
   - ❌ Dead-letter queue
   - ❌ Logs correlacionados (correlation_id)

---

## 7. Evidências de Código

### 7.1 Endpoints

**Arquivo:** `public/index.php`
- Linha 413: `/webhook/asaas`
- Linha 416: `/api/whatsapp/webhook`
- Linha 528-530: `/communication-hub`

### 7.2 Controllers

- `src/Controllers/WhatsAppWebhookController.php` (207 linhas)
- `src/Controllers/CommunicationHubController.php` (552 linhas)
- `src/Controllers/CommunicationEventsController.php` (214 linhas)

### 7.3 Services

- `src/Services/EventIngestionService.php` (293 linhas)
- `src/Services/EventRouterService.php` (266 linhas)
- `src/Services/EventNormalizationService.php` (existe, não verificado)

### 7.4 Migrations

- `database/migrations/20250201_create_communication_events_table.php`
- `database/migrations/20250201_create_tenant_message_channels_table.php`
- `database/migrations/20250131_02_create_chat_messages_table.php`

### 7.5 Views

- `views/communication_hub/index.php`
- `views/communication_hub/thread.php`
- `views/settings/communication_events/index.php` (assumido)

---

**Próximo Passo:** Ver documento `ARQUITETURA_CENTRAL_COMUNICACAO_ALVO.md` para entender como deve funcionar.

