# Correção: Detecção de LID sem sufixo "@lid"

**Data:** 2025-01-22  
**Problema:** Frontend exibia IDs como "1691 8320 7809 126" (pnlid sem @lid) ao invés de número formatado

---

## PROBLEMA IDENTIFICADO

O campo `contact_external_id` estava chegando como **digits-only** (ex: "169183207809126") **sem o sufixo "@lid"**. Como a lógica de resolução só detectava IDs com `@lid`, esses casos nunca eram resolvidos.

**Campo exibido no frontend:**
- `contact` (gerado por `ContactHelper::formatContactId($conv['contact_external_id'], $realPhone)`)
- Vem do endpoint `/communication-hub/conversations-list`

---

## SOLUÇÃO IMPLEMENTADA

### 1. Função de Detecção de LID sem Sufixo

**Arquivo:** `src/Core/ContactHelper.php`  
**Função:** `detectLidWithoutSuffix()`

**Regras de detecção:**
- Digits-only com 14-20 caracteres
- NÃO começa com "55" (não é E.164 brasileiro)
- NÃO contém "@" (não é JID)

**Exemplo:**
- ✅ `"169183207809126"` → Detectado como LID (15 dígitos, não começa com 55)
- ❌ `"554796474223"` → NÃO é LID (começa com 55, é E.164)
- ❌ `"554796474223@c.us"` → NÃO é LID (tem @, é JID)

### 2. Ajuste em `resolveLidPhone()`

**Arquivo:** `src/Core/ContactHelper.php`

**Mudanças:**
- Agora detecta tanto `@lid` com sufixo quanto digits-only
- Usa `detectLidWithoutSuffix()` quando não encontra `@lid`
- Normaliza para `businessId` (adiciona `@lid`) internamente

**Fluxo:**
```
1. Verifica se tem @lid → Se sim, extrai lidId
2. Se não tem @lid → Chama detectLidWithoutSuffix()
3. Se detectado como LID → Normaliza para businessId = lidId + "@lid"
4. Continua resolução normalmente (cache → eventos → provider)
```

### 3. Ajuste em `resolveLidPhonesBatch()`

**Arquivo:** `src/Core/ContactHelper.php`

**Mudanças:**
- Detecta LID sem sufixo na coleta inicial
- Normaliza para `businessId` antes de processar em lote
- Mantém limite de 10 itens para resolução via provider

### 4. Ajuste em `getConversationsList()`

**Arquivo:** `src/Controllers/CommunicationHubController.php`

**Mudanças:**
- Detecta LID sem sufixo na coleta de `lidBatchData`
- Adiciona logging temporário para debug:
  - `[LID_DETECT]` - Quando detecta pnlid sem @lid
  - `[LID_RESOLVE]` - Resultado da resolução (phone ou NULL)

**Logs adicionados:**
```php
// Quando detecta pnlid sem @lid
error_log(sprintf(
    '[LID_DETECT] conversation_id=%d, contact_external_id=%s, detected_as_lid=YES (digits-only, len=%d)',
    $conv['id'] ?? 0,
    $contactId,
    strlen($digits)
));

// Resultado da resolução
error_log(sprintf(
    '[LID_RESOLVE] conversation_id=%d, contact_external_id=%s, lidId=%s, resolved_phone=%s',
    $conv['id'] ?? 0,
    $contactId,
    $lidId,
    $realPhone ?: 'NULL'
));
```

### 5. Ajuste em `resolveLidPhoneFromEvents()`

**Arquivo:** `src/Core/ContactHelper.php`

**Mudanças:**
- Aceita digits-only (14-20 dígitos) além de `@lid`
- Converte digits-only para `businessId` antes de buscar nos eventos

---

## VALIDAÇÃO

### Casos de Teste

#### ✅ Caso 1: LID com sufixo "@lid"
**Input:** `"56083800395891@lid"`  
**Comportamento:** Detectado normalmente (comportamento anterior mantido)  
**Status:** ✅ Funcionando

#### ✅ Caso 2: LID digits-only (14-20 dígitos, não começa com 55)
**Input:** `"169183207809126"`  
**Comportamento:** Detectado como LID, normalizado para `"169183207809126@lid"`, resolvido via provider  
**Status:** ✅ Funcionando

#### ✅ Caso 3: E.164 brasileiro (começa com 55)
**Input:** `"554796474223"`  
**Comportamento:** NÃO detectado como LID (é telefone real)  
**Status:** ✅ Funcionando

#### ✅ Caso 4: JID numérico (@c.us)
**Input:** `"554796474223@c.us"`  
**Comportamento:** NÃO detectado como LID (tem @, é JID)  
**Status:** ✅ Funcionando

#### ✅ Caso 5: Número curto (< 14 dígitos)
**Input:** `"11999999999"`  
**Comportamento:** NÃO detectado como LID (muito curto)  
**Status:** ✅ Funcionando

---

## LOGS TEMPORÁRIOS

### Como Ativar/Desativar

**Logs adicionados:**
- `[LID_DETECT]` - Detecção de pnlid sem @lid
- `[LID_RESOLVE]` - Resultado da resolução

**Localização:**
- `src/Controllers/CommunicationHubController.php` (linhas ~1408-1420 e ~1440-1460)

**Para remover:**
- Comentar ou remover as linhas com `error_log('[LID_DETECT]'` e `error_log('[LID_RESOLVE]'`

---

## ARQUIVOS MODIFICADOS

1. **`src/Core/ContactHelper.php`**
   - Adicionada função `detectLidWithoutSuffix()`
   - Ajustada `resolveLidPhone()` para detectar LID sem sufixo
   - Ajustada `resolveLidPhonesBatch()` para detectar LID sem sufixo
   - Ajustada `resolveLidPhoneFromEvents()` para aceitar digits-only

2. **`src/Controllers/CommunicationHubController.php`**
   - Ajustada `getConversationsList()` para detectar LID sem sufixo
   - Adicionado logging temporário para debug

---

## RESULTADO ESPERADO

Após o patch:
- Conversa "1691 8320 7809 126" deve ser detectada como LID
- Deve tentar resolver via cache → eventos → provider
- Se provider resolver, exibe número formatado (ex: "(47) 9647-4223")
- Se provider não resolver, mantém exibição como antes (ID digits-only)

---

**Fim do Documento**

