# Checklist de Implementação - Fase 1 MVP

**Data:** 2025-01-31  
**Status:** Implementação Completa

---

## ✅ Componentes Implementados

### 1. WhatsApp Gateway Client
- [x] `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php`
- [x] Métodos: listChannels, createChannel, getChannel, getQr, sendText, setChannelWebhook, setGlobalWebhook
- [x] Autenticação via header `X-Gateway-Secret`
- [x] Tratamento de erros e timeouts

### 2. WhatsApp Webhook Controller
- [x] `src/Controllers/WhatsAppWebhookController.php`
- [x] Rota: `POST /api/whatsapp/webhook`
- [x] Validação de secret (opcional)
- [x] Mapeamento de eventos: message → whatsapp.inbound.message, message.ack → whatsapp.delivery.ack, connection.update → whatsapp.connection.update
- [x] Resolução de tenant_id pelo channel_id

### 3. Sistema de Eventos
- [x] Migration: `database/migrations/20250201_create_communication_events_table.php`
- [x] `src/Controllers/EventIngestionController.php` - Rota: `POST /api/events`
- [x] `src/Services/EventIngestionService.php`
  - [x] Geração de trace_id e event_id (UUID v4)
  - [x] Cálculo de idempotency_key
  - [x] Verificação de idempotência
  - [x] Inserção de eventos

### 4. Normalização e Roteamento
- [x] `src/Services/EventNormalizationService.php`
  - [x] Normalização de eventos
  - [x] Resolução de tenant_id (por channel, invoice, etc.)
- [x] `src/Services/EventRouterService.php`
  - [x] Busca de regras de roteamento
  - [x] Roteamento para WhatsApp (via gateway)
  - [x] Roteamento para chat (placeholder)
  - [x] Roteamento para email (placeholder)
- [x] Migration: `database/migrations/20250201_create_routing_rules_table.php`
- [x] Seeder: `database/seeds/SeedDefaultRoutingRules.php`

### 5. Tenant Message Channels
- [x] Migration: `database/migrations/20250201_create_tenant_message_channels_table.php`
- [x] Mapeamento tenant_id → channel_id

### 6. Rotas
- [x] `POST /api/whatsapp/webhook` → WhatsAppWebhookController@handle
- [x] `POST /api/events` → EventIngestionController@handle

---

## 📋 Próximos Passos (Execução)

### 1. Executar Migrations

```bash
# Executar migrations
php database/migrate.php
```

Migrations a executar:
- `20250201_create_communication_events_table.php`
- `20250201_create_routing_rules_table.php`
- `20250201_create_tenant_message_channels_table.php`

### 2. Executar Seeder

```bash
# Executar seeder de regras padrão
php -r "
require 'database/migrate.php';
\$db = PixelHub\Core\DB::getConnection();
\$seeder = new SeedDefaultRoutingRules();
\$seeder->run(\$db);
echo 'Regras padrão criadas!' . PHP_EOL;
"
```

### 3. Configurar Variáveis de Ambiente

Adicionar ao `.env`:

```env
# WPP Gateway
WPP_GATEWAY_BASE_URL=https://wpp.pixel12digital.com.br
WPP_GATEWAY_SECRET=seu_secret_aqui

# Webhook do PixelHub
PIXELHUB_WHATSAPP_WEBHOOK_URL=https://painel.pixel12digital.com.br/api/whatsapp/webhook
PIXELHUB_WHATSAPP_WEBHOOK_SECRET=seu_webhook_secret_aqui

# Event Ingestion
EVENT_INGESTION_SECRET=seu_event_secret_aqui
```

### 4. Testes

#### Teste 1: Conexão com Gateway
```php
$client = new \PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient();
$result = $client->listChannels();
var_dump($result);
```

#### Teste 2: Criar Channel
```php
$client = new \PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient();
$result = $client->createChannel('test_channel_123');
var_dump($result);
```

#### Teste 3: Obter QR
```php
$client = new \PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient();
$result = $client->getQr('test_channel_123');
var_dump($result);
```

#### Teste 4: Enviar Mensagem
```php
$client = new \PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient();
$result = $client->sendText('test_channel_123', '5511999999999', 'Teste de mensagem');
var_dump($result);
```

#### Teste 5: Webhook Inbound
Simular POST para `/api/whatsapp/webhook`:

```bash
curl -X POST https://painel.pixel12digital.com.br/api/whatsapp/webhook \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Secret: seu_webhook_secret" \
  -d '{
    "event": "message",
    "channel": "test_channel_123",
    "from": "5511999999999",
    "body": "Olá, esta é uma mensagem de teste"
  }'
```

#### Teste 6: Event Ingestion
```bash
curl -X POST https://painel.pixel12digital.com.br/api/events \
  -H "Content-Type: application/json" \
  -H "X-Event-Secret: seu_event_secret" \
  -d '{
    "event_type": "billing.invoice.overdue",
    "source_system": "billing",
    "tenant_id": 1,
    "payload": {
      "invoice_id": 123,
      "to": "5511999999999",
      "text": "Sua fatura está vencida"
    }
  }'
```

#### Teste 7: Idempotência
Enviar o mesmo evento duas vezes e verificar que não duplica:

```bash
# Primeira vez
curl -X POST https://painel.pixel12digital.com.br/api/events \
  -H "Content-Type: application/json" \
  -H "X-Event-Secret: seu_event_secret" \
  -d '{
    "event_type": "test.event",
    "source_system": "test",
    "payload": {
      "id": "test_123"
    }
  }'

# Segunda vez (deve retornar mesmo event_id)
curl -X POST https://painel.pixel12digital.com.br/api/events \
  -H "Content-Type: application/json" \
  -H "X-Event-Secret: seu_event_secret" \
  -d '{
    "event_type": "test.event",
    "source_system": "test",
    "payload": {
      "id": "test_123"
    }
  }'
```

---

## 🔍 Verificações

### Banco de Dados
```sql
-- Verificar tabelas criadas
SHOW TABLES LIKE 'communication_events';
SHOW TABLES LIKE 'routing_rules';
SHOW TABLES LIKE 'tenant_message_channels';

-- Verificar regras de roteamento
SELECT * FROM routing_rules;

-- Verificar eventos
SELECT * FROM communication_events ORDER BY created_at DESC LIMIT 10;
```

### Logs
Verificar `logs/pixelhub.log` para:
- `[WhatsAppGateway]` - Requisições ao gateway
- `[WhatsAppWebhook]` - Eventos recebidos
- `[EventIngestion]` - Eventos ingeridos
- `[EventRouter]` - Roteamento de eventos

---

## ⚠️ Observações

1. **UUID**: Implementado sem dependência externa (função `generateUuid()` em EventIngestionService)
2. **Match Expression**: Substituído por `switch` para compatibilidade
3. **Autoload**: Certificar que `src/Integrations/` está no autoload (já está via PSR-4)
4. **Secrets**: Nunca logar secrets em texto puro
5. **Timeouts**: Gateway client tem timeout de 30s (configurável)

---

## 📚 Documentação Relacionada

- `docs/AUDITORIA_CENTRAL_COMUNICACAO_PIXELHUB.md` - Auditoria completa
- `docs/FASE1_WPP_GATEWAY.md` - Documentação da Fase 1

---

**Checklist criado em:** 2025-01-31  
**Versão:** 1.0

