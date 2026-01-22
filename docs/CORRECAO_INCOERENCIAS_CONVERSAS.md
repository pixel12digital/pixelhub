# Correção de Incoerências em Conversas

## Problema Identificado

Foram encontradas múltiplas incoerências nas conversas do sistema:

1. **Timestamps incorretos**: Diferença de 3 horas (problema de timezone)
2. **Tenant IDs NULL**: Conversas não vinculadas quando deveriam estar vinculadas
3. **Contagem de mensagens incorreta**: Contando mensagens vazias ou não contando mensagens com conteúdo

## Causa Raiz

### 1. Problema de Timezone
- A função `extractMessageTimestamp()` estava convertendo timestamps Unix usando o timezone do servidor PHP (America/Sao_Paulo)
- Timestamps Unix são sempre em UTC, então a conversão estava incorreta
- **Solução**: Modificada a função para converter explicitamente para UTC

### 2. Tenant ID NULL
- Alguns eventos tinham `tenant_id` mas a conversa estava com `tenant_id = NULL`
- Isso acontecia quando a conversa era criada antes do tenant ser identificado
- **Solução**: Script de correção atualiza `tenant_id` baseado no tenant_id mais comum nos eventos

### 3. Message Count Incorreto
- O sistema estava contando mensagens vazias ou não contando mensagens com mídia
- **Solução**: Script recalcula baseado em eventos com conteúdo real ou mídia

## Correções Aplicadas

### Conversa do Aguiar (ID: 10)

**Antes:**
- `last_message_at`: 2026-01-21 14:18:51 (incorreto)
- `tenant_id`: NULL (incorreto)
- `message_count`: 2 (incorreto)

**Depois:**
- `last_message_at`: 2026-01-21 17:18:51 (corrigido - UTC)
- `tenant_id`: 121 (corrigido)
- `message_count`: 1 (corrigido)

### Total de Conversas Corrigidas

**28 conversas corrigidas** das 94 verificadas:
- 19 conversas com problemas de timestamp
- 19 conversas com tenant_id NULL
- Várias conversas com message_count incorreto

## Scripts Criados

### 1. `database/investigate-fix-aguiar-conversation.php`
- Investiga e corrige uma conversa específica
- Identifica problemas: timestamp, tenant_id, message_count
- Aplica correções automaticamente

### 2. `database/check-all-conversations-inconsistencies.php`
- Verifica todas as conversas recentes (últimos 30 dias)
- Identifica incoerências sem aplicar correções
- Gera relatório de problemas encontrados

### 3. `database/fix-all-conversations-inconsistencies.php`
- Corrige automaticamente todas as incoerências encontradas
- Processa em transação (rollback em caso de erro)
- Relatório de conversas corrigidas

## Mudanças no Código

### `src/Services/ConversationService.php`

**Função `extractMessageTimestamp()` corrigida:**
- Agora converte timestamps explicitamente para UTC
- Salva e restaura timezone original do PHP
- Garante consistência com timestamps do WhatsApp

```php
// Antes: usava timezone do servidor
return date('Y-m-d H:i:s', (int) $timestamp);

// Depois: usa UTC explicitamente
date_default_timezone_set('UTC');
$result = date('Y-m-d H:i:s', (int) $timestamp);
date_default_timezone_set($originalTimezone);
```

## Próximos Passos

1. ✅ Correção de timezone implementada (CONCLUÍDO)
2. ✅ Scripts de correção criados (CONCLUÍDO)
3. ✅ Conversas corrigidas (28 conversas)
4. ⏳ Monitorar novas conversas para garantir que não há mais problemas
5. ⏳ Considerar criar job periódico para verificar e corrigir automaticamente

## Prevenção Futura

Para evitar problemas futuros:

1. **Timestamps sempre em UTC**: A função `extractMessageTimestamp()` já está corrigida
2. **Validação de tenant_id**: Considerar melhorar a lógica de vinculação automática
3. **Contagem precisa**: Garantir que apenas mensagens com conteúdo real sejam contadas

## Notas Importantes

- **Timestamps antigos**: Apenas novas mensagens terão timestamps corretos em UTC
- **Conversas corrigidas**: Scripts podem ser executados novamente se necessário
- **Performance**: Scripts processam até 100 conversas por vez para evitar timeout

