# PATCH J — Normalização de Histórico Inbound Órfão

**Data:** 16/01/2026  
**Status:** ✅ Script criado, aguardando execução  
**Prioridade:** Alta

---

## 📋 RESUMO

Antes da criação do mapeamento `pixel12digital → tenant_id=121`, os eventos do inbound tinham `tenant_id=NULL` porque não havia canal habilitado. Isso resultou em **5.682 eventos órfãos** que precisam ser normalizados para garantir consistência na UI.

---

## 🎯 OBJETIVO

Garantir que mensagens recebidas antes da criação do canal (quando `tenant_id` era `NULL`) não fiquem "órfãs" e que a UI não pareça quebrada.

---

## 📊 DIAGNÓSTICO REALIZADO

### Eventos Órfãos

**Script:** `database/auditoria-inbound-duplicidade.php`

**Resultado:**
- ✅ **5.682 eventos órfãos** encontrados (tenant_id=NULL para `pixel12digital`)
- ✅ Todos criados **antes de 17:38:18** (data de criação do canal tenant_id=121)
- ✅ Após 17:38:18, eventos passaram a ter `tenant_id=121` corretamente

**Conclusão:** O problema não era duplicidade, mas sim a ausência de mapeamento antes. Agora que existe, o histórico precisa ser normalizado.

---

## 🛠️ SCRIPT CRIADO

**Arquivo:** `database/patch-j-normalizar-inbound-orphans.php`

**Modos de execução:**

1. **Dry-run (visualização):**
   ```bash
   php database/patch-j-normalizar-inbound-orphans.php dry-run
   ```

2. **Apply (aplicação):**
   ```bash
   php database/patch-j-normalizar-inbound-orphans.php apply 121
   ```

**O que o script faz:**

1. ✅ **Diagnóstico:** Conta eventos e conversations órfãs
2. ✅ **Validação:** Verifica se tenant_id=121 existe e tem canal habilitado
3. ✅ **Aplicação (modo apply):**
   - Atualiza `communication_events`: `tenant_id=NULL → tenant_id=121`
   - Atualiza `conversations`: `tenant_id=NULL → tenant_id=121`
4. ✅ **Validação final:** Confirma que não restaram órfãos

---

## 📝 QUERIES QUE SERÃO EXECUTADAS

### A) Atualizar Eventos Órfãos

```sql
UPDATE communication_events
SET tenant_id = 121,
    updated_at = NOW()
WHERE source_system = 'wpp_gateway'
  AND (tenant_id IS NULL OR tenant_id = 0)
  AND (
      JSON_EXTRACT(metadata, '$.channel_id') = 'pixel12digital'
      OR JSON_EXTRACT(payload, '$.session.id') = 'pixel12digital'
      OR JSON_EXTRACT(payload, '$.sessionId') = 'pixel12digital'
      OR JSON_EXTRACT(payload, '$.channelId') = 'pixel12digital'
  );
```

**Impacto esperado:** ~5.682 eventos atualizados

### B) Atualizar Conversations Órfãs

```sql
UPDATE conversations
SET tenant_id = 121,
    updated_at = NOW()
WHERE (tenant_id IS NULL OR tenant_id = 0)
  AND channel_id = 'pixel12digital';
```

**Impacto esperado:** Depende de quantas conversations foram criadas sem tenant_id

---

## ✅ VALIDAÇÕES APÓS APLICAÇÃO

1. **Enviar mensagem inbound** para `pixel12digital` e confirmar que entra no tenant 121
2. **Abrir o painel** e conferir se a conversa aparece na lista correta
3. **Conferir se conversas antigas** não ficaram separadas das novas
4. **Verificar eventos** para confirmar que todos têm `tenant_id=121`

---

## 🔄 ROLLBACK (SE NECESSÁRIO)

Se precisar reverter, execute:

```sql
-- Reverter eventos (CUIDADO: só se realmente necessário)
UPDATE communication_events
SET tenant_id = NULL,
    updated_at = NOW()
WHERE tenant_id = 121
  AND source_system = 'wpp_gateway'
  AND created_at < '2026-01-16 17:38:18'
  AND (
      JSON_EXTRACT(metadata, '$.channel_id') = 'pixel12digital'
      OR JSON_EXTRACT(payload, '$.session.id') = 'pixel12digital'
  );

-- Reverter conversations (CUIDADO: só se realmente necessário)
UPDATE conversations
SET tenant_id = NULL,
    updated_at = NOW()
WHERE tenant_id = 121
  AND channel_id = 'pixel12digital'
  AND created_at < '2026-01-16 17:38:18';
```

---

## 📚 ARQUIVOS RELACIONADOS

- **Script de diagnóstico:** `database/auditoria-inbound-duplicidade.php`
- **Script de normalização:** `database/patch-j-normalizar-inbound-orphans.php`
- **Correção inbound:** `src/Controllers/WhatsAppWebhookController.php` (ORDER BY id ASC adicionado)

---

## 🎯 PRÓXIMOS PASSOS

1. ✅ Executar `dry-run` para confirmar diagnóstico
2. ⏳ Executar `apply` quando confirmado
3. ⏳ Validar resultados na UI
4. ⏳ Confirmar que conversas estão unificadas

---

**Documento gerado em:** 16/01/2026  
**Última atualização:** 16/01/2026  
**Versão:** 1.0

