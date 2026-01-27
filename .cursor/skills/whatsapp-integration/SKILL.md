---
name: whatsapp-integration
description: Integração completa com WhatsApp via WPP Gateway. Trabalha com envio e recebimento de textos, mídias (imagens, áudios, vídeos, PDFs), processamento de webhooks, e gerenciamento de canais. Use quando trabalhar com WhatsApp, gateway WPP Connect, webhooks, ou comunicação via WhatsApp no projeto PixelHub.
---

# Integração WhatsApp - PixelHub

## Visão Geral

O projeto usa WPP Gateway (WPP Connect) na VPS para comunicação WhatsApp. O projeto no hostmidia se conecta ao gateway via API REST.

**Arquitetura:**
- Gateway: VPS (https://wpp.pixel12digital.com.br)
- Projeto: Servidor hostmidia (banco local, serve dev e produção)
- Autenticação: Header `X-Gateway-Secret`

## Cliente Gateway

**Classe:** `PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient`

### Configuração

```php
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;

$client = new WhatsAppGatewayClient(
    $baseUrl,  // Opcional, usa WPP_GATEWAY_BASE_URL do .env
    $secret,   // Opcional, usa GatewaySecret::getDecrypted()
    $timeout   // Opcional, padrão 30s
);
```

### Métodos Disponíveis

#### Envio de Mensagens

**Texto:**
```php
$result = $client->sendText($channelId, $to, $text, $metadata);
// $to: formato E.164 (ex: "5511999999999")
```

**Áudio PTT (voice note):**
```php
$result = $client->sendAudioBase64Ptt($channelId, $to, $base64Ptt, $metadata);
// $base64Ptt: OGG/Opus em base64 (pode ter prefixo data:audio/...;base64,)
// Limite: 16MB
```

**Imagem:**
```php
$result = $client->sendImage($channelId, $to, $base64, $url, $caption, $metadata);
// Fornecer $base64 OU $url (não ambos)
```

**Documento/PDF:**
```php
$result = $client->sendDocument($channelId, $to, $base64, $url, $fileName, $caption, $metadata);
// $fileName: obrigatório (ex: "documento.pdf")
```

**Vídeo:**
```php
$result = $client->sendVideo($channelId, $to, $base64, $url, $caption, $metadata);
```

#### Download de Mídias

```php
$result = $client->downloadMedia($channelId, $mediaId);
// Retorna: ['success' => bool, 'data' => string (binary), 'mime_type' => string]
```

#### Gerenciamento de Canais

```php
// Listar canais
$channels = $client->listChannels();

// Criar canal
$result = $client->createChannel($channelId);

// Obter canal
$channel = $client->getChannel($channelId);

// QR Code
$qr = $client->getQr($channelId);
```

#### Webhooks

```php
// Webhook por canal
$client->setChannelWebhook($channelId, $url, $secret);

// Webhook global
$client->setGlobalWebhook($url, $secret);
```

## Webhook de Recebimento

**Controller:** `PixelHub\Controllers\WhatsAppWebhookController`
**Rota:** `POST /api/whatsapp/webhook`

### Fluxo de Processamento

1. **Webhook recebe evento** → `WhatsAppWebhookController::handle()`
2. **Valida secret** (se configurado via `PIXELHUB_WHATSAPP_WEBHOOK_SECRET`)
3. **Extrai channel_id** de múltiplas fontes (sessionId, channel, metadata)
4. **Resolve tenant_id** via `tenant_message_channels`
5. **Ingere evento** → `EventIngestionService::ingest()`
6. **Processa mídia** (se houver) → `WhatsAppMediaService::processMediaFromEvent()`
7. **Resolve conversa** → `ConversationService::resolveConversation()`

### Tipos de Eventos

- `message` → `whatsapp.inbound.message`
- `message.ack` → `whatsapp.delivery.ack`
- `connection.update` → `whatsapp.connection.update`
- `message.sent` → `whatsapp.outbound.message`

## Processamento de Mídias

**Service:** `PixelHub\Services\WhatsAppMediaService`

### Fluxo Automático

1. Evento ingerido com `event_type = 'whatsapp.inbound.message'`
2. `EventIngestionService` chama `processMediaFromEvent()` automaticamente
3. Service detecta mídia no payload (Baileys, WPP Connect, base64)
4. Baixa mídia via `WhatsAppGatewayClient::downloadMedia()`
5. Salva em `storage/whatsapp-media/tenant-{id}/YYYY/MM/DD/`
6. Registra em `communication_media`

### Formatos Suportados

- **Áudio:** OGG/Opus (PTT), pode vir em base64 no campo `text`
- **Imagem:** JPEG, PNG (pode vir em base64 no campo `text`)
- **Vídeo:** MP4, WebM
- **Documento:** PDF, DOC, DOCX

### Endpoint de Mídia

**Rota:** `GET /communication-hub/media?path=whatsapp-media/...`
**Controller:** `CommunicationHubController::serveMedia()`
**Autenticação:** Requer `Auth::requireInternal()`

## Envio via Painel de Comunicação

**Controller:** `PixelHub\Controllers\CommunicationHubController`
**Método:** `send()`
**Rota:** `POST /communication-hub/send`

### Tipos de Mensagem

- **text:** Usa `sendText()`
- **audio:** Usa `sendAudioBase64Ptt()` (base64 do áudio)
- **image:** Usa `sendImage()` (base64 ou URL)
- **document:** Usa `sendDocument()` (base64 ou URL)
- **video:** Usa `sendVideo()` (base64 ou URL)

### Payload de Envio

```json
{
  "conversation_id": 123,
  "message": "Texto da mensagem",
  "message_type": "text|audio|image|document|video",
  "media_base64": "...",  // Para mídias
  "media_url": "...",      // Alternativa ao base64
  "file_name": "...",      // Para documentos
  "caption": "..."         // Opcional para mídias
}
```

## Estrutura de Banco de Dados

### Tabelas Principais

**`communication_events`**
- Armazena todos os eventos (inbound/outbound)
- Campos: `event_id` (UUID), `event_type`, `payload`, `metadata`, `tenant_id`, `status`

**`communication_media`**
- Armazena mídias processadas
- Campos: `event_id`, `media_id`, `media_type`, `mime_type`, `stored_path`, `file_name`, `file_size`

**`conversations`**
- Conversas unificadas
- Campos: `conversation_key`, `contact_external_id`, `remote_key`, `channel_id`, `tenant_id`

**`tenant_message_channels`**
- Canais configurados por tenant
- Campos: `channel_id`, `session_id`, `tenant_id`, `provider`, `is_enabled`

## Padrões e Convenções

### Normalização de Telefones

- Sempre usar formato E.164 (ex: "5511999999999")
- Usar `PhoneNormalizer::toE164OrNull()` para normalizar
- Remover sufixos `@c.us`, `@s.whatsapp.net`, etc.

### Channel ID

- Case-insensitive (normalizar para comparação)
- Pode ter espaços (remover para comparação)
- Prioridade: `sessionId` > `channelId` > `channel`

### Resolução de Tenant

1. Busca em `tenant_message_channels` por `channel_id`
2. Se não encontrar, tenta canais compartilhados (tenant_id NULL)
3. Se ainda não encontrar, evento fica com `tenant_id = NULL`

### Logs Padrão

- `[HUB_WEBHOOK_IN]` - Entrada de webhook
- `[HUB_MSG_SAVE]` - Tentativa de salvar evento
- `[HUB_MSG_SAVE_OK]` - Evento salvo com sucesso
- `[HUB_MSG_DROP]` - Evento descartado (duplicado)
- `[HUB_CHANNEL_ID_EXTRACTION]` - Extração de channel_id

## Troubleshooting

### Mídia não baixa

1. Verificar se `channel_id` está correto no evento
2. Verificar se gateway está acessível
3. Verificar logs de `WhatsAppMediaService::processMediaFromEvent()`
4. Verificar se tabela `communication_media` existe

### Evento não cria conversa

1. Verificar se `shouldCreateConversation()` retorna true
2. Verificar se `contact_external_id` está presente
3. Verificar logs de `ConversationService::resolveConversation()`

### Envio falha

1. Verificar se sessão está conectada no gateway
2. Verificar timeout (aumentar para mídias grandes)
3. Verificar formato de base64 (remover prefixo data: se houver)
4. Verificar tamanho (limite 16MB para áudio)

### Timeout de Áudio (Problema Comum)

**Sintoma:** Áudio de 4 segundos demora 48+ segundos e retorna erro 500 com mensagem "O gateway WPPConnect está demorando mais de 30 segundos para processar o áudio."

**Causa:** Timeout do Nginx na VPS está muito baixo (60s padrão). O gateway precisa de mais tempo para processar áudios.

**Solução:**

1. **Aumentar timeouts do Nginx na VPS:**
   ```bash
   # Editar arquivo de configuração (geralmente em /etc/nginx/sites-available/whatsapp-multichannel)
   sudo nano /etc/nginx/sites-available/whatsapp-multichannel
   
   # Alterar de 60s para 120s:
   proxy_connect_timeout 120s;
   proxy_send_timeout 120s;
   proxy_read_timeout 120s;
   
   # Testar e recarregar
   sudo nginx -t
   sudo systemctl reload nginx
   ```

2. **Verificar timeout do WPPConnect (se aplicável):**
   ```bash
   # Procurar por timeouts de 30s no código do gateway
   find /usr/src -name "*.js" -o -name ".env" 2>/dev/null | xargs grep -l "timeout.*30\|30000" 2>/dev/null
   ```

**Diagnóstico:**

- **No projeto (Hostmidia):** Timeout PHP está em 120s, cURL em 90s (suficiente)
- **Na VPS (Gateway):** Verificar timeouts do Nginx:
  ```bash
  grep -r "proxy_read_timeout\|proxy_connect_timeout\|proxy_send_timeout" /etc/nginx/ 2>/dev/null
  ```

**Arquitetura de Deployment:**

- **Gateway WPPConnect:** VPS (https://wpp.pixel12digital.com.br)
- **Projeto PixelHub:** Servidor Hostmidia (banco local, serve dev e produção)
- **Comunicação:** API REST do projeto → Gateway na VPS
- **Autenticação:** Header `X-Gateway-Secret` (criptografado via `GatewaySecret`)

**Scripts de Diagnóstico:**

- `database/diagnostico-gateway-audio-vps.sh` - Diagnóstico completo na VPS
- `database/verificar-timeout-wppconnect.sh` - Verificar timeout do WPPConnect
- `database/encontrar-config-nginx.sh` - Encontrar configuração do Nginx

## Referências

- Gateway Base URL: `WPP_GATEWAY_BASE_URL` no `.env`
- Gateway Secret: `WPP_GATEWAY_SECRET` (criptografado via `GatewaySecret`)
- Webhook Secret: `PIXELHUB_WHATSAPP_WEBHOOK_SECRET` no `.env`
- **Arquitetura:** Gateway na VPS, projeto no Hostmidia
