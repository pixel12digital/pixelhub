# Resultado do Diagnóstico: Caso Victor (169183207809126)

## Problema Identificado

O contato Victor (169183207809126) não estava exibindo o nome "Victor" e mostrava "Contato Desconhecido" mesmo após a implementação da resolução de nomes.

## Causa Raiz

A função `resolveDisplayNameFromEvents()` estava buscando eventos usando apenas o formato `phoneE164` completo (ex: `55169183207809126`), mas os eventos no banco tinham o identificador no formato `169183207809126@lid` (sem prefixo 55 e com sufixo @lid).

**Query original:**
```sql
WHERE JSON_EXTRACT(ce.payload, '$.message.from') LIKE '%55169183207809126%'
```

**Problema:** Não encontrava eventos com `Message.From = "169183207809126@lid"`

## Correção Aplicada

**Arquivo:** `src/Core/ContactHelper.php`

**Função:** `resolveDisplayNameFromEvents()`

**Mudança:** A busca agora tenta múltiplos formatos do telefone:

1. `phoneE164` completo (ex: `55169183207809126`)
2. Apenas dígitos (ex: `55169183207809126`)
3. Sem prefixo 55 (ex: `169183207809126`)
4. Com sufixo @lid (ex: `169183207809126@lid`)

**Código:**
```php
// Extrai apenas os dígitos do phoneE164 para busca flexível
$phoneDigits = preg_replace('/[^0-9]/', '', $phoneE164);
// Remove prefixo 55 se existir (para buscar também sem prefixo)
$phoneDigitsWithout55 = (strlen($phoneDigits) >= 13 && substr($phoneDigits, 0, 2) === '55') 
    ? substr($phoneDigits, 2) 
    : $phoneDigits;

// Busca por from ou to contendo o telefone (com e sem prefixo 55, com e sem @lid)
$patterns = [
    "%{$phoneE164}%",           // Formato completo: 55169183207809126
    "%{$phoneDigits}%",           // Apenas dígitos: 55169183207809126
    "%{$phoneDigitsWithout55}%",  // Sem prefixo 55: 169183207809126
    "%{$phoneDigitsWithout55}@lid%", // Com @lid: 169183207809126@lid
];
```

## Resultados dos Testes

### 1. normalizeDisplayName()
✅ Funciona corretamente:
- `"~Victor"` → `"Victor"` (remove tilde)
- `"Victor"` → `"Victor"` (mantém)
- `"  ~Victor  "` → `"Victor"` (remove espaços e tilde)

### 2. extractNameFromPayload()
✅ Extrai nome corretamente:
- Campo encontrado: `raw.payload.notifyName = "~Victor"`
- Nome extraído: `"Victor"`

### 3. resolveDisplayNameFromEvents()
✅ Encontra eventos e extrai nome:
- Eventos encontrados: 4 (inbound messages)
- Nome resolvido: `"Victor"`

### 4. resolveDisplayName()
✅ Resolve nome completo:
- Nome resolvido: `"Victor"`

### 5. Cache
✅ Nome salvo no cache:
```
ID: 1 | Phone: 55169183207809126 | Name: Victor | Source: payload | Updated: 2026-01-22 14:17:30
```

## Dados Encontrados no Diagnóstico

### Eventos
- **Total encontrados:** 10 eventos
- **Eventos INBOUND com nome:** 4 eventos
- **Campo de nome:** `raw.payload.notifyName = "~Victor"`
- **Formato do identificador nos eventos:** `169183207809126@lid`

### Conversas
- **Total encontradas:** 2 conversas
- **Conversa ID 15:** `contact_name = "~Victor"` (vinculada a tenant)
- **Conversa ID 17:** `contact_name = NULL` (não vinculada)

## Status Final

✅ **PROBLEMA RESOLVIDO**

A correção permite que a busca de eventos encontre nomes mesmo quando:
- O identificador nos eventos está sem prefixo 55
- O identificador tem sufixo @lid
- O nome no payload tem caracteres especiais (ex: ~)

## Próximos Passos

1. ✅ Correção aplicada e testada
2. ⏳ Testar no ambiente de produção
3. ⏳ Verificar se outros contatos também se beneficiam da correção
4. ⏳ Monitorar logs `[NAME_TRACE]` para confirmar funcionamento

## Arquivos Alterados

1. `src/Core/ContactHelper.php`
   - Função `resolveDisplayNameFromEvents()`: Busca flexível por múltiplos formatos

## Scripts de Diagnóstico Criados

1. `database/check-victor-name-resolution.php` - Diagnóstico completo
2. `database/test-victor-name-extraction.php` - Testes unitários
3. `database/check-cache-victor.php` - Verificação de cache



