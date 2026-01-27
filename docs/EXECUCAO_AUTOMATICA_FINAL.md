# Execução Automática Final - Pronto para Teste

## ✅ Hardening Completo Implementado

### 1. Endpoint `/diagnostic-channel-fix.php` Protegido

**Segurança implementada:**
- ✅ GET nunca aplica fix (somente diagnóstico)
- ✅ POST exige autenticação forte (mínimo 1):
  - Token via header `X-DIAG-TOKEN` comparado com `$_ENV['DIAG_TOKEN']`
  - Allowlist de IP via `$_ENV['DIAG_ALLOWED_IPS']`
  - Sessão admin autenticada (se Auth disponível)
  - Ambiente não-prod (dev/local/development/test)
- ✅ POST é idempotente (UPDATE quando existe, INSERT quando não existe)
- ✅ Logs sem dados sensíveis (apenas: timestamp, IP, tenant_id, provider, channel_id, action)

### 2. Correlação de Logs com request_id

**Implementado:**
- ✅ `request_id` único gerado no início do método `send()`
- ✅ `request_id` incluído em TODOS os logs:
  - STAMP
  - TRACE
  - RESOLUÇÃO
  - RETURN_POINT
  - Método `json()`
- ✅ `request_id` passado via header `X-Request-ID` para o método `json()` capturar
- ✅ Formato: `[CommunicationHub::send][rid=XXXX]` e `[Controller::json][rid=XXXX]`

### 3. Sanitização de Logs

**Dados sensíveis mascarados:**
- ✅ Telefone: mantém apenas últimos 4 dígitos
- ✅ Mensagem: truncada se > 50 chars
- ✅ `base64Ptt`: removido completamente
- ✅ Loga apenas campos seguros: `success`, `error_code`, `channel_id`, `tenant_id`, `thread_id`

## 🔄 Fluxo de Execução Automática

### Passo 0: Hardening (Já Implementado)
✅ Endpoint protegido
✅ Logs sanitizados
✅ request_id implementado

### Passo 1: Aplicar Fix Automaticamente

**Quando:** Antes do primeiro teste (ou quando necessário)

**Como:** Chamar internamente `POST /diagnostic-channel-fix.php` com token/guard

**O que faz:**
- Verifica vínculo atual do tenant 25
- Aplica fix (UPDATE ou INSERT conforme necessário)
- Retorna diagnóstico completo

**Log gerado:**
```
[diagnostic-channel-fix] FIX APLICADO - IP: X.X.X.X, Tenant: 25, Provider: wpp_gateway, Action: UPDATE/INSERT, RecordID: XXX, ChannelID: pixel12digital
```

### Passo 2: Usuário Clica "Enviar"

**Ação do usuário:** Apenas clicar em enviar mensagem

**O que acontece automaticamente:**
1. Gera `request_id` único
2. Loga STAMP + `__FILE__` + `__LINE__`
3. Loga TRACE completo
4. Processa envio
5. Se erro: loga RETURN_POINT
6. Método `json()` loga payload final (sanitizado)

### Passo 3: Coletar Logs Automaticamente

**Buscar no log do servidor por `request_id`:**

```bash
# Exemplo: buscar por request_id específico
grep "rid=abc123def456" /var/log/php/error.log
```

**Ou buscar pelo stamp:**
```bash
grep "SEND_HANDLER_STAMP=15a1023" /var/log/php/error.log | grep "rid="
```

### Passo 4: Classificação Automática

**Caso A - Handler errado/deploy/OPcache:**
- ❌ Stamp NÃO aparece no log
- **Ação:** Verificar roteamento, deploy, OPcache

**Caso B - Mutação fora do send():**
- ✅ Stamp aparece
- ✅ Logs do `send()` mostram: `channel_id = pixel12digital`
- ❌ Logs do `json()` mostram: `channel_id = "Pixel12 Digital"`
- **Ação:** Identificar camada que muta (middleware/base response)

**Caso C - Vínculo tenant↔canal:**
- ✅ Stamp aparece
- ✅ RETURN_POINT indica: canal não habilitado para tenant 25
- **Ação:** Aplicar fix (já implementado) e retestar

## 📊 Entregável Final (Formato Fixo)

Após o teste, retornar:

```
Stamp apareceu? (sim/não) + __FILE__
request_id do envio
TRACE: raw/trim + tenant_id + thread_id
RETURN_POINT (ou "nenhum, envio OK")
channel_id antes do json() e channel_id no JSON final
Classificação: (A) / (B) / (C)
Fix aplicado? (sim/não) + antes/depois do vínculo
Resultado do novo teste (HTTP 200 ou erro novo)
```

## 🎯 Próximo Passo

**Usuário:** Apenas clicar em "Enviar" uma mensagem

**Cursor:** Automaticamente:
1. Aplicar fix (se necessário)
2. Coletar logs do request_id
3. Classificar problema (A/B/C)
4. Aplicar correção
5. Retornar entregável formatado

**Tudo pronto para execução automática!**

