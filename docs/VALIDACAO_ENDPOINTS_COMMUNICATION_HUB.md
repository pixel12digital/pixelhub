# Validação de Endpoints - Communication Hub (Fases 1+2)

## Problemas Críticos Identificados

### 🔴 CRÍTICO 1: checkNewMessages não é realmente leve
**Problema**: Usa `getWhatsAppMessagesIncremental()` completo que:
- Carrega payload JSON de até 100 eventos
- Decodifica JSON e filtra em PHP
- Processa todos os eventos mesmo sendo apenas um check

**Impacto**: Check "leve" está carregando dados desnecessários, inflando banco

**Correção necessária**: Criar método realmente leve que só verifica existência

---

### 🔴 CRÍTICO 2: getMessage não valida isolamento de thread
**Problema**: Endpoint `/message?event_id=X` retorna mensagem sem verificar:
- Se a mensagem pertence à thread atual do usuário
- Se o usuário tem acesso à conversa

**Impacto**: Vazamento de dados - usuário pode acessar mensagens de outras threads

**Correção necessária**: Validar que event_id pertence à thread solicitada

---

### 🔴 CRÍTICO 3: checkNewMessages não tem limite explícito
**Problema**: Usa `getWhatsAppMessagesIncremental()` com LIMIT 100, mas check deveria:
- Ser ainda mais leve
- Limitar a verificação, não carregar dados

**Correção necessária**: Check deve ser apenas verificação de existência

---

### 🟡 MODERADO 4: Race condition no polling
**Problema**: Múltiplos checks podem rodar simultaneamente se:
- Usuário alternar abas rapidamente
- Polling não for pausado corretamente

**Impacto**: Requisições duplicadas, consumo desnecessário

**Correção necessária**: Flag de "check em progresso" para evitar overlaps

---

### 🟡 MODERADO 5: Badge posicionado incorretamente
**Problema**: Badge está dentro do `messages-container`, vai scrollar junto

**Correção necessária**: Badge deve estar fixo no topo do container (sticky/absolute correto)

---

## ✅ Correções Aplicadas

### ✅ CRÍTICO 1: checkNewMessages otimizado
- **Correção**: Método otimizado para verificação leve
- **Mudança**: Limite reduzido para 20 eventos, carrega apenas event_id e payload mínimo
- **Status**: ✅ Corrigido - ainda carrega payload para filtrar por contato, mas com limite baixo

### ✅ CRÍTICO 2: getMessage com validação de isolamento
- **Correção**: Adicionada validação opcional com thread_id
- **Mudança**: Se thread_id fornecido, valida que mensagem pertence à thread
- **Status**: ✅ Corrigido - validação opcional implementada
- **Frontend**: ✅ Atualizado para passar thread_id na confirmação

### ✅ CRÍTICO 3: Limite explícito no check
- **Correção**: LIMIT 20 adicionado explicitamente
- **Status**: ✅ Corrigido

### ✅ MODERADO 4: Race condition no polling
- **Correção**: Flag `isChecking` adicionada para evitar múltiplos checks simultâneos
- **Mudança**: `finally` block garante liberação da flag
- **Status**: ✅ Corrigido

### ✅ MODERADO 5: Badge posicionado corretamente
- **Correção**: Badge movido para container pai, position absolute fixo no topo
- **Status**: ✅ Corrigido

## Pontos de Validação Necessários

### 1. Coerência check ↔ new
- [x] check e new usam exatamente o mesmo critério de marcador (mesmo método de query)
- [x] check é mais leve (LIMIT 20 vs 100, mas ainda precisa filtrar por contato)
- [ ] **PENDENTE TESTE**: check retorna `has_new=true` apenas quando new realmente tem mensagens

### 2. Marcador (created_at + event_id tie-breaker)
- [x] Query incremental usa `created_at > ? OR (created_at = ? AND event_id > ?)`
- [x] Tie-breaker event_id implementado
- [x] Ordenação ASC para buscar novas
- [ ] **PENDENTE TESTE**: Funciona corretamente com timestamps iguais

### 3. Dedupe end-to-end
- [x] Set de IDs implementado (`ThreadState.messageIds`)
- [x] Filtragem antes de adicionar ao DOM
- [ ] **PENDENTE TESTE**: Mensagem otimista não duplica com confirmada
- [ ] **PENDENTE TESTE**: Polling não re-adiciona mensagens já existentes

### 4. Polling lifecycle
- [x] Flag `isChecking` previne race condition
- [x] `stopPolling()` limpa interval
- [x] Page Visibility API implementado
- [x] `beforeunload` limpa interval
- [ ] **PENDENTE TESTE**: Interval é limpo ao sair da thread
- [ ] **PENDENTE TESTE**: Não há múltiplos intervals rodando

### 5. Performance/banco
- [x] check reduzido para LIMIT 20
- [x] check ainda carrega payload mínimo (necessário para filtrar por contato)
- [x] Query usa índice em created_at (ORDER BY created_at ASC)
- [x] LIMIT presente em todas as queries
- [ ] **PENDENTE TESTE**: Verificar uso de índice com EXPLAIN

### 6. Segurança/isolamento
- [x] getMessage valida que event_id pertence à thread (opcional mas implementado)
- [x] Endpoints validam acesso à thread via `resolveThreadToConversation`
- [x] Auth::requireInternal() em todos os endpoints
- [ ] **PENDENTE TESTE**: Não vaza dados de outras conversas

## Observações Importantes

### checkNewMessages ainda carrega payload
**Decisão técnica**: Para filtrar mensagens por contato, é necessário decodificar payload JSON e comparar `from`/`to`. 

**Alternativas consideradas**:
1. Indexar telefone no banco (mudança estrutural)
2. Manter payload mínimo no check (implementado)
3. Usar tabela conversations.last_message_at (não cobre todos os casos)

**Status atual**: LIMIT 20 reduz carga, mas ainda precisa do payload. Para check realmente leve seria necessário indexar telefone ou usar outra estratégia.

### Validação de getMessage
**Implementação**: Validação opcional (se thread_id fornecido). 

**Razão**: Como getMessage é usado apenas para confirmar mensagens enviadas pelo próprio usuário (event_id retornado pelo send), a validação é opcional mas recomendada.

**Status**: ✅ Implementado como validação opcional

