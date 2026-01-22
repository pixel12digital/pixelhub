# Etapa 1: Núcleo Conversacional — Implementação

**Data:** 2026-01-09  
**Versão:** 1.0  
**Status:** ✅ Implementado

---

## Objetivo

Criar um núcleo conversacional central (`Conversation`) que organize mensagens sem alterar fluxos existentes.

**Princípio:** Centralização por referência, não por substituição.

---

## O Que Foi Implementado

### 1. Tabela `conversations`

**Arquivo:** `database/migrations/20260109_create_conversations_table.php`

**Estrutura:**
- `conversation_key` — Chave única: `{channel_type}_{channel_account_id}_{contact_external_id}`
- `channel_type` — whatsapp, email, webchat, etc.
- `channel_account_id` — FK para `tenant_message_channels` (opcional)
- `contact_external_id` — ID externo do contato (telefone, e-mail)
- `contact_name` — Nome do contato (extraído do provedor)
- `tenant_id` — FK para `tenants` (opcional)
- `status` — new, open, pending, closed, archived
- `assigned_to` — FK para `users` (atendente)
- Metadados: SLA, contadores, timestamps

**Características:**
- ✅ Não altera tabelas existentes
- ✅ Compatível com estrutura atual
- ✅ Índices otimizados para consultas

---

### 2. ConversationService (Resolvedor)

**Arquivo:** `src/Services/ConversationService.php`

**Responsabilidades:**
1. **Identificar conversa existente** — Busca por `conversation_key`
2. **Criar nova conversa** — Se não existe, cria automaticamente
3. **Atualizar metadados** — Última mensagem, contadores, status

**Método Principal:**
```php
ConversationService::resolveConversation($eventData)
```

**Comportamento:**
- ✅ Não quebra se tabela não existir (retorna null)
- ✅ Não quebra se houver erro (log e continua)
- ✅ Não aplica regras de negócio (apenas organiza)
- ✅ Não envia mensagens
- ✅ Não altera fluxos existentes

---

### 3. Integração Incremental

**Arquivo:** `src/Services/EventIngestionService.php`

**Modificação:**
- Após ingerir evento, chama `ConversationService::resolveConversation()`
- Integração é **opcional** (try/catch, não quebra se falhar)
- Logs informativos quando conversa é resolvida

**Fluxo:**
```
Webhook → EventIngestionService::ingest()
    ↓
Insere em communication_events
    ↓
[NOVO] ConversationService::resolveConversation()
    ↓
Cria/atualiza conversations
    ↓
Retorna event_id (compatível com código existente)
```

---

## Compatibilidade

### ✅ O Que Continua Funcionando

1. **Webhooks** — Continuam iguais
2. **communication_events** — Continua sendo usado
3. **UI atual** — `/communication-hub` continua funcionando
4. **Gateways** — Nenhuma alteração
5. **Roteamento** — Nenhuma alteração

### ✅ O Que Foi Adicionado

1. **Tabela conversations** — Nova entidade central
2. **ConversationService** — Resolvedor de conversas
3. **Integração automática** — Após ingestão de eventos

---

## Como Testar

### 1. Executar Migration

```bash
php database/migrate.php
```

**Verificar:**
```sql
SHOW TABLES LIKE 'conversations';
DESCRIBE conversations;
```

### 2. Testar Resolução de Conversa

**Cenário 1: Primeira mensagem**
1. Enviar mensagem WhatsApp para o sistema
2. Webhook recebe → Evento ingerido
3. Verificar se conversa foi criada:
   ```sql
   SELECT * FROM conversations 
   WHERE channel_type = 'whatsapp' 
   ORDER BY created_at DESC 
   LIMIT 1;
   ```

**Cenário 2: Mensagem subsequente**
1. Enviar segunda mensagem do mesmo contato
2. Verificar se conversa foi atualizada (não criada nova):
   ```sql
   SELECT * FROM conversations 
   WHERE contact_external_id = '5547999999999';
   -- Deve ter apenas 1 registro
   -- message_count deve ser 2
   -- last_message_at deve ser atualizado
   ```

### 3. Verificar Logs

**Logs esperados:**
```
[EventIngestion] Evento ingerido: whatsapp.inbound.message (event_id: xxx, ...)
[EventIngestion] Conversa resolvida: conversation_id=1, conversation_key=whatsapp_1_5547999999999
```

### 4. Testar Compatibilidade

**Verificar que UI atual continua funcionando:**
1. Acessar `/communication-hub`
2. Verificar se threads aparecem normalmente
3. Verificar se envio de mensagens funciona

---

## Estrutura de Dados

### Exemplo de Conversa Criada

```json
{
  "id": 1,
  "conversation_key": "whatsapp_1_5547999999999",
  "channel_type": "whatsapp",
  "channel_account_id": 1,
  "contact_external_id": "5547999999999",
  "contact_name": "João Silva",
  "tenant_id": 10,
  "product_id": null,
  "status": "new",
  "assigned_to": null,
  "message_count": 1,
  "unread_count": 1,
  "last_message_at": "2026-01-09 10:00:00",
  "last_message_direction": "inbound",
  "created_at": "2026-01-09 10:00:00"
}
```

---

## Próximos Passos (Não Implementados Nesta Etapa)

### Fase 2: Integração com UI
- Atualizar `/communication-hub` para ler de `conversations`
- Adicionar filtros por status, atendente, SLA
- Ordenação por SLA, não lidas, prioridade

### Fase 3: Funcionalidades Básicas
- Transferir conversa
- Encerrar/pausar
- Tags e notas internas

### Fase 4: Atribuição
- Sistema de atribuição automática
- Round-robin, disponibilidade, prioridade

---

## Troubleshooting

### Problema: Conversas não estão sendo criadas

**Verificar:**
1. Migration foi executada?
   ```sql
   SHOW TABLES LIKE 'conversations';
   ```

2. Evento é de mensagem?
   - Apenas eventos `whatsapp.inbound.message`, `whatsapp.outbound.message` geram conversas

3. Logs mostram erro?
   ```bash
   tail -f logs/pixelhub.log | grep ConversationService
   ```

### Problema: Múltiplas conversas para mesmo contato

**Causa:** `conversation_key` diferente (channel_account_id diferente)

**Solução:** Verificar se `tenant_message_channels` está correto

### Problema: Tabela não existe

**Solução:** Executar migration
```bash
php database/migrate.php
```

---

## Validação de Sucesso

✅ **Etapa 1 considerada completa quando:**

1. Migration executada com sucesso
2. Conversas sendo criadas automaticamente após ingestão de eventos
3. UI atual continua funcionando normalmente
4. Logs mostram resolução de conversas
5. Nenhum erro em produção

---

**Status:** ✅ Implementação completa e pronta para testes

