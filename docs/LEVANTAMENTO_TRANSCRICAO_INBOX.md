# Levantamento: Transcrição de Áudio — Communication Hub → Inbox

**Data:** 29/01/2026  
**Objetivo:** Replicar a transcrição de áudio do Painel de Comunicação no Inbox.  
**Status:** ✅ Implementado

---

## 1. O que já existe (Communication Hub)

### 1.1 Backend (API)

| Item | Detalhe |
|------|---------|
| **Rotas** | `public/index.php` |
| POST `/communication-hub/transcribe` | Body: `event_id` (string). Dispara transcrição. |
| GET `/communication-hub/transcription-status?event_id=xxx` | Retorna status e transcrição. |
| **Controller** | `CommunicationHubController::transcribe()`, `getTranscriptionStatus()` |
| **Service** | `AudioTranscriptionService::transcribeByEventId()`, `getStatus()` |
| **Storage** | Tabela `communication_media`: `transcription`, `transcription_status`, `transcription_error`, `transcription_at` |
| **API externa** | OpenAI Whisper (`whisper-1`), config em `/settings/ai` |

### 1.2 WhatsAppMediaService

- Ao retornar mídia, adiciona: `transcription`, `transcription_status`, `transcription_error`, `transcription_at`
- Usado por `thread-data` e `messages/new` — o Inbox já recebe esses campos quando existem

### 1.3 UI no Communication Hub (`views/communication_hub/index.php`)

| Componente | Função |
|------------|--------|
| `renderMediaPlayer()` | Para áudio: player + botão transcrever + accordion (se já transcrito) + badges (processing/failed) |
| `transcribeAudio(btn, eventId)` | POST `/communication-hub/transcribe` |
| `pollTranscriptionStatus(badge, eventId, attempts)` | GET status a cada 2s, até 30 tentativas |
| `showTranscription(badge, transcription)` | Cria accordion com texto |
| `toggleTranscription(btn)` | Abre/fecha accordion |
| **CSS** | `.audio-transcribe-btn`, `.transcription-accordion`, `.transcription-status-badge`, `.transcription-spinner` |

### 1.4 Regras P0 (obrigatórias)

- NÃO transcrever automaticamente
- NÃO cron/job recorrente
- APENAS transcrever quando o usuário clicar manualmente

---

## 2. O que o Inbox precisa

### 2.1 Dados

- `thread-data` e `messages/new` já retornam `media.transcription`, `media.transcription_status`
- `event_id` disponível em `msg.id` ou `msg.event_id` (e em `media.event_id` quando existir)

### 2.2 UI

- Para áudio: player + botão "Transcrever" (quando não há transcrição e status ≠ processing)
- Se já transcrito: accordion colapsável com o texto
- Se processing: badge "Processando..."
- Se failed: badge "Falhou · Tentar novamente"

### 2.3 Chamadas de API

- Usar `INBOX_BASE_URL + '/communication-hub/transcribe'` (mesmo endpoint do Hub)
- Usar `INBOX_BASE_URL + '/communication-hub/transcription-status?event_id=' + eventId`

---

## 3. Arquivos alterados (implementado 29/01/2026)

| Arquivo | Alteração |
|---------|-----------|
| `views/layout/main.php` | CSS de transcrição (scoped ao Inbox), `buildInboxAudioWithTranscription()`, uso em `renderInboxMessages` e `appendInboxMessages`, funções JS `inboxTranscribeAudio`, `inboxPollTranscriptionStatus`, `inboxShowTranscription`, `inboxToggleTranscription` |

---

## 4. Fluxo

```
Usuário vê áudio no Inbox
  → Clica em "Transcrever"
  → POST /communication-hub/transcribe { event_id }
  → Backend: AudioTranscriptionService::transcribeByEventId()
  → Resposta: completed (com transcription) ou processing
  → Se processing: polling GET /transcription-status a cada 2s
  → UI: accordion com transcrição ou badge de status
```

---

*Documento criado em 29/01/2026.*
