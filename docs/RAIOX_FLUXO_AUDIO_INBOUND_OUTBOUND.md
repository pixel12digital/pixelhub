# RAIO-X: Fluxo de ÁUDIO (Inbound e Outbound) – ImobSites e Pixel12 Digital

**Provider:** wpp_gateway / WPPConnect  
**Canais:** ImobSites, Pixel12 Digital (pixel12digital)  
**Data:** 2026

---

## 1) Onde o áudio entra (INBOUND)

### Rotas/endpoints que recebem webhooks

| Método | Rota | Controller@método | Ficheiro |
|--------|------|-------------------|----------|
| POST | `/api/whatsapp/webhook` | `WhatsAppWebhookController@handle` | `src/Controllers/WhatsAppWebhookController.php` |

**Registo da rota:** `public/index.php` linha 442:
```php
$router->post('/api/whatsapp/webhook', 'WhatsAppWebhookController@handle');
```

O gateway (WPPConnect / gateway-wrapper) envia para `https://[DOMÍNIO]/api/whatsapp/webhook` (ex: `https://hub.pixel12digital.com.br/api/whatsapp/webhook`).

---

### Caminho completo: controller → serviço → persistência

```
1. WhatsAppWebhookController::handle()
   └─ src/Controllers/WhatsAppWebhookController.php
   └─ Lê php://input → JSON → payload
   └─ Valida PIXELHUB_WHATSAPP_WEBHOOK_SECRET (se definido)
   └─ mapEventType($eventType) → 'message' → 'whatsapp.inbound.message'
   └─ resolveTenantByChannel($channelId) → tenant_id (tenant_message_channels, provider=wpp_gateway, channel_id)
   └─ EventIngestionService::ingest([ event_type, source_system='wpp_gateway', payload, tenant_id, metadata ])

2. EventIngestionService::ingest()
   └─ src/Services/EventIngestionService.php
   └─ INSERT communication_events (event_id, idempotency_key, event_type, payload, metadata, status=queued)
   └─ Se event_type === 'whatsapp.inbound.message' && source_system === 'wpp_gateway':
        └─ WhatsAppMediaService::processMediaFromEvent($fullEvent)

3. WhatsAppMediaService::processMediaFromEvent()
   └─ src/Services/WhatsAppMediaService.php
   └─ Detecta áudio (base64 no text, ou message.message.audioMessage, ou type=audio/ptt)
   └─ processBase64Audio() OU download via WhatsAppGatewayClient::downloadMedia() + saveMediaRecord()

4. Persistência
   └─ communication_events (evento bruto)
   └─ communication_media (se for mídia: event_id, media_type, mime_type, stored_path, file_name, file_size)
   └─ storage/whatsapp-media/... (arquivo físico)
```

Não existe rota/controller separado para áudio; o tipo **message** é único e a diferenciação é feita no payload e em `WhatsAppMediaService`.

---

### Como o sistema detecta que a mensagem é áudio

**Ficheiro:** `src/Services/WhatsAppMediaService.php`

1. **Base64 no campo `text`** (linhas 29–62, 93–94)  
   - `text = payload['text'] ?? payload['message']['text']`  
   - Se `strlen($text) > 100` e `preg_match('/^[A-Za-z0-9+\/=\s]+$/', $text)`  
   - `base64_decode` → se `substr($decoded, 0, 4) === 'OggS'` → trata como áudio OGG e chama `processBase64Audio()`.

2. **Formato Baileys** (linhas 66–89)  
   - `messageContent = payload['message']['message']`  
   - Se `isset($messageContent['audioMessage'])` → `$baileysMediaType = 'audio'`, `$baileysMediaData = $messageContent['audioMessage']`.

3. **Campo `type` / `message.type`** (linhas 131–134, 191–195)  
   - `$mediaType = $baileysMediaType ?? $payload['type'] ?? $payload['message']['type'] ?? ...`  
   - Considera `in_array($typeCheck, ['audio', 'ptt', 'image', 'video', 'document', 'sticker'])` para decidir que é mídia e obter `mediaId` (inclusive fallbacks).

Resumo: áudio é detectado por **base64 OGG em `text`**, **`audioMessage`** (Baileys) ou **`type` in ['audio','ptt']**.

---

### Exemplo de payload e campos usados

**Exemplo 1 – Áudio em base64 no `text` (formato comum no WPP/gateway):**

```json
{
  "event": "message",
  "channel": "pixel12digital",
  "sessionId": "pixel12digital",
  "message": {
    "id": "ABC123",
    "from": "5511965221349@c.us",
    "text": "T2dnUwACAAAAAAAAAAAA..."
  }
}
```

Campos usados no fluxo de áudio:

- `channel` / `sessionId` / `channelId` → `metadata.channel_id` e para `resolveTenantByChannel` e para `downloadMedia(channelId, mediaId)`.
- `message.text` → se for base64 longo e decodificado com header `OggS` → `processBase64Audio`.
- `message.id` → possível fallback para `mediaId` e para idempotency.

**Exemplo 2 – Áudio com `mediaId` (WPP Connect / Baileys):**

```json
{
  "event": "message",
  "channel": "ImobSites",
  "message": {
    "id": "XYZ",
    "from": "5511999999999@c.us",
    "type": "ptt",
    "mediaId": "MEDIA_xxx",
    "mimetype": "audio/ogg; codecs=opus"
  }
}
```

Ou em estrutura Baileys:

```json
{
  "message": {
    "message": {
      "audioMessage": {
        "url": "...",
        "mimetype": "audio/ogg"
      }
    },
    "key": { "id": "MSG_ID" }
  }
}
```

Campos usados:

- `type` / `message.type` = `'audio'` ou `'ptt'` → mídia de áudio.
- `mediaId` / `media_id` / `mediaUrl` / `message.mediaId` / `message.key.id` etc. → `WhatsAppMediaService` (linhas 102–146) e `downloadMedia(channelId, mediaId)`.
- `mimetype` / `message.mimetype` / `media.mimetype` → `mime_type` em `communication_media` e `getExtensionFromMimeType`.

---

## 2) Download / obtenção do arquivo de áudio

### Existência de download do binário

- **Sim**, em dois modos:
  1. **Base64 no `text`:** não há download; o binário vem em `text`, é decodificado e salvo por `processBase64Audio()` (linhas 307–369).
  2. **`mediaId` / URL:**  
     - `WhatsAppGatewayClient::downloadMedia($channelId, $mediaId)`  
     - `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php` linhas 160–218.

Origem do binário:

- **Base64:** `payload['text']` ou `payload['message']['text']`.
- **Download:**  
  - Se `filter_var($mediaId, FILTER_VALIDATE_URL)` → `$url = $mediaId`.  
  - Senão: `GET {baseUrl}/api/channels/{channelId}/media/{mediaId}`.  
  - Headers: `X-Gateway-Secret`, `Accept: */*`, `CURLOPT_BINARYTRANSFER => true`.  
  - O corpo da resposta é tratado como `$response` (binary string) e devolvido em `['data' => $response, 'mime_type' => $contentType]`.

---

### Normalização (mp3/ogg/opus)

- **Não existe** conversão de formato.  
- O que existe:
  - `getExtensionFromMimeType()` mapeia `audio/ogg`, `audio/oga`, `audio/mpeg`, `audio/mp3`, `audio/wav`, `audio/webm` para `ogg`, `oga`, `mp3`, `wav`, `webm`.
  - Base64 OGG é sempre salvo como `.ogg` e `mime_type = 'audio/ogg'`.  
- **Não há** ffmpeg, nem transcrição para mp3/opus, etc.

---

### Onde salva e como nomeia

**Ficheiro:** `src/Services/WhatsAppMediaService.php`

- **Diretório base:** `__DIR__ . '/../../storage/whatsapp-media'` (linha 535 em `getMediaDir`).
- **Subdivisão:**
  - Por tenant: `whatsapp-media/tenant-{tenant_id}/` se `$tenantId` existir.
  - Por data: `Y/m/d` (ex: `2026/01/16`).
- **Nome do ficheiro:** `bin2hex(random_bytes(16)) . '.' . $extension` (ex: `f6528d90b33fe0db1a41f275ab9c8346.ogg`).
- **`stored_path` em `communication_media`:**  
  `'whatsapp-media/' . (tenant ? "tenant-{$tenantId}/" : '') . $subDir . '/' . $fileName`

Exemplo:  
`whatsapp-media/tenant-1/2026/01/16/f6528d90b33fe0db1a41f275ab9c8346.ogg`

Organização: **tenant → data → ficheiro**. Não há organização por `channel_id` nem por `thread_id`/`conversation_id` no path.

---

### Validações e comportamento em falha

| O quê | Onde | O que acontece se falhar |
|------|------|---------------------------|
| `mediaId` ausente | `WhatsAppMediaService::processMediaFromEvent` | `return null`; log `[WhatsAppMediaService] MediaId não encontrado`. |
| `channel_id` ausente nos metadados | Linhas 218–227 | `saveMediaRecord(..., stored_path=null, file_name=null, file_size=null)`; registo em `communication_media` sem ficheiro. |
| `downloadMedia` falha ou `data` vazio | Linhas 236–240 | `saveMediaRecord(..., null, null, null)`. |
| `file_put_contents` falha | Linhas 262–264, 346–348 | `saveMediaRecord(..., null, null, null)`. |
| `!file_exists($fullPath)` após escrever | Linhas 267–270 | `saveMediaRecord(..., null, null, null)`. |
| `filesize === 0` ou `false` | Linhas 273–278 | `@unlink($fullPath)` e `saveMediaRecord(..., null, null, null)`. |
| Exceção em `processMediaFromEvent` | Linhas 294–296 | `saveMediaRecord(..., null, null, null)`. |
| Exceção em `processMediaFromEvent` no `EventIngestionService` | `EventIngestionService::ingest` linhas 260–264 | `error_log` e continua; não quebra a ingestão do evento. |

Não há validação explícita de:

- Tamanho máximo do ficheiro.
- Duração do áudio.
- Mimetype contra allowlist (apenas uso para extensão e `Content-Type`).

---

## 3) Como o áudio é armazenado e exibido

### Tabelas e colunas

**`communication_media`** (migration `database/migrations/20260116_create_communication_media_table.php`):

| Coluna | Uso para áudio |
|--------|-----------------|
| `event_id` | FK para `communication_events.event_id`. |
| `media_id` | ID no provider ou `event_id` (fallback em base64). |
| `media_type` | `'audio'`, `'voice'` (ou `'ptt'` se vier no payload e não for normalizado). |
| `mime_type` | Ex: `audio/ogg`. |
| `stored_path` | Ex: `whatsapp-media/tenant-1/2026/01/16/xxx.ogg`. |
| `file_name` | Nome do ficheiro (ex: `xxx.ogg`). |
| `file_size` | Tamanho em bytes. |

**`communication_events`:**  
Guarda o `payload` bruto (JSON). Não há colunas dedicadas como `media_url` ou `attachment`; a referência ao áudio é via `communication_media.event_id`.

---

### Como o front recebe e reproduz

- **Leitura no backend:**  
  `WhatsAppMediaService::getMediaByEventId($eventId)` → devolve `type`, `media_type`, `mime_type`, `url`, `path`/`stored_path`, `file_name`, `size`/`file_size`.

- **Construção da URL (getMediaUrl):**  
  - `pixelhub_url('/communication-hub/media?path=' . urlencode($storedPath))`  
  - Ex: `{BASE}/communication-hub/media?path=whatsapp-media%2Ftenant-1%2F2026%2F01%2F16%2Fxxx.ogg`

- **Endpoint de “stream”/entrega:**  
  `GET /communication-hub/media?path=...`  
  - **Controller:** `CommunicationHubController::serveMedia()` (linhas 3510–3572).  
  - **Comportamento:**  
    - `Auth::requireInternal()`.  
    - Path deve começar por `whatsapp-media` (anti path traversal).  
    - `storage/` + path → `readfile()`.  
    - Headers: `Content-Type` (do `communication_media.mime_type` ou `mime_content_type`), `Content-Length`, `Content-Disposition: inline`, `Cache-Control: private, max-age=31536000`.  

Não é um stream adaptativo; é entrega direta do ficheiro. Não há link público nem URL assinada; o acesso exige sessão interna.

---

### Uso no front (thread / lista)

- **`views/communication_hub/thread.php`** (PHP e JS):  
  - Se `mimeType.startsWith('audio/')` ou `mediaType in ['audio','voice']`:  
    - `<audio controls><source src="<?= $media['url'] ?>" type="...">`.
  - Idem em JS (linhas 298–304):  
    - `mimeType.startsWith('audio/') || mediaType === 'audio' || mediaType === 'voice'` → `<audio controls><source src="...">`.

- **`views/communication_hub/index.php`** (lista):  
  - `renderMediaPlayer(media)` (linhas 1491–1506):  
    - `mimeType.startsWith('audio/') || mediaType === 'audio' || mediaType === 'voice'` →  
      `mediaHtml = '<audio controls preload="none" src="' + safeUrl + '"></audio>'`.

Ou seja: o front espera `media.url` (apontando para `/communication-hub/media?path=...`) e `media_type`/`mime_type` para decidir o `<audio>`.

---

## 4) Envio de áudio (OUTBOUND)

### Onde fica a função/serviço de envio

- **Envio de mensagem (Hub):**  
  - `CommunicationHubController::send()`  
  - `src/Controllers/CommunicationHubController.php`, a partir da linha 309.  
  - Rota: `POST /communication-hub/send`.

- **Cliente do gateway:**  
  - `WhatsAppGatewayClient` em `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php`.  
  - Método usado no envio: **apenas `sendText()`** (linhas 86–116).

- **Chamada real (ex.: linha 974 do `CommunicationHubController`):**  
  `$gateway->sendText($targetChannelId, $phoneNormalized, $message, [ ... ])`.

- **Payload para o gateway:**  
  `POST {baseUrl}/api/messages` com  
  `{ "channel": $channelId, "to": $phone, "text": $message, "metadata": {...} }`.

Não existe em todo o projeto:

- `sendAudio`, `sendMedia`, `sendFile`, `sendVoice`, nem endpoint de “upload de mídia” para o gateway.
- Nenhum parâmetro `attachment`, `media_url`, `base64`, `file` no `POST /communication-hub/send` nem no `WhatsAppGatewayClient`.

Conclusão: **envio de áudio (OUTBOUND) não está implementado**; só texto.

---

### Como o canal é escolhido (channel_id, tenant_id, sessionId)

No `CommunicationHubController::send()`:

1. **`thread_id`** (ex: `whatsapp_123`):  
   - Extrai `conversation_id`.  
   - `SELECT tenant_id, channel_id FROM conversations WHERE id = ?`  
   - `channel_id` da conversa é usado como `sessionId` para o gateway.  
   - `tenant_id` da conversa prevalece sobre `tenant_id` do POST.

2. **`channel_id` do POST:**  
   - Ignorado quando há `thread_id` válido.  
   - Usado em fluxos sem `thread_id` (ex. encaminhamento).

3. **`forward_to_all` / `channel_ids`:**  
   - Pode enviar para vários canais; cada um é validado via `tenant_message_channels` (provider=`wpp_gateway`, `channel_id` ou `session_id`).

4. **Validação do sessionId:**  
   - `validateGatewaySessionId($sessionId, $tenantId)` (linhas 3628–3692):  
     - `tenant_message_channels` com `provider='wpp_gateway'`, `is_enabled=1` e `channel_id` ou `session_id` conforme o schema.  
   - O `session_id` canónico é o que vai em `sendText($targetChannelId, ...)`.

Ou seja: o “canal correto” vem de `conversations.channel_id` (ou do POST) e é validado em `tenant_message_channels`; **não** há escolha por tipo de mídia (áudio vs texto).

---

### Como seria enviar áudio (hoje não existe)

- O gateway atual só recebe `{ channel, to, text }` em `POST /api/messages`.  
- No código do Painel **não há**:
  - Upload de ficheiro para o gateway.
  - Envio de URL de mídia.
  - Envio de base64.
  - Chamada a qualquer endpoint do gateway para mídia/áudio.

Para suportar áudio outbound seria necessário:

- Um endpoint no gateway (ex. `POST /api/messages` com `type: 'audio'` e `url`/`base64`/`file`) ou algo equivalente.
- Em `WhatsAppGatewayClient`: algo como `sendAudio($channelId, $to, $audioUrlOuBase64, $opts)`.
- Em `CommunicationHubController::send()`: aceitar `attachment`/`audio` (upload ou URL), escolher `sendText` vs `sendAudio` e montar o payload adequado.

---

### Erros mais comuns e tratamento (envio de texto hoje)

No `send()` e no `WhatsAppGatewayClient`:

- **THREAD_MISSING_CHANNEL_ID:** conversa sem `channel_id` → 400 e mensagem ao utilizador.
- **CHANNEL_NOT_FOUND:** `validateGatewaySessionId` não encontra canal ativo para o `session_id`/`channel_id` e tenant → 400.
- **THREAD_NOT_FOUND:** `conversation_id` inválido → 404.
- **Canal ou mensagem vazios** → 400.
- **`to` vazio para WhatsApp** → 400.
- **Erro de rede/HTTP no gateway:** `request()` devolve `success=false`, `error`; o `CommunicationHubController` propaga em JSON (ex. 500).
- **Timeout:** `CURLOPT_TIMEOUT` 30s (padrão do `WhatsAppGatewayClient`); 60s em `downloadMedia`. Não há retry automático no envio.

Não há tratamento específico para áudio porque o envio de áudio **não existe**.

---

## 5) Problemas e gaps

### 10 riscos/gaps

1. **Outbound de áudio inexistente**  
   - Só `sendText`; não há `sendAudio`/`sendMedia`. O gateway (`/api/messages`) só recebe texto.  
   - **Impacto:** utilizador não pode responder com áudio pelo Hub.

2. **`channel_id` vs `session_id` e multi‑canal**  
   - `tenant_message_channels` pode ter `channel_id` e/ou `session_id`; `resolveTenantByChannel` e webhook usam `channel_id`; `validateGatewaySessionId` e `getSessionIdColumnName` tentam alinhar.  
   - **Risco:** ImobSites vs Pixel12 (pixel12digital) com convenções diferentes (maiúsculas, espaços) podem causar falha de resolução de tenant ou de canal.

3. **`type='ptt'` sem mapeamento de mimetype**  
   - `guessMimeType()` tem `'audio'` e `'voice'` → `audio/ogg`; **não** tem `'ptt'` → cai em `application/octet-stream`.  
   - **Impacto:** extensão pode ficar `bin` e o `<audio>` no browser pode não funcionar.

4. **Base64: `file_put_contents` retornar true sem ficheiro**  
   - Documentado em `docs/DIAGNOSTICO_AUDIOS_RESUMO_FINAL.md`: ficheiro por vezes não aparece em disco apesar de `file_put_contents` true e `stored_path` preenchido.  
   - **Risco:** `communication_media` com `stored_path` mas 404 em `/communication-hub/media`.

5. **`mediaId` incorreto quando há base64**  
   - Em `processBase64Audio` usa-se `event_id` como `media_id` (linha 357). Se depois se tentar download por `mediaId` no gateway, o `event_id` não é um `mediaId` válido → 404.  
   - No fluxo base64 isso não é usado para download, mas a confusão e possíveis reaproveitamentos futuros são um risco.

6. **Falta de idempotência por `message.id` em certos formatos**  
   - `calculateIdempotencyKey` usa `payload['id']`, `messageId`, `message_id`, etc. Se o gateway enviar em estruturas diferentes (ex. `message.key.id`), o idempotency_key pode mudar e gerar duplicados.

7. **Sem validação de tamanho/duração/mimetype para áudio**  
   - Não há limite de tamanho, duração nem allowlist de mimetypes.  
   - **Risco:** ficheiros enormes ou tipos inesperados (ex. executáveis nomeados como áudio) em storage e no endpoint de media.

8. **`/communication-hub/media` sem link assinado e sem rate limit**  
   - Acesso apenas por `Auth::requireInternal()`. Quem tem sessão pode montar URLs com qualquer `path` em `whatsapp-media/`.  
   - **Risco:** enumeração de paths e consumo de banda sem rate limit.

9. **Storage apenas em filesystem**  
   - Tudo em `storage/whatsapp-media/`. Não há S3, GCS, etc.  
   - **Risco:** backups, redundância e escalabilidade dependem só do disco do servidor.

10. **Correlação entre outbound e acks**  
    - O `sendText` devolve `message_id` e `correlationId` do gateway, mas não há no código do Painel um fluxo claro que persista e correlacione com `communication_events` (ex. `whatsapp.outbound.message` ou acks) para garantir rastreio fim‑a‑fim.

---

### Mínimo necessário para ficar correto (passos curtos)

**Inbound (áudio):**

1. **PTT em `guessMimeType`:**  
   - Em `WhatsAppMediaService::guessMimeType`, adicionar  
     `'ptt' => 'audio/ogg'`  
   - Garantir que `getExtensionFromMimeType('audio/ogg')` continue `ogg`.

2. **Validar gravação em disco no base64:**  
   - Em `processBase64Audio` (e `processBase64Image`), após `file_put_contents`:  
     - Verificar `file_exists` e `filesize > 0`.  
     - Se falhar, não gravar `stored_path` (ou gravar `null`), logar e, se possível, retry com path/dir alternativo.

3. **Tamanho máximo para base64:**  
   - Antes de decodificar, checar `strlen` (ex. ≤ 16MB). Rejeitar/truncar com log se ultrapassar.

4. **`channel_id` em `metadata` para base64:**  
   - Em `processBase64Audio`, garantir que `metadata['channel_id']` do evento esteja disponível (o webhook já coloca; o `EventIngestionService` chama `processMediaFromEvent` com o evento completo).  
   - Só é crítico se no futuro se usar `channel_id` para algo além de log (ex. download fallback).

**Outbound (áudio):**

5. **Definir contrato no gateway:**  
   - Que o gateway aceite em `POST /api/messages` (ou endpoint novo) algo como:  
     `{ "channel", "to", "type": "audio", "url" }` ou `"base64"` ou `"mediaId"` de um upload prévio.  
   - Documentar o formato exato.

6. **`WhatsAppGatewayClient::sendAudio` (ou `sendMedia`):**  
   - Novo método que chame o endpoint de mídia do gateway com `channel`, `to`, `audio` (URL ou base64) e opções.

7. **`CommunicationHubController::send()`:**  
   - Aceitar `attachment` (upload) ou `media_url`/`audio_url`.  
   - Se `attachment`/`audio` estiver preenchido e `message` vazio ou com legenda, chamar `sendAudio` em vez de `sendText`.  
   - Validar mimetype (ex. `audio/ogg`, `audio/mpeg`, `audio/mp4`) e tamanho máximo (ex. 16MB).

**Persistência e exibição:**

8. **Outbound em `communication_events`:**  
   - Ao enviar áudio, criar evento `whatsapp.outbound.message` com payload que indique `type: 'audio'` e referência ao ficheiro ou `communication_media`, para o histórico e para a thread.

9. **`/communication-hub/media`:**  
   - Manter `Auth::requireInternal()`.  
   - Opcional: validar que o `path` existe em `communication_media.stored_path` (JOIN com `communication_events`) para evitar servir ficheiros “órfãos” por path guessing.

10. **Correlação e acks:**  
    - Ao enviar (texto ou, no futuro, áudio), persistir `correlation_id`/`message_id` do gateway e, quando o webhook trouxer `message.sent`/`message.ack`, atualizar o evento ou um estado de “entregue”.  
    - Pode ser uma tabela `message_delivery` ou colunas em `communication_events`; o mínimo é não perder o `correlation_id` na resposta do `send`.

---

## Resumo por canal (ImobSites e Pixel12 Digital)

- **ImobSites e Pixel12 Digital** usam o mesmo fluxo:  
  - Webhook → `WhatsAppWebhookController` → `EventIngestionService` → `WhatsAppMediaService` (e opcionalmente `downloadMedia`).  
- A distinção é só pelo `channel_id`/`session_id` no payload e na tabela `tenant_message_channels`.  
- O provider é sempre `wpp_gateway`; não há ramificações por canal no código de áudio.  
- Problemas conhecidos (ex. `onMessage` não chegando para `pixel12digital` em certos períodos) estão na documentação (ex. `CONFIRMACAO_PROBLEMA_EMISSAO_ONMESSAGE.md`, `RESUMO_DIAGNOSTICO_LOGS_GATEWAY.md`) e afetam **qualquer** tipo de mensagem (texto, áudio, etc.), não só áudio.

---

## Referência rápida de ficheiros

| O quê | Ficheiro |
|-------|----------|
| Webhook inbound | `src/Controllers/WhatsAppWebhookController.php` |
| Ingestão + disparo de mídia | `src/Services/EventIngestionService.php` |
| Deteção, download e gravação de áudio | `src/Services/WhatsAppMediaService.php` |
| Download no gateway | `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php` → `downloadMedia` |
| Envio (só texto) | `CommunicationHubController::send`, `WhatsAppGatewayClient::sendText` |
| Servir ficheiro de mídia | `CommunicationHubController::serveMedia` |
| Exibir áudio na thread | `views/communication_hub/thread.php`, `views/communication_hub/index.php` (renderMediaPlayer) |
| Tabela de mídia | `database/migrations/20260116_create_communication_media_table.php` |
| Rotas | `public/index.php` (webhook, communication-hub, media) |

