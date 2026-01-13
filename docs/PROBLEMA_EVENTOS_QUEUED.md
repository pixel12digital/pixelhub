# ⚠️ Problema: Eventos ficam em status 'queued' e não são processados automaticamente

**Data:** 2026-01-13  
**Status:** 🔍 **DIAGNÓSTICO NECESSÁRIO**

---

## 🎯 Problema Identificado

Eventos do WhatsApp estão sendo inseridos no banco com status `queued`, mas não estão sendo processados automaticamente pelo `EventIngestionService::ingest()`.

### Evidências

1. **Eventos ficam em `queued`:**
   ```
   694a86f8... | whatsapp.inbound.message | 2026-01-13 19:44:18 | queued
   ```

2. **Processamento manual funciona:**
   - Quando executamos `resolveConversation()` manualmente, a conversa é atualizada
   - O evento é marcado como `processed` e a conversa é atualizada corretamente

3. **Código atual:**
   - `WhatsAppWebhookController` chama `EventIngestionService::ingest()`
   - `EventIngestionService::ingest()` chama `resolveConversation()` dentro de um try/catch
   - O catch engole exceções: `error_log("[EventIngestion] Erro ao resolver conversa (não crítico): " . $e->getMessage());`

---

## 🔍 Hipóteses

### Hipótese 1: Exception sendo engolida
**Probabilidade:** 🔴 **Alta**

`resolveConversation()` pode estar lançando uma exception que está sendo engolida pelo catch block em `EventIngestionService::ingest()` (linha 200-203).

**Verificação necessária:**
- Verificar logs do PHP (`error_log`) para mensagens de erro
- Procurar por: `[EventIngestion] Erro ao resolver conversa`

### Hipótese 2: Problema no pipeline de processamento
**Probabilidade:** 🟡 **Média**

O `EventIngestionService::ingest()` pode não estar sendo chamado corretamente pelo webhook, ou há algum problema no fluxo que impede o processamento.

**Verificação necessária:**
- Verificar logs do webhook
- Confirmar se `ingest()` está sendo chamado

### Hipótese 3: Timeout ou limite de execução
**Probabilidade:** 🟡 **Baixa**

O processamento pode estar demorando muito e sendo interrompido.

---

## 💡 Solução Temporária

**Script de processamento manual:**
```bash
php database/process-latest-servpro-event.php
```

Este script processa eventos em status `queued` manualmente.

---

## 🔧 Solução Permanente (Recomendada)

### Opção 1: Melhorar logging
Adicionar mais logs detalhados antes do try/catch para identificar onde está falhando:

```php
error_log('[DIAGNOSTICO] ANTES do try/catch de resolveConversation');
try {
    $conversation = \PixelHub\Services\ConversationService::resolveConversation([...]);
    error_log('[DIAGNOSTICO] DEPOIS do resolveConversation (sucesso)');
} catch (\Exception $e) {
    error_log('[DIAGNOSTICO] EXCEPTION capturada: ' . $e->getMessage());
    error_log('[DIAGNOSTICO] Stack trace: ' . $e->getTraceAsString());
}
```

### Opção 2: Worker/Queue para processamento assíncrono
Criar um worker que processa eventos em status `queued` periodicamente.

### Opção 3: Remover try/catch (temporariamente para debug)
Remover o try/catch temporariamente para ver a exception real, depois restaurar.

---

## 📋 Próximos Passos

1. ✅ **Verificar logs do servidor** - Buscar mensagens de erro no `error_log`
2. ⏳ **Adicionar logs mais detalhados** - Para identificar exatamente onde está falhando
3. ⏳ **Processar eventos pendentes manualmente** - Enquanto investiga o problema
4. ⏳ **Implementar solução permanente** - Worker ou correção no fluxo atual

---

**Última atualização:** 2026-01-13

