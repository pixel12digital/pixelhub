# Relatório: Problemas Encontrados no Communication Hub

**Data:** 2026-01-13  
**Versão:** 1.0  
**Status:** 🔴 Em Análise e Correção

---

## Sumário Executivo

Durante a implementação e testes do Communication Hub (sistema de conversas WhatsApp), foram identificados três problemas críticos que impedem o funcionamento completo da funcionalidade de atualização em tempo real e envio de mensagens:

1. **Polling não funcionava** - Mensagens não apareciam automaticamente na UI
2. **Erro de URL inválida** - `TypeError: Failed to construct 'URL': Invalid URL`
3. **Erro de canal não configurado** - `channel_id = 0` sendo enviado, causando "Nenhum canal WhatsApp configurado no sistema"

---

## Problema 1: Polling Não Funcionava - Mensagens Não Apareciam Automaticamente

### Descrição do Problema

**Sintoma:**
- Mensagens recebidas via WhatsApp não apareciam automaticamente na UI da thread
- Era necessário recarregar a página (F5/CTRL+F5) para ver mensagens novas
- Isso afetava diretamente a percepção de "tempo real" e criava experiência inferior ao padrão de CRMs e WhatsApp

**Evidência:**
- Mensagens apareciam corretamente após reload da página
- Backend estava persistindo corretamente (mensagens no banco)
- Nenhuma chamada periódica aparecia na aba Network do DevTools

### Causa Raiz Identificada

**Bug Crítico 1.1: Flag `isChecking` nunca era resetada**

**Arquivo:** `views/communication_hub/thread.php`  
**Função:** `checkForNewMessages()` (linha ~287)

**Código problemático:**
```javascript
async function checkForNewMessages() {
    if (ThreadState.isChecking) return; // BLOQUEIA se já está checking
    
    ThreadState.isChecking = true; // MARCA como checking
    
    try {
        // ... lógica de check ...
    } catch (error) {
        console.error('Erro ao verificar novas mensagens:', error);
    }
    // ❌ FALTA: ThreadState.isChecking = false; nunca é resetado!
}
```

**Consequência:**
- Na primeira execução, `ThreadState.isChecking` era marcado como `true`
- Todas as execuções subsequentes eram bloqueadas pela verificação `if (ThreadState.isChecking) return;`
- O polling ficava travado após a primeira tentativa
- **Resultado:** Nenhuma chamada periódica ocorria, explicando a ausência de tráfego no Network

**Bug Crítico 1.2: Inicialização de marcadores não garantia timestamp**

**Arquivo:** `views/communication_hub/thread.php`  
**Função:** `initializeMarkers()` (linha ~344)

**Problema:**
- Se não houvesse mensagens iniciais no DOM, `lastTimestamp` não era definido
- O polling ficava bloqueado porque `checkForNewMessages()` retornava quando `!ThreadState.lastTimestamp`

### Soluções Aplicadas

**Correção 1.1: Reset de Flag de Checking**
- ✅ Adicionado bloco `finally` para garantir reset de `ThreadState.isChecking = false`
- ✅ Garantia que o polling não fica travado após primeira execução

**Correção 1.2: Inicialização Melhorada**
- ✅ `initializeMarkers()` agora define timestamp padrão (1 minuto atrás) se não houver mensagens
- ✅ Permite buscar mensagens recentes mesmo sem histórico inicial
- ✅ Após inicializar, agenda check imediatamente se houver timestamp

**Correção 1.3: Logs de Debug Adicionados**
- ✅ Logs detalhados em cada etapa do polling
- ✅ Facilita identificação de problemas futuros

### Status
✅ **RESOLVIDO** - Polling agora funciona corretamente

---

## Problema 2: Erro de URL Inválida - TypeError: Failed to construct 'URL'

### Descrição do Problema

**Sintoma:**
- Console mostrava erro repetido: `TypeError: Failed to construct 'URL': Invalid URL`
- Erro ocorria dentro de `checkForNewMessages()`, chamado por `startPolling()/setInterval`
- Polling iniciava, mas falhava antes de realizar requisições
- Por isso não apareciam chamadas no Network e mensagens não atualizavam

**Evidência:**
- Console mostrava 70+ erros do tipo `TypeError: Failed to construct 'URL'`
- Network não mostrava chamadas periódicas
- Mensagens não apareciam automaticamente

### Causa Raiz Identificada

**Bug 2.1: Uso de `new URL()` com caminho relativo**

**Arquivo:** `views/communication_hub/thread.php`  
**Funções:** `checkForNewMessages()`, `fetchNewMessages()`, `confirmSentMessage()`

**Código problemático:**
```javascript
const checkUrl = new URL(THREAD_CONFIG.baseUrl + '/communication-hub/messages/check');
```

**Problema:**
- `new URL()` requer URL absoluta (com protocolo `http://` ou `https://`)
- `pixelhub_url('')` retorna apenas caminho relativo (ex: `/painel.pixel12digital`)
- Tentativa de criar URL com caminho relativo gerava `TypeError`

**Bug 2.2: URLs protocol-relative (`//`) gerando domínio incorreto**

**Evidência posterior:**
- Após correção do `new URL()`, apareceu novo erro: `net::ERR_NAME_NOT_RESOLVED`
- URL sendo chamada: `https://communication-hub/messages/check?...`
- Navegador interpretava `//communication-hub/...` como protocol-relative
- Isso virava `https://communication-hub/...` (domínio separado inexistente)

**Causa:**
- Concatenação de `baseUrl` (que pode terminar com `/`) + `/communication-hub/...`
- Gerava `//communication-hub/...` (duas barras)
- Navegador interpreta como protocol-relative URL

### Soluções Aplicadas

**Correção 2.1: Substituição de `new URL()` por URLs relativas**

**Antes:**
```javascript
const checkUrl = new URL(THREAD_CONFIG.baseUrl + '/communication-hub/messages/check');
checkUrl.searchParams.set('thread_id', THREAD_CONFIG.threadId);
```

**Depois:**
```javascript
const checkPath = normalizeUrlPath(THREAD_CONFIG.baseUrl + '/communication-hub/messages/check');
const checkParams = new URLSearchParams({
    thread_id: THREAD_CONFIG.threadId,
    after_timestamp: ThreadState.lastTimestamp
});
const checkUrl = checkPath + '?' + checkParams.toString();
```

**Correção 2.2: Função `normalizeUrlPath()` criada**

**Função criada:**
```javascript
function normalizeUrlPath(path) {
    path = String(path || '').trim();
    
    // Se começar com //, remove a primeira barra (protocol-relative)
    if (path.startsWith('//')) {
        path = path.substring(1);
    }
    
    // Se não começar com /, adiciona
    if (!path.startsWith('/')) {
        path = '/' + path;
    }
    
    return path;
}
```

**Aplicada em:**
- ✅ `checkForNewMessages()` - URL de check
- ✅ `fetchNewMessages()` - URL de busca de mensagens
- ✅ `confirmSentMessage()` - URL de confirmação
- ✅ `sendMessage()` - URL de envio

### Status
✅ **RESOLVIDO** - URLs agora são construídas corretamente como paths relativos

---

## Problema 3: Erro de Canal Não Configurado - channel_id = 0

### Descrição do Problema

**Sintoma:**
- Ao tentar enviar mensagem pela thread, sistema exibia alerta: "Nenhum canal WhatsApp configurado no sistema"
- Network mostrava `POST /communication-hub/send → 400 (Bad Request)`
- Payload mostrava `channel_id: 0` sendo enviado
- Recebimento/polling funcionava OK, mas envio falhava

**Evidência:**
- Payload do POST mostrava: `channel_id: 0`
- Resposta do servidor: `{"success": false, "error": "Nenhum canal WhatsApp configurado no sistema"}`
- Thread tinha informações do contato e tenant, mas não do canal

### Causa Raiz Identificada

**Problema 3.1: `channel_id` não estava sendo identificado da thread**

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Função:** `getWhatsAppThreadInfo()` (linha ~826)

**Problema:**
- Função buscava `channel_id` apenas via `LEFT JOIN` com `tenant_message_channels` baseado em `tenant_id`
- Se conversa não tivesse `tenant_id` ou tenant não tivesse canal configurado, `channel_id` ficava `NULL`
- `NULL` era convertido para `0` no formulário HTML
- Backend recebia `channel_id = 0` e rejeitava

**Problema 3.2: Busca de canal não considerava eventos originais**

**Problema:**
- Canal usado nas mensagens originais da conversa não era considerado
- Sistema tentava buscar canal do tenant, mas deveria buscar do evento original
- Para UX padrão CRM/WhatsApp, canal de saída deve ser o mesmo que recebeu

**Problema 3.3: Lógica de prioridade no método `send()` não funcionava**

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Função:** `send()` (linha ~192)

**Problema:**
- Mesmo com prioridade definida, se `channel_id` viesse como `0` do frontend, validação falhava
- Fallback para canal compartilhado só funcionava se `channel_id` fosse `NULL`, não `0`

### Soluções Aplicadas

**Correção 3.1: Busca de `channel_id` dos eventos originais**

**Arquivo:** `src/Controllers/CommunicationHubController.php`  
**Função:** `getWhatsAppThreadInfo()`

**Implementação:**
```php
// Busca channel_id usado nas mensagens originais da conversa
$contactId = $conversation['contact_external_id'];
$eventStmt = $db->prepare("
    SELECT ce.payload
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND (
        JSON_EXTRACT(ce.payload, '$.from') = ?
        OR JSON_EXTRACT(ce.payload, '$.to') = ?
        OR JSON_EXTRACT(ce.payload, '$.message.from') = ?
        OR JSON_EXTRACT(ce.payload, '$.message.to') = ?
    )
    ORDER BY ce.created_at DESC
    LIMIT 1
");
// Extrai channel_id do payload do evento
```

**Correção 3.2: Fallback para canal compartilhado em `getWhatsAppThreadInfo()`**

**Implementação:**
```php
// Se ainda não tem channel_id, tenta buscar qualquer canal habilitado (fallback)
if (!$channelId) {
    $fallbackStmt = $db->prepare("
        SELECT channel_id 
        FROM tenant_message_channels 
        WHERE provider = 'wpp_gateway' 
        AND is_enabled = 1
        LIMIT 1
    ");
    // ...
}
```

**Correção 3.3: Lógica de prioridade melhorada no método `send()`**

**Prioridades implementadas:**
1. **PRIORIDADE 1:** Usa `channel_id` fornecido diretamente (vem da thread)
2. **PRIORIDADE 2:** Busca `channel_id` dos eventos da conversa usando `thread_id`
3. **PRIORIDADE 3:** Busca canal do tenant
4. **PRIORIDADE 4:** Fallback para canal compartilhado/default (qualquer canal habilitado)

**Correção 3.4: Campo `channel_id` adicionado no formulário**

**Arquivo:** `views/communication_hub/thread.php`

**Implementação:**
```php
<?php if (isset($thread['channel_id'])): ?>
    <input type="hidden" name="channel_id" value="<?= htmlspecialchars($thread['channel_id']) ?>">
<?php endif; ?>
```

**Correção 3.5: Logs de debug adicionados**

**Logs implementados:**
- `[CommunicationHub::send] Recebido: ...` - dados recebidos no POST
- `[CommunicationHub::getWhatsAppThreadInfo] ...` - channel_id encontrado (ou não)
- `[CommunicationHub::send] Channel_id encontrado...` - qual caminho foi usado

### Status
🟡 **EM ANÁLISE** - Correções aplicadas, mas `channel_id = 0` ainda sendo enviado

**Evidência atual:**
- Payload mostra `channel_id: 0` sendo enviado
- Indica que `getWhatsAppThreadInfo()` está retornando `channel_id = NULL` ou `0`
- Logs devem mostrar onde está falhando a busca

---

## Análise Detalhada do Problema 3 (Atual)

### Hipóteses para `channel_id = 0`

**Hipótese 3.1: `getWhatsAppThreadInfo()` retorna `NULL` para `channel_id`**

**Possíveis causas:**
1. Tabela `conversations` não tem registro para `thread_id = whatsapp_1`
2. Query de busca de eventos não encontra eventos relacionados ao contato
3. Payload dos eventos não contém `channel_id`
4. Não há canais habilitados no sistema (`tenant_message_channels` vazia)

**Hipótese 3.2: Conversão de `NULL` para `0` no HTML**

**Causa:**
- PHP `(int) null` = `0`
- HTML `<input value="0">` envia string `"0"`
- Backend recebe `channel_id = 0` e valida como inválido

**Hipótese 3.3: Estrutura do payload dos eventos diferente do esperado**

**Possível causa:**
- `channel_id` pode estar em local diferente no JSON
- Pode estar em `payload.channel_id`, `payload.message.channel_id`, ou outro caminho
- Query `JSON_EXTRACT` pode não estar encontrando

### Próximos Passos de Investigação

**1. Verificar logs do servidor**
- Procurar por `[CommunicationHub::getWhatsAppThreadInfo]` nos logs
- Verificar se `channel_id` está sendo encontrado
- Verificar qual caminho está sendo seguido

**2. Verificar estrutura dos eventos**
```sql
SELECT ce.event_id, ce.event_type, ce.payload
FROM communication_events ce
WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
ORDER BY ce.created_at DESC
LIMIT 5;
```
- Verificar se `channel_id` existe no payload
- Verificar estrutura exata do JSON

**3. Verificar tabela `tenant_message_channels`**
```sql
SELECT * FROM tenant_message_channels 
WHERE provider = 'wpp_gateway' 
AND is_enabled = 1;
```
- Verificar se há canais habilitados
- Verificar estrutura da tabela

**4. Verificar tabela `conversations`**
```sql
SELECT * FROM conversations 
WHERE id = 1; -- ou o ID da conversa whatsapp_1
```
- Verificar se conversa existe
- Verificar se tem `tenant_id` e `contact_external_id`

**5. Adicionar validação no frontend**
- Não enviar `channel_id` se for `0` ou `null`
- Deixar backend buscar automaticamente

---

## Soluções Propostas (Não Implementadas)

### Solução A: Validação no Frontend

**Implementação:**
```javascript
// Em sendMessage(), antes de enviar
if (formData.get('channel_id') === '0' || formData.get('channel_id') === '') {
    formData.delete('channel_id'); // Remove se for 0 ou vazio
}
```

**Vantagem:** Força backend a buscar canal automaticamente

### Solução B: Busca mais robusta no backend

**Implementação:**
- Buscar `channel_id` diretamente do `conversation_key` se disponível
- Usar `channel_account_id` da tabela `conversations` se existir
- Buscar em múltiplos formatos de payload

### Solução C: Campo `channel_account_id` na tabela `conversations`

**Implementação:**
- Adicionar campo `channel_account_id` em `conversations`
- Preencher ao criar conversa a partir do evento
- Usar diretamente em `getWhatsAppThreadInfo()`

**Vantagem:** Fonte única da verdade para canal da conversa

---

## Resumo das Correções Aplicadas

### ✅ Problema 1: Polling Não Funcionava
- **Status:** RESOLVIDO
- **Correções:**
  - Reset de flag `isChecking` em bloco `finally`
  - Inicialização melhorada de marcadores
  - Logs de debug adicionados

### ✅ Problema 2: Erro de URL Inválida
- **Status:** RESOLVIDO
- **Correções:**
  - Substituição de `new URL()` por URLs relativas
  - Função `normalizeUrlPath()` criada
  - Aplicada em todas as funções que constroem URLs

### 🟡 Problema 3: Canal Não Configurado
- **Status:** EM ANÁLISE
- **Correções aplicadas:**
  - Busca de `channel_id` dos eventos originais
  - Fallback para canal compartilhado
  - Lógica de prioridade melhorada
  - Logs de debug adicionados
- **Problema persistente:**
  - `channel_id = 0` ainda sendo enviado
  - Indica que busca não está encontrando canal válido

---

## Recomendações Imediatas

### 1. Verificar Logs do Servidor
```bash
tail -f logs/pixelhub.log | grep CommunicationHub
```
- Verificar mensagens de log sobre `channel_id`
- Identificar onde está falhando a busca

### 2. Verificar Estrutura do Banco
- Confirmar se há canais em `tenant_message_channels`
- Confirmar se conversa existe em `conversations`
- Verificar estrutura dos eventos em `communication_events`

### 3. Implementar Validação no Frontend
- Não enviar `channel_id` se for `0` ou vazio
- Forçar backend a buscar automaticamente

### 4. Considerar Adicionar Campo `channel_account_id` em `conversations`
- Fonte única da verdade para canal da conversa
- Evita necessidade de buscar em eventos toda vez

---

## Conclusão

Dois dos três problemas críticos foram **resolvidos**:
- ✅ Polling agora funciona corretamente
- ✅ URLs são construídas corretamente

O terceiro problema (canal não configurado) está **em análise**:
- Correções foram aplicadas
- Logs de debug foram adicionados
- Próximo passo: analisar logs para identificar causa exata do `channel_id = 0`

**Prioridade:** Alta - Bloqueia funcionalidade de envio de mensagens

---

**Última atualização:** 2026-01-13  
**Próxima revisão:** Após análise dos logs do servidor

