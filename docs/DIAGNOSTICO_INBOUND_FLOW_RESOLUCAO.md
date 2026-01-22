# Diagnóstico e Resolução - Fluxo Inbound WhatsApp

## 🔴 Problema Identificado

Mensagens enviadas via WhatsApp não apareciam na Inbox, mesmo com:
- ✅ Eventos chegando corretamente ao webhook (`/api/whatsapp/webhook`)
- ✅ Eventos sendo ingeridos em `communication_events`
- ✅ Conversas sendo criadas na tabela `conversations`

## 📊 Diagnóstico Realizado

### 1. Verificação de Configuração
- ⚠️ `PIXELHUB_WHATSAPP_WEBHOOK_SECRET` não configurado (aceita requisições sem validação)
- ✅ Endpoint `/api/whatsapp/webhook` está ativo

### 2. Verificação de Eventos
- ✅ **10 mensagens inbound** recebidas nas últimas 24 horas
- ⚠️ Todos os eventos com `status = 'queued'` (não processados ainda)
- ⚠️ Todos os eventos com `tenant_id = NULL` (nenhum canal configurado)

### 3. Verificação de Conversas
- ✅ **2 conversas criadas** nas últimas 24 horas:
  - `whatsapp:208989199560861:global` (Contact: 208989199560861)
  - `whatsapp:554796164699:global` (Contact: 554796164699)

### 4. Problema Raiz Identificado

O `CommunicationHubController::getWhatsAppThreads()` estava:
- ❌ Lendo de `communication_events` e agrupando dinamicamente
- ❌ Filtrando por `tenant_id` que estava `NULL` em todos os eventos
- ❌ Ignorando a tabela `conversations` que é a fonte de verdade

## ✅ Resolução Implementada

### 1. Ajuste do `CommunicationHubController`

#### `getWhatsAppThreads()` - Agora lê de `conversations` primeiro
```php
private function getWhatsAppThreads(PDO $db, ?int $tenantId, string $status): array
{
    // 1. Tenta ler da tabela conversations (fonte de verdade)
    if (tabela existe) {
        return $this->getWhatsAppThreadsFromConversations($db, $tenantId, $status);
    }
    
    // 2. Fallback: lê de communication_events (compatibilidade)
    return $this->getWhatsAppThreadsFromEvents($db, $tenantId, $status);
}
```

#### `getWhatsAppThreadsFromConversations()` - Nova função
- Lê diretamente da tabela `conversations`
- Filtra por `channel_type = 'whatsapp'`
- Não filtra por `tenant_id` quando for `NULL` (mostra todas)
- Retorna formato padronizado para UI

#### `getWhatsAppMessages()` - Suporta dois formatos de thread_id
- **Novo formato**: `whatsapp_{conversation_id}` (lê de `conversations`)
- **Formato antigo**: `whatsapp_{tenant_id}_{from}` (lê de `communication_events`)

#### `getWhatsAppThreadInfo()` - Suporta ambos os formatos
- Busca informações da conversa pelo `conversation_id` ou pelo formato antigo
- Retorna dados completos incluindo `assigned_to`, `status`, `unread_count`

### 2. Correções Específicas

1. **Filtro de tenant_id**: Não filtra quando `tenant_id` é `NULL` (mostra todas as conversas)
2. **Normalização de contato**: Remove sufixos `@c.us`, `@lid`, etc. para comparar contatos
3. **Formato de thread_id**: Suporta `whatsapp_{conversation_id}` (nova forma) e `whatsapp_{tenant_id}_{from}` (compatibilidade)

## 🧪 Validação

### Teste Manual
1. ✅ Eventos chegam via webhook
2. ✅ Conversas são criadas automaticamente
3. ✅ UI lê de `conversations` (fonte de verdade)
4. ✅ Threads aparecem na Inbox mesmo com `tenant_id = NULL`

### Próximos Passos Recomendados

1. **Configurar canais no `tenant_message_channels`**:
   ```sql
   INSERT INTO tenant_message_channels (tenant_id, provider, channel_id, is_enabled, webhook_configured)
   VALUES (?, 'wpp_gateway', ?, 1, 1);
   ```
   Isso permitirá:
   - Resolver `tenant_id` automaticamente nas conversas
   - Filtrar conversas por tenant na UI
   - Vincular conversas a clientes específicos

2. **Processar eventos `queued`**:
   - Atualmente todos os eventos ficam com `status = 'queued'`
   - Implementar worker/processador para marcar como `processed`

3. **Configurar `PIXELHUB_WHATSAPP_WEBHOOK_SECRET`** (opcional, mas recomendado):
   - Garantir autenticação do webhook
   - Evitar requisições não autorizadas

## 📝 Arquivos Modificados

- `src/Controllers/CommunicationHubController.php`
  - `getWhatsAppThreads()` - Agora lê de `conversations` primeiro
  - `getWhatsAppThreadsFromConversations()` - Nova função
  - `getWhatsAppThreadsFromEvents()` - Refatorada para fallback
  - `getWhatsAppMessages()` - Suporta dois formatos de thread_id
  - `getWhatsAppThreadInfo()` - Suporta dois formatos de thread_id

## ✅ Estado Final

- ✅ Webhook recebe eventos corretamente
- ✅ Eventos são ingeridos em `communication_events`
- ✅ Conversas são criadas em `conversations`
- ✅ UI lê de `conversations` (fonte de verdade)
- ✅ Threads aparecem na Inbox
- ⚠️ `tenant_id` ainda é `NULL` (falta configurar canais)

---

**Data**: 2026-01-09
**Status**: ✅ Resolvido
**Próxima Etapa**: Configurar canais no `tenant_message_channels` para vincular conversas a tenants

