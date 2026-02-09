# Investigação: Conversa 5511984078606 → pixel12digital não aparece no Inbox

**Data:** 2026-02-09  
**Cenário:** Mensagem de Adriana (5511984078606) para Pixel12 Digital recebida no WhatsApp às 07:14, mas não aparece no Inbox do Pixel Hub.

---

## 1. Resumo Executivo

A conversa de **5511984078606** (Adriana) para o canal **pixel12digital** não está visível no Inbox. Este documento consolida a documentação de communications, o fluxo completo (webhook → ingestão → conversation → UI) e as hipóteses de causa raiz.

---

## 2. Fluxo End-to-End (Webhook → Inbox)

### 2.1 Arquitetura

```
[WhatsApp] → [Gateway WPP (VPS)] → POST /api/whatsapp/webhook → [PixelHub]
                                                                    ↓
                                          WhatsAppWebhookController::handle()
                                                                    ↓
                                          EventIngestionService::ingest()
                                                                    ↓
                                          ConversationService::resolveConversation()
                                                                    ↓
                                          [conversations]
                                                                    ↓
                                          CommunicationHubController::getWhatsAppThreadsFromConversations()
                                                                    ↓
                                          [Inbox UI]
```

### 2.2 Pontos Críticos

| Etapa | Arquivo | O que pode falhar |
|-------|---------|-------------------|
| 1. Webhook | `WhatsAppWebhookController.php` | channel_id ausente, payload malformado, secret inválido |
| 2. Ingestão | `EventIngestionService.php` | idempotência (DROP_DUPLICATE), tenant_id inválido, payload JSON inválido |
| 3. Conversa | `ConversationService.php` | extractChannelInfo retorna NULL, channel_id ausente |
| 4. Listagem | `CommunicationHubController.php` | Filtro session_id, status, tenant_id |

---

## 3. Hipóteses de Causa Raiz

### Hipótese A: Webhook não chegou ao Hub

**Evidência:** Nenhum evento em `communication_events` com from contendo 5511984078606.

**Verificar:**
- Logs do servidor: `[HUB_WEBHOOK_IN]`, `[WHATSAPP INBOUND RAW]`
- Tabela `webhook_raw_logs` (se existir)
- Gateway VPS: webhook configurado para a URL correta? Eventos onmessage enviados?

**Referência:** `docs/AUDITORIA_FALHA_RECEBIMENTO_WHATSAPP_2026-01-15.md`

---

### Hipótese B: channel_id ausente ou diferente no payload

**Evidência:** Gateway pode enviar `session.id` como "pixel12digital" enquanto o banco tem "Pixel12 Digital". O `resolveTenantByChannel` faz normalização case-insensitive e remove espaços, então isso normalmente funciona.

**Risco:** Se o payload não tiver `session`, `channel`, `sessionId` em nenhum nível, `channel_id` fica NULL. O `ConversationService::extractChannelInfo` usa `metadata.channel_id` como fallback (prioridade 10). O webhook passa `metadata['channel_id']` = valor extraído por `extractChannelId()` do controller.

**Verificar:** Eventos no banco com `JSON_EXTRACT(metadata, '$.channel_id')` e `JSON_EXTRACT(payload, '$.session.id')`.

---

### Hipótese C: extractChannelInfo retorna NULL

**Causas possíveis:**
- `from` ausente no payload (inbound) – caminhos: `message.from`, `from`, `data.from`, `raw.payload.from`, etc.
- Contato em formato @lid sem mapeamento em `whatsapp_business_ids`
- Grupo sem participant/author

**Verificar:** Logs `[CONVERSATION UPSERT] extractChannelInfo: ERRO` e `[CONVERSATION UPSERT] extractChannelInfo: Payload sem from válido`.

---

### Hipótese D: Evento descartado por idempotência

**Evidência:** Log `[HUB_MSG_DROP] DROP_DUPLICATE`.

**Verificar:** idempotency_key calculada incorretamente ou mensagem duplicada pelo gateway.

---

### Hipótese E: Conversa criada mas filtrada na UI

**Cenários:**
1. **session_id filtrado:** Usuário selecionou "Canal: pixel12digital" e a conversa tem `channel_id` NULL ou diferente.
2. **status:** Conversa com status `closed`, `archived` ou `ignored` não aparece em "Ativas".
3. **Ordenação:** Conversa existe mas está fora do LIMIT 100 (muitas conversas mais recentes).

**Query do Inbox:** `getWhatsAppThreadsFromConversations` filtra por:
- `c.channel_type = 'whatsapp'`
- `c.channel_id = ?` (quando session_id fornecido)
- `c.status NOT IN ('closed', 'archived', 'ignored')` (quando status=active)

---

### Hipótese F: tenant_id NULL e regras de canal

O `resolveTenantByChannel` retorna NULL se o channel_id não estiver em `tenant_message_channels`. Conversas com `tenant_id = NULL` ainda são criadas e devem aparecer na lista (com "Sem tenant"). A UX não exclui conversas sem tenant quando o filtro é "Todos".

---

## 4. Script de Diagnóstico

**Arquivo:** `database/diagnostico-5511984078606-inbox.php`

**Execução:**
```bash
php database/diagnostico-5511984078606-inbox.php
```

**O que verifica:**
1. Eventos em `communication_events` com from contendo 5511984078606
2. Conversas em `conversations` para esse número
3. Canais pixel12digital em `tenant_message_channels`
4. Últimos webhooks em `webhook_raw_logs`
5. Simulação da query do Inbox (inclui a conversa?)

---

## 5. Documentação e Código Relevantes

### Documentação
- `docs/ARQUITETURA_CENTRAL_COMUNICACAO_ALVO.md` – Arquitetura do Inbox
- `docs/AUDITORIA_CENTRAL_COMUNICACAO_PIXELHUB.md` – Estado atual e gaps
- `docs/AUDITORIA_FALHA_RECEBIMENTO_WHATSAPP_2026-01-15.md` – Checklist de falha de recebimento
- `docs/VALIDACAO_ENDPOINTS_COMMUNICATION_HUB.md` – Endpoints e validações
- `.cursor/skills/whatsapp-integration/SKILL.md` – Fluxo de webhook e processamento

### Código Principal
- `src/Controllers/WhatsAppWebhookController.php` – Recebe webhook, extrai channel_id, resolve tenant
- `src/Services/EventIngestionService.php` – Ingestão, idempotência, chama resolveConversation
- `src/Services/ConversationService.php` – extractChannelInfo, resolveConversation, createConversation
- `src/Controllers/CommunicationHubController.php` – getWhatsAppThreadsFromConversations, index

### Scripts de Correção
- `database/check-pixel12digital-channel.php` – Verifica canal pixel12digital
- `database/fix-pixel12digital-channel.php` – Cria/atualiza canal pixel12digital

---

## 6. Próximos Passos

1. **Executar diagnóstico:** `php database/diagnostico-5511984078606-inbox.php` no servidor (banco remoto).
2. **Interpretar resultado:**
   - Se **nenhum evento** → Hipótese A (webhook não chegou). Verificar VPS/Gateway.
   - Se **eventos existem mas conversa não** → Hipóteses B, C ou D. Verificar logs do PHP.
   - Se **conversa existe** → Hipótese E (filtro/ordenação). Verificar session_id e status na UI.
3. **Logs a buscar:** `[HUB_WEBHOOK_IN]`, `[HUB_CHANNEL_ID_EXTRACTION]`, `[CONVERSATION UPSERT]`, `[HUB_CONV_MATCH]`, `[HUB_MSG_DROP]`.
4. **Gateway:** Se necessário, pedir ao Charles (VPS) blocos de comando para verificar logs do gateway-wrapper e envio de webhooks para pixel12digital.

---

## 7. Referência Rápida: Normalização channel_id

O `resolveTenantByChannel` normaliza:
```php
$normalizedChannelId = strtolower(str_replace(' ', '', $channelId));
// "Pixel12 Digital" e "pixel12digital" → "pixel12digital"
```

A query em `tenant_message_channels` usa:
```sql
LOWER(REPLACE(channel_id, ' ', '')) = ?
```

Ou seja, "Pixel12 Digital" no banco casa com "pixel12digital" do gateway.
