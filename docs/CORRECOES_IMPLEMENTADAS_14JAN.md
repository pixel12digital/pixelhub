# Correções Implementadas - 14/01/2026

## Problemas Identificados

### 1. Charles (4699) - Frontend travado em thread errada
- **Causa:** Frontend estava com thread 34 aberta, então polling não capturava mensagens da thread 35
- **Evidência:** Mensagem ID 4547 existe no banco (thread whatsapp_35, 15:27:23)

### 2. ServPro (4223) - Mensagem não entra no banco
- **Causa:** Ingestão/persistência - webhook não chegou ou foi descartado
- **Evidência:** Não há mensagem no período 15:24-15:27 para thread whatsapp_34

---

## Correções Implementadas

### A) Frontend - Parar de "reabrir conversa salva" errada

**Arquivo:** `views/communication_hub/index.php`

**Mudanças:**

1. **Nova função `handleConversationClick()`:**
   - Força `activeThreadId = clickedThreadId` (ignora thread salva)
   - Reseta markers completamente (`lastTimestamp`, `lastEventId`, `messageIds`)
   - Carrega conversa full (não incremental)
   - Logs detalhados para rastreamento

2. **Correção na reabertura de conversa salva:**
   - Valida se thread salva ainda existe na lista antes de reabrir
   - Se usuário clicou em outra thread, ignora restore
   - Não força reabertura se thread não existe mais

3. **Todos os cliques agora usam `handleConversationClick()`:**
   - Lista inicial (PHP)
   - Lista atualizada via AJAX (JavaScript)

**Comportamento garantido:**
- ✅ Ao clicar em conversa: `setActiveThread(clickedThreadId)`, reset markers, load full, depois polling incremental
- ✅ Reabertura de conversa salva não "trava" usuário em thread antiga
- ✅ Logs completos: `activeThreadId` antes/depois + markers atualizados

---

### B) Backend - Instrumentação completa do webhook

**Arquivo:** `src/Controllers/WhatsAppWebhookController.php`

**Mudanças:**

1. **Logs antes de ingerir:**
   - `timestamp`
   - `from/chatId`
   - `eventId` (do payload)
   - `tenant_id` resolvido
   - `channel_id` extraído
   - `event_type` mapeado

2. **Logs após ingestão:**
   - `event_id` (UUID gerado)
   - `id_pk` (ID numérico do banco)
   - Resultado do insert (SUCCESS/ERROR)
   - Se erro: exception class + message

3. **Logs formatados:**
   ```
   [WEBHOOK INSTRUMENTADO] ANTES DE INGERIR: timestamp=..., from=..., tenant_id=...
   [WEBHOOK INSTRUMENTADO] INSERT REALIZADO: event_id=..., id_pk=..., SUCCESS=true
   [WEBHOOK INSTRUMENTADO] RESULTADO FINAL: event_id=..., id_pk=..., SUCCESS=true
   ```

**O que os logs mostram:**
- ✅ Se webhook chegou (log "ANTES DE INGERIR")
- ✅ Se insert ocorreu (log "INSERT REALIZADO" com id_pk)
- ✅ Se houve erro/dedupe/roteamento errado (log de exception)

---

### C) Script de teste rápido

**Arquivo:** `database/check-recent-messages-quick.php`

**Uso:**
```bash
php database/check-recent-messages-quick.php
```

**O que faz:**
- Busca últimas 10 mensagens criadas nos últimos 30 segundos
- Mostra: ID, Event ID, Created, From, To, Tenant ID, Thread ID
- Identifica se mensagem foi criada e em qual thread

**Quando usar:**
- Imediatamente após enviar mensagem do ServPro
- Verifica se mensagem entrou no banco em tempo real

---

## Testes de Aceite

### Teste 1: Charles (Frontend)

1. Abrir painel de comunicação
2. Clicar em conversa do Charles (whatsapp_35)
3. **Verificar:**
   - ✅ Mensagem 15:27:23 aparece
   - ✅ Console mostra: `handleConversationClick() - activeThreadId DEPOIS=whatsapp_35`
   - ✅ Console mostra: `MARKERS RESETADOS`
4. Enviar outra mensagem do Charles
5. **Verificar:**
   - ✅ Aparece em tempo real sem refresh
   - ✅ Lista sobe + badge coerente

### Teste 2: ServPro (Ingestão)

1. Enviar mensagem do ServPro
2. Imediatamente executar:
   ```bash
   php database/check-recent-messages-quick.php
   ```
3. **Verificar logs do backend:**
   ```bash
   tail -f /caminho/para/logs/error.log | grep "WEBHOOK INSTRUMENTADO"
   ```
4. **Verificar:**
   - ✅ Log "ANTES DE INGERIR" aparece (webhook chegou)
   - ✅ Log "INSERT REALIZADO" aparece (insert ocorreu)
   - ✅ Script mostra mensagem no banco
   - ✅ Thread ID = whatsapp_34

**Se webhook não chega:**
- Verificar logs do gateway (status da requisição POST e retries)

**Se webhook chega mas não insere:**
- Verificar log de exception (dedupe, erro SQL, roteamento errado)

---

## Próximos Passos

1. **Testar Charles:** Abrir thread 35 e verificar se mensagem aparece
2. **Testar ServPro:** Enviar mensagem e verificar logs + script
3. **Se ServPro não entra:** Verificar logs do gateway e roteamento de tenant/channel

---

## Logs Esperados

### Frontend (Console do Navegador)
```
[LOG TEMPORARIO] handleConversationClick() - activeThreadId ANTES=whatsapp_34
[LOG TEMPORARIO] handleConversationClick() - activeThreadId DEPOIS=whatsapp_35
[LOG TEMPORARIO] handleConversationClick() - MARKERS RESETADOS
[LOG TEMPORARIO] loadConversation() - ESTADO RESETADO para thread_id=whatsapp_35
```

### Backend (error.log)
```
[WEBHOOK INSTRUMENTADO] ANTES DE INGERIR: timestamp=2026-01-14 15:44:38, from=554796474223, tenant_id=2, channel_id=Pixel12 Digital, event_type=whatsapp.inbound.message
[WEBHOOK INSTRUMENTADO] INSERT REALIZADO: event_id=xxx, id_pk=4663, timestamp=2026-01-14 15:44:38, from=554796474223, tenant_id=2, channel_id=Pixel12 Digital
[WEBHOOK INSTRUMENTADO] RESULTADO FINAL: event_id=xxx, id_pk=4663, from=554796474223, tenant_id=2, channel_id=Pixel12 Digital, SUCCESS=true
```

