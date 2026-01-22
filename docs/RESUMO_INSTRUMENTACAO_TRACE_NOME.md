# Resumo: Instrumentação de Trace para Diagnóstico de Nome

## Objetivo

Instrumentar logs detalhados (`[NAME_TRACE]`) para diagnosticar por que `resolveDisplayName()` retorna `null` para o contato Victor (169183207809126).

## Mudanças Implementadas

### 1. Logs de Trace em `ContactHelper::resolveDisplayName()`

**Arquivo:** `src/Core/ContactHelper.php`

**Funcionalidade:**
- Detecta automaticamente o caso do Victor quando `phoneE164` contém `169183207809126` ou `55169183207809126`
- Loga cada etapa da resolução:
  - **START**: Inputs recebidos (phoneE164, sessionId, provider, tenantName)
  - **cache**: Resultado da busca no cache (com/sem sessionId)
  - **events**: Quantidade de eventos encontrados e campos de nome
  - **provider**: URL chamada, status HTTP, campos do JSON retornado
  - **END**: Resultado final

**Formato dos logs:**
```
[NAME_TRACE] START phoneE164=55169183207809126, sessionId=..., provider=wpp_gateway, tenantName=NULL
[NAME_TRACE] step=cache key=provider+sessionId+phone hit=NO query=wpp_gateway|...|55169183207809126
[NAME_TRACE] step=events found_count=5
[NAME_TRACE] step=events event[0] fields=notifyName=Victor, pushName=Victor
[NAME_TRACE] step=events extracted_name=Victor saved_to_cache=YES
[NAME_TRACE] END result=Victor
```

### 2. Logs de Trace em `resolveDisplayNameFromEvents()`

**Funcionalidade:**
- Loga quantidade de eventos encontrados
- Para cada evento, loga campos de nome encontrados:
  - `notifyName`
  - `verifiedName`
  - `name`
  - `formattedName`
  - `pushName`
- Loga se `extractNameFromPayload()` retornou nome ou vazio
- Loga se salvou no cache com sucesso

**Formato dos logs:**
```
[NAME_TRACE] step=events START
[NAME_TRACE] step=events found_count=5
[NAME_TRACE] step=events event[0] fields=notifyName=Victor, pushName=Victor
[NAME_TRACE] step=events event[1] fields=NONE
[NAME_TRACE] step=events extracted_name=Victor saved_to_cache=YES
[NAME_TRACE] step=events result=SUCCESS name=Victor
```

### 3. Logs de Trace em `resolveDisplayNameViaProvider()`

**Funcionalidade:**
- Tenta múltiplos formatos de JID:
  1. `phoneE164` puro (ex: `55169183207809126`)
  2. `phoneE164@s.whatsapp.net`
  3. `phoneE164@c.us`
- Loga URL chamada, JID variant usado, status HTTP
- Loga todas as chaves do JSON retornado
- Loga campos de nome encontrados no JSON
- Loga se `normalizeDisplayName()` descartou o nome

**Formato dos logs:**
```
[NAME_TRACE] step=provider START
[NAME_TRACE] step=provider url=https://wpp.pixel12digital.com.br/api/{sessionId}/contact/55169183207809126
[NAME_TRACE] step=provider jidVariant=55169183207809126 http_code=200 curl_error=NONE
[NAME_TRACE] step=provider json_keys=name, pushName, notifyName, contact
[NAME_TRACE] step=provider name_fields=name=Victor, pushName=Victor
[NAME_TRACE] step=provider extracted_name=Victor normalized=Victor
[NAME_TRACE] step=provider result=SUCCESS name=Victor saved_to_cache=YES
```

### 4. Melhoria: Múltiplos Formatos de JID no Provider

**Funcionalidade:**
- Agora tenta 3 formatos diferentes de JID ao chamar a API do gateway
- Se um formato falhar (404), tenta o próximo
- Evita falhas por formato incorreto do identificador

**Código:**
```php
$jidVariants = [$jidOrPhone];
if (preg_match('/^[0-9]+$/', $jidOrPhone)) {
    $jidVariants[] = $jidOrPhone . '@s.whatsapp.net';
    $jidVariants[] = $jidOrPhone . '@c.us';
}
```

### 5. Script de Diagnóstico

**Arquivo:** `database/check-victor-name-resolution.php`

**Funcionalidade:**
- Verifica cache de nomes (`wa_contact_names_cache`)
- Verifica eventos recentes com campos de nome
- Verifica conversas relacionadas
- Mostra resultados formatados para análise

**Uso:**
```bash
php database/check-victor-name-resolution.php
```

### 6. Documentação

**Arquivo:** `docs/DIAGNOSTICO_NOME_VICTOR.md`

**Conteúdo:**
- Passo a passo para diagnosticar o problema
- Como ver os logs
- Queries SQL para verificar cache e eventos
- Possíveis causas e soluções

## Como Usar

### 1. Ativar Logs (já ativo automaticamente)

Os logs são ativados automaticamente quando o `phoneE164` contém `169183207809126` ou `55169183207809126`. Não é necessário fazer nada.

### 2. Ver os Logs

**Linux/Mac:**
```bash
tail -f /var/log/php/error.log | grep NAME_TRACE
```

**Windows (XAMPP):**
```bash
# Verifique o arquivo de log configurado no php.ini
# Geralmente: C:\xampp\php\logs\php_error_log
```

### 3. Executar Script de Diagnóstico

```bash
php database/check-victor-name-resolution.php
```

### 4. Analisar Resultados

Seguir o guia em `docs/DIAGNOSTICO_NOME_VICTOR.md` para identificar a causa raiz.

## Possíveis Causas Identificadas

Com base nos logs, as possíveis causas são:

1. **Cache vazio**: Nome nunca foi salvo no cache
2. **Eventos sem nome**: Payloads não contêm campos de nome
3. **Provider retorna 404**: JID incorreto (agora tenta múltiplos formatos)
4. **Provider retorna 200 mas sem nome**: Campo de nome em chave diferente
5. **normalizeDisplayName() descarta**: Nome não passa na normalização

## Próximos Passos

1. Executar a listagem/detalhe da conversa do Victor
2. Verificar logs `[NAME_TRACE]` no error_log
3. Executar script de diagnóstico
4. Identificar causa raiz baseado nos logs
5. Aplicar correção mínima necessária

## Como Desativar Logs (Após Diagnóstico)

Após identificar e corrigir o problema, remover a lógica de detecção do caso do Victor:

1. Remover detecção `$isVictorCase` em `resolveDisplayName()`
2. Remover todos os `if ($isVictorCase)` e `if ($traceLog)`
3. Manter apenas logs de erro essenciais

## Arquivos Alterados

1. `src/Core/ContactHelper.php`
   - `resolveDisplayName()`: Logs de trace
   - `resolveDisplayNameFromEvents()`: Logs de trace + parâmetro `$traceLog`
   - `resolveDisplayNameViaProvider()`: Logs de trace + múltiplos formatos de JID + parâmetro `$traceLog`

2. `database/check-victor-name-resolution.php` (NOVO)
   - Script de diagnóstico

3. `docs/DIAGNOSTICO_NOME_VICTOR.md` (NOVO)
   - Guia passo a passo

4. `docs/RESUMO_INSTRUMENTACAO_TRACE_NOME.md` (NOVO)
   - Este documento

