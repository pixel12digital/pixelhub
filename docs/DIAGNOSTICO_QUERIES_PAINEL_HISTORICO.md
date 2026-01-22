# Diagnóstico: Queries do Painel de Histórico (Pós PATCH J)

**Data:** 16/01/2026  
**Status:** ⚠️ Problema identificado na query de mensagens  
**Prioridade:** Alta

---

## 🎯 OBJETIVO

Após o PATCH J normalizar todos os eventos órfãos, verificar se as queries do painel que montam o histórico da conversa estão filtrando corretamente por `tenant_id` e `channel_id` para evitar mistura de dados.

---

## 🔍 PROBLEMA IDENTIFICADO

### Arquivo: `src/Controllers/CommunicationHubController.php`
### Método: `getWhatsAppMessagesFromConversation()` (linha 1454)

### Query Problemática (linhas 1823-1837):

```php
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.payload,
        ce.metadata,
        ce.tenant_id
    FROM communication_events ce
    {$whereClause}
    ORDER BY ce.created_at ASC
    LIMIT 500
");
```

### Filtro WHERE Problemático (linhas 1811-1815):

```php
// Filtro por tenant_id (se disponível)
if ($tenantId) {
    $where[] = "(ce.tenant_id = ? OR ce.tenant_id IS NULL)";  // ⚠️ PROBLEMA
    $params[] = $tenantId;
}
```

**Problema:**
- Após o PATCH J, não deveria haver eventos com `tenant_id IS NULL` para `pixel12digital`
- Mas o filtro `OR ce.tenant_id IS NULL` ainda permite buscar eventos sem tenant
- Isso pode misturar eventos de outros tenants que também não têm tenant_id (se existirem)
- A busca por padrões de contato (LIKE) pode pegar contatos com números parecidos de outros tenants

### Verificação Adicional (linhas 1940-1943):

```php
// Verifica se tenant_id bate (se ambos tiverem tenant_id definido)
if ($tenantId && $event['tenant_id'] && $event['tenant_id'] != $tenantId) {
    continue; // Exclui após já ter buscado do banco
}
```

**Problema:**
- Esta verificação acontece DEPOIS da query SQL
- Eventos de outros tenants já foram carregados do banco
- Pode causar mistura se houver números de telefone similares entre tenants

---

## 🛠️ CORREÇÃO PROPOSTA

### PATCH K: Filtrar estritamente por tenant_id e channel_id

**Alteração 1: Remover `OR ce.tenant_id IS NULL` do filtro SQL**

**Antes:**
```php
if ($tenantId) {
    $where[] = "(ce.tenant_id = ? OR ce.tenant_id IS NULL)";
    $params[] = $tenantId;
}
```

**Depois:**
```php
// PATCH K: Após PATCH J, todos os eventos devem ter tenant_id
// Remove OR ce.tenant_id IS NULL para filtrar estritamente por tenant
if ($tenantId) {
    $where[] = "ce.tenant_id = ?";
    $params[] = $tenantId;
}
```

**Alteração 2: Adicionar filtro por channel_id na query SQL**

**Adicionar após linha 1815:**
```php
// PATCH K: Filtro adicional por channel_id para garantir isolamento por sessão
if (!empty($sessionId)) {
    $where[] = "(
        JSON_EXTRACT(ce.metadata, '$.channel_id') = ?
        OR JSON_EXTRACT(ce.payload, '$.session.id') = ?
        OR JSON_EXTRACT(ce.payload, '$.sessionId') = ?
        OR JSON_EXTRACT(ce.payload, '$.channelId') = ?
    )";
    $params[] = $sessionId;
    $params[] = $sessionId;
    $params[] = $sessionId;
    $params[] = $sessionId;
}
```

**Alteração 3: Remover verificação redundante após query (opcional)**

Como a query já filtra corretamente, a verificação nas linhas 1940-1943 pode ser mantida como "safety check" ou removida para performance.

---

## 📊 IMPACTO ESPERADO

### Antes da Correção:
- Query pode trazer eventos de outros tenants (com números similares)
- Mistura de histórico entre tenants diferentes
- Mensagens "estranhas" aparecendo (ex: rótulo "IMOBSITES")

### Depois da Correção:
- Query filtra estritamente por `tenant_id` e `channel_id`
- Isolamento completo entre tenants e sessões
- Histórico consistente e limpo

---

## ✅ VALIDAÇÃO APÓS CORREÇÃO

1. **Verificar na UI:**
   - Abrir conversa da Magda
   - Confirmar que NÃO aparece rótulo "IMOBSITES"
   - Verificar que horários estão corretos

2. **Verificar logs:**
   - Query deve retornar apenas eventos com `tenant_id=121` e `channel_id='pixel12digital'`
   - Nenhum evento de outros tenants deve aparecer

3. **Query de teste:**
   ```sql
   SELECT COUNT(*) 
   FROM communication_events ce
   WHERE ce.tenant_id = 121
     AND JSON_EXTRACT(ce.metadata, '$.channel_id') = 'pixel12digital'
     AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
     AND JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE '%558799884234%';
   ```

---

## 📚 ARQUIVOS RELACIONADOS

- **Controller:** `src/Controllers/CommunicationHubController.php`
  - Método: `getWhatsAppMessagesFromConversation()` (linha 1454)
  - Query SQL: linhas 1823-1837
  - Filtro WHERE: linhas 1811-1815

---

## 🎯 PRÓXIMOS PASSOS

1. ✅ Aplicar PATCH K (remover `OR ce.tenant_id IS NULL` e adicionar filtro por `channel_id`)
2. ⏳ Testar conversa da Magda na UI
3. ⏳ Validar que rótulos e horários estão corretos

---

**Documento gerado em:** 16/01/2026  
**Última atualização:** 16/01/2026  
**Versão:** 1.0

