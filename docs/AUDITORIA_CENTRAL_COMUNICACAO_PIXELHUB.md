# Auditoria Técnica - Central de Comunicação PixelHub

**Data:** 2025-01-31  
**Objetivo:** Mapear o que já existe e identificar gaps para implementar a arquitetura de "Posto Digital Central de Comunicação"

---

## 1. Resumo Executivo

### Estado Atual

O PixelHub possui **infraestrutura parcial** para comunicação, mas **não está centralizado** conforme a arquitetura proposta. Existem:

- ✅ **WhatsApp manual** (via WhatsApp Web) para cobranças
- ✅ **Chat interno** vinculado a pedidos de serviço
- ✅ **Webhook do Asaas** (financeiro)
- ✅ **Logs básicos** de mensagens enviadas
- ❌ **Sem sistema de eventos** (sistemas não emitem eventos estruturados)
- ❌ **Sem normalização centralizada** (cada módulo trata comunicação de forma isolada)
- ❌ **Sem roteamento inteligente** (não há camada de orquestração)
- ❌ **Sem correlação/trace_id** (impossível rastrear fluxo completo)
- ❌ **Sem idempotência** (risco de duplicação)
- ❌ **Sem replay** (não há como reprocessar eventos)

### Gaps Críticos (P0)

1. **Sistema de Eventos**: Não existe. Sistemas falam direto com WhatsApp/chat
2. **Normalização**: Cada módulo tem sua própria lógica de mensagem
3. **Roteamento**: Não há camada que decide o que fazer com eventos
4. **Correlação**: Sem `trace_id` ou `correlation_id`
5. **Idempotência**: Sem controle de duplicação
6. **Observabilidade**: Logs básicos, sem estrutura para auditoria completa

### Recomendação

Implementar em **3 fases**:
- **Fase 1 (MVP)**: Sistema de eventos básico + normalização + roteamento simples
- **Fase 2**: Correlação + idempotência + observabilidade
- **Fase 3**: Replay + dead letter queue + automações avançadas

### WhatsApp Adapter: WPP Gateway

**Decisão Arquitetural:** WhatsApp não usará API oficial. O canal WhatsApp será gerenciado pelo **WPP Gateway** já em produção.

- **Base URL:** https://wpp.pixel12digital.com.br
- **Autenticação:** Header `X-Gateway-Secret` obrigatório
- **Endpoints principais:**
  - `POST /api/channels` - Criar canal (session)
  - `GET /api/channels` - Listar canais
  - `GET /api/channels/:channel/qr` - Obter QR code para conectar
  - `POST /api/messages` - Enviar mensagem
  - `POST /api/webhooks` ou `POST /api/channels/:channel/webhook` - Configurar webhook
- **Eventos recebidos via webhook:**
  - `message` - Mensagem recebida
  - `message.ack` - Confirmação de entrega/leitura
  - `connection.update` - Mudança de status da conexão

**Mapeamento Tenant → Channel:**
- Cada tenant terá um `channel_id` único no gateway
- Tabela `tenant_message_channels` mapeia tenant_id → channel_id
- Webhook do channel aponta para `/api/whatsapp/webhook` do PixelHub

**Variáveis de ambiente necessárias:**
- `WPP_GATEWAY_BASE_URL=https://wpp.pixel12digital.com.br`
- `WPP_GATEWAY_SECRET=***` (secret do gateway)
- `PIXELHUB_WHATSAPP_WEBHOOK_URL=https://SEU-PIXELHUB/api/whatsapp/webhook`
- `PIXELHUB_WHATSAPP_WEBHOOK_SECRET=***` (opcional, para validar webhooks)

---

## 2. O que já temos pronto

### 2.1. Pontos de Entrada

#### ✅ Webhook do Asaas
- **Arquivo:** `src/Controllers/AsaasWebhookController.php`
- **Rota:** `POST /webhook/asaas`
- **O que faz:** Recebe webhooks do Asaas, valida token, grava log, atualiza faturas
- **Validação:** Token via header `HTTP_ASAAS_ACCESS_TOKEN` ou `HTTP_X_ASAAS_ACCESS_TOKEN`
- **Log:** Tabela `asaas_webhook_logs` (event, payload, created_at)
- **Status:** ✅ Produção

#### ✅ Endpoints de WhatsApp (Manual)
- **Arquivo:** `src/Controllers/BillingCollectionsController.php`
- **Rotas:**
  - `GET /billing/whatsapp-modal` - Exibe modal de cobrança
  - `POST /billing/whatsapp-sent` - Marca mensagem como enviada
  - `GET /billing/tenant-reminder` - Dados para cobrança agregada
  - `POST /billing/tenant-reminder-sent` - Marca cobrança agregada como enviada
- **O que faz:** Gera link WhatsApp Web, registra envio em `billing_notifications`
- **Status:** ✅ Produção (mas é manual, não automático)

#### ✅ Endpoints de WhatsApp Genérico
- **Arquivo:** `src/Controllers/TenantsController.php`
- **Rotas:**
  - `POST /tenants/whatsapp-generic-log` - Registra envio genérico
  - `GET /tenants/whatsapp-timeline-ajax` - Timeline de mensagens
- **O que faz:** Registra envios não relacionados a cobrança em `whatsapp_generic_logs`
- **Status:** ✅ Produção

#### ✅ Chat Interno
- **Arquivo:** `src/Controllers/ChatController.php`
- **Rotas:**
  - `GET /chat/order` - Exibe chat vinculado a pedido
  - `POST /chat/message` - Envia mensagem no chat
  - `GET /chat/messages` - Lista mensagens (AJAX)
- **O que faz:** Chat vinculado a `service_orders`, integrado com IA (AIOrchestratorController)
- **Status:** ✅ Produção (mas é específico para pedidos de serviço)

### 2.2. Modelo de Dados

#### ✅ Tabela: `billing_notifications`
- **Migration:** `database/migrations/20251118_create_billing_notifications_table.php`
- **Campos principais:**
  - `id`, `tenant_id`, `invoice_id`
  - `channel` (padrão: 'whatsapp_web')
  - `template` (pre_due, overdue_3d, overdue_7d, bulk_reminder)
  - `status` (prepared, sent_manual, sent_auto, failed)
  - `message`, `phone_raw`, `phone_normalized`
  - `sent_at`, `created_at`, `updated_at`, `last_error`
- **Índices:** tenant_id, invoice_id, status
- **Relacionamentos:** FK para `tenants`, FK para `billing_invoices`
- **Gaps:** ❌ Sem `trace_id`, ❌ Sem `correlation_id`, ❌ Sem `event_id`, ❌ Sem `source_system`

#### ✅ Tabela: `whatsapp_generic_logs`
- **Migration:** `database/migrations/20250128_create_whatsapp_generic_logs_table.php`
- **Campos principais:**
  - `id`, `tenant_id`, `template_id`
  - `phone`, `message`
  - `sent_at`, `created_at`
- **Índices:** tenant_id, template_id, sent_at
- **Relacionamentos:** FK para `tenants`, FK para `whatsapp_templates`
- **Gaps:** ❌ Sem `trace_id`, ❌ Sem `correlation_id`, ❌ Sem `event_id`, ❌ Sem `source_system`, ❌ Sem `status` (sempre assume enviado)

#### ✅ Tabela: `chat_threads`
- **Migration:** `database/migrations/20250131_01_create_chat_threads_table.php`
- **Campos principais:**
  - `id`, `customer_id`, `order_id` (OBRIGATÓRIO)
  - `status` (open, waiting_user, waiting_ai, escalated, closed)
  - `current_step` (step_0_welcome, step_1_identity, etc.)
  - `metadata` (JSON)
  - `created_at`, `updated_at`
- **Índices:** customer_id, order_id, status, current_step
- **Relacionamentos:** FK para `service_orders`, FK para `tenants`
- **Gaps:** ❌ Sem `trace_id`, ❌ Sem `correlation_id`, ❌ Sem `source_system`

#### ✅ Tabela: `chat_messages`
- **Migration:** `database/migrations/20250131_02_create_chat_messages_table.php`
- **Campos principais:**
  - `id`, `thread_id`
  - `role` (system, assistant, user, tool)
  - `content`, `metadata` (JSON)
  - `created_at`
- **Índices:** thread_id, role, created_at
- **Relacionamentos:** FK para `chat_threads`
- **Gaps:** ❌ Sem `trace_id`, ❌ Sem `correlation_id`, ❌ Sem `message_id` (para idempotência)

#### ✅ Tabela: `asaas_webhook_logs`
- **Migration:** `database/migrations/20251118_create_asaas_webhook_logs_table.php`
- **Campos principais:**
  - `id`, `event`, `payload` (LONGTEXT)
  - `created_at`
- **Índices:** event, created_at
- **Gaps:** ❌ Sem `trace_id`, ❌ Sem `processed_at`, ❌ Sem `status` (não sabemos se foi processado com sucesso)

### 2.3. Services

#### ✅ WhatsAppBillingService
- **Arquivo:** `src/Services/WhatsAppBillingService.php`
- **Métodos:**
  - `normalizePhone()` - Normaliza telefone para wa.me
  - `suggestStageForInvoice()` - Sugere estágio de cobrança
  - `buildMessageForInvoice()` - Monta mensagem por fatura
  - `buildReminderMessageForTenant()` - Monta mensagem agregada
  - `prepareNotificationForInvoice()` - Cria registro em billing_notifications
- **Status:** ✅ Produção
- **Gaps:** ❌ Hardcoded (não usa templates dinâmicos), ❌ Sem normalização de payload

#### ✅ WhatsAppHistoryService
- **Arquivo:** `src/Services/WhatsAppHistoryService.php`
- **Métodos:**
  - `getTimelineByTenant()` - Unifica billing_notifications + whatsapp_generic_logs
- **Status:** ✅ Produção
- **Gaps:** ❌ Apenas leitura, não tem correlação

#### ✅ ServiceChatService
- **Arquivo:** `src/Services/ServiceChatService.php`
- **Métodos:**
  - `createThread()` - Cria thread vinculado a pedido
  - `findThread()`, `findThreadByOrder()`
  - `addMessage()` - Adiciona mensagem
  - `getMessages()` - Lista mensagens
  - `updateStatus()`, `updateStep()`, `updateMetadata()`
- **Status:** ✅ Produção
- **Gaps:** ❌ Específico para pedidos, não é genérico

### 2.4. Observabilidade (Parcial)

#### ✅ Logs Básicos
- **Função:** `pixelhub_log()` em `public/index.php`
- **Arquivo:** `logs/pixelhub.log`
- **O que registra:** Logs de rotas, erros, debug
- **Gaps:** ❌ Não é estruturado (JSON), ❌ Sem níveis (INFO/ERROR/WARN), ❌ Sem trace_id

#### ✅ Logs de Webhook
- **Tabela:** `asaas_webhook_logs`
- **O que registra:** Payload completo do webhook
- **Gaps:** ❌ Sem status de processamento, ❌ Sem retry tracking

### 2.5. Segurança (Parcial)

#### ✅ Validação de Webhook
- **Arquivo:** `src/Controllers/AsaasWebhookController.php` (linha 22-34)
- **Método:** Valida token via header
- **Status:** ✅ Implementado

#### ❌ Validação de Origem
- **Gap:** Não há validação de IP ou assinatura HMAC para webhooks
- **Risco:** Webhooks podem ser falsificados se token vazar

#### ❌ Rate Limiting
- **Gap:** Não há rate limit em endpoints de mensagens
- **Risco:** Spam ou abuso

#### ❌ Mascaramento de Tokens
- **Gap:** Tokens podem aparecer em logs/UI
- **Recomendação:** Implementar mascaramento em views

---

## 3. O que falta implementar (Gaps)

### 3.1. Gaps Críticos (P0) - MVP

#### ❌ Sistema de Eventos
**Descrição:** Não existe camada de eventos. Sistemas falam direto com WhatsApp/chat.

**O que falta:**
- Tabela `communication_events` para receber eventos de todos os sistemas
- Endpoint `POST /api/events` para receber eventos estruturados
- Schema de eventos padronizado (event_type, source_system, payload, tenant_id, etc.)

**Impacto:** Sem isso, não há centralização. Cada sistema continua falando direto com canais.

**Arquivos sugeridos:**
- `database/migrations/XXXXXX_create_communication_events_table.php`
- `src/Controllers/EventIngestionController.php`
- `src/Services/EventIngestionService.php`

---

#### ❌ Normalização e Roteamento
**Descrição:** Não há camada que normaliza payloads e decide o que fazer com eventos.

**O que falta:**
- Service `EventNormalizationService` para normalizar eventos de diferentes sistemas
- Service `EventRouterService` para decidir:
  - Qual canal usar (WhatsApp, chat, e-mail)
  - Se precisa de IA
  - Se precisa de intervenção humana
  - Qual template usar
- Tabela `routing_rules` para regras configuráveis

**Impacto:** Sem isso, cada módulo continua com sua própria lógica.

**Arquivos sugeridos:**
- `src/Services/EventNormalizationService.php`
- `src/Services/EventRouterService.php`
- `database/migrations/XXXXXX_create_routing_rules_table.php`

---

#### ❌ Correlação (trace_id / correlation_id)
**Descrição:** Impossível rastrear um evento do início ao fim.

**O que falta:**
- Campo `trace_id` em todas as tabelas de comunicação
- Campo `correlation_id` para agrupar eventos relacionados
- Geração automática de trace_id no EventIngestionService
- Propagação de trace_id em toda a cadeia

**Impacto:** Sem isso, não há auditoria completa. Não dá para saber qual evento gerou qual mensagem.

**Mudanças necessárias:**
- Adicionar `trace_id VARCHAR(36)` em:
  - `communication_events`
  - `billing_notifications`
  - `whatsapp_generic_logs`
  - `chat_messages`
  - `asaas_webhook_logs`
- Adicionar `correlation_id VARCHAR(36)` nas mesmas tabelas

---

#### ❌ Idempotência
**Descrição:** Risco de processar o mesmo evento duas vezes.

**O que falta:**
- Campo `event_id` único por evento (UUID ou hash)
- Campo `idempotency_key` em `communication_events`
- Verificação de duplicação antes de processar
- Tabela `idempotency_keys` para cache de eventos já processados

**Impacto:** Sem isso, mensagens podem ser duplicadas.

**Mudanças necessárias:**
- Adicionar `event_id VARCHAR(36) UNIQUE` em `communication_events`
- Adicionar `idempotency_key VARCHAR(255) UNIQUE` em `communication_events`
- Criar tabela `idempotency_keys` (key, event_id, created_at, expires_at)

---

### 3.2. Gaps Importantes (P1) - Fase 2

#### ❌ Observabilidade Estruturada
**O que falta:**
- Logs estruturados (JSON) com trace_id, level, context
- Tabela `communication_logs` para logs de processamento
- Campos `processed_at`, `processing_time_ms`, `error_message` em eventos
- Dashboard de métricas (eventos/hora, taxa de erro, tempo médio)

**Arquivos sugeridos:**
- `src/Core/StructuredLogger.php`
- `database/migrations/XXXXXX_create_communication_logs_table.php`

---

#### ❌ Retry e Dead Letter Queue
**O que falta:**
- Campo `retry_count` em `communication_events`
- Campo `max_retries` (padrão: 3)
- Campo `next_retry_at` para backoff exponencial
- Tabela `dead_letter_queue` para eventos que falharam após N tentativas
- Worker/cron para processar retries

**Arquivos sugeridos:**
- `database/migrations/XXXXXX_add_retry_fields_to_communication_events.php`
- `database/migrations/XXXXXX_create_dead_letter_queue_table.php`
- `src/Services/EventRetryService.php`
- `src/Workers/EventRetryWorker.php` (ou cron job)

---

#### ❌ Status de Entrega
**O que falta:**
- Campo `delivery_status` em mensagens (queued, sent, delivered, failed, read)
- Campo `delivery_confirmed_at` quando mensagem é confirmada
- Webhook para receber status de entrega do WhatsApp (se usar API oficial)
- Atualização automática de status

**Mudanças necessárias:**
- Adicionar `delivery_status`, `delivery_confirmed_at` em:
  - `billing_notifications`
  - `whatsapp_generic_logs`
- Endpoint `POST /webhook/whatsapp/delivery-status` para receber confirmações

---

### 3.3. Gaps Desejáveis (P2) - Fase 3

#### ❌ Replay de Eventos
**O que falta:**
- Endpoint `POST /api/events/replay` para reprocessar eventos
- Filtros por trace_id, correlation_id, data, source_system
- Modo "dry-run" para testar sem enviar mensagens

---

#### ❌ Automações Avançadas
**O que falta:**
- Sistema de workflows (ex: se evento X, então dispara Y)
- Integração com IA para respostas automáticas
- Escalonamento automático (se não responder em X horas, notifica supervisor)

---

#### ❌ Multi-tenant Isolado
**O que falta:**
- Garantir que eventos de um tenant não vazem para outro
- Validação de tenant_id em todos os endpoints
- Logs separados por tenant (opcional)

---

## 4. Plano de Implementação (Roadmap)

### Fase 1: MVP - Sistema de Eventos Básico (2-3 semanas)

#### Checkpoint 1.1: Estrutura de Eventos (3 dias)
- [ ] Criar migration `communication_events`
  - Campos: id, event_type, source_system, payload (JSON), tenant_id, trace_id, event_id, idempotency_key, status, created_at, processed_at
- [ ] Criar `EventIngestionController` com endpoint `POST /api/events`
- [ ] Criar `EventIngestionService` para validar e gravar eventos
- [ ] Implementar validação de idempotência (verificar event_id antes de gravar)

**Arquivos:**
- `database/migrations/20250201_create_communication_events_table.php`
- `src/Controllers/EventIngestionController.php`
- `src/Services/EventIngestionService.php`

---

#### Checkpoint 1.2: Normalização Básica (3 dias)
- [ ] Criar `EventNormalizationService`
  - Método `normalize()` que recebe evento bruto e retorna evento normalizado
  - Extrai tenant_id, identifica source_system, valida payload
- [ ] Criar schema de evento normalizado (classe `NormalizedEvent`)
- [ ] Testes com eventos do Asaas, eventos de cobrança, eventos de chat

**Arquivos:**
- `src/Services/EventNormalizationService.php`
- `src/Models/NormalizedEvent.php`

---

#### Checkpoint 1.3: Roteamento Simples (4 dias)
- [ ] Criar `EventRouterService`
  - Método `route()` que recebe evento normalizado e decide:
    - Canal (whatsapp, chat, email)
    - Template (se aplicável)
    - Prioridade
- [ ] Criar tabela `routing_rules` (event_type, source_system, channel, template, priority)
- [ ] Implementar regras padrão:
  - `billing.invoice.overdue` → WhatsApp, template `overdue_7d`
  - `billing.invoice.pre_due` → WhatsApp, template `pre_due`
  - `chat.message.received` → Chat interno
- [ ] Integrar com serviços existentes (WhatsAppBillingService, ServiceChatService)

**Arquivos:**
- `src/Services/EventRouterService.php`
- `database/migrations/20250202_create_routing_rules_table.php`
- `database/seeds/SeedDefaultRoutingRules.php`

---

#### Checkpoint 1.4: Migração Gradual (5 dias)
- [ ] Refatorar `AsaasWebhookController` para emitir evento em vez de processar direto
  - Webhook recebe → grava em `communication_events` → EventRouter processa
- [ ] Refatorar `BillingCollectionsController` para usar eventos
  - Ao marcar como enviada, emite evento `billing.notification.sent`
- [ ] Manter compatibilidade: endpoints antigos continuam funcionando
- [ ] Testes end-to-end

**Arquivos:**
- Modificar `src/Controllers/AsaasWebhookController.php`
- Modificar `src/Controllers/BillingCollectionsController.php`

---

### Fase 2: Observabilidade e Confiabilidade (2 semanas)

#### Checkpoint 2.1: Correlação (3 dias)
- [ ] Adicionar `trace_id` e `correlation_id` em todas as tabelas
- [ ] Modificar `EventIngestionService` para gerar trace_id (UUID)
- [ ] Propagar trace_id em toda a cadeia (eventos → mensagens → logs)
- [ ] Criar view `communication_timeline` que une eventos + mensagens por trace_id

**Migrations:**
- `20250210_add_trace_id_to_communication_events.php`
- `20250210_add_trace_id_to_billing_notifications.php`
- `20250210_add_trace_id_to_whatsapp_generic_logs.php`
- `20250210_add_trace_id_to_chat_messages.php`

---

#### Checkpoint 2.2: Logs Estruturados (2 dias)
- [ ] Criar `StructuredLogger` com métodos `info()`, `error()`, `warn()`
- [ ] Logs em JSON com trace_id, level, message, context
- [ ] Integrar em EventIngestionService, EventRouterService
- [ ] Criar tabela `communication_logs` para logs importantes

**Arquivos:**
- `src/Core/StructuredLogger.php`
- `database/migrations/20250212_create_communication_logs_table.php`

---

#### Checkpoint 2.3: Retry e Dead Letter (4 dias)
- [ ] Adicionar campos de retry em `communication_events`
- [ ] Criar `EventRetryService` com backoff exponencial
- [ ] Criar tabela `dead_letter_queue`
- [ ] Criar worker/cron `process-event-retries.php` (executa a cada 5 minutos)
- [ ] Dashboard para visualizar eventos em retry e dead letter

**Arquivos:**
- `database/migrations/20250215_add_retry_fields_to_communication_events.php`
- `database/migrations/20250215_create_dead_letter_queue_table.php`
- `src/Services/EventRetryService.php`
- `public/workers/process-event-retries.php` (ou cron job)

---

#### Checkpoint 2.4: Status de Entrega (3 dias)
- [ ] Adicionar `delivery_status` em mensagens
- [ ] Criar endpoint `POST /webhook/whatsapp/delivery-status` (se usar API oficial)
- [ ] Atualizar status quando mensagem é confirmada
- [ ] Dashboard de métricas (taxa de entrega, tempo médio)

**Arquivos:**
- Modificar migrations de `billing_notifications` e `whatsapp_generic_logs`
- `src/Controllers/WhatsAppDeliveryStatusController.php`

---

### Fase 3: Recursos Avançados (2 semanas)

#### Checkpoint 3.1: Replay (3 dias)
- [ ] Endpoint `POST /api/events/replay`
- [ ] Filtros por trace_id, correlation_id, data, source_system
- [ ] Modo dry-run
- [ ] Interface admin para replay

---

#### Checkpoint 3.2: Automações (5 dias)
- [ ] Sistema de workflows (tabela `workflows`, `workflow_steps`)
- [ ] Integração com IA para respostas automáticas
- [ ] Escalonamento automático

---

#### Checkpoint 3.3: Multi-tenant (2 dias)
- [ ] Validação de tenant_id em todos os endpoints
- [ ] Isolamento de dados por tenant
- [ ] Logs separados (opcional)

---

## 5. Riscos e Recomendações

### 5.1. Riscos Técnicos

#### 🔴 Alto: Migração de Sistemas Existentes
**Risco:** Refatorar sistemas que já estão em produção pode quebrar funcionalidades.

**Mitigação:**
- Manter endpoints antigos funcionando (compatibilidade retroativa)
- Migração gradual: novos eventos usam novo sistema, antigos continuam como estão
- Feature flag para ativar/desativar novo sistema por módulo

---

#### 🟡 Médio: Performance
**Risco:** Processar eventos síncronamente pode travar requisições.

**Mitigação:**
- Processar eventos de forma assíncrona (queue)
- Usar workers/cron para processar eventos em background
- Limitar tempo de processamento (timeout de 30s)

---

#### 🟡 Médio: Duplicação de Mensagens
**Risco:** Sem idempotência, eventos podem ser processados duas vezes.

**Mitigação:**
- Implementar idempotência desde o Checkpoint 1.1
- Usar `event_id` único por evento
- Verificar duplicação antes de processar

---

#### 🟢 Baixo: Escalabilidade
**Risco:** Sistema pode não escalar se volume de eventos crescer muito.

**Mitigação:**
- Usar índices adequados (trace_id, event_id, tenant_id)
- Particionar tabelas por data se necessário (futuro)
- Considerar fila externa (Redis/RabbitMQ) se volume for muito alto

---

### 5.2. Riscos de Segurança

#### 🔴 Alto: Validação de Webhooks
**Risco:** Webhooks podem ser falsificados se token vazar.

**Recomendação:**
- Implementar validação HMAC além do token
- Validar IP de origem (whitelist)
- Rotacionar tokens periodicamente

---

#### 🟡 Médio: Exposição de Dados
**Risco:** Payloads de eventos podem conter dados sensíveis.

**Recomendação:**
- Mascarar dados sensíveis em logs (CPF, telefone, e-mail)
- Não logar payloads completos em produção
- Criptografar payloads sensíveis no banco (opcional)

---

#### 🟡 Médio: Rate Limiting
**Risco:** Endpoints podem ser abusados (spam).

**Recomendação:**
- Implementar rate limiting por IP/tenant
- Limitar número de eventos por minuto por source_system
- Bloquear IPs suspeitos automaticamente

---

### 5.3. Recomendações Gerais

1. **Testes:** Criar testes unitários e de integração para cada checkpoint
2. **Documentação:** Documentar schema de eventos e regras de roteamento
3. **Monitoramento:** Criar dashboard de métricas desde o início
4. **Rollback:** Ter plano de rollback para cada fase
5. **Comunicação:** Avisar equipe sobre mudanças e treinar no novo sistema

---

## 6. Apêndice: Inventário Técnico

### 6.1. Rotas/Endpoints

#### Webhooks
| Método | Rota | Controller | Método | Status |
|--------|------|------------|--------|--------|
| POST | `/webhook/asaas` | AsaasWebhookController | handle | ✅ Produção |

#### WhatsApp (Cobranças)
| Método | Rota | Controller | Método | Status |
|--------|------|------------|--------|--------|
| GET | `/billing/whatsapp-modal` | BillingCollectionsController | showWhatsAppModal | ✅ Produção |
| POST | `/billing/whatsapp-sent` | BillingCollectionsController | markWhatsAppSent | ✅ Produção |
| GET | `/billing/tenant-reminder` | BillingCollectionsController | getTenantReminderData | ✅ Produção |
| POST | `/billing/tenant-reminder-sent` | BillingCollectionsController | markTenantReminderSent | ✅ Produção |

#### WhatsApp (Genérico)
| Método | Rota | Controller | Método | Status |
|--------|------|------------|--------|--------|
| POST | `/tenants/whatsapp-generic-log` | TenantsController | logGenericWhatsApp | ✅ Produção |
| GET | `/tenants/whatsapp-timeline-ajax` | TenantsController | getWhatsAppTimelineAjax | ✅ Produção |

#### Chat Interno
| Método | Rota | Controller | Método | Status |
|--------|------|------------|--------|--------|
| GET | `/chat/order` | ChatController | show | ✅ Produção |
| POST | `/chat/message` | ChatController | sendMessage | ✅ Produção |
| GET | `/chat/messages` | ChatController | getMessages | ✅ Produção |

#### ❌ Endpoints Faltando
| Método | Rota | Descrição | Prioridade |
|--------|------|-----------|------------|
| POST | `/api/events` | Receber eventos de sistemas | P0 |
| POST | `/api/events/replay` | Reprocessar eventos | P2 |
| POST | `/webhook/whatsapp/delivery-status` | Status de entrega WhatsApp | P1 |

---

### 6.2. Tabelas/Migrations

#### Tabelas Existentes
| Tabela | Migration | Campos Principais | Gaps |
|--------|-----------|-------------------|------|
| `billing_notifications` | `20251118_create_billing_notifications_table.php` | tenant_id, invoice_id, channel, template, status, message, phone_raw, phone_normalized, sent_at | ❌ Sem trace_id, ❌ Sem correlation_id, ❌ Sem event_id |
| `whatsapp_generic_logs` | `20250128_create_whatsapp_generic_logs_table.php` | tenant_id, template_id, phone, message, sent_at | ❌ Sem trace_id, ❌ Sem correlation_id, ❌ Sem status |
| `chat_threads` | `20250131_01_create_chat_threads_table.php` | customer_id, order_id, status, current_step, metadata | ❌ Sem trace_id, ❌ Sem correlation_id |
| `chat_messages` | `20250131_02_create_chat_messages_table.php` | thread_id, role, content, metadata | ❌ Sem trace_id, ❌ Sem correlation_id |
| `asaas_webhook_logs` | `20251118_create_asaas_webhook_logs_table.php` | event, payload, created_at | ❌ Sem trace_id, ❌ Sem processed_at, ❌ Sem status |

#### ❌ Tabelas Faltando
| Tabela | Descrição | Prioridade |
|--------|----------|------------|
| `communication_events` | Eventos centralizados | P0 |
| `routing_rules` | Regras de roteamento | P0 |
| `idempotency_keys` | Cache de eventos processados | P0 |
| `communication_logs` | Logs estruturados | P1 |
| `dead_letter_queue` | Eventos que falharam | P1 |
| `workflows` | Automações avançadas | P2 |

---

### 6.3. Services

#### Services Existentes
| Service | Arquivo | Métodos Principais | Status |
|---------|---------|-------------------|--------|
| WhatsAppBillingService | `src/Services/WhatsAppBillingService.php` | normalizePhone, suggestStageForInvoice, buildMessageForInvoice, buildReminderMessageForTenant | ✅ Produção |
| WhatsAppHistoryService | `src/Services/WhatsAppHistoryService.php` | getTimelineByTenant | ✅ Produção |
| ServiceChatService | `src/Services/ServiceChatService.php` | createThread, addMessage, getMessages, updateStatus | ✅ Produção |

#### ❌ Services Faltando
| Service | Descrição | Prioridade |
|---------|-----------|------------|
| EventIngestionService | Receber e validar eventos | P0 |
| EventNormalizationService | Normalizar payloads | P0 |
| EventRouterService | Decidir roteamento | P0 |
| EventRetryService | Processar retries | P1 |
| StructuredLogger | Logs estruturados | P1 |

---

### 6.4. Jobs/Queues/Workers

#### ❌ Não Existe Sistema de Filas
**Gap:** Não há sistema de filas/workers. Tudo é processado síncronamente.

**Recomendação:**
- Usar cron jobs para processar eventos em background
- Criar `public/workers/process-event-retries.php` (executa a cada 5 minutos)
- Considerar Redis/RabbitMQ no futuro se volume crescer

---

### 6.5. Variáveis de Ambiente

#### Existentes (Relacionadas)
| Variável | Descrição | Onde Usada |
|----------|-----------|------------|
| `ASAAS_WEBHOOK_TOKEN` | Token para validar webhooks do Asaas | AsaasWebhookController |

#### ❌ Faltando
| Variável | Descrição | Prioridade |
|----------|-----------|------------|
| `EVENT_INGESTION_SECRET` | Secret para validar eventos de sistemas externos | P0 |
| `WHATSAPP_API_KEY` | Chave da API oficial do WhatsApp (se usar) | P1 |
| `WHATSAPP_WEBHOOK_SECRET` | Secret para validar webhooks do WhatsApp | P1 |
| `MAX_EVENT_RETRIES` | Número máximo de retries (padrão: 3) | P1 |
| `EVENT_PROCESSING_TIMEOUT` | Timeout para processar evento (padrão: 30s) | P1 |

---

## 7. Conclusão

O PixelHub possui **base sólida** para comunicação (WhatsApp, chat, webhooks), mas **não está centralizado** conforme a arquitetura proposta. A implementação deve seguir o roadmap em 3 fases, priorizando:

1. **Fase 1 (MVP)**: Sistema de eventos + normalização + roteamento básico
2. **Fase 2**: Observabilidade + retry + dead letter
3. **Fase 3**: Replay + automações + multi-tenant avançado

**Próximo passo:** Aprovar roadmap e iniciar Fase 1, Checkpoint 1.1.

---

**Documento gerado em:** 2025-01-31  
**Versão:** 1.0

