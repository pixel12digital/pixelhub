# 🔍 Guia de Diagnóstico: Mensagem ServPro não sobe pro topo

**Problema:** Mensagem do ServPro (554796474223) não aparece no topo da lista nem mostra badge de não lidas.

**Data:** 2026-01-13

---

## 📋 Checklist de Diagnóstico

### Opção 1: Script PHP Automático

Execute o script após enviar uma mensagem de teste:

```bash
php database/diagnose-servpro-simple.php
```

O script irá:
1. Buscar eventos recentes do ServPro
2. Verificar classificação (inbound/outbound)
3. Verificar conversa atualizada
4. Verificar isolamento (conversa do Charles)
5. Testar endpoint de updates

---

### Opção 2: Queries SQL Manuais

Execute as queries em `database/queries-diagnostico-servpro.sql` na ordem:

1. **Verificar evento em communication_events**
2. **Verificar conversa do ServPro (antes e depois)**
3. **Verificar conversa do Charles (isolamento)**
4. **Verificar conversas similares (heurística 9º dígito)**
5. **Simular endpoint de updates**

---

## 🎯 O que Verificar

### (A) Classificação Inbound/Outbound

**Query:**
```sql
SELECT 
    event_id,
    event_type,
    created_at,
    JSON_EXTRACT(payload, '$.event') as gateway_event_type,
    JSON_EXTRACT(payload, '$.fromMe') as fromMe,
    JSON_EXTRACT(payload, '$.message.fromMe') as message_fromMe
FROM communication_events
WHERE payload LIKE '%554796474223%'
AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
ORDER BY created_at DESC
LIMIT 1;
```

**O que verificar:**
- ✅ `event_type` deve ser `whatsapp.inbound.message`
- ✅ Se for `whatsapp.outbound.message`, o problema está no mapeamento
- ✅ Verificar campos `fromMe` no payload (se existirem)

**Causa provável:** `WhatsAppWebhookController::mapEventType()` está mapeando `'message'` sempre como inbound, mas o gateway pode estar enviando outro tipo ou o payload pode ter `fromMe = true`.

---

### (B) Conversa Atualizada

**Query:**
```sql
SELECT 
    id,
    conversation_key,
    contact_external_id,
    last_message_at,
    last_message_direction,
    unread_count,
    message_count,
    updated_at
FROM conversations
WHERE contact_external_id = '554796474223'
ORDER BY last_message_at DESC
LIMIT 1;
```

**O que verificar:**
- ✅ `last_message_at` deve ser atualizado para o horário do teste
- ✅ `unread_count` deve ser > 0 (se evento foi inbound)
- ✅ `last_message_direction` deve ser `'inbound'`
- ✅ `updated_at` deve ser recente (últimos minutos)

**Causa provável:** `ConversationService::resolveConversation()` não está encontrando/atualizando a conversa correta.

---

### (C) Isolamento (Conversa do Charles)

**Query:**
```sql
SELECT 
    id,
    contact_external_id,
    last_message_at,
    updated_at,
    TIMESTAMPDIFF(SECOND, updated_at, NOW()) as seconds_ago
FROM conversations
WHERE contact_external_id = '554796164699'
LIMIT 1;
```

**O que verificar:**
- ⚠️ Se `updated_at` foi atualizado nos últimos minutos, pode ser matching indevido
- ⚠️ Heurística do 9º dígito pode estar "roubando" a mensagem do ServPro para o Charles

**Causa provável:** `ConversationService::findEquivalentConversation()` está sendo muito agressiva.

---

### (D) Endpoint de Updates

**Query:**
```sql
SET @after_timestamp = DATE_SUB(NOW(), INTERVAL 1 HOUR);

SELECT 
    MAX(GREATEST(COALESCE(updated_at, '1970-01-01'), COALESCE(last_message_at, '1970-01-01'))) as latest_update_ts
FROM conversations
WHERE channel_type = 'whatsapp'
AND (updated_at > @after_timestamp OR last_message_at > @after_timestamp);
```

**O que verificar:**
- ✅ Deve retornar um timestamp recente
- ✅ Se retornar NULL, o endpoint não detectaria atualizações

**Causa provável:** Filtros no `CommunicationHubController::checkUpdates()` estão excluindo a conversa.

---

## 🔧 Correções Esperadas

### Se for (A) Classificação:

**Arquivo:** `src/Controllers/WhatsAppWebhookController.php`

**Problema:** Mapeamento não verifica se mensagem é inbound ou outbound.

**Correção esperada:**
```php
private function mapEventType(string $gatewayEventType, array $payload): ?string
{
    // Se for 'message', verifica se é inbound ou outbound
    if ($gatewayEventType === 'message') {
        $fromMe = $payload['fromMe'] 
            ?? $payload['message']['fromMe'] 
            ?? $payload['data']['fromMe'] 
            ?? false;
        
        return $fromMe ? 'whatsapp.outbound.message' : 'whatsapp.inbound.message';
    }
    
    // Outros eventos...
    $mapping = [
        'message.ack' => 'whatsapp.delivery.ack',
        'connection.update' => 'whatsapp.connection.update',
        'message.sent' => 'whatsapp.outbound.message',
        // ...
    ];
    
    return $mapping[$gatewayEventType] ?? null;
}
```

---

### Se for (B) Matching:

**Arquivo:** `src/Services/ConversationService.php`

**Problema:** Heurística do 9º dígito está sendo muito agressiva.

**Correção esperada:**
```php
private static function findEquivalentConversation(array $channelInfo, string $contactExternalId): ?array
{
    // ... código existente ...
    
    // ADICIONAR: Não aplicar equivalência se já existe match exato
    $exactMatch = self::findByKey($conversationKey);
    if ($exactMatch) {
        return null; // Já existe match exato, não buscar equivalente
    }
    
    // ... resto do código ...
}
```

---

### Se for (C) Polling:

**Arquivo:** `src/Controllers/CommunicationHubController.php`

**Problema:** Filtros ou timestamp estão excluindo a conversa.

**Correção esperada:**
- Verificar filtros de `tenant_id` e `status` em `checkUpdates()`
- Garantir que `after_timestamp` está sendo comparado corretamente
- Verificar timezone/format do timestamp

---

## 📊 Resposta Esperada do Diagnóstico

Após executar o diagnóstico, você deve ter:

1. **event_id:** UUID do evento
2. **event_type:** `whatsapp.inbound.message` ou `whatsapp.outbound.message`
3. **channel_id:** ID do canal (ex: "Pixel12 Digital")
4. **tenant_id:** ID do tenant ou NULL
5. **conversation_id:** ID da conversa atualizada (ou NENHUMA)
6. **last_message_at:** Timestamp da última mensagem
7. **unread_count:** Contador de não lidas
8. **last_message_direction:** `inbound` ou `outbound`
9. **endpoint_updates:** `has_updates=true` ou `has_updates=false`
10. **conclusão:** (A) classificação vs (B) matching vs (C) polling

---

## 🚀 Próximos Passos

1. Execute o diagnóstico (script ou queries)
2. Envie os 10 itens acima
3. Receba o diagnóstico fechado e prompt de correção exato

---

**Última atualização:** 2026-01-13

