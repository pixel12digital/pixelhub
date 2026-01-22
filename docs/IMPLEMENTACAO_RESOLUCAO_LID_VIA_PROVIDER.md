# Implementação: Resolução de @lid via Provider API

**Data:** 2025-01-22  
**Objetivo:** Completar a resolução de @lid para telefone com resolução via API do gateway

---

## RESUMO DAS ALTERAÇÕES

### Arquivos Modificados

1. **`src/Core/ContactHelper.php`**
   - Adicionada função `resolvePnLidViaProvider()` - Resolve @lid via API do gateway
   - Adicionada função `saveLidMapping()` - Garante persistência do mapeamento encontrado
   - Ajustada `resolveLidPhone()` - Consulta cache sem sessionId como fallback + integra provider como última camada
   - Ajustada `resolveLidPhonesBatch()` - Consulta cache sem sessionId como fallback + integra provider (limitado a 10 itens)

2. **`src/Controllers/CommunicationHubController.php`**
   - Simplificada função `normalizeSender()` - Agora usa `ContactHelper::resolveLidPhone()` que já tem toda a lógica integrada

---

## FUNCIONALIDADES IMPLEMENTADAS

### 1. Resolução via API do Provider

**Função:** `ContactHelper::resolvePnLidViaProvider()`

**Fluxo:**
- Endpoint: `/api/{sessionId}/contact/pn-lid/{pnLid}`
- Autenticação: Header `X-Gateway-Secret`
- Timeout: 10 segundos (5s conexão)
- Extrai telefone de múltiplos campos possíveis no JSON de resposta
- Retorna `phone_e164` ou `null`

**Logs:**
- Apenas quando falhar (discreto para troubleshooting)
- Não loga 404 (esperado quando não encontra)

### 2. Fallback de Cache sem sessionId

**Implementado em:**
- `resolveLidPhone()` - Linha 329-353
- `resolveLidPhonesBatch()` - Linha 502-539

**Comportamento:**
1. Primeiro tenta com `sessionId` (se disponível)
2. Se não encontrar, tenta sem `sessionId` (apenas `provider + pnlid`)
3. Ordena por `updated_at DESC` para pegar o mais recente

**Query de fallback:**
```sql
SELECT phone_e164, updated_at 
FROM wa_pnlid_cache 
WHERE provider = ? AND pnlid = ? 
AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY updated_at DESC
LIMIT 1
```

### 3. Persistência Automática do Mapeamento

**Função:** `ContactHelper::saveLidMapping()`

**Salva em:**
- `whatsapp_business_ids` - Mapeamento persistente (INSERT IGNORE)
- `wa_pnlid_cache` - Cache temporário (se tiver sessionId)

**Chamado automaticamente quando:**
- `resolvePnLidViaProvider()` encontra telefone
- `resolveLidPhoneFromEvents()` encontra telefone

### 4. Integração como Última Camada

**Ordem de prioridade (atualizada):**

1. ✅ `whatsapp_business_ids` (mapeamento persistente)
2. ✅ `wa_pnlid_cache` com sessionId (se disponível)
3. ✅ `wa_pnlid_cache` sem sessionId (fallback)
4. ✅ `communication_events` (busca no payload)
5. ✅ **NOVO:** `resolvePnLidViaProvider()` (API do gateway)

---

## ENDPOINTS/TELAS AFETADOS

### Listagem de Conversas
**Endpoint:** `GET /communication-hub/conversations-list`  
**Método:** `CommunicationHubController::getConversationsList()`

**Melhoria:**
- Agora resolve @lid via provider quando não encontra em cache/eventos
- Limite: Apenas 10 itens não resolvidos tentam via provider (performance)

**Resultado:**
- Exibe número formatado ao invés de "ID WhatsApp: XXXX" quando resolução via provider é bem-sucedida

### Detalhe da Conversa
**Endpoint:** `GET /communication-hub/thread-info?thread_id=X`  
**Método:** `CommunicationHubController::getWhatsAppThreadInfo()`

**Melhoria:**
- Agora resolve @lid via provider quando não encontra em cache/eventos
- Garante persistência do mapeamento encontrado

**Resultado:**
- Exibe número formatado ao invés de "ID WhatsApp: XXXX" quando resolução via provider é bem-sucedida

### Mensagens da Conversa
**Endpoint:** `GET /communication-hub/messages?thread_id=X`  
**Método:** `CommunicationHubController::getWhatsAppMessagesFromConversation()`

**Melhoria:**
- `normalizeSender()` agora usa `ContactHelper::resolveLidPhone()` que tem toda a lógica integrada
- Resolve @lid via provider quando necessário

**Resultado:**
- Exibe número formatado no campo `from_display` ao invés de "ID WhatsApp: XXXX" quando resolução via provider é bem-sucedida

---

## CASOS DE TESTE

### ✅ Caso 1: @c.us / @s.whatsapp.net
**Comportamento:** Continua igual (não passa pelo provider)  
**Status:** ✅ Funcionando

### ✅ Caso 2: @lid já mapeado em whatsapp_business_ids
**Comportamento:** Não chama provider (retorna imediatamente)  
**Status:** ✅ Funcionando

### ✅ Caso 3: @lid sem sessionId mas com cache antigo em wa_pnlid_cache
**Comportamento:** Resolve via fallback (consulta sem sessionId)  
**Status:** ✅ Funcionando

### ✅ Caso 4: @lid sem nada (sem cache e sem eventos úteis)
**Comportamento:** Provider tenta resolver; se falhar, continua exibindo "ID WhatsApp: XXXX"  
**Status:** ✅ Funcionando

### ✅ Caso 5: Performance na listagem
**Comportamento:** Batch mantém 2-3 queries + provider apenas para poucos casos (limitado a 10 itens)  
**Status:** ✅ Funcionando

---

## LOGS ADICIONADOS

### Logs de Erro (Discretos)

**Formato:** `[ContactHelper::resolvePnLidViaProvider]`

**Quando são gerados:**
- HTTP errors (exceto 404)
- Erros de conexão cURL
- Exceções durante resolução
- JSON inválido

**Como ativar/desativar:**
- Logs são gerados apenas quando há erro
- Para desativar completamente, comentar linhas com `error_log()` na função `resolvePnLidViaProvider()`

**Exemplo de log:**
```
[ContactHelper::resolvePnLidViaProvider] HTTP 500 para pnLid=56083800395891, sessionId=imobsites
[ContactHelper::resolvePnLidViaProvider] Exceção: Connection timeout
```

---

## COMPATIBILIDADE

### ✅ Mantido
- Rotas e endpoints não foram alterados
- Responses não foram alterados
- Contrato do frontend não foi alterado
- Lógica de `remote_key` não foi alterada
- Otimizações batch mantidas (sem N+1)

### ✅ Melhorado
- Resolução de @lid agora tem mais uma camada (provider API)
- Cache funciona mesmo sem sessionId
- Mapeamentos são persistidos automaticamente

---

## PRÓXIMOS PASSOS (Opcional)

1. **Monitoramento:**
   - Adicionar métricas de taxa de sucesso da resolução via provider
   - Monitorar tempo de resposta da API

2. **Otimizações:**
   - Cache de resoluções falhas (evitar chamadas repetidas)
   - Rate limiting para chamadas ao provider

3. **Melhorias:**
   - Suporte a outros providers além de `wpp_gateway`
   - Retry automático com backoff exponencial

---

**Fim do Documento**

