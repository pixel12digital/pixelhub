# Implementação de Transcrição de Áudio - Documentação Completa

**Data:** 30/01/2026  
**Status:** ⚠️ REVERTIDO (rollback aplicado)  
**Motivo do Rollback:** Problema não identificado com envio/recebimento de áudios após deploy

---

## 1. Resumo Executivo

Foi implementada uma funcionalidade de **transcrição de áudio sob demanda** para o Painel de Comunicação (Communication Hub), utilizando a API OpenAI Whisper. A implementação foi **revertida** devido a problemas em produção.

### Regras P0 Definidas (Obrigatórias)

| Regra | Descrição |
|-------|-----------|
| ❌ | NÃO transcrever áudios automaticamente no webhook |
| ❌ | NÃO criar/ativar cron ou job recorrente |
| ❌ | NÃO processar pendentes em lote automaticamente |
| ❌ | NÃO exibir transcrição automaticamente para todos |
| ❌ | NÃO criar novas telas ou fluxos paralelos |
| ✅ | APENAS transcrever quando usuário clicar manualmente |

---

## 2. Arquivos Criados

### 2.1. Migration - Alteração da Tabela `communication_media`

**Arquivo:** `database/migrations/20260130_alter_communication_media_add_transcription.php`

```php
<?php
/**
 * Migration: Adicionar campos de transcrição à tabela communication_media
 * 
 * Campos adicionados:
 * - transcription (TEXT): Texto transcrito do áudio
 * - transcription_status (ENUM): 'pending', 'processing', 'completed', 'failed'
 * - transcription_error (TEXT): Mensagem de erro se falhar
 * - transcription_at (DATETIME): Timestamp da transcrição
 * 
 * Índices:
 * - idx_transcription_status: Para buscar pendentes
 * - idx_media_type_transcription: Para buscar áudios pendentes
 */
class AlterCommunicationMediaAddTranscription
{
    public function up(PDO $db): void
    {
        // Adiciona colunas de transcrição
        $db->exec("
            ALTER TABLE communication_media
            ADD COLUMN transcription TEXT NULL AFTER file_name,
            ADD COLUMN transcription_status ENUM('pending', 'processing', 'completed', 'failed') NULL AFTER transcription,
            ADD COLUMN transcription_error TEXT NULL AFTER transcription_status,
            ADD COLUMN transcription_at DATETIME NULL AFTER transcription_error
        ");
        
        // Índices para performance
        $db->exec("
            ALTER TABLE communication_media
            ADD INDEX idx_transcription_status (transcription_status),
            ADD INDEX idx_media_type_transcription (media_type, transcription_status)
        ");
    }
    
    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE communication_media
            DROP INDEX idx_media_type_transcription,
            DROP INDEX idx_transcription_status,
            DROP COLUMN transcription_at,
            DROP COLUMN transcription_error,
            DROP COLUMN transcription_status,
            DROP COLUMN transcription
        ");
    }
}
```

**Status:** ✅ Migration foi executada no banco (colunas existem)

---

### 2.2. Service - AudioTranscriptionService

**Arquivo:** `src/Services/AudioTranscriptionService.php`

```php
<?php
namespace PixelHub\Services;

class AudioTranscriptionService
{
    private const WHISPER_API_URL = 'https://api.openai.com/v1/audio/transcriptions';
    private const WHISPER_MODEL = 'whisper-1';
    private const MAX_FILE_SIZE = 25 * 1024 * 1024; // 25MB limite da API
    private const TIMEOUT_SECONDS = 60;
    
    /**
     * Transcreve um áudio pelo ID da mídia
     */
    public static function transcribe(int $mediaId): array;
    
    /**
     * Transcreve um áudio pelo event_id
     */
    public static function transcribeByEventId(string $eventId): array;
    
    /**
     * Processa áudios pendentes (para uso manual/CLI)
     */
    public static function transcribePending(int $limit = 10): array;
    
    /**
     * Retorna estatísticas de transcrição
     */
    public static function getStats(): array;
    
    /**
     * Verifica saúde do serviço
     */
    public static function checkHealth(): array;
    
    // Métodos privados
    private static function callWhisperApi(string $filePath, string $apiKey): array;
    private static function updateTranscriptionStatus(int $mediaId, string $status, ?string $error = null): void;
    private static function saveTranscription(int $mediaId, string $transcription): void;
    private static function getApiKey(): string;
}
```

**Características:**
- Usa OpenAI Whisper API (modelo `whisper-1`)
- Custo: ~$0.006/minuto de áudio
- Suporta formatos: OGG, MP3, WAV, M4A, WEBM
- Limite de arquivo: 25MB
- Timeout: 60 segundos
- Reutiliza configuração de API Key existente em `/settings/ai`

---

### 2.3. Script CLI - transcribe_audios.php

**Arquivo:** `scripts/transcribe_audios.php`

```php
#!/usr/bin/env php
<?php
/**
 * Script CLI para transcrição manual de áudios
 * 
 * Uso:
 *   php scripts/transcribe_audios.php --check     # Verifica saúde do serviço
 *   php scripts/transcribe_audios.php --stats     # Mostra estatísticas
 *   php scripts/transcribe_audios.php --limit=5   # Processa 5 áudios pendentes
 * 
 * NOTA: Não é para rodar automaticamente via cron.
 * Usar apenas para processamento manual quando necessário.
 */
```

**Opções:**
- `--check` - Verifica API Key e conexão com OpenAI
- `--stats` - Mostra estatísticas (total, pendentes, processados, falhas)
- `--limit=N` - Processa N áudios pendentes
- `--help` - Mostra ajuda

---

## 3. Arquivos Modificados

### 3.1. Rotas - public/index.php

**Adições:**

```php
// Transcrição de áudio sob demanda
$router->post('/communication-hub/transcribe', 'CommunicationHubController@transcribe');
$router->get('/communication-hub/transcription-status', 'CommunicationHubController@getTranscriptionStatus');
```

---

### 3.2. Controller - CommunicationHubController.php

**Métodos Adicionados:**

```php
/**
 * POST /communication-hub/transcribe
 * Body: { event_id: string }
 * 
 * Dispara transcrição de um áudio específico.
 * Retorna imediatamente com status ou resultado.
 */
public function transcribe(): void
{
    // 1. Valida event_id
    // 2. Busca mídia no banco
    // 3. Verifica se é áudio
    // 4. Se já transcrito, retorna transcrição existente
    // 5. Se processando, retorna status
    // 6. Marca como 'processing'
    // 7. Chama AudioTranscriptionService::transcribe()
    // 8. Retorna resultado JSON
}

/**
 * GET /communication-hub/transcription-status?event_id=xxx
 * 
 * Retorna status atual da transcrição de um áudio.
 */
public function getTranscriptionStatus(): void
{
    // Retorna: status, transcription, error
}
```

---

### 3.3. View - views/communication_hub/thread.php

**Alterações na UI:**

#### PHP (Renderização do Áudio)

```php
<?php elseif (strpos($mimeType, 'audio/') === 0 || in_array($mediaType, ['audio', 'voice'])): ?>
    <?php
    $hasTranscription = !empty($media['transcription']);
    $transcriptionStatus = $media['transcription_status'] ?? null;
    $eventIdForTranscription = $media['event_id'] ?? $msgId;
    ?>
    <div class="audio-player-container" data-event-id="<?= htmlspecialchars($eventIdForTranscription) ?>">
        <!-- Player de áudio existente -->
        <audio controls preload="metadata">...</audio>
        
        <!-- Botão Transcrever (se não tem transcrição) -->
        <?php if (!$hasTranscription && $transcriptionStatus !== 'processing'): ?>
            <button type="button" class="transcribe-btn" 
                    onclick="transcribeAudio(this, '<?= $eventIdForTranscription ?>')">
                🎤 Transcrever
            </button>
        <?php elseif ($transcriptionStatus === 'processing'): ?>
            <span class="transcription-status">
                <span class="spinner"></span> Transcrevendo...
            </span>
        <?php endif; ?>
        
        <!-- Área de transcrição (se já transcrito) -->
        <?php if ($hasTranscription): ?>
            <details class="transcription-area">
                <summary>📝 Ver transcrição</summary>
                <div><?= htmlspecialchars($media['transcription']) ?></div>
            </details>
        <?php endif; ?>
    </div>
<?php endif; ?>
```

#### JavaScript (Funções de Transcrição)

```javascript
/**
 * Inicia transcrição de um áudio
 */
async function transcribeAudio(btn, eventId) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Transcrevendo...';
    
    const response = await fetch('/communication-hub/transcribe', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'event_id=' + encodeURIComponent(eventId)
    });
    
    const data = await response.json();
    
    if (data.success && data.status === 'completed') {
        showTranscription(container, data.transcription);
    } else if (data.status === 'processing') {
        pollTranscriptionStatus(container, eventId, btn);
    } else {
        // Erro - restaura botão
    }
}

/**
 * Polling para verificar status da transcrição
 */
async function pollTranscriptionStatus(container, eventId, btn, attempts = 0) {
    if (attempts > 30) { /* timeout */ return; }
    
    await new Promise(r => setTimeout(r, 2000)); // 2 segundos
    
    const response = await fetch('/communication-hub/transcription-status?event_id=' + eventId);
    const data = await response.json();
    
    if (data.status === 'completed') {
        showTranscription(container, data.transcription);
    } else if (data.status === 'processing') {
        pollTranscriptionStatus(container, eventId, btn, attempts + 1);
    } else {
        // Erro ou falha
    }
}

/**
 * Exibe transcrição na UI
 */
function showTranscription(container, transcription) {
    // Remove botão, adiciona <details> com transcrição
}
```

#### CSS (Estilos)

```css
.transcribe-btn {
    background: #f0f0f0;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 10px;
    cursor: pointer;
}

.transcribe-btn:hover {
    background: #e0e0e0;
}

.spinner {
    display: inline-block;
    width: 12px;
    height: 12px;
    border: 2px solid #ccc;
    border-top-color: #666;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.transcription-area {
    margin-top: 8px;
    padding: 8px;
    background: #f9f9f9;
    border-radius: 4px;
    font-size: 12px;
}
```

---

### 3.4. Service - WhatsAppMediaService.php

**Alteração Mínima:**

```php
// Antes
return [
    'id' => (int) $media['id'],
    // ... campos existentes
];

// Depois
$result = [
    'id' => (int) $media['id'],
    // ... campos existentes
];

// Adiciona campos de transcrição se existirem
if (array_key_exists('transcription', $media)) {
    $result['transcription'] = $media['transcription'];
    $result['transcription_status'] = $media['transcription_status'] ?? null;
}

return $result;
```

---

## 4. Fluxo de Funcionamento

```
┌─────────────────────────────────────────────────────────────────┐
│                    FLUXO DE TRANSCRIÇÃO                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. Usuário vê mensagem de áudio no chat                       │
│     └── Botão "Transcrever" aparece ao lado do player          │
│                                                                 │
│  2. Usuário clica em "Transcrever"                             │
│     └── JavaScript: transcribeAudio(btn, eventId)              │
│     └── POST /communication-hub/transcribe {event_id}          │
│                                                                 │
│  3. Backend processa                                            │
│     └── CommunicationHubController::transcribe()               │
│     └── Marca status = 'processing'                            │
│     └── AudioTranscriptionService::transcribe($mediaId)        │
│         └── Lê arquivo do storage                              │
│         └── Envia para OpenAI Whisper API                      │
│         └── Recebe texto transcrito                            │
│         └── Salva no banco (transcription, status='completed') │
│                                                                 │
│  4. Resposta retorna ao frontend                               │
│     └── { success: true, status: 'completed', transcription }  │
│                                                                 │
│  5. UI atualiza                                                 │
│     └── Remove botão "Transcrever"                             │
│     └── Adiciona <details> com transcrição colapsável          │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 5. Schema do Banco de Dados

### Tabela: communication_media (colunas adicionadas)

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `transcription` | TEXT NULL | Texto transcrito do áudio |
| `transcription_status` | ENUM('pending','processing','completed','failed') NULL | Status da transcrição |
| `transcription_error` | TEXT NULL | Mensagem de erro se falhou |
| `transcription_at` | DATETIME NULL | Data/hora da transcrição |

### Índices Adicionados

| Nome | Colunas | Propósito |
|------|---------|-----------|
| `idx_transcription_status` | (transcription_status) | Buscar por status |
| `idx_media_type_transcription` | (media_type, transcription_status) | Buscar áudios pendentes |

**Status:** ✅ Colunas e índices existem no banco (migration foi executada)

---

## 6. Configuração Necessária

A transcrição reutiliza a configuração existente de OpenAI em `/settings/ai`:

- **API Key:** Já configurada para sugestão de nomes de projetos
- **Tabela:** `ai_settings` (coluna `openai_api_key` criptografada)

Nenhuma nova configuração é necessária.

---

## 7. Custos Estimados

| Métrica | Valor |
|---------|-------|
| Custo por minuto | ~$0.006 USD |
| Áudio típico (30s) | ~$0.003 USD |
| 100 transcrições/mês | ~$0.30 USD |
| 1000 transcrições/mês | ~$3.00 USD |

---

## 8. Commits Relacionados

| Hash | Mensagem | Status |
|------|----------|--------|
| `58e93e8` | feat: Transcricao de audio sob demanda (WhatsApp) - Migration, Service, Script CLI, UI | ⚠️ Revertido |
| `6bb22ba` | Revert "feat: Transcricao de audio sob demanda..." | ✅ Atual |

---

## 9. Estado Atual

### O que FOI aplicado (permanece):
- ✅ Colunas no banco de dados (`transcription`, `transcription_status`, etc.)
- ✅ Índices no banco de dados

### O que FOI revertido (removido):
- ❌ `AudioTranscriptionService.php` (deletado)
- ❌ `transcribe_audios.php` (deletado)
- ❌ `20260130_alter_communication_media_add_transcription.php` (deletado)
- ❌ Rotas de transcrição em `public/index.php`
- ❌ Métodos no `CommunicationHubController`
- ❌ Alterações na UI do `thread.php`
- ❌ Alterações no `WhatsAppMediaService`

---

## 10. Próximos Passos para Reimplementação

Quando for reimplementar, investigar:

1. **Causa do problema:** Por que envio/recebimento de áudios parou após deploy?
2. **Teste local primeiro:** Garantir que funciona localmente antes de deploy
3. **Deploy incremental:** Fazer deploy de cada arquivo separadamente para isolar problema
4. **Logs detalhados:** Adicionar mais logs para diagnóstico

### Arquivos a recriar:
1. `src/Services/AudioTranscriptionService.php`
2. `scripts/transcribe_audios.php`
3. Rotas em `public/index.php`
4. Métodos em `CommunicationHubController.php`
5. Alterações em `views/communication_hub/thread.php`
6. Alterações em `src/Services/WhatsAppMediaService.php`

### Migration NÃO precisa ser recriada
As colunas já existem no banco. A migration pode ser ignorada.

---

## 11. Referências

- [OpenAI Whisper API](https://platform.openai.com/docs/guides/speech-to-text)
- Configuração existente: `/settings/ai`
- Skill WhatsApp: `.cursor/skills/whatsapp-integration/SKILL.md`

---

*Documento gerado em 30/01/2026 após rollback da implementação.*
