# Correções Implementadas - Bug de Duplicação de Conversas

**Data:** 2026-01-22  
**Status:** ✅ Implementado

---

## Resumo das Correções

Foram implementadas 3 correções principais para prevenir e resolver duplicação de conversas:

### 1. ✅ Prevenção na Criação (ConversationService)

**Arquivo:** `src/Services/ConversationService.php`

**Mudança:** Adicionada verificação de duplicados por `remote_key` antes de criar nova conversa.

**Método adicionado:**
- `findDuplicateByRemoteKey()` - Busca conversas duplicadas por `remote_key` antes de criar nova

**Comportamento:**
- Antes de criar nova conversa, verifica se já existe uma com mesmo `remote_key`
- Se encontrar, atualiza a existente ao invés de criar nova
- Prioriza conversa com `thread_key` completo (mais completa)

**Localização no código:**
- Linha ~138-147: Verificação de duplicados antes de criar

---

### 2. ✅ Atualização de Duplicados no Vínculo (CommunicationHubController)

**Arquivo:** `src/Controllers/CommunicationHubController.php`

**Mudança:** Ao vincular uma conversa a um tenant, também atualiza todas as conversas duplicadas.

**Método modificado:**
- `linkIncomingLeadToTenant()` - Agora atualiza todas as conversas com mesmo `remote_key`

**Comportamento:**
- Ao vincular conversa A ao Tenant X, busca todas as conversas com mesmo `remote_key`
- Atualiza todas para o mesmo `tenant_id` e `is_incoming_lead = 0`
- Previne que conversas duplicadas fiquem com tenants diferentes

**Localização no código:**
- Linha ~3884-3920: Lógica de atualização de duplicados

---

### 3. ✅ Migration para Índice Único (Prevenção no Banco)

**Arquivo:** `database/migrations/20260122_prevent_conversation_duplication.php`

**Mudança:** Adiciona índice único composto `(channel_type, remote_key)` para prevenir duplicação no banco.

**Comportamento:**
- Verifica se já existem duplicados antes de criar índice
- Se encontrar duplicados, cancela a migration (requer limpeza manual primeiro)
- Se não houver duplicados, cria índice único que previne novos duplicados

**IMPORTANTE:** Esta migration requer que duplicados existentes sejam resolvidos primeiro.

---

## Próximos Passos (Não Implementados)

### 4. Script de Limpeza (One-time)

**Status:** ⏳ Pendente

**Necessário para:**
- Resolver casos existentes de duplicação (ex: Conversas 15 e 17)
- Permitir execução da migration de índice único

**Estratégia sugerida:**
1. Identificar todos os pares duplicados (Query 2 do diagnóstico)
2. Para cada par:
   - Escolher conversa canônica (critério: `thread_key` completo, mais recente, ou mais mensagens)
   - Migrar dados se necessário
   - Atualizar referências (se houver outras tabelas)
   - Marcar/deletar duplicada

---

## Impacto das Correções

### Prevenção Futura
- ✅ Novas conversas não serão criadas se já existir uma com mesmo `remote_key`
- ✅ Vínculo de tenant atualiza todas as duplicadas automaticamente
- ✅ Índice único no banco previne duplicação mesmo se houver race condition

### Casos Existentes
- ⚠️ Duplicados existentes (ex: Conversas 15 e 17) precisam ser resolvidos manualmente
- ⚠️ Migration de índice único não pode ser executada enquanto houver duplicados

---

## Testes Recomendados

1. **Teste de Prevenção:**
   - Criar evento com `169183207809126@lid`
   - Verificar se reutiliza conversa existente ao invés de criar nova

2. **Teste de Vínculo:**
   - Vincular conversa A ao Tenant X
   - Verificar se conversa B (duplicada) também foi atualizada

3. **Teste de Migration:**
   - Resolver duplicados existentes
   - Executar migration
   - Tentar criar duplicado (deve falhar com erro de constraint)

---

## Arquivos Modificados

1. `src/Services/ConversationService.php`
   - Adicionado método `findDuplicateByRemoteKey()`
   - Adicionada verificação antes de criar conversa

2. `src/Controllers/CommunicationHubController.php`
   - Modificado método `linkIncomingLeadToTenant()`
   - Adicionada lógica de atualização de duplicados

3. `database/migrations/20260122_prevent_conversation_duplication.php`
   - Nova migration para índice único

---

**Fim do Documento**

