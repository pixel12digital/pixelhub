# Diagnóstico: Resolução de Nome - Caso Victor (169183207809126)

## Objetivo

Identificar exatamente por que `resolveDisplayName()` retorna `null` para o contato Victor e corrigir apenas o parsing/chave/identificador se estiver errado.

## Passo 1: Confirmar o que o frontend recebe

### No Browser (Network Tab)

1. Abra o DevTools (F12) → Network
2. Filtre por "conversations" ou "thread"
3. Capture os seguintes requests:
   - `GET /api/communication-hub/conversations`
   - `GET /api/communication-hub/thread/{threadId}` (para a conversa do Victor)

### Verificar campos de nome no payload

Procure por:
- `contact_name`
- `display_name`
- `pushName`
- `notifyName`
- `verifiedName`

**Se não existe nenhum campo de nome:**
- O backend está realmente retornando `null`/placeholder
- Prosseguir para Passo 2

## Passo 2: Logs de Trace Instrumentados

### Logs Ativos

Os logs `[NAME_TRACE]` foram adicionados especificamente para o caso do Victor. Eles são ativados automaticamente quando:
- `phoneE164` contém `169183207809126` ou `55169183207809126`

### Onde ver os logs

**Linux/Mac:**
```bash
tail -f /var/log/php/error.log | grep NAME_TRACE
# ou
tail -f /var/log/apache2/error.log | grep NAME_TRACE
```

**Windows (XAMPP):**
```bash
# Verifique o arquivo de log do PHP configurado no php.ini
# Geralmente em: C:\xampp\php\logs\php_error_log
# ou: C:\xampp\apache\logs\error.log
```

### Formato dos logs

```
[NAME_TRACE] START phoneE164=55169183207809126, sessionId=..., provider=wpp_gateway, tenantName=NULL
[NAME_TRACE] step=cache key=provider+sessionId+phone hit=NO query=wpp_gateway|...|55169183207809126
[NAME_TRACE] step=cache key=provider+phone hit=NO query=wpp_gateway|55169183207809126
[NAME_TRACE] step=events START
[NAME_TRACE] step=events found_count=5
[NAME_TRACE] step=events event[0] fields=notifyName=Victor, pushName=Victor
[NAME_TRACE] step=events extracted_name=Victor saved_to_cache=YES
[NAME_TRACE] step=events result=SUCCESS name=Victor
[NAME_TRACE] END result=Victor
```

### O que cada step significa

- **START**: Início da resolução, mostra inputs
- **cache**: Busca no cache `wa_contact_names_cache`
  - `key=provider+sessionId+phone`: Busca com sessionId
  - `key=provider+phone`: Busca sem sessionId (fallback)
  - `hit=YES/NO`: Se encontrou no cache
- **events**: Busca em eventos recentes
  - `found_count`: Quantos eventos foram encontrados
  - `event[N] fields=...`: Campos de nome encontrados em cada evento
  - `extracted_name`: Nome extraído e normalizado
- **provider**: Chamada à API do gateway
  - `url=...`: URL chamada
  - `jidVariant=...`: Formato do JID usado
  - `http_code=...`: Status HTTP da resposta
  - `json_keys=...`: Chaves do JSON retornado
  - `name_fields=...`: Campos de nome encontrados no JSON
- **END**: Resultado final (`result=Victor` ou `result=NULL`)

## Passo 3: Verificar Cache no Banco

### Executar script de diagnóstico

```bash
php database/check-victor-name-resolution.php
```

### Ou executar query manual

```sql
SELECT * FROM wa_contact_names_cache
WHERE phone_e164 = '55169183207809126'
   OR phone_e164 = '169183207809126'
   OR phone_e164 LIKE '%169183207809126%'
ORDER BY updated_at DESC
LIMIT 5;
```

### Se não existir linha no cache

Verificar:
1. **Nome extraído é vazio?**
   - Ver logs `[NAME_TRACE] step=events extracted_name=...`
   - Se `extracted_name=EMPTY`, o payload não tem nome válido

2. **normalizeDisplayName() está descartando?**
   - Ver logs `[NAME_TRACE] step=events name_rejected=...`
   - Verificar regras de normalização em `ContactHelper::normalizeDisplayName()`

3. **Estamos salvando com phone_e164 diferente?**
   - Verificar se está salvando com `55` ou sem
   - Verificar se está salvando com `@lid` ou sem
   - Comparar com o `phoneE164` usado na busca

## Passo 4: Validar Eventos Têm Nome

### Executar query

```sql
SELECT 
    ce.id,
    ce.event_type,
    ce.created_at,
    JSON_EXTRACT(ce.payload, '$.from') as from_field,
    JSON_EXTRACT(ce.payload, '$.message.notifyName') as notifyName,
    JSON_EXTRACT(ce.payload, '$.raw.payload.notifyName') as raw_notifyName,
    JSON_EXTRACT(ce.payload, '$.raw.payload.sender.verifiedName') as verifiedName,
    JSON_EXTRACT(ce.payload, '$.raw.payload.sender.name') as sender_name,
    JSON_EXTRACT(ce.payload, '$.raw.payload.sender.formattedName') as formattedName,
    JSON_EXTRACT(ce.payload, '$.pushName') as pushName
FROM communication_events ce
WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
AND (
    JSON_EXTRACT(ce.payload, '$.from') LIKE '%169183207809126%' 
    OR JSON_EXTRACT(ce.payload, '$.to') LIKE '%169183207809126%'
    OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE '%169183207809126%'
    OR JSON_EXTRACT(ce.payload, '$.message.to') LIKE '%169183207809126%'
)
ORDER BY ce.created_at DESC
LIMIT 10;
```

### Se NÃO existir nenhum campo de nome

- Isso explica o fallback de eventos falhar
- Prosseguir para Passo 5 (tentar via provider)

## Passo 5: Validar Provider Endpoint

### Formato do JID esperado pelo gateway

A função `resolveDisplayNameViaProvider()` agora tenta múltiplos formatos:

1. `phoneE164` puro (ex: `55169183207809126`)
2. `phoneE164@s.whatsapp.net` (ex: `55169183207809126@s.whatsapp.net`)
3. `phoneE164@c.us` (ex: `55169183207809126@c.us`)

### Verificar logs

```
[NAME_TRACE] step=provider url=https://wpp.pixel12digital.com.br/api/{sessionId}/contact/55169183207809126
[NAME_TRACE] step=provider jidVariant=55169183207809126 http_code=200 curl_error=NONE
[NAME_TRACE] step=provider json_keys=name, pushName, notifyName, ...
[NAME_TRACE] step=provider name_fields=name=Victor, pushName=Victor
[NAME_TRACE] step=provider extracted_name=Victor normalized=Victor
```

### Se HTTP 404

- Gateway não encontrou o contato com esse formato
- Verificar qual formato o gateway espera (pode ser necessário usar JID completo)

### Se HTTP 200 mas sem nome

- Verificar `json_keys` nos logs
- Verificar se o campo de nome está em outra chave
- Ajustar `resolveDisplayNameViaProvider()` para incluir novas chaves

## Passo 6: Conclusão

### Possíveis causas

**A) Não há nome em eventos e gateway não fornece nome**
- **Solução:** Impossível obter (manter "Contato Desconhecido")
- **Ação:** Nenhuma

**B) Gateway fornece nome, mas identificador (JID) está errado**
- **Solução:** Corrigir montagem do JID
- **Ação:** Ajustar `resolveDisplayNameViaProvider()` para tentar mais formatos

**C) Nome veio, mas normalizeDisplayName descartou**
- **Solução:** Ajustar normalização
- **Ação:** Revisar `normalizeDisplayName()` para aceitar o formato do nome

**D) Salvou no cache com chave errada**
- **Solução:** Corrigir padronização do phone_e164
- **Ação:** Garantir que `saveContactDisplayName()` usa o mesmo formato que a busca

## Entregáveis

1. **Logs [NAME_TRACE] do caso do Victor** mostrando cada step
2. **1 print/trecho do JSON de resposta do provider contact endpoint** (se aplicável)
3. **Se houver correção: diff mínimo e commit**

## Como Desativar Logs de Trace

Após o diagnóstico, para desativar os logs `[NAME_TRACE]`, remover ou comentar a lógica de detecção do caso do Victor em `ContactHelper::resolveDisplayName()`:

```php
// Remover ou comentar:
$isVictorCase = false;
if (!empty($phoneE164)) {
    $phoneDigits = preg_replace('/[^0-9]/', '', $phoneE164);
    $isVictorCase = (
        $phoneDigits === '169183207809126' || 
        $phoneDigits === '55169183207809126' ||
        strpos($phoneE164, '169183207809126') !== false
    );
}
```

E remover todos os `if ($isVictorCase)` e `if ($traceLog)` dos logs.

