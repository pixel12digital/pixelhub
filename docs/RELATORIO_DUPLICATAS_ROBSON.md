# RELATÓRIO: INVESTIGAÇÃO DE CONVERSAS DUPLICADAS - ROBSON

## Problema Identificado

Existem **2 conversas diferentes** para o mesmo tenant (ID 130 - Robson Wagner Alves Vieira | CFC Bom Conselho):

### Conversa #1 (ID: 103) - INCORRETA
- **Contact External ID**: `5587999884234` (com um dígito 9 a mais!)
- **Conversation Key**: `whatsapp_shared_5587999884234`
- **Thread Key**: NULL
- **Channel ID**: NULL
- **Mensagens**: 1
- **Criada em**: 2026-01-23 08:10:33
- **Status**: Parece ser uma conversa "shared" (sem channel_account_id)

### Conversa #2 (ID: 8) - CORRETA
- **Contact External ID**: `558799884234` (número correto)
- **Conversation Key**: `whatsapp_4_558799884234`
- **Thread Key**: `wpp_gateway:pixel12digital:tel:558799884234`
- **Channel ID**: `pixel12digital`
- **Mensagens**: 23
- **Criada em**: 2026-01-16 11:34:17
- **Status**: Conversa completa com thread_key e channel_id

## Causa Raiz

1. **Número incorreto na criação**: A conversa ID 103 foi criada com `contact_external_id = 5587999884234` (com um 9 a mais) ao invés de `558799884234`.

2. **Falha na detecção de duplicatas**: O método `findEquivalentConversation()` no `ConversationService` não detecta essa duplicata porque:
   - Ele só funciona para números com 8 ou 9 dígitos após o DDD
   - Neste caso, temos 10 e 11 dígitos após o DDD (87)
   - O método `findConversationByContactOnly()` busca apenas por `contact_external_id` exato

3. **Conversa "shared"**: A conversa ID 103 tem `conversation_key` começando com `whatsapp_shared_`, indicando que foi criada sem `channel_account_id`, enquanto a conversa ID 8 tem `channel_account_id = 4`.

## Solução Proposta

### Opção 1: Mesclar conversas (RECOMENDADO)
- Mover a mensagem da conversa ID 103 para a conversa ID 8
- Deletar a conversa ID 103
- Garantir que a conversa ID 8 tenha todos os dados corretos

### Opção 2: Corrigir número da conversa ID 103
- Atualizar `contact_external_id` da conversa ID 103 para `558799884234`
- Atualizar `conversation_key` para corresponder
- Verificar se isso não causa conflito com a conversa ID 8

### Opção 3: Deletar conversa duplicada
- Se a conversa ID 103 tem apenas 1 mensagem e é mais recente, pode ser deletada
- A conversa ID 8 é a principal (23 mensagens, mais antiga, thread_key completo)

## Prevenção Futura

1. **Melhorar `findEquivalentConversation()`**: Estender para detectar números com 10 ou 11 dígitos após o DDD
2. **Normalização mais robusta**: Normalizar números antes de criar/buscar conversas
3. **Validação**: Verificar se já existe conversa com número normalizado antes de criar nova

## Próximos Passos

1. ✅ Investigação completa - CONCLUÍDA
2. ⏳ Criar script de correção para mesclar/deletar duplicata
3. ⏳ Melhorar detecção de duplicatas no ConversationService
4. ⏳ Testar correção em ambiente de desenvolvimento

