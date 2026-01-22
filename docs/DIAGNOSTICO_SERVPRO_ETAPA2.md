# 🔍 Diagnóstico ServPro - Etapa 2: Rastreamento do Fluxo

**Data:** 2026-01-13  
**Status:** ✅ Logs temporários adicionados | ⏳ Aguardando teste em produção

---

## 📊 Resultado da Etapa 1

### Verificação 1: Status do Evento

**Evento:** `006bb2b4-d536-40e3-89ee-061679d3d068`

- ✅ **Evento inserido:** Sim (created_at: 2026-01-13 17:53:34)
- ❌ **Status:** `queued` (não foi processado)
- ❌ **processed_at:** NULL
- ✅ **Classificação:** `whatsapp.inbound.message` (correto)

**Conclusão:** O evento foi apenas inserido em `communication_events`, mas **não foi processado pelo pipeline**.

---

## 🔧 Logs Temporários Adicionados

### 1. EventIngestionService::ingest()

**Localização:** Antes de chamar `resolveConversation()`

**Logs adicionados:**
- `[DIAGNOSTICO] EventIngestion::ingest() - CHAMANDO resolveConversation: event_id=..., event_type=..., tenant_id=...`
- `[DIAGNOSTICO] EventIngestion::ingest() - resolveConversation RETORNOU: conversation_id=... ou NULL`

### 2. ConversationService::resolveConversation()

**Localização:** Início do método e antes/depois de `updateConversationMetadata()`

**Logs adicionados:**
- `[DIAGNOSTICO] ConversationService::resolveConversation() - INICIADO: event_type=..., from=..., to=...`
- `[DIAGNOSTICO] ConversationService::resolveConversation() - EARLY RETURN: não é evento de mensagem` (se retornar antes)
- `[DIAGNOSTICO] ConversationService::resolveConversation() - ANTES updateConversationMetadata: conversation_id=..., last_message_at=..., unread_count=...`
- `[DIAGNOSTICO] ConversationService::resolveConversation() - DEPOIS updateConversationMetadata: conversation_id=..., last_message_at=..., unread_count=...`

### 3. ConversationService::updateConversationMetadata()

**Localização:** Antes e depois do UPDATE SQL

**Logs adicionados:**
- `[DIAGNOSTICO] ConversationService::updateConversationMetadata() - EXECUTANDO UPDATE: conversation_id=..., direction=..., message_timestamp=...`
- `[DIAGNOSTICO] ConversationService::updateConversationMetadata() - UPDATE EXECUTADO: success=..., rows_affected=..., last_message_at=...`

---

## 🎯 Próximo Teste em Produção

### Passo 1: Fazer deploy dos logs

```bash
git pull
```

### Passo 2: Enviar nova mensagem de teste

- **De:** ServPro (554796474223)
- **Para:** Pixel12 Digital
- **Texto:** `TESTE SERVPRO ETAPA2 <hora>`

### Passo 3: Verificar logs do servidor

**Buscar nos logs (error_log ou arquivo de log PHP):**

```bash
# Buscar logs de diagnóstico
grep "DIAGNOSTICO" /caminho/do/log/pixelhub.log | tail -20

# Ou buscar no error_log do PHP
tail -100 /var/log/php/error.log | grep DIAGNOSTICO
```

### Passo 4: Coletar evidências

**O que verificar nos logs:**

1. ✅ **Se `resolveConversation()` foi chamado:**
   - Deve aparecer: `[DIAGNOSTICO] EventIngestion::ingest() - CHAMANDO resolveConversation`
   - Se não aparecer, o problema é que `ingest()` não está chamando `resolveConversation()`

2. ✅ **Se entrou em `resolveConversation()`:**
   - Deve aparecer: `[DIAGNOSTICO] ConversationService::resolveConversation() - INICIADO`
   - Se não aparecer, o problema é early return em `isMessageEvent()`

3. ✅ **Se encontrou conversa existente:**
   - Deve aparecer: `[CONVERSATION UPSERT] Conversa existente encontrada: conversation_id=34`
   - Deve aparecer: `[DIAGNOSTICO] ConversationService::resolveConversation() - ANTES updateConversationMetadata`

4. ✅ **Se executou UPDATE:**
   - Deve aparecer: `[DIAGNOSTICO] ConversationService::updateConversationMetadata() - EXECUTANDO UPDATE`
   - Deve aparecer: `[DIAGNOSTICO] ConversationService::updateConversationMetadata() - UPDATE EXECUTADO: success=true, rows_affected=1`

5. ✅ **Se UPDATE afetou linhas:**
   - `rows_affected` deve ser `1` (não `0`)
   - Se for `0`, o UPDATE não encontrou a linha (WHERE id = ? não matchou)

---

## 🔍 Checklist de Verificação

Após o teste, verificar:

- [ ] Log `CHAMANDO resolveConversation` apareceu?
- [ ] Log `resolveConversation() - INICIADO` apareceu?
- [ ] Log `Conversa existente encontrada` apareceu?
- [ ] Log `ANTES updateConversationMetadata` apareceu?
- [ ] Log `EXECUTANDO UPDATE` apareceu?
- [ ] Log `UPDATE EXECUTADO` apareceu?
- [ ] `rows_affected` foi `1` ou `0`?
- [ ] Log `DEPOIS updateConversationMetadata` mostra valores atualizados?

---

## 📝 Interpretação dos Resultados

### Cenário 1: `resolveConversation()` não foi chamado

**Sintoma:** Não aparece log `CHAMANDO resolveConversation`

**Causa:** `EventIngestionService::ingest()` não está chamando `resolveConversation()` para este evento

**Possíveis razões:**
- Exception sendo engolida antes de chegar no try/catch
- Condição que impede a chamada
- Evento sendo ingerido por outro caminho

---

### Cenário 2: `resolveConversation()` retornou antes (early return)

**Sintoma:** Aparece `INICIADO` mas não aparece `ANTES updateConversationMetadata`

**Causa:** Early return em algum ponto:
- `isMessageEvent()` retornou false
- `extractChannelInfo()` retornou NULL
- `findByKey()` não encontrou conversa e não criou nova

---

### Cenário 3: UPDATE não executou ou não afetou linhas

**Sintoma:** Aparece `EXECUTANDO UPDATE` mas `rows_affected = 0`

**Causa:** WHERE não encontrou a linha:
- `conversation_id` está errado
- Conversa foi deletada entre encontrar e atualizar
- Transação foi revertida

---

### Cenário 4: UPDATE executou mas valores não mudaram

**Sintoma:** `rows_affected = 1` mas `DEPOIS updateConversationMetadata` mostra valores antigos

**Causa:** 
- UPDATE está usando valores errados
- `messageTimestamp` está errado
- `direction` está errado
- Cache/transação não commitou

---

## 🚀 Próximo Passo

1. Fazer `git pull` em produção
2. Enviar mensagem de teste
3. Coletar logs com `grep "DIAGNOSTICO"`
4. Enviar logs aqui para análise

Com os logs, será identificado exatamente onde o fluxo está quebrando.

---

**Última atualização:** 2026-01-13

