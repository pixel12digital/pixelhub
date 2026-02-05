# Auditoria de Confiabilidade — Inbox WhatsApp (mensagens ausentes)

**Contato:** Robson (+55 87 9988-4234)  
**Data dos eventos:** 05/02/2026  
**Janela:** 12:04 → 12:15  
**Objetivo:** Causa raiz dos 4 itens ausentes no Inbox e plano de correção com mínima intervenção.

---

## 1. Raio-X (resultado do script `database/auditoria-inbox-robson-0502.php`)

| Item | Existe no webhook? | Existe em communication_events? | Existe em messages/UI? | Motivo provável |
|------|--------------------|--------------------------------|------------------------|-----------------|
| Áudio 1 (1:59) 12:04 | sim | **sim** (id 152016) | não exibe | Evento existe; mídia não baixou (communication_media vazio ou arquivo ausente) |
| Áudio 2 (0:24) 12:04 | sim | **sim** (id 152032) | não exibe | Evento existe; mídia não baixou |
| Texto D ("Relatorio de turmas teoricas...") 12:13 | ? | **não** | não | Perda antes de persistir (webhook não chegou ou foi descartado) |
| Imagem 12:15 | sim | **sim** (id 152170) | não exibe | Evento existe; pode ser mídia não baixada ou UI não renderiza |

---

## 2. Classificação (A/B/C/D)

| Item | Classificação | Interpretação |
|------|---------------|---------------|
| Áudio 1, Áudio 2 | **D** | Existem em `communication_events`, mas mídia não baixou; UI não exibe placeholder quando `communication_media` está vazio ou falhou |
| Texto D | **A** | Não existe registro — perda antes de persistir (webhook não chegou ou dedupe/idempotência descartou) |
| Imagem 12:15 | **D ou C** | Evento existe (id 152170); se mídia falhou = D; se mídia OK mas não exibe = C (filtro/consulta/frontend) |

---

## 3. Causa raiz mais provável

### 3.1 Áudios 12:04 (não exibem)

- **Eventos existem** em `communication_events` (ids 152016, 152032).
- **Mídia não foi baixada** ou falhou em `WhatsAppMediaService::processMediaFromEvent()`.
- **Webhook usa `process_media_sync=false`** → responde 200 antes de baixar mídia; o download roda em background.
- Se o processo terminar antes do download (ex.: `fastcgi_finish_request` + fim da requisição), o download pode não concluir.
- **UI:** Se não há registro em `communication_media` ou `stored_path` aponta para arquivo inexistente, o frontend pode não mostrar placeholder/erro.

### 3.2 Texto D ("turmas teóricas")

- **Não existe** em `communication_events`.
- Possíveis causas:
  1. **Webhook não chegou** do gateway para essa mensagem.
  2. **Deduplicação** — idempotency_key igual à de outra mensagem (improvável para conteúdos distintos).
  3. **Gateway não enviou** webhook para esse texto (ex.: mensagem muito longa, formato diferente, ou envio em lote com falha parcial).

### 3.3 Imagem 12:15

- **Evento existe** (id 152170); payload contém preview base64.
- Se não exibe:
  - **Mídia não baixada** → caso D (como nos áudios).
  - **Mídia OK mas UI não exibe** → caso C (filtro, ordenação, ou renderização).

---

## 4. Plano de correção (mínima intervenção)

### 4.1 Princípios

- Manter endpoint e contrato de webhook inalterados.
- Preservar lógica atual e ajustar apenas onde necessário.
- Foco: fallback de persistência, placeholder, retry de mídia e logs.

### 4.2 Ajustes pontuais

| Ação | Onde | Objetivo |
|------|------|----------|
| **1. Placeholder para mídia falha** | `getWhatsAppMessagesFromConversation()` / frontend | Quando evento existe mas `communication_media` vazio ou arquivo ausente, retornar `media.url` com placeholder ou flag `media_failed=true` para o frontend exibir "Mídia não disponível" em vez de ocultar a mensagem. |
| **2. Fallback de persistência** | `EventIngestionService` | Garantir que **sempre** persiste o evento, mesmo se `processMediaFromEvent` falhar. Hoje já faz isso; confirmar que nenhum `throw` interrompe o fluxo antes do `INSERT`. |
| **3. Retry assíncrono de mídia** | Novo job ou cron | Para eventos com `event_type=whatsapp.inbound.message` e tipo ptt/audio/image, sem registro em `communication_media`, criar job que chame `WhatsAppMediaService::processMediaFromEvent()` em retry. |
| **4. Log para texto perdido** | `WhatsAppWebhookController` | Se payload vier com `type=chat` e body vazio ou muito grande, logar `[HUB_MSG_SAVE]` com hash do conteúdo para rastrear se o webhook chegou. |
| **5. Instrumentar logs** | `EventIngestionService` | Logar `[HUB_MSG_SAVE_OK]` com `message_id` e `conversation_id` para cada evento persistido; manter `[HUB_MSG_DROP]` para dedupe. |
| **6. Verificar gateway** | VPS (Charles) | Conferir se o gateway envia webhook para **todos** os tipos (ptt, audio, image, chat) e se mensagens longas ou em lote são enviadas corretamente. |

---

## 5. Checklist de teste (pós-correção)

- [ ] Múltiplos áudios no mesmo minuto chegam todos e exibem (ou placeholder).
- [ ] Texto multiline longo não é descartado (incluindo "Relatorio de turmas teoricas...").
- [ ] Imagem sempre gera evento (mesmo se download falhar); UI exibe placeholder.
- [ ] UI exibe todas as mensagens na ordem correta (ou recuperável por scroll/paginação).
- [ ] Áudios com mídia falha exibem "Áudio não disponível" em vez de sumir.

---

## 6. Comandos e scripts úteis

### Rodar auditoria local

```bash
php database/auditoria-inbox-robson-0502.php
```

### Logs a verificar (Hostmidia / VPS)

```bash
# Webhook recebido
grep 'HUB_WEBHOOK_IN' /var/log/... 

# Evento salvo
grep 'HUB_MSG_SAVE_OK' /var/log/...

# Evento descartado (dedupe)
grep 'HUB_MSG_DROP' /var/log/...
```

### Reprocessar mídia de evento específico

```php
$event = EventIngestionService::findByEventId('uuid-do-evento');
if ($event) {
    WhatsAppMediaService::processMediaFromEvent($event);
}
```

---

## 7. Referências

- `src/Controllers/WhatsAppWebhookController.php` — recebe webhook, chama `EventIngestionService::ingest()`
- `src/Services/EventIngestionService.php` — persistência e idempotência
- `src/Services/WhatsAppMediaService.php` — download e armazenamento de mídia
- `src/Controllers/CommunicationHubController.php` — `getWhatsAppMessagesFromConversation()` (query do Inbox)
- `docs/BLOCO_VPS_DIAGNOSTICO_FFMPEG_AUDIO.md` — diagnóstico ffmpeg no gateway (áudios WebM→OGG)
