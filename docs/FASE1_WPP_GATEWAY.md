# Fase 1 - Integração WPP Gateway e Sistema de Eventos

**Data:** 2025-01-31  
**Status:** Em Implementação

---

## Objetivo

Implementar o MVP do "Posto Digital Central de Comunicação" integrando o WPP Gateway como adapter de WhatsApp, com sistema de eventos, normalização, roteamento, correlação e idempotência.

---

## Arquitetura: WPP Gateway como Adapter

### Visão Geral

O WPP Gateway (https://wpp.pixel12digital.com.br) é o **único ponto de integração** com WhatsApp. O PixelHub não fala diretamente com WhatsApp, apenas com o gateway.

```
Sistemas da Holding → PixelHub (Eventos) → Roteamento → WPP Gateway → WhatsApp
                                                              ↓
                                                         Webhook → PixelHub → Eventos
```

### Endpoints do Gateway

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/api/channels` | Criar novo canal (session) |
| GET | `/api/channels` | Listar todos os canais |
| GET | `/api/channels/:channel/qr` | Obter QR code para conectar |
| POST | `/api/messages` | Enviar mensagem |
| POST | `/api/webhooks` | Configurar webhook global |
| POST | `/api/channels/:channel/webhook` | Configurar webhook por canal |

### Autenticação

Todas as requisições ao gateway devem incluir o header:
```
X-Gateway-Secret: {WPP_GATEWAY_SECRET}
```

### Eventos Recebidos (Webhook)

O gateway envia eventos para o PixelHub via webhook configurado:

| Evento | Descrição | Payload |
|--------|-----------|---------|
| `message` | Mensagem recebida | `{ event: "message", channel: "...", from: "...", body: "...", ... }` |
| `message.ack` | Confirmação de entrega/leitura | `{ event: "message.ack", messageId: "...", status: "delivered|read" }` |
| `connection.update` | Mudança de status da conexão | `{ event: "connection.update", channel: "...", status: "connected|disconnected" }` |

---

## Mapeamento Tenant → Channel

### Tabela: `tenant_message_channels`

Cada tenant pode ter múltiplos canais de comunicação, mas para WhatsApp, cada tenant terá **um channel único**.

```sql
CREATE TABLE tenant_message_channels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    provider VARCHAR(50) NOT NULL DEFAULT 'wpp_gateway',
    channel_id VARCHAR(100) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    webhook_configured BOOLEAN DEFAULT FALSE,
    metadata JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY unique_tenant_provider (tenant_id, provider),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

### Fluxo de Configuração

1. **Habilitar WhatsApp para tenant:**
   - Criar channel no gateway via `POST /api/channels`
   - Salvar `channel_id` em `tenant_message_channels`
   - Obter QR code via `GET /api/channels/:channel/qr`
   - Exibir QR para conectar WhatsApp

2. **Configurar Webhook:**
   - Após conectar, configurar webhook do channel
   - `POST /api/channels/:channel/webhook` com URL do PixelHub
   - Marcar `webhook_configured = TRUE`

---

## Variáveis de Ambiente

Adicionar ao `.env`:

```env
# WPP Gateway
WPP_GATEWAY_BASE_URL=https://wpp.pixel12digital.com.br
WPP_GATEWAY_SECRET=seu_secret_aqui

# Webhook do PixelHub (para receber eventos do gateway)
PIXELHUB_WHATSAPP_WEBHOOK_URL=https://painel.pixel12digital.com.br/api/whatsapp/webhook
PIXELHUB_WHATSAPP_WEBHOOK_SECRET=seu_webhook_secret_aqui

# Event Ingestion (para sistemas internos emitirem eventos)
EVENT_INGESTION_SECRET=seu_event_secret_aqui
```

---

## Fluxos de Comunicação

### Outbound (PixelHub → WhatsApp)

1. Sistema emite evento (ex: `billing.invoice.overdue`)
2. EventIngestionService grava em `communication_events`
3. EventRouterService identifica: `billing.invoice.*` → WhatsApp
4. EventRouterService chama WhatsAppGatewayClient.sendText()
5. Gateway envia mensagem para WhatsApp
6. EventRouterService atualiza `communication_events.status = 'processed'`

### Inbound (WhatsApp → PixelHub)

1. Gateway recebe mensagem do WhatsApp
2. Gateway envia webhook para PixelHub: `POST /api/whatsapp/webhook`
3. WhatsAppWebhookController recebe e valida
4. Cria evento: `whatsapp.inbound.message`
5. EventRouterService roteia para chat interno ou IA
6. Resposta (se necessário) volta pelo mesmo fluxo outbound

---

## Componentes Implementados

### 1. WhatsAppGatewayClient
**Arquivo:** `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php`

Cliente HTTP para comunicação com o WPP Gateway.

**Métodos:**
- `listChannels()` - Lista todos os canais
- `createChannel(string $channelId)` - Cria novo canal
- `getChannel(string $channelId)` - Obtém dados do canal
- `getQr(string $channelId)` - Obtém QR code
- `sendText(string $channelId, string $to, string $text, ?array $metadata)` - Envia mensagem
- `setChannelWebhook(string $channelId, string $url, ?string $secret)` - Configura webhook do canal
- `setGlobalWebhook(string $url, ?string $secret)` - Configura webhook global

### 2. WhatsAppWebhookController
**Arquivo:** `src/Controllers/WhatsAppWebhookController.php`

Recebe webhooks do gateway e converte em eventos.

**Rota:** `POST /api/whatsapp/webhook`

**Validação:**
- Verifica `PIXELHUB_WHATSAPP_WEBHOOK_SECRET` (se configurado)
- Aceita eventos: `message`, `message.ack`, `connection.update`

**Ações:**
- `message` → Cria evento `whatsapp.inbound.message`
- `message.ack` → Cria evento `whatsapp.delivery.ack`
- `connection.update` → Cria evento `whatsapp.connection.update`

### 3. Sistema de Eventos

#### Tabela: `communication_events`
Armazena todos os eventos do sistema.

#### EventIngestionController
**Rota:** `POST /api/events`

Permite que sistemas internos emitam eventos estruturados.

#### EventIngestionService
- Gera `trace_id` (UUID)
- Gera `event_id` (UUID)
- Calcula `idempotency_key` (source_system + external_id + event_type)
- Garante idempotência antes de inserir

### 4. Normalização e Roteamento

#### EventNormalizationService
Normaliza eventos de diferentes sistemas para formato padrão.

#### EventRouterService
Decide o que fazer com cada evento baseado em regras configuráveis.

**Tabela:** `routing_rules`

**Regras padrão:**
- `whatsapp.inbound.message` → Chat interno
- `billing.invoice.overdue` → WhatsApp (via gateway)
- `billing.invoice.pre_due` → WhatsApp (via gateway)
- `asaas.payment.*` → Nenhuma ação (apenas log)

---

## Checkpoints de Implementação

### ✅ Checkpoint 1: WhatsAppGatewayClient
- [x] Cliente HTTP implementado
- [x] Métodos principais criados
- [x] Tratamento de erros e timeouts

### ✅ Checkpoint 2: WhatsAppWebhookController
- [x] Endpoint de webhook criado
- [x] Validação de secret
- [x] Conversão de eventos do gateway para eventos internos

### ✅ Checkpoint 3: Sistema de Eventos
- [x] Migration `communication_events`
- [x] EventIngestionController
- [x] EventIngestionService com idempotência

### ✅ Checkpoint 4: Normalização e Roteamento
- [x] EventNormalizationService
- [x] EventRouterService
- [x] Migration `routing_rules`
- [x] Seeder de regras padrão

### ✅ Checkpoint 5: Tenant Channels
- [x] Migration `tenant_message_channels`
- [x] Integração com WhatsAppGatewayClient

### ✅ Checkpoint 6: Testes End-to-End
- [x] Teste conexão: listChannels() e createChannel()
- [x] Teste QR: getQr() e validar conexão
- [x] Teste outbound: sendText()
- [x] Teste inbound: webhook do gateway → evento → roteamento
- [x] Teste idempotência: re-enviar mesmo evento

**Validação Completa:** Ver [VALIDACAO_COMPLETA_WPP_GATEWAY_VPS_WRAPPER.md](./VALIDACAO_COMPLETA_WPP_GATEWAY_VPS_WRAPPER.md)

---

## Próximos Passos

✅ **Infraestrutura Validada** — VPS, Docker, Gateway e WPPConnect estão 100% funcionais.

**Foco Atual:** Configurações e melhorias exclusivamente no PixelHub:
1. Processamento interno de eventos
2. Regras de negócio e roteamento
3. Filas e processamento assíncrono
4. Interface e visualização de eventos

---

**Documento criado em:** 2025-01-31  
**Versão:** 1.0

