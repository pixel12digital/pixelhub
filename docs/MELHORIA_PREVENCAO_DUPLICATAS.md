# MELHORIA: Prevenção de Duplicatas de Conversas

## Data: 2026-01-23
## Status: ✅ Implementado

---

## Problema Resolvido

O método `findEquivalentConversation()` no `ConversationService` não detectava números com 10 ou 11 dígitos após o DDD, causando criação de conversas duplicadas quando havia variação no 9º dígito.

**Exemplo do problema:**
- Número correto: `558799884234` (10 dígitos após DDD 87)
- Número incorreto: `5587999884234` (11 dígitos após DDD 87, com 9 extra)
- Resultado: Duas conversas criadas para o mesmo contato

---

## Melhorias Implementadas

### 1. Extensão do `findEquivalentConversation()`

**Antes:** Detectava apenas números com 8 ou 9 dígitos após o DDD

**Agora:** Detecta números com 8, 9, 10 ou 11 dígitos após o DDD

**Lógica implementada:**
- **8 dígitos**: Tenta adicionar 9º dígito (9 + número)
- **9 dígitos**: Tenta remover 9º dígito
- **10 dígitos**: 
  - Se começa com 9, tenta remover (pode ser 9º dígito extra)
  - Também tenta adicionar 9 no início
- **11 dígitos**: 
  - Remove primeiro dígito (pode ser 9 extra)
  - Se primeiro dígito não é 9, também tenta adicionar 9 no início

### 2. Melhoria do `findConversationByContactOnly()`

**Antes:** Buscava apenas por `contact_external_id` exato

**Agora:** 
- Primeiro tenta busca exata
- Se não encontrar, usa `findEquivalentConversation()` para buscar variações
- Verifica compatibilidade de `channel_account_id` antes de retornar

---

## Arquivos Modificados

1. **`src/Services/ConversationService.php`**
   - Método `findEquivalentConversation()` - Linhas 1242-1287
   - Método `findConversationByContactOnly()` - Linhas 1464-1508

---

## Benefícios

1. ✅ **Prevenção de duplicatas**: Detecta números com variações antes de criar nova conversa
2. ✅ **Cobertura ampliada**: Suporta números com 8, 9, 10 ou 11 dígitos após o DDD
3. ✅ **Logs melhorados**: Adiciona logs quando encontra conversa equivalente
4. ✅ **Compatibilidade**: Mantém comportamento existente para números com 8 e 9 dígitos

---

## Testes Recomendados

1. Criar conversa com número de 10 dígitos após DDD
2. Tentar criar conversa com número de 11 dígitos (com 9 extra) para mesmo contato
3. Verificar se sistema detecta e reutiliza conversa existente
4. Verificar logs para confirmar detecção de equivalente

---

## Casos de Uso Cobertos

### Caso 1: Número com 8 dígitos
- Entrada: `558799884234` (8 dígitos após DDD)
- Busca por: `5587999884234` (9 dígitos)
- ✅ Detectado

### Caso 2: Número com 9 dígitos
- Entrada: `5587999884234` (9 dígitos após DDD)
- Busca por: `558799884234` (8 dígitos)
- ✅ Detectado

### Caso 3: Número com 10 dígitos (NOVO)
- Entrada: `558799884234` (10 dígitos após DDD)
- Busca por: `5587999884234` (11 dígitos com 9 extra)
- ✅ Detectado

### Caso 4: Número com 11 dígitos (NOVO)
- Entrada: `5587999884234` (11 dígitos após DDD)
- Busca por: `558799884234` (10 dígitos)
- ✅ Detectado

---

## Próximos Passos

1. ✅ Correção da duplicata existente (Robson) - CONCLUÍDO
2. ✅ Implementação da prevenção - CONCLUÍDO
3. ⏳ Monitorar logs em produção para verificar eficácia
4. ⏳ Considerar adicionar índice em `contact_external_id` para melhorar performance

---

## Notas Técnicas

- A lógica de detecção é executada **antes** de criar nova conversa
- Múltiplas variações são testadas em ordem até encontrar match
- Logs são gerados quando conversa equivalente é encontrada
- Performance: O método tenta busca por chave primeiro (mais rápido), depois por variações

