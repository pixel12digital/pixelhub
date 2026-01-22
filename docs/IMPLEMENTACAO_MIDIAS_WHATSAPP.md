# Implementação de Mídias WhatsApp - WPP Connect

## Status
✅ Implementação completa realizada em 16/01/2026

## Resumo da Implementação

Foi implementado suporte completo para receber e exibir mídias (áudio, imagem, vídeo, documentos, stickers) do WhatsApp via WPP Connect no Pixel Hub.

## Arquivos Criados/Modificados

### Novos Arquivos
1. `src/Services/WhatsAppMediaService.php` - Serviço para processar e armazenar mídias
2. `database/migrations/20260116_create_communication_media_table.php` - Migration da tabela
3. `database/run-migration-communication-media.php` - Script para executar migration
4. `database/execute-migration-media-sql.php` - Script direto SQL
5. `database/verify-migration-media.php` - Script de verificação

### Arquivos Modificados
1. `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php` - Adicionado método `downloadMedia()`
2. `src/Services/EventIngestionService.php` - Adicionado processamento automático de mídias
3. `src/Controllers/CommunicationHubController.php` - Adicionado suporte a mídias nas mensagens e endpoint `serveMedia()`
4. `views/communication_hub/thread.php` - Atualizado para exibir mídias
5. `public/index.php` - Adicionada rota `/communication-hub/media`

## Estrutura do Banco de Dados

### Tabela: `communication_media`

```sql
CREATE TABLE IF NOT EXISTS communication_media (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(36) NOT NULL UNIQUE,
    media_id VARCHAR(255) NOT NULL,
    media_type VARCHAR(50) NOT NULL,
    mime_type VARCHAR(100) NULL,
    stored_path VARCHAR(500) NULL,
    file_name VARCHAR(255) NULL,
    file_size INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_event_id (event_id),
    INDEX idx_media_id (media_id),
    INDEX idx_media_type (media_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (event_id) REFERENCES communication_events(event_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Formato WPP Connect

O código foi ajustado para suportar o formato do WPP Connect. Os campos esperados no payload:

### Identificação de Mídia
- `mediaId` ou `media_id` - ID da mídia
- `mediaUrl` ou `media_url` - URL da mídia (WPP Connect pode fornecer URL direta)
- `id` ou `messageId` - ID da mensagem (usado como fallback)

### Tipo de Mídia
- `type` - Tipo da mensagem: `audio`, `ptt`, `image`, `video`, `document`, `sticker`
- `message.type` - Tipo aninhado no objeto message

### MIME Type
- `mimetype` ou `mimeType` - Tipo MIME do arquivo
- `message.mimetype` - Tipo MIME aninhado

## Executando a Migration

### Opção 1: Via Script PHP (Recomendado)
```bash
php database/execute-migration-media-sql.php
```

### Opção 2: SQL Direto
Execute no MySQL do servidor remoto:
```sql
CREATE TABLE IF NOT EXISTS communication_media (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(36) NOT NULL UNIQUE COMMENT 'UUID do evento (FK para communication_events)',
    media_id VARCHAR(255) NOT NULL COMMENT 'ID da mídia no WhatsApp Gateway',
    media_type VARCHAR(50) NOT NULL COMMENT 'Tipo de mídia (audio, image, video, document, sticker)',
    mime_type VARCHAR(100) NULL COMMENT 'MIME type do arquivo',
    stored_path VARCHAR(500) NULL COMMENT 'Caminho relativo do arquivo armazenado',
    file_name VARCHAR(255) NULL COMMENT 'Nome do arquivo',
    file_size INT UNSIGNED NULL COMMENT 'Tamanho do arquivo em bytes',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_event_id (event_id),
    INDEX idx_media_id (media_id),
    INDEX idx_media_type (media_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (event_id) REFERENCES communication_events(event_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Verificar Migration
```bash
php database/verify-migration-media.php
```

## Fluxo de Processamento

1. **Webhook recebe mensagem** → `WhatsAppWebhookController::handle()`
2. **Evento é ingerido** → `EventIngestionService::ingest()`
3. **Processamento automático de mídia** → `WhatsAppMediaService::processMediaFromEvent()`
   - Extrai `mediaId` do payload (suporta múltiplos formatos)
   - Baixa mídia via `WhatsAppGatewayClient::downloadMedia()`
   - Armazena em `storage/whatsapp-media/`
   - Salva metadados em `communication_media`
4. **Exibição na interface** → `CommunicationHubController::getWhatsAppMessages()`
   - Busca mídia associada ao evento
   - Inclui informações de mídia na resposta
   - View exibe mídia (imagem, vídeo, áudio, documento)

## Estrutura de Armazenamento

```
storage/
└── whatsapp-media/
    ├── tenant-{id}/          # Mídias por tenant (se houver tenant_id)
    │   └── YYYY/MM/DD/
    │       └── {hash}.{ext}
    └── YYYY/MM/DD/           # Mídias sem tenant
        └── {hash}.{ext}
```

## Endpoints

### GET `/communication-hub/media?path={stored_path}`
Serve mídias armazenadas de forma segura (requer autenticação interna).

**Parâmetros:**
- `path` (obrigatório) - Caminho relativo da mídia (ex: `whatsapp-media/2026/01/16/abc123.ogg`)

**Resposta:**
- Arquivo binário com headers HTTP apropriados
- Content-Type baseado no `mime_type` armazenado

## Suporte a Tipos de Mídia

### Áudio (`audio`, `ptt`, `voice`)
- Player HTML5 com controles
- Formatos: OGG, MP3, WAV, WEBM

### Imagem (`image`, `sticker`)
- Exibição direta com `<img>`
- Clique para abrir em nova aba
- Formatos: JPEG, PNG, WEBP, GIF

### Vídeo (`video`)
- Player HTML5 com controles
- Formatos: MP4, WEBM, MOV

### Documento (`document`)
- Link para download
- Exibe nome e tamanho do arquivo
- Formatos: PDF, DOC, DOCX, etc.

## Logs e Debugging

Logs são gravados em `error_log` com prefixo `[WhatsAppMediaService]`:

```
[WhatsAppMediaService] Processando mídia - event_id: xxx, mediaType: audio, mediaId: yyy, mimeType: audio/ogg
[WhatsAppMediaService] Falha ao baixar mídia: {erro}
[WhatsAppMediaService] ERRO: Tabela communication_media não existe...
```

## Próximos Passos

1. ✅ Executar migration no banco remoto
2. ⏳ Testar envio de mídia pelo WhatsApp
3. ⏳ Verificar se mídia aparece no Pixel Hub
4. ⏳ Se necessário, reprocessar mensagens antigas com mídia

## Reprocessamento de Mídias Antigas

Se houver mensagens antigas com mídia que não foram processadas, execute:

```php
<?php
require_once __DIR__ . '/../public/index.php';

use PixelHub\Core\DB;
use PixelHub\Services\WhatsAppMediaService;

$db = DB::getConnection();

// Busca eventos recentes com possível mídia
$stmt = $db->query("
    SELECT event_id, payload 
    FROM communication_events 
    WHERE event_type = 'whatsapp.inbound.message'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC
");

while ($event = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $result = WhatsAppMediaService::processMediaFromEvent($event);
    if ($result) {
        echo "Processada mídia para evento: {$event['event_id']}\n";
    }
}
```

## Problemas Conhecidos e Soluções

### Tabela não existe
**Sintoma:** Erro `Table 'communication_media' doesn't exist`
**Solução:** Execute a migration conforme instruções acima

### Mídia não aparece
**Possíveis causas:**
1. Migration não foi executada
2. Webhook não está enviando `mediaId` no formato esperado
3. Erro ao baixar mídia do gateway

**Debug:**
- Verificar logs do PHP
- Verificar payload do webhook nos logs
- Verificar se `communication_media` tem registros

### Erro ao baixar mídia
**Possíveis causas:**
1. `mediaId` não é válido
2. Gateway não retorna mídia no endpoint esperado
3. Problema de conexão com gateway

**Debug:**
- Verificar logs `[WhatsAppGateway::downloadMedia]`
- Verificar se `channel_id` está correto
- Verificar se gateway suporta endpoint `/api/channels/{channel}/media/{mediaId}`

## Notas sobre WPP Connect

O WPP Connect pode usar formatos diferentes de payload. O código foi ajustado para suportar:
- `mediaUrl` (URL direta da mídia)
- `mediaId` (ID para baixar via API)
- `mimetype` (sem camelCase)
- Estrutura aninhada em `message.*`

Se o formato do WPP Connect for diferente, ajuste em `WhatsAppMediaService::processMediaFromEvent()`.







