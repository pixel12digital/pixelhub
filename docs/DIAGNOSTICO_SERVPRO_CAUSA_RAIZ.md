# 🔍 Diagnóstico ServPro - Causa Raiz Identificada

**Data:** 2026-01-13  
**Status:** ✅ **CAUSA RAIZ IDENTIFICADA**

---

## 🎯 Problema Identificado

### Sintoma
Mensagens do ServPro (554796474223) não atualizam a conversa (não "sobem" pro topo, não incrementam `unread_count`).

### Causa Raiz
**`extractChannelInfo()` retorna `NULL` porque o gateway está enviando um ID interno do WhatsApp Business (`10523374551225@lid`) ao invés do número de telefone real (`554796474223`).**

---

## 📊 Evidências

### 1. Payload do Evento
```json
{
  "message": {
    "from": "10523374551225@lid",  // ❌ ID interno, não é número de telefone
    "to": "554797309525@c.us"
  },
  "raw": {
    "payload": {
      "from": "10523374551225@lid",
      "sender": {
        "id": "10523374551225@lid",
        "name": "ServPro",
        "verifiedName": "Servpro"
      }
    }
  }
}
```

### 2. Tentativa de Normalização
- `10523374551225@lid` → Remove `@lid` → `10523374551225` (14 dígitos)
- `PhoneNormalizer::toE164OrNull("10523374551225")` → Retorna `NULL` porque:
  - Não começa com `55` (DDI do Brasil)
  - Tem 14 dígitos (mais que o máximo de 13 para números BR)
  - Não é um formato válido do Brasil

### 3. Resultado
- `extractChannelInfo()` retorna `NULL`
- `resolveConversation()` retorna `NULL` (early return na linha 60)
- Conversa não é atualizada
- Evento permanece com status `queued`

---

## 🔍 Análise Técnica

### Fluxo Esperado vs Real

**Esperado:**
```
WhatsAppWebhook → EventIngestionService::ingest() 
  → ConversationService::resolveConversation()
    → extractChannelInfo() retorna channelInfo válido
      → findByKey() encontra conversa existente
        → updateConversationMetadata() atualiza conversa
```

**Real:**
```
WhatsAppWebhook → EventIngestionService::ingest() 
  → ConversationService::resolveConversation()
    → extractChannelInfo() retorna NULL ❌
      → resolveConversation() retorna NULL (early return)
        → Conversa não é atualizada
```

### Por que o Gateway Envia `@lid`?

O WhatsApp Business usa IDs internos (`@lid` = "Linked ID") para contas verificadas/empresariais. O número real (`554796474223`) não aparece diretamente no payload quando é uma conta business.

---

## 💡 Soluções Possíveis

### Solução 1: Mapear ID para Número Real (Recomendada)

Criar uma tabela de mapeamento `whatsapp_business_ids` para associar IDs internos aos números reais:

```sql
CREATE TABLE whatsapp_business_ids (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id VARCHAR(100) UNIQUE NOT NULL,  -- Ex: 10523374551225@lid
    phone_number VARCHAR(20) NOT NULL,         -- Ex: 554796474223
    tenant_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_business_id (business_id),
    INDEX idx_phone_number (phone_number)
);
```

**Vantagens:**
- ✅ Resolve o problema de forma definitiva
- ✅ Permite rastrear múltiplos IDs para o mesmo número
- ✅ Mantém histórico de mudanças

**Desvantagens:**
- ⚠️ Requer população inicial da tabela
- ⚠️ Pode precisar atualização quando IDs mudarem

### Solução 2: Usar `notifyName` ou `verifiedName` para Matching

Se o `notifyName` ou `verifiedName` for "ServPro", buscar conversa existente por nome:

```php
// Em extractChannelInfo(), se normalização falhar:
if (!$contactExternalId && $channelType === 'whatsapp') {
    $notifyName = $payload['message']['notifyName'] 
        ?? $payload['raw']['payload']['notifyName'] 
        ?? null;
    
    if ($notifyName === 'ServPro') {
        // Busca conversa existente do ServPro
        $existing = self::findConversationByName('ServPro', $tenantId);
        if ($existing) {
            $contactExternalId = $existing['contact_external_id'];
        }
    }
}
```

**Vantagens:**
- ✅ Implementação rápida
- ✅ Não requer nova tabela

**Desvantagens:**
- ⚠️ Frágil (depende do nome ser exato)
- ⚠️ Não funciona se nome mudar
- ⚠️ Pode causar conflitos se houver múltiplos contatos com mesmo nome

### Solução 3: Extrair Número do `chatId` ou Outros Campos

Verificar se há algum campo no payload que contenha o número real, ou usar heurística para extrair do `chatId`:

```php
// Tentar extrair número do chatId ou outros campos
$chatId = $payload['raw']['payload']['chatId'] ?? null;
// Se chatId for "554796474223@lid", extrair "554796474223"
```

**Vantagens:**
- ✅ Não requer mudanças estruturais

**Desvantagens:**
- ⚠️ Pode não funcionar se formato mudar
- ⚠️ Heurística pode falhar em casos edge

### Solução 4: Usar `findEquivalentConversation()` com Fallback

Se `extractChannelInfo()` retornar `NULL`, tentar buscar conversa por outros critérios (nome, tenant_id, etc.):

```php
// Em resolveConversation(), se extractChannelInfo() retornar NULL:
if (!$channelInfo) {
    // Tenta buscar conversa existente por tenant_id + nome
    $notifyName = $payload['message']['notifyName'] ?? null;
    if ($notifyName && $tenantId) {
        $existing = self::findConversationByTenantAndName($tenantId, $notifyName);
        if ($existing) {
            // Usa contact_external_id da conversa existente
            $channelInfo = [
                'channel_type' => 'whatsapp',
                'contact_external_id' => $existing['contact_external_id'],
                'direction' => 'inbound',
                // ...
            ];
        }
    }
}
```

**Vantagens:**
- ✅ Funciona com dados existentes
- ✅ Não requer nova tabela

**Desvantagens:**
- ⚠️ Pode não funcionar para novas conversas
- ⚠️ Depende de conversa já existir

---

## 🎯 Recomendação

**Solução 1 (Mapeamento) + Solução 4 (Fallback)** é a mais robusta:

1. Criar tabela `whatsapp_business_ids` para mapeamento direto
2. Implementar fallback para buscar conversa existente se mapeamento não existir
3. Popular tabela com dados existentes (conversas já criadas)

---

## 📝 Próximos Passos

1. ✅ **Causa raiz identificada** - `extractChannelInfo()` retorna `NULL`
2. ⏳ **Implementar solução** - Escolher e implementar uma das soluções acima
3. ⏳ **Testar** - Enviar mensagem de teste e verificar se conversa atualiza
4. ⏳ **Remover logs temporários** - Após confirmação, remover logs de diagnóstico

---

**Última atualização:** 2026-01-13

