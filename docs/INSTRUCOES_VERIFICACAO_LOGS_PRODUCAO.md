# 🔍 Instruções: Verificação de Logs em Produção

**Objetivo:** Confirmar se `resolveConversation()` está sendo chamado e onde está falhando.

---

## 📋 Checklist de Verificação

### 1. Verificar se o evento foi processado

```bash
php database/check-event-processing.php
```

**Esperado:**
- Se `status = 'queued'` → Evento não foi processado (problema no pipeline)
- Se `status = 'processed'` → Evento foi processado, mas conversa não atualizou (problema em `resolveConversation()`)

---

### 2. Buscar logs de diagnóstico

Os logs temporários foram adicionados e devem aparecer em:

#### Opção A: Log do PHP (error_log)
```bash
# No servidor de produção
tail -200 /var/log/php/error.log | grep -E "DIAGNOSTICO|CONVERSATION UPSERT|EventIngestion"
```

#### Opção B: Log do PixelHub
```bash
tail -200 logs/pixelhub.log | grep -E "DIAGNOSTICO|CONVERSATION UPSERT"
```

#### Opção C: Log do Apache/Nginx
```bash
tail -200 /var/log/apache2/error.log | grep -E "DIAGNOSTICO|CONVERSATION UPSERT"
# ou
tail -200 /var/log/nginx/error.log | grep -E "DIAGNOSTICO|CONVERSATION UPSERT"
```

---

### 3. Logs esperados (se tudo funcionar)

```
[DIAGNOSTICO] EventIngestion::ingest() - CHAMANDO resolveConversation: event_id=..., event_type=..., tenant_id=...
[CONVERSATION UPSERT] INICIO resolveConversation para event_id=..., contact=..., tenant_id=...
[CONVERSATION UPSERT] Conversa existente encontrada: conversation_id=34
[CONVERSATION UPSERT] UPDATE EXECUTADO para conversation_id=34. last_message_at=..., unread_count incrementado se inbound.
[DIAGNOSTICO] EventIngestion::ingest() - resolveConversation RETORNOU: conversation_id=34, conversation_key=...
```

---

### 4. Se os logs NÃO aparecerem

**Possíveis causas:**
1. ❌ `resolveConversation()` não está sendo chamado (exception antes da linha 171)
2. ❌ Logs estão sendo escritos em outro arquivo
3. ❌ `error_log` não está configurado no PHP

**Solução:** Verificar se há exception sendo lançada antes de `resolveConversation()`:

```bash
tail -500 /var/log/php/error.log | grep -E "EventIngestion|Exception|Fatal|Error"
```

---

### 5. Se os logs aparecerem mas pararem em algum ponto

**Exemplo:**
```
[DIAGNOSTICO] EventIngestion::ingest() - CHAMANDO resolveConversation: ...
[CONVERSATION UPSERT] INICIO resolveConversation para event_id=...
# (para aqui - não aparece "Conversa existente encontrada")
```

**Significa:** `resolveConversation()` está sendo chamado, mas está retornando `null` ou lançando exception antes de encontrar a conversa.

**Verificar:**
- `extractChannelInfo()` retorna `NULL`?
- Exception em `findByKey()` ou `findEquivalentConversation()`?

---

### 6. Se aparecer "UPDATE EXECUTADO" mas conversa não atualiza

**Possíveis causas:**
1. ❌ UPDATE não está afetando linhas (`WHERE id = ?` não encontra a conversa)
2. ❌ Transaction não está sendo commitada
3. ❌ UPDATE está sendo executado mas depois revertido

**Verificar:**
- Adicionar log do `rows_affected` do UPDATE
- Verificar se há transaction que não está sendo commitada

---

## 🎯 Próximos Passos Após Verificação

1. **Se logs não aparecerem:** Adicionar mais logs antes de `resolveConversation()`
2. **Se logs pararem em algum ponto:** Adicionar logs específicos naquele ponto
3. **Se UPDATE executar mas não atualizar:** Verificar `rows_affected` e transactions

---

**Última atualização:** 2026-01-13

