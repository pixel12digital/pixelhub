# RELATÓRIO DE AUDITORIA — RECEBIMENTO QUEBROU APÓS HABILITAR ENVIO

**Data:** 16/01/2026  
**Status:** ⚠️ Problema crítico identificado  
**Prioridade:** Alta

---

## 📋 RESUMO EXECUTIVO

Após corrigir o envio de mensagens pelo Painel de Comunicação, o **recebimento (inbound) passou a falhar ou funcionar incorretamente**. A investigação identificou que a causa raiz provável é **duplicidade de mapeamento de sessão** na tabela `tenant_message_channels`, resultando em roteamento não-determinístico do inbound para o tenant errado.

---

## 🔍 MAPEAMENTO DO FLUXO INBOUND

### 1. Endpoint do Webhook

**Rota:** `POST /api/whatsapp/webhook`  
**Controller:** `src/Controllers/WhatsAppWebhookController.php`  
**Método:** `handle()`

### 2. Fluxo de Resolução de Tenant/Session

```
1. Webhook recebe payload do gateway
   ↓
2. Extrai channel_id (sessionId) do payload
   → Método: handle() (linhas 170-253)
   → Prioridade: sessionId do payload → session.id → data.session.id → channelId
   ↓
3. Resolve tenant_id pelo channel_id
   → Método: resolveTenantByChannel($channelId) (linha 256)
   → Query: SELECT tenant_id FROM tenant_message_channels 
            WHERE provider='wpp_gateway' 
            AND channel_id = ? 
            AND is_enabled = 1 
            LIMIT 1
   ↓
4. Ingesta evento com tenant_id resolvido
   → Método: EventIngestionService::ingest()
```

### 3. Código Crítico: `resolveTenantByChannel()`

**Arquivo:** `src/Controllers/WhatsAppWebhookController.php` (linhas 413-448)

```php
private function resolveTenantByChannel(?string $channelId): ?int
{
    // ...
    $stmt = $db->prepare("
        SELECT tenant_id 
        FROM tenant_message_channels 
        WHERE provider = 'wpp_gateway' 
        AND channel_id = ? 
        AND is_enabled = 1
        LIMIT 1
    ");
    $stmt->execute([$channelId]);
    $result = $stmt->fetch();
    
    $tenantId = $result ? (int) $result['tenant_id'] : null;
    return $tenantId;
}
```

**⚠️ PROBLEMA IDENTIFICADO:**
- A query usa `LIMIT 1` sem `ORDER BY`
- Se houver múltiplos registros habilitados para o mesmo `channel_id`, o resultado é **não-determinístico**
- O banco pode retornar qualquer um dos registros, causando roteamento inconsistente

---

## 🧪 ANÁLISE DOS PATCHES (H2/I)

### PATCH H2: `session_id` como Fonte

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Métodos:**
- `getSessionIdColumnName()` (linha 3573)
- `validateGatewaySessionId()` (linha 3613)

**O que faz:**
- Detecta se a tabela `tenant_message_channels` tem coluna `session_id`
- Se sim, usa `session_id` para validar sessões do gateway
- Se não, usa `channel_id` como fallback

**Impacto no inbound:**
- ✅ **NÃO AFETA DIRETAMENTE** o inbound
- O inbound usa apenas `channel_id` na query (não verifica `session_id`)
- Métodos do send não são chamados pelo inbound

### PATCH I: Tenant por Conversa (Auto-cura)

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Localização:** `send()` (linhas 373-405)

**O que faz:**
- Quando `thread_id` existe, deriva `tenant_id` da tabela `conversations`
- Se conversa não tem `tenant_id` mas tem `channel_id`, tenta resolver e **persiste** na conversa (auto-cura)

**Impacto no inbound:**
- ⚠️ **PODE AFETAR INDIRETAMENTE** através de "auto-cura"
- Se uma conversa criada pelo inbound não tinha `tenant_id`, o send pode adicionar um `tenant_id` errado baseado na resolução por `channel_id`
- Mas isso só acontece se houver envio via painel após recebimento

**Conclusão:** PATCH I não é a causa direta, mas pode ter agravado o problema se houve duplicidade de registros.

---

## 📊 VERIFICAÇÕES NECESSÁRIAS NO BANCO DE DADOS

### Query 1: Registros para sessão `pixel12digital`

```sql
SELECT id, tenant_id, provider, channel_id, 
       COALESCE(session_id, 'NULL') as session_id, 
       is_enabled, created_at, updated_at
FROM tenant_message_channels
WHERE provider = 'wpp_gateway' 
AND (channel_id = 'pixel12digital' OR session_id = 'pixel12digital')
ORDER BY is_enabled DESC, id DESC;
```

**O que verificar:**
- Quantos registros existem para `pixel12digital`
- Quantos estão habilitados (`is_enabled = 1`)
- Quais `tenant_id` estão associados
- Se há múltiplos tenants para a mesma sessão

### Query 2: Sessões habilitadas por tenant

```sql
SELECT tenant_id, provider, channel_id, 
       COALESCE(session_id, 'NULL') as session_id, 
       is_enabled
FROM tenant_message_channels
WHERE provider = 'wpp_gateway' 
AND is_enabled = 1
ORDER BY channel_id, tenant_id;
```

**O que verificar:**
- Se há duplicidade de `channel_id` entre tenants
- Se o tenant 121 tem registro para `pixel12digital`
- Se outro tenant também tem registro para `pixel12digital`

### Query 3: Eventos recentes do inbound

```sql
SELECT ce.id, ce.event_id, ce.event_type, ce.tenant_id, 
       ce.metadata, ce.created_at,
       JSON_EXTRACT(ce.metadata, '$.channel_id') as metadata_channel_id
FROM communication_events ce
WHERE ce.source_system = 'wpp_gateway'
AND (
    JSON_EXTRACT(ce.metadata, '$.channel_id') = 'pixel12digital'
    OR JSON_EXTRACT(ce.payload, '$.session.id') = 'pixel12digital'
    OR JSON_EXTRACT(ce.payload, '$.sessionId') = 'pixel12digital'
    OR JSON_EXTRACT(ce.payload, '$.channelId') = 'pixel12digital'
)
ORDER BY ce.created_at DESC
LIMIT 30;
```

**O que verificar:**
- Qual `tenant_id` está sendo atribuído aos eventos recentes
- Se houve mudança de `tenant_id` após criação do canal no tenant 121
- Comparar eventos antes vs depois da criação do canal

---

## 🎯 HIPÓTESES DE CAUSA RAIZ

### Hipótese A: Duplicidade de Mapeamento (⚠️ MAIS PROVÁVEL)

**Cenário:**
- Antes: `pixel12digital` estava mapeado apenas para tenant X (funcionava)
- Depois: Foi criado registro para tenant 121 com `channel_id = 'pixel12digital'` e `is_enabled = 1`
- Agora há 2 registros habilitados para a mesma sessão

**Impacto:**
- Query `SELECT tenant_id ... LIMIT 1` pode retornar qualquer um dos registros
- Resultado é não-determinístico (pode variar entre requisições)
- Inbound pode rotear mensagens para o tenant errado

**Evidência esperada:**
- 2+ registros na `tenant_message_channels` para `pixel12digital` com `is_enabled = 1`
- Eventos recentes com `tenant_id` inconsistente ou mudando

### Hipótese B: Ordem de Criação (Registro Novo Retornado Primeiro)

**Cenário:**
- MySQL pode retornar registros em ordem de inserção (sem `ORDER BY`)
- Registro do tenant 121 foi criado mais recentemente
- Query `LIMIT 1` pode estar pegando o registro mais recente (tenant 121) em vez do antigo

**Impacto:**
- Inbound sempre roteia para tenant 121, ignorando o tenant original

**Evidência esperada:**
- Eventos recentes todos com `tenant_id = 121`
- Tenant original deixou de receber mensagens

### Hipótese C: Auto-cura do PATCH I Modificando Dados Historicamente

**Cenário:**
- Inbound cria conversas sem `tenant_id` corretamente
- PATCH I (auto-cura) resolve `tenant_id` usando `resolveTenantByChannelId()`
- Se houver duplicidade, resolve para o tenant errado
- Auto-cura persiste `tenant_id` errado na conversa

**Impacto:**
- Conversas passam a ter `tenant_id` incorreto
- Mensagens subsequentes podem ser roteadas incorretamente

**Evidência esperada:**
- Conversas com `tenant_id` diferente do esperado
- Diferença entre `tenant_id` do evento vs `tenant_id` da conversa

---

## 🛠️ PLANO DE CORREÇÃO

### Fase 1: Confirmação (Agora)

**Ação:** Executar queries de verificação no banco de dados

**Script criado:** `database/auditoria-inbound-duplicidade.php`

**Resultado esperado:**
- Confirmar duplicidade de registros
- Identificar qual tenant deveria receber as mensagens
- Verificar eventos recentes para ver padrão de roteamento

### Fase 2: Medida de Contenção Imediata

**Se duplicidade confirmada:**

```sql
-- Desabilitar temporariamente o registro do tenant 121
UPDATE tenant_message_channels 
SET is_enabled = 0, updated_at = NOW()
WHERE provider = 'wpp_gateway' 
AND channel_id = 'pixel12digital' 
AND tenant_id = 121;
```

**Teste:**
- Enviar mensagem do WhatsApp para `pixel12digital`
- Verificar se cai no tenant correto (não 121)
- Confirmar que inbound volta a funcionar

**Rollback (se necessário):**
```sql
UPDATE tenant_message_channels 
SET is_enabled = 1, updated_at = NOW()
WHERE provider = 'wpp_gateway' 
AND channel_id = 'pixel12digital' 
AND tenant_id = 121;
```

### Fase 3: Correção Definitiva

**Opção 1: Constraint UNIQUE (Recomendado)**

**Migration:**
```sql
-- Garantir que apenas um registro habilitado por channel_id
ALTER TABLE tenant_message_channels 
ADD UNIQUE INDEX idx_provider_channel_enabled (provider, channel_id, is_enabled)
WHERE is_enabled = 1;
```

**Problema:** MySQL não suporta índices parciais com `WHERE`. Alternativa:

```sql
-- Índice composto (pode ter múltiplos is_enabled=1, mas ajuda)
ALTER TABLE tenant_message_channels 
ADD INDEX idx_provider_channel_enabled (provider, channel_id, is_enabled);

-- Constraint lógica na aplicação
```

**Opção 2: Campo `owner_tenant_id` (Mais Flexível)**

**Migration:**
```sql
ALTER TABLE tenant_message_channels 
ADD COLUMN owner_tenant_id INT UNSIGNED NULL AFTER tenant_id,
ADD INDEX idx_owner_tenant (owner_tenant_id);

-- Migrar dados existentes
UPDATE tenant_message_channels 
SET owner_tenant_id = tenant_id 
WHERE owner_tenant_id IS NULL AND is_enabled = 1;
```

**Alteração no código:**
```php
// Inbound: Prioriza owner_tenant_id
$stmt = $db->prepare("
    SELECT COALESCE(owner_tenant_id, tenant_id) as tenant_id 
    FROM tenant_message_channels 
    WHERE provider = 'wpp_gateway' 
    AND channel_id = ? 
    AND is_enabled = 1
    ORDER BY owner_tenant_id IS NULL, id ASC
    LIMIT 1
");
```

**Opção 3: Resolver Deterministicamente (Mais Simples)**

**Alteração no código:**
```php
// Inbound: Ordenar por id (mais antigo primeiro = tenant original)
$stmt = $db->prepare("
    SELECT tenant_id 
    FROM tenant_message_channels 
    WHERE provider = 'wpp_gateway' 
    AND channel_id = ? 
    AND is_enabled = 1
    ORDER BY id ASC  -- ← GARANTE ORDEM DETERMINÍSTICA
    LIMIT 1
");
```

**Vantagem:** Correção imediata sem mudança no schema  
**Desvantagem:** Assume que o tenant original é o mais antigo (pode não ser verdade)

---

## 📝 CHECKLIST DE DIAGNÓSTICO

- [ ] Executar `database/auditoria-inbound-duplicidade.php`
- [ ] Verificar quantos registros existem para `pixel12digital`
- [ ] Verificar quais tenants estão associados
- [ ] Verificar eventos recentes do inbound
- [ ] Comparar tenant_id antes vs depois da criação do canal
- [ ] Testar envio de mensagem para `pixel12digital`
- [ ] Verificar qual tenant recebe a mensagem
- [ ] Aplicar medida de contenção se duplicidade confirmada
- [ ] Testar se inbound volta a funcionar
- [ ] Implementar correção definitiva

---

## ⚠️ REGRAS PARA PREVENÇÃO FUTURA

1. **Regra de ouro:** Uma sessão (sessionId) habilitada deve pertencer a apenas **UM tenant** por vez
2. **Validação na criação:** Antes de criar canal, verificar se já existe outro tenant com mesma sessão habilitada
3. **Constraint lógica:** Adicionar verificação na aplicação antes de habilitar canal
4. **Logs:** Registrar sempre qual tenant foi resolvido no inbound (já existe)

---

## 📚 ARQUIVOS RELACIONADOS

- **Inbound:** `src/Controllers/WhatsAppWebhookController.php`
  - Método: `resolveTenantByChannel()` (linhas 413-448)
  
- **Send:** `src/Controllers/CommunicationHubController.php`
  - Método: `resolveTenantByChannelId()` (linhas 3703-3732)
  - Método: `validateGatewaySessionId()` (linhas 3613-3677)
  - Método: `send()` com PATCH I (linhas 373-405)

- **Script de Auditoria:** `database/auditoria-inbound-duplicidade.php`

---

## 🎯 CONCLUSÃO

**Status:** ⚠️ Problema crítico identificado, aguardando confirmação via queries no banco

**Causa provável:** Duplicidade de mapeamento de sessão `pixel12digital` para múltiplos tenants, resultando em roteamento não-determinístico no inbound.

**Próximos passos:**
1. Executar queries de verificação
2. Confirmar duplicidade
3. Aplicar medida de contenção imediata
4. Implementar correção definitiva

---

**Documento gerado em:** 16/01/2026  
**Última atualização:** 16/01/2026  
**Versão:** 1.0

