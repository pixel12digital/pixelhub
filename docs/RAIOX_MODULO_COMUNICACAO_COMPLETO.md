# 📌 Raio-X Completo — Módulo de Chat / Comunicação (Pixel Hub)

**Data:** 2026-01-13  
**Versão:** 1.0  
**Status:** ✅ Documentação Completa

---

## 📋 Índice

1. [Visão Arquitetural Geral](#1-visão-arquitetural-geral)
2. [Mapeamento de Arquivos e Pastas](#2-mapeamento-de-arquivos-e-pastas)
3. [Modelo de Dados (Banco)](#3-modelo-de-dados-banco)
4. [Fluxos de Comunicação Existentes](#4-fluxos-de-comunicação-existentes)
5. [Integrações Externas](#5-integrações-externas)
6. [Estados, Status e Regras](#6-estados-status-e-regras)
7. [Multi-Tenant e Isolamento](#7-multi-tenant-e-isolamento)
8. [Pontos Sensíveis e Dívidas Técnicas](#8-pontos-sensíveis-e-dívidas-técnicas)
9. [Logs, Debug e Observabilidade](#9-logs-debug-e-observabilidade)
10. [Limites Atuais do Sistema](#10-limites-atuais-do-sistema)

---

## 1. Visão Arquitetural Geral

### 1.1. Arquitetura Atual

O módulo de comunicação do Pixel Hub segue uma **arquitetura híbrida parcialmente modular**, com os seguintes componentes:

#### **Estrutura Geral:**
```
Request → Router → Controller → Service → Database
                    ↓
                  View (PHP + JavaScript)
```

#### **Características:**
- ✅ **Parcialmente modular**: O módulo tem serviços separados (EventIngestionService, ConversationService, EventRouterService), mas ainda está acoplado ao Hub
- ✅ **Preparado para múltiplos canais**: A arquitetura suporta WhatsApp, chat interno e email (planejado), mas apenas WhatsApp está totalmente implementado
- ⚠️ **Acoplamento ao Hub**: Controllers e views estão dentro do projeto principal, não são um módulo independente
- ✅ **Event-Driven**: Sistema baseado em eventos (`communication_events`) que permite rastreamento e processamento assíncrono

### 1.2. Responsabilidades do Módulo

O módulo de comunicação assume as seguintes responsabilidades:

#### **Backend:**
1. **Persistência de Eventos**: Armazena todos os eventos de comunicação em `communication_events`
2. **Gerenciamento de Conversas**: Cria e atualiza conversas na tabela `conversations`
3. **Ingestão de Eventos**: Recebe eventos via webhook e API (`EventIngestionService`)
4. **Normalização de Eventos**: Normaliza eventos de diferentes sistemas (`EventNormalizationService`)
5. **Roteamento de Eventos**: Roteia eventos para canais apropriados (`EventRouterService`)
6. **Envio de Mensagens**: Envia mensagens via gateway WhatsApp (`CommunicationHubController::send()`)
7. **Resolução de Conversas**: Identifica ou cria conversas baseado em eventos (`ConversationService`)
8. **Normalização de Telefones**: Normaliza números de telefone para formato E.164 (`PhoneNormalizer`)

#### **Frontend:**
1. **Exibição de Conversas**: Lista conversas ativas (`communication_hub/index.php`)
2. **Visualização de Thread**: Exibe mensagens de uma conversa específica (`communication_hub/thread.php`)
3. **Polling em Tempo Real**: Verifica novas mensagens periodicamente (JavaScript inline)
4. **Envio de Mensagens**: Interface para envio de mensagens pelo operador
5. **Atualização Automática**: Atualiza lista de conversas quando há novas mensagens

---

## 2. Mapeamento de Arquivos e Pastas

### 2.1. Backend

#### **Controllers** (`src/Controllers/`)

| Arquivo | Função Principal | Crítico? | Reutilizado? |
|---------|------------------|----------|--------------|
| `CommunicationHubController.php` | Painel operacional de comunicação, envio de mensagens, listagem de threads | ✅ **CRÍTICO** | Não |
| `CommunicationEventsController.php` | Visualização de eventos de comunicação (debug/admin) | ⚠️ Auxiliar | Não |
| `EventIngestionController.php` | Recebe eventos via API (`POST /api/events`) | ✅ **CRÍTICO** | Não |
| `WhatsAppWebhookController.php` | Recebe webhooks do gateway WhatsApp (`POST /api/whatsapp/webhook`) | ✅ **CRÍTICO** | Não |
| `WhatsAppGatewaySettingsController.php` | Configuração do gateway WhatsApp | ⚠️ Auxiliar | Não |
| `WhatsAppGatewayTestController.php` | Testes do gateway WhatsApp | ⚠️ Auxiliar | Não |

#### **Services** (`src/Services/`)

| Arquivo | Função Principal | Crítico? | Reutilizado? |
|---------|------------------|----------|--------------|
| `ConversationService.php` | Resolve/cria conversas baseado em eventos | ✅ **CRÍTICO** | Sim (EventIngestionService) |
| `EventIngestionService.php` | Ingere eventos no sistema (idempotência, validação) | ✅ **CRÍTICO** | Sim (Controllers) |
| `EventNormalizationService.php` | Normaliza eventos de diferentes sistemas | ✅ **CRÍTICO** | Sim (EventRouterService) |
| `EventRouterService.php` | Roteia eventos para canais apropriados | ✅ **CRÍTICO** | Sim (EventIngestionController) |
| `PhoneNormalizer.php` | Normaliza telefones para E.164 | ✅ **CRÍTICO** | Sim (ConversationService, WhatsAppBillingService) |
| `WhatsAppBillingService.php` | Serviço de cobrança via WhatsApp (fora do módulo de comunicação) | ⚠️ Auxiliar | Não |

#### **Integrations** (`src/Integrations/WhatsAppGateway/`)

| Arquivo | Função Principal | Crítico? | Reutilizado? |
|---------|------------------|----------|--------------|
| `WhatsAppGatewayClient.php` | Cliente HTTP para comunicação com gateway WhatsApp | ✅ **CRÍTICO** | Sim (CommunicationHubController) |

#### **Jobs / Workers**

❌ **Não há jobs/workers assíncronos**. Todo processamento é síncrono.

#### **APIs / Endpoints**

| Endpoint | Método | Controller | Função |
|----------|--------|------------|--------|
| `/api/events` | POST | `EventIngestionController::handle()` | Recebe eventos de sistemas internos |
| `/api/whatsapp/webhook` | POST | `WhatsAppWebhookController::handle()` | Recebe webhooks do gateway |
| `/communication-hub` | GET | `CommunicationHubController::index()` | Lista conversas |
| `/communication-hub/thread` | GET | `CommunicationHubController::thread()` | Visualiza conversa |
| `/communication-hub/send` | POST | `CommunicationHubController::send()` | Envia mensagem |
| `/communication-hub/check-updates` | GET | `CommunicationHubController::checkUpdates()` | Verifica atualizações na lista |
| `/communication-hub/messages/check` | GET | `CommunicationHubController::checkNewMessages()` | Verifica novas mensagens |
| `/communication-hub/messages/new` | GET | `CommunicationHubController::getNewMessages()` | Busca novas mensagens |
| `/communication-hub/message` | GET | `CommunicationHubController::getMessage()` | Busca mensagem específica |

#### **Webhooks**

| Webhook | Endpoint | Controller | Validação |
|---------|----------|------------|-----------|
| Gateway WhatsApp | `/api/whatsapp/webhook` | `WhatsAppWebhookController` | Header `X-Webhook-Secret` (opcional) |
| Eventos Internos | `/api/events` | `EventIngestionController` | Header `X-Event-Secret` (opcional) |

### 2.2. Frontend

#### **Telas Principais** (`views/communication_hub/`)

| Arquivo | Função Principal | Crítico? |
|---------|------------------|----------|
| `index.php` | Lista de conversas (sidebar + área principal) | ✅ **CRÍTICO** |
| `thread.php` | Visualização de thread (mensagens + formulário de envio) | ✅ **CRÍTICO** |

#### **Componentes Reutilizáveis**

❌ **Não há componentes reutilizáveis separados**. Todo código está inline nas views.

#### **Scripts JS Específicos do Chat**

| Localização | Função | Crítico? |
|-------------|--------|----------|
| `views/communication_hub/index.php` (linhas 235-414) | Polling da lista de conversas | ✅ **CRÍTICO** |
| `views/communication_hub/thread.php` (linhas 113-696) | Polling de mensagens, envio otimista, scroll automático | ✅ **CRÍTICO** |

#### **CSS/Estilos Dedicados**

❌ **Não há arquivos CSS dedicados**. Estilos estão inline nas views PHP.

---

## 3. Modelo de Dados (Banco)

### 3.1. Tabelas Principais

#### **`communication_events`**

**Finalidade:** Armazena todos os eventos de comunicação do sistema (fonte de verdade).

**Principais Colunas:**
- `id` (INT UNSIGNED, PK, AUTO_INCREMENT)
- `event_id` (VARCHAR(36), UNIQUE) - UUID único do evento
- `idempotency_key` (VARCHAR(255), UNIQUE) - Chave para garantir idempotência
- `event_type` (VARCHAR(100)) - Tipo do evento (ex: `whatsapp.inbound.message`, `whatsapp.outbound.message`)
- `source_system` (VARCHAR(50)) - Sistema de origem (`wpp_gateway`, `asaas`, `billing`, etc.)
- `tenant_id` (INT UNSIGNED, NULL) - FK para `tenants`
- `trace_id` (VARCHAR(36)) - UUID para rastrear fluxo completo
- `correlation_id` (VARCHAR(36), NULL) - UUID para agrupar eventos relacionados
- `payload` (JSON) - Payload completo do evento
- `metadata` (JSON, NULL) - Metadados adicionais
- `status` (VARCHAR(20)) - `queued`, `processing`, `processed`, `failed`
- `processed_at` (DATETIME, NULL)
- `error_message` (TEXT, NULL)
- `retry_count` (INT UNSIGNED, DEFAULT 0)
- `max_retries` (INT UNSIGNED, DEFAULT 3)
- `next_retry_at` (DATETIME, NULL)
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Chaves:**
- **PK:** `id`
- **FK:** `tenant_id` → `tenants(id)` ON DELETE SET NULL
- **UNIQUE:** `event_id`, `idempotency_key`

**Índices:**
- `idx_event_type` (event_type)
- `idx_source_system` (source_system)
- `idx_tenant_id` (tenant_id)
- `idx_trace_id` (trace_id)
- `idx_correlation_id` (correlation_id)
- `idx_status` (status)
- `idx_created_at` (created_at)
- `idx_next_retry_at` (next_retry_at)

**Migration:** `database/migrations/20250201_create_communication_events_table.php`

---

#### **`conversations`**

**Finalidade:** Núcleo conversacional central - agrupa mensagens por canal + contato.

**Principais Colunas:**
- `id` (INT UNSIGNED, PK, AUTO_INCREMENT)
- `conversation_key` (VARCHAR(255), UNIQUE) - Chave única: `{channel_type}_{channel_account_id}_{contact_external_id}`
- `channel_type` (VARCHAR(50)) - `whatsapp`, `email`, `webchat`, etc.
- `channel_account_id` (INT UNSIGNED, NULL) - FK para `tenant_message_channels`
- `channel_id` (VARCHAR(100), NULL) - ID do channel no gateway (session.id para WhatsApp)
- `contact_external_id` (VARCHAR(255)) - ID externo do contato (telefone, e-mail, etc.)
- `contact_name` (VARCHAR(255), NULL) - Nome do contato
- `tenant_id` (INT UNSIGNED, NULL) - FK para `tenants`
- `product_id` (INT UNSIGNED, NULL) - Produto associado
- `status` (VARCHAR(20)) - `new`, `open`, `pending`, `closed`, `archived`
- `assigned_to` (INT UNSIGNED, NULL) - FK para `users`
- `assigned_at` (DATETIME, NULL)
- `first_response_at` (DATETIME, NULL)
- `first_response_by` (INT UNSIGNED, NULL) - FK para `users`
- `closed_at` (DATETIME, NULL)
- `closed_by` (INT UNSIGNED, NULL) - FK para `users`
- `sla_minutes` (INT UNSIGNED, DEFAULT 60)
- `sla_status` (VARCHAR(20), DEFAULT 'ok') - `ok`, `warning`, `breach`
- `last_message_at` (DATETIME, NULL)
- `last_message_direction` (VARCHAR(10), NULL) - `inbound`, `outbound`
- `message_count` (INT UNSIGNED, DEFAULT 0)
- `unread_count` (INT UNSIGNED, DEFAULT 0)
- `metadata` (JSON, NULL)
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Chaves:**
- **PK:** `id`
- **FK:** `tenant_id` → `tenants(id)` ON DELETE SET NULL
- **FK:** `assigned_to` → `users(id)` ON DELETE SET NULL
- **FK:** `first_response_by` → `users(id)` ON DELETE SET NULL
- **FK:** `closed_by` → `users(id)` ON DELETE SET NULL
- **UNIQUE:** `conversation_key`

**Índices:**
- `idx_channel_type` (channel_type)
- `idx_channel_account` (channel_account_id)
- `idx_channel_id` (channel_id)
- `idx_contact_external` (contact_external_id)
- `idx_tenant` (tenant_id)
- `idx_status` (status)
- `idx_assigned_to` (assigned_to)
- `idx_last_message_at` (last_message_at)
- `idx_sla_status` (sla_status)
- `idx_created_at` (created_at)

**Migrations:**
- `database/migrations/20260109_create_conversations_table.php`
- `database/migrations/20260113_alter_conversations_add_channel_id.php`

---

#### **`tenant_message_channels`**

**Finalidade:** Mapeia tenants para canais de comunicação (WhatsApp, etc.).

**Principais Colunas:**
- `id` (INT UNSIGNED, PK, AUTO_INCREMENT)
- `tenant_id` (INT UNSIGNED, NOT NULL) - FK para `tenants`
- `provider` (VARCHAR(50), DEFAULT 'wpp_gateway') - Provedor: `wpp_gateway`, etc.
- `channel_id` (VARCHAR(100)) - ID do channel no provedor
- `is_enabled` (BOOLEAN, DEFAULT TRUE)
- `webhook_configured` (BOOLEAN, DEFAULT FALSE)
- `metadata` (JSON, NULL) - Metadados do channel (status, qr, etc.)
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Chaves:**
- **PK:** `id`
- **FK:** `tenant_id` → `tenants(id)` ON DELETE CASCADE
- **UNIQUE:** `unique_tenant_provider` (tenant_id, provider)

**Índices:**
- `idx_channel_id` (channel_id)
- `idx_provider` (provider)
- `idx_is_enabled` (is_enabled)

**Migration:** `database/migrations/20250201_create_tenant_message_channels_table.php`

---

#### **`chat_threads`**

**Finalidade:** Threads de conversa vinculadas a pedidos de serviço (chat interno).

**Principais Colunas:**
- `id` (INT UNSIGNED, PK, AUTO_INCREMENT)
- `customer_id` (INT UNSIGNED, NULL) - FK para `tenants`
- `order_id` (INT UNSIGNED, NOT NULL) - FK para `service_orders` (OBRIGATÓRIO)
- `status` (VARCHAR(50), DEFAULT 'open') - `open`, `waiting_user`, `waiting_ai`, `escalated`, `closed`
- `current_step` (VARCHAR(50), NULL) - `step_0_welcome`, `step_1_identity`, etc.
- `metadata` (JSON, NULL)
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Chaves:**
- **PK:** `id`
- **FK:** `order_id` → `service_orders(id)` ON DELETE CASCADE
- **FK:** `customer_id` → `tenants(id)` ON DELETE SET NULL

**Índices:**
- `idx_customer_id` (customer_id)
- `idx_order_id` (order_id)
- `idx_status` (status)
- `idx_current_step` (current_step)

**Migration:** `database/migrations/20250131_01_create_chat_threads_table.php`

**⚠️ IMPORTANTE:** O chat sempre nasce com `order_id` - nunca existe solto.

---

#### **`chat_messages`**

**Finalidade:** Mensagens das conversas do chat interno.

**Principais Colunas:**
- `id` (INT UNSIGNED, PK, AUTO_INCREMENT)
- `thread_id` (INT UNSIGNED, NOT NULL) - FK para `chat_threads`
- `role` (VARCHAR(20)) - `system`, `assistant`, `user`, `tool`
- `content` (TEXT)
- `metadata` (JSON, NULL) - `extracted_fields`, `step_id`, `confidence`, etc.
- `created_at` (DATETIME)

**Chaves:**
- **PK:** `id`
- **FK:** `thread_id` → `chat_threads(id)` ON DELETE CASCADE

**Índices:**
- `idx_thread_id` (thread_id)
- `idx_role` (role)
- `idx_created_at` (created_at)

**Migration:** `database/migrations/20250131_02_create_chat_messages_table.php`

---

### 3.2. Relacionamentos Entre Tabelas

```
tenants (1) ──→ (N) tenant_message_channels
tenants (1) ──→ (N) conversations
tenants (1) ──→ (N) communication_events
tenants (1) ──→ (N) chat_threads

conversations (1) ──→ (N) communication_events (via contact_external_id + tenant_id)
tenant_message_channels (1) ──→ (N) conversations (via channel_account_id)

chat_threads (1) ──→ (N) chat_messages
service_orders (1) ──→ (N) chat_threads

users (1) ──→ (N) conversations.assigned_to
users (1) ──→ (N) conversations.first_response_by
users (1) ──→ (N) conversations.closed_by
```

---

## 4. Fluxos de Comunicação Existentes

### 4.1. Recebimento de Mensagens (Inbound)

#### **Fluxo Completo:**

```
1. Gateway WhatsApp → Webhook
   ↓
2. WhatsAppWebhookController::handle()
   - Valida secret (opcional)
   - Extrai event_type do payload
   - Mapeia para evento interno (ex: 'message' → 'whatsapp.inbound.message')
   - Resolve tenant_id pelo channel_id (session.id)
   ↓
3. EventIngestionService::ingest()
   - Gera event_id (UUID)
   - Calcula idempotency_key (evita duplicatas)
   - Valida tenant_id (verifica se existe)
   - Insere em communication_events
   ↓
4. ConversationService::resolveConversation()
   - Extrai informações do canal (channel_type, contact_external_id, etc.)
   - Gera conversation_key
   - Busca conversa existente por chave
   - Se não encontrar, tenta encontrar conversa equivalente (variação do 9º dígito)
   - Se não encontrar, cria nova conversa
   - Atualiza metadados (last_message_at, message_count, unread_count)
   ↓
5. EventNormalizationService::normalize() (opcional)
   - Normaliza evento para formato padrão
   ↓
6. EventRouterService::route() (opcional)
   - Busca regras de roteamento
   - Roteia para canal apropriado (whatsapp, chat, email)
```

#### **Endpoint de Entrada:**
- **URL:** `POST /api/whatsapp/webhook`
- **Controller:** `WhatsAppWebhookController::handle()`
- **Validação:** Header `X-Webhook-Secret` (opcional, via `PIXELHUB_WHATSAPP_WEBHOOK_SECRET`)

#### **Validação:**
- JSON válido
- `event_type` presente no payload
- Evento mapeado para tipo interno

#### **Associação a Canal:**
- Extrai `session.id` do payload → `channel_id`
- Busca em `tenant_message_channels` por `channel_id` → `tenant_id`

#### **Associação a Contato:**
- Extrai `from` ou `message.from` do payload
- Remove sufixos (`@c.us`, `@lid`, etc.)
- Normaliza para E.164 via `PhoneNormalizer::toE164OrNull()`
- Armazena em `conversations.contact_external_id`

#### **Associação a Conversa:**
- Gera `conversation_key`: `{channel_type}_{channel_account_id}_{contact_external_id}`
- Busca conversa existente por `conversation_key`
- Se não encontrar, tenta encontrar conversa equivalente (variação do 9º dígito para números BR)
- Se não encontrar, cria nova conversa

#### **Associação a Tenant:**
- Resolve `tenant_id` pelo `channel_id` (via `tenant_message_channels`)
- Se não encontrar, `tenant_id` fica `NULL` (conversa compartilhada)

---

### 4.2. Envio de Mensagens (Outbound)

#### **Fluxo Completo:**

```
1. Operador → Interface (communication_hub/thread.php)
   ↓
2. JavaScript: sendMessage()
   - Coleta dados do formulário
   - Envia POST para /communication-hub/send
   ↓
3. CommunicationHubController::send()
   - Valida campos obrigatórios (channel, message, to)
   - Resolve channel_id (prioridade):
     a) Usa channel_id fornecido diretamente (vem da thread)
     b) Busca channel_id dos eventos da conversa usando thread_id
     c) Busca canal do tenant
     d) Fallback: qualquer canal habilitado
   - Valida se canal existe e está habilitado
   - Normaliza telefone via WhatsAppBillingService::normalizePhone()
   - Valida sessão do canal (getChannel() → verifica status)
   ↓
4. WhatsAppGatewayClient::sendText()
   - Faz requisição HTTP POST para gateway
   - Endpoint: /api/messages
   - Payload: { channel, to, text, metadata }
   ↓
5. Gateway WhatsApp → Envia mensagem
   ↓
6. CommunicationHubController::send() (continuação)
   - Se sucesso, cria evento outbound:
     EventIngestionService::ingest([
       event_type: 'whatsapp.outbound.message',
       source_system: 'pixelhub_operator',
       payload: { to, message, channel_id },
       tenant_id: ...
     ])
   - Retorna JSON com success/error
   ↓
7. ConversationService::resolveConversation()
   - Atualiza conversa (last_message_at, message_count)
```

#### **Decisão do Canal de Envio:**
- **PRIORIDADE 1:** Usa `channel_id` fornecido diretamente (vem da thread)
- **PRIORIDADE 2:** Busca `channel_id` dos eventos da conversa usando `thread_id`
- **PRIORIDADE 3:** Busca canal do tenant (`tenant_message_channels`)
- **PRIORIDADE 4:** Fallback para canal compartilhado/default (qualquer canal habilitado)

#### **Validação do Canal Ativo:**
- Chama `WhatsAppGatewayClient::getChannel(channel_id)`
- Verifica `status` ou `connection` no retorno
- Se não estiver `connected` ou `open`, retorna erro `SESSION_DISCONNECTED`

#### **Tratamento de Status de Envio:**
- **Sucesso:** Cria evento `whatsapp.outbound.message` em `communication_events`
- **Erro:** Retorna JSON com `error` e `error_code` específico:
  - `SESSION_DISCONNECTED` - Sessão desconectada
  - `INVALID_SECRET` - Secret inválido
  - `UNAUTHORIZED` - Credenciais inválidas (401)
  - `CHANNEL_NOT_FOUND` - Canal não encontrado (404)
  - `GATEWAY_ERROR` - Erro genérico do gateway

---

## 5. Integrações Externas

### 5.1. WhatsApp Gateway

#### **Quem Inicia:**
- **Hub → Gateway:** Envio de mensagens (`WhatsAppGatewayClient::sendText()`)
- **Gateway → Hub:** Recebimento de mensagens (webhook)

#### **Configuração:**
- **Base URL:** `WPP_GATEWAY_BASE_URL` (padrão: `https://wpp.pixel12digital.com.br`)
- **Secret:** `WPP_GATEWAY_SECRET` (criptografado via `GatewaySecret`)
- **Autenticação:** Header `X-Gateway-Secret`

#### **Endpoints do Gateway:**
- `GET /api/channels` - Lista canais
- `GET /api/channels/{channelId}` - Obtém canal específico
- `POST /api/channels` - Cria canal
- `GET /api/channels/{channelId}/qr` - Obtém QR code
- `POST /api/messages` - Envia mensagem
- `POST /api/channels/{channelId}/webhook` - Configura webhook do canal
- `POST /api/webhooks` - Configura webhook global

#### **Payload Esperado (Envio):**
```json
{
  "channel": "Pixel12 Digital",
  "to": "5511999999999",
  "text": "Mensagem...",
  "metadata": {
    "sent_by": 1,
    "sent_by_name": "Operador",
    "message_id": "..."
  }
}
```

#### **Payload Recebido (Webhook):**
```json
{
  "event": "message",
  "session": {
    "id": "Pixel12 Digital",
    "name": "Pixel12 Digital"
  },
  "message": {
    "from": "554796164699@c.us",
    "to": "554797309525@c.us",
    "text": "..."
  },
  "raw": {
    "provider": "wppconnect",
    "payload": {...}
  }
}
```

#### **Campos Obrigatórios:**
- **Envio:** `channel`, `to`, `text`
- **Webhook:** `event`, `session.id` (ou `channel`), `message.from` (ou `from`)

#### **Tratamento de Erros:**
- **Erro de Conexão:** Retorna `error: "Erro de conexão: {mensagem}"`
- **Erro HTTP:** Retorna `error: "{mensagem do gateway}"`, `http_status: {código}`
- **Erro JSON:** Retorna `error: "Resposta inválida do gateway: {mensagem}"`

---

### 5.2. Webhooks Configuráveis

#### **Webhook do Gateway:**
- **URL:** Configurável via `setChannelWebhook()` ou `setGlobalWebhook()`
- **Secret:** Opcional, validado via `PIXELHUB_WHATSAPP_WEBHOOK_SECRET`
- **Endpoint:** `/api/whatsapp/webhook`

#### **Webhook de Eventos Internos:**
- **URL:** `/api/events`
- **Secret:** Opcional, validado via `EVENT_INGESTION_SECRET`
- **Header:** `X-Event-Secret` ou `Authorization: Bearer {secret}`

---

### 5.3. Dependências Externas

- **Gateway WhatsApp:** Serviço externo (`wpp.pixel12digital.com.br`)
- **Nenhuma outra dependência externa** para o módulo de comunicação

---

## 6. Estados, Status e Regras

### 6.1. Mensagem Enviada vs Recebida

#### **Direção:**
- **Inbound:** `event_type = 'whatsapp.inbound.message'` → `direction = 'inbound'`
- **Outbound:** `event_type = 'whatsapp.outbound.message'` → `direction = 'outbound'`

#### **Onde Vive:**
- **Backend:** Campo `last_message_direction` em `conversations`
- **Frontend:** Classe CSS `message-bubble inbound` ou `outbound` em `thread.php`

#### **Regras:**
- Inbound incrementa `unread_count` em `conversations`
- Outbound não incrementa `unread_count`
- Ambos incrementam `message_count`

---

### 6.2. Mensagem Pendente

❌ **Não há status de "pendente" para mensagens individuais**. O sistema não rastreia status de entrega/leitura por mensagem.

---

### 6.3. Mensagem Lida

#### **Onde Vive:**
- **Backend:** Campo `unread_count` em `conversations` (contador, não por mensagem)
- **Frontend:** Badge de contador não lidas na lista de conversas

#### **Regras:**
- Quando operador abre thread, `unread_count` é zerado (`markConversationAsRead()`)
- Inbound incrementa `unread_count`
- Outbound não incrementa `unread_count`

---

### 6.4. Conversa Ativa / Arquivada / Encerrada

#### **Status em `conversations`:**
- `new` - Nova conversa
- `open` - Conversa aberta/ativa
- `pending` - Aguardando resposta
- `closed` - Fechada
- `archived` - Arquivada

#### **Onde Vive:**
- **Backend:** Campo `status` em `conversations`
- **Frontend:** Filtro na lista (`status = 'active'` filtra `NOT IN ('closed', 'archived')`)

#### **Regras:**
- Quando nova mensagem chega em conversa `closed`, status muda para `open`
- Fechamento manual (não implementado ainda)

---

### 6.5. Marcação Visual no Frontend

#### **Lista de Conversas:**
- Badge vermelho com contador de não lidas (`unread_count > 0`)
- Ordenação por `last_message_at DESC`

#### **Thread:**
- Mensagens inbound: fundo branco, alinhadas à esquerda
- Mensagens outbound: fundo `#dcf8c6`, alinhadas à direita
- Badge de "novas mensagens" quando scrollado para cima

---

### 6.6. Duplicidade de Lógica

⚠️ **Há duplicidade em:**
- Normalização de telefone: `PhoneNormalizer::toE164OrNull()` vs `WhatsAppBillingService::normalizePhone()`
- Busca de `channel_id`: Lógica repetida em `CommunicationHubController::send()` e `getWhatsAppThreadInfo()`

---

## 7. Multi-Tenant e Isolamento

### 7.1. Identificação do Tenant

#### **Pontos de Identificação:**
1. **Webhook Inbound:** Resolve `tenant_id` pelo `channel_id` (via `tenant_message_channels`)
2. **Envio:** Resolve `tenant_id` da conversa ou do formulário
3. **Listagem:** Filtro opcional por `tenant_id` na query

#### **Onde é Aplicado:**
- **Tabela `communication_events`:** Campo `tenant_id` (pode ser NULL)
- **Tabela `conversations`:** Campo `tenant_id` (pode ser NULL)
- **Tabela `tenant_message_channels`:** Campo `tenant_id` (NOT NULL)

---

### 7.2. Ponto do Fluxo onde Tenant é Aplicado

#### **Inbound:**
```
Webhook → resolveTenantByChannel(channel_id) → tenant_id → EventIngestionService → ConversationService
```

#### **Outbound:**
```
Formulário → tenant_id (opcional) → CommunicationHubController::send() → EventIngestionService
```

---

### 7.3. Risco de Vazamento Entre Tenants

#### **Análise:**

✅ **Isolamento em Queries:**
- Listagem de conversas filtra por `tenant_id` quando fornecido
- Busca de mensagens filtra por `contact_external_id` + `tenant_id` (quando ambos definidos)

⚠️ **Pontos de Atenção:**
- **Conversas com `tenant_id = NULL`:** Podem ser visualizadas por qualquer operador (comportamento intencional para conversas compartilhadas)
- **Busca de mensagens:** Se `tenant_id` for NULL na conversa, busca todas as mensagens do contato (pode misturar tenants se mesmo número for usado por múltiplos tenants)

#### **Recomendação:**
- Adicionar validação de isolamento explícita em `getWhatsAppMessagesFromConversation()` quando `tenant_id` está definido

---

### 7.4. Chat é 100% Tenant-Safe?

❌ **Não completamente**. Há riscos:
1. Conversas com `tenant_id = NULL` são compartilhadas
2. Busca de mensagens não valida isolamento quando `tenant_id` é NULL na conversa
3. Falta validação explícita de permissões de acesso por tenant

---

## 8. Pontos Sensíveis e Dívidas Técnicas

### 8.1. Pontos Frágeis Conhecidos

#### **1. Resolução de `channel_id` no Envio**
- **Problema:** Lógica complexa com múltiplas prioridades, pode falhar silenciosamente
- **Localização:** `CommunicationHubController::send()` (linhas 221-325)
- **Risco:** Envio pode falhar se nenhuma prioridade encontrar canal válido

#### **2. Normalização de Telefone Duplicada**
- **Problema:** Dois serviços diferentes (`PhoneNormalizer` vs `WhatsAppBillingService`)
- **Localização:** `src/Services/PhoneNormalizer.php` e `src/Services/WhatsAppBillingService.php`
- **Risco:** Inconsistências podem causar duplicação de conversas

#### **3. Busca de Mensagens sem Validação de Tenant**
- **Problema:** Quando `tenant_id` é NULL, busca todas as mensagens do contato
- **Localização:** `CommunicationHubController::getWhatsAppMessagesFromConversation()` (linhas 734-836)
- **Risco:** Vazamento de dados entre tenants se mesmo número for usado por múltiplos

#### **4. Polling com Flag `isChecking`**
- **Problema:** Flag pode travar se não resetar corretamente (já corrigido, mas requer monitoramento)
- **Localização:** `views/communication_hub/thread.php` (JavaScript)
- **Risco:** Polling pode parar de funcionar

---

### 8.2. Trechos que Exigem Cuidado Extremo

#### **1. `ConversationService::resolveConversation()`**
- **Por quê:** Lógica complexa de matching de conversas, pode criar duplicatas
- **Cuidado:** Não alterar sem testar extensivamente variações do 9º dígito

#### **2. `EventIngestionService::ingest()`**
- **Por quê:** Ponto central de ingestão, afeta todo o sistema
- **Cuidado:** Validação de idempotência é crítica

#### **3. `CommunicationHubController::getWhatsAppMessagesFromConversation()`**
- **Por quê:** Filtragem de mensagens por contato, pode vazar dados
- **Cuidado:** Validar isolamento de tenant sempre

---

### 8.3. Gambiarras Assumidas

#### **1. Fallback para Conversas Compartilhadas**
- **Onde:** `CommunicationHubController::send()` (linha 305-324)
- **O que:** Se não encontrar canal do tenant, usa qualquer canal habilitado
- **Risco:** Pode enviar mensagem pelo canal errado

#### **2. Busca de Mensagens em PHP (não SQL)**
- **Onde:** `CommunicationHubController::getWhatsAppMessagesFromConversation()` (linhas 776-833)
- **O que:** Busca todos os eventos e filtra em PHP ao invés de SQL
- **Risco:** Performance degradada com muitos eventos

---

### 8.4. Partes que Não Devem Ser Tocadas sem Refatoração Maior

#### **1. Estrutura de `communication_events`**
- **Por quê:** Fonte de verdade para todo o sistema
- **Refatoração necessária:** Migração de dados se estrutura mudar

#### **2. Estrutura de `conversations`**
- **Por quê:** Núcleo conversacional, usado por múltiplos fluxos
- **Refatoração necessária:** Migração de dados se estrutura mudar

#### **3. Formato de `conversation_key`**
- **Por quê:** Usado para matching de conversas
- **Refatoração necessária:** Recalcular todas as chaves se formato mudar

---

## 9. Logs, Debug e Observabilidade

### 9.1. Onde São Registrados Logs

#### **Backend (PHP):**
- **Função:** `error_log()` e `pixelhub_log()` (se disponível)
- **Arquivo:** `logs/pixelhub.log` (se configurado) ou log padrão do PHP

#### **Logs Principais:**
- `[CommunicationHub]` - Ações do CommunicationHubController
- `[EventIngestion]` - Ingestão de eventos
- `[CONVERSATION UPSERT]` - Resolução/criação de conversas
- `[WhatsAppWebhook]` - Webhooks recebidos
- `[WhatsAppGateway]` - Requisições ao gateway
- `[WHATSAPP INBOUND RAW]` - Payloads brutos de webhooks

---

### 9.2. Logs de Erro de Webhook

✅ **Sim, há logs detalhados:**
- **Localização:** `WhatsAppWebhookController::handle()` (linhas 51-63)
- **Conteúdo:** Headers, payload completo (primeiros 2000 chars), `channel_id` extraído, `tenant_id` resolvido

---

### 9.3. Logs de Envio/Recebimento

✅ **Sim:**
- **Envio:** Logs em `CommunicationHubController::send()` (linhas 205, 277, 298, 318, 403-410)
- **Recebimento:** Logs em `WhatsAppWebhookController::handle()` (linhas 51-63, 125-139, 154-162)

---

### 9.4. Ferramentas ou Tabelas de Apoio para Debug

#### **Tabelas:**
- `communication_events` - Todos os eventos (fonte de verdade)
- `conversations` - Estado das conversas
- `tenant_message_channels` - Configuração de canais

#### **Endpoints de Debug:**
- `/settings/communication-events` - Visualização de eventos
- `/settings/communication-events/view?event_id={id}` - Detalhes de evento
- `/diagnostic/communication` - Página de diagnóstico (testes de canal, envio, etc.)

#### **Scripts de Apoio:**
- `database/check-communication-events.php` - Verifica eventos
- `database/check-conversations-table.php` - Verifica conversas
- `database/check-channel-id-format.php` - Verifica formato do channel_id
- `database/list-threads-for-diagnostic.php` - Lista threads disponíveis

---

## 10. Limites Atuais do Sistema

### 10.1. O que o Módulo Não Suporta Hoje

#### **1. Múltiplos Canais Simultâneos por Tenant**
- **Limite:** Um tenant pode ter apenas um canal WhatsApp ativo (`UNIQUE KEY unique_tenant_provider`)
- **Impacto:** Não suporta múltiplas instâncias WhatsApp por tenant

#### **2. Status de Entrega/Leitura por Mensagem**
- **Limite:** Sistema não rastreia status de entrega/leitura individual
- **Impacto:** Não há confirmação de leitura por mensagem

#### **3. Mídia (Imagens, Áudios, Documentos)**
- **Limite:** Apenas mensagens de texto são suportadas
- **Impacto:** Mídias são exibidas como `[media]` ou `[tipo]`

#### **4. Chat Interno sem Order**
- **Limite:** Chat sempre nasce vinculado a `service_orders` (`order_id` obrigatório)
- **Impacto:** Não há chat genérico/standalone

#### **5. Email como Canal**
- **Limite:** Email está planejado mas não implementado
- **Impacto:** Apenas WhatsApp e chat interno funcionam

#### **6. Webhooks de Outros Sistemas**
- **Limite:** Apenas gateway WhatsApp tem webhook configurado
- **Impacto:** Outros sistemas precisam usar API `/api/events`

---

### 10.2. O que Está Parcialmente Implementado

#### **1. Roteamento de Eventos**
- **Status:** Estrutura existe (`EventRouterService`, tabela `routing_rules`), mas regras não estão configuradas
- **Impacto:** Eventos não são roteados automaticamente

#### **2. SLA de Conversas**
- **Status:** Campos existem (`sla_minutes`, `sla_status`), mas cálculo não está implementado
- **Impacto:** SLA não é calculado/atualizado automaticamente

#### **3. Atribuição de Conversas**
- **Status:** Campos existem (`assigned_to`, `assigned_at`), mas interface não permite atribuir
- **Impacto:** Conversas não podem ser atribuídas a operadores

#### **4. Fechamento de Conversas**
- **Status:** Campos existem (`closed_at`, `closed_by`), mas interface não permite fechar
- **Impacto:** Conversas não podem ser fechadas manualmente

---

### 10.3. O que Foi Pensado mas Nunca Finalizado

#### **1. Sistema de Tags/Metadados**
- **Status:** Campo `metadata` (JSON) existe, mas não há interface para gerenciar
- **Impacto:** Metadados não são utilizados

#### **2. Histórico de Atribuições**
- **Status:** Apenas última atribuição é armazenada
- **Impacto:** Não há histórico de quem atendeu quando

#### **3. Notificações Push**
- **Status:** Não implementado
- **Impacto:** Operadores precisam verificar manualmente novas mensagens

#### **4. Busca de Mensagens**
- **Status:** Não implementado
- **Impacto:** Não é possível buscar mensagens por conteúdo

#### **5. Exportação de Conversas**
- **Status:** Não implementado
- **Impacto:** Não é possível exportar conversas para análise

---

## 📝 Conclusão

Este documento mapeia completamente o módulo de comunicação do Pixel Hub, incluindo:

- ✅ Arquitetura atual (parcialmente modular, preparada para múltiplos canais)
- ✅ Todos os arquivos e pastas (backend e frontend)
- ✅ Modelo de dados completo (5 tabelas principais)
- ✅ Fluxos de recebimento e envio detalhados
- ✅ Integrações externas (gateway WhatsApp)
- ✅ Estados, status e regras de negócio
- ✅ Análise de multi-tenant e isolamento
- ✅ Pontos sensíveis e dívidas técnicas
- ✅ Logs e observabilidade
- ✅ Limites atuais do sistema

**Próximos Passos Recomendados:**
1. Centralizar normalização de telefone (remover duplicidade)
2. Adicionar validação explícita de isolamento de tenant
3. Implementar interface de atribuição/fechamento de conversas
4. Otimizar busca de mensagens (SQL ao invés de PHP)
5. Implementar rastreamento de status de entrega/leitura

---

**Última atualização:** 2026-01-13  
**Versão do documento:** 1.0

