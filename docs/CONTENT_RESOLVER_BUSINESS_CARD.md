# Content Resolver — Cartão de Visita Express

**Objetivo:** Ajustar e preparar o conteúdo coletado para caber perfeitamente no template selecionado, removendo ou truncando itens automaticamente quando necessário.

---

## 🎯 Princípios

1. **Prioridade de Conteúdo:** WhatsApp > Email > Site > Instagram > Endereço > Outros
2. **Preservação de Dados:** Nunca perde informação, apenas ajusta para caber
3. **Qualidade > Quantidade:** Melhor ter 3 itens bem legíveis que 6 ilegíveis
4. **Fallback Inteligente:** Se não cabe, reduz automaticamente (não bloqueia)

---

## 📋 Regras de Resolução

### 1. Resolução do Nome (Front)

**Problema:** Nome muito longo que não cabe no template

**Solução:**
```
SE nome_completo > capacidade_template ENTÃO:
  - Se template tem tag "longname": usar versão abreviada (primeiro nome + último sobrenome)
  - Se template NÃO tem tag "longname": trocar para template LONGNAME
  - Se não existe template LONGNAME: truncar para capacidade mantendo primeiros nomes
```

**Prioridade de Abreviação:**
1. `Roberto Carlos Junior` → `Roberto Junior` (remove meio)
2. `Maria da Silva Santos` → `Maria Santos` (remove preposições)
3. `Dr. Carlos Mendes Filho` → `Dr. Carlos Mendes` (mantém título, remove Filho)

---

### 2. Resolução do Verso (Back Items)

**Problema:** Mais itens do que o template suporta

**Solução por Prioridade:**

#### Prioridade 1: Itens Obrigatórios (nunca removidos)
- ✅ **WhatsApp** - Sempre mantém (prioridade máxima)
- ✅ **Nome/Cargo** - Sempre mantém (se fornecido)

#### Prioridade 2: Itens Recomendados
- ⚡ **Email** - Remove apenas se template muito limitado (≤2 itens)
- ⚡ **QR Code** - Remove apenas se template não suporta (tag `qr_optimized` = false)

#### Prioridade 3: Itens Opcionais
- ⚠️ **Site** - Remove se exceder capacidade
- ⚠️ **Instagram** - Remove se exceder capacidade
- ⚠️ **Telefone** - Remove se exceder capacidade (se já tem WhatsApp)
- ⚠️ **Endereço** - Remove se exceder capacidade

#### Prioridade 4: Itens Extras
- ❌ **Slogan** - Remove se exceder capacidade
- ❌ **Serviços** - Reduz para 1 item se exceder capacidade, remove se ainda não caber

---

### 3. Resolução de Textos Longos

**Problema:** Cargo, slogan ou serviço muito longo

**Solução:**
```
SE texto > 40 caracteres ENTÃO:
  - Truncar para 40 caracteres com "..."
  - Priorizar início do texto (remover do final)
  - Ex: "Consultor em Gestão Empresarial e Estratégia" → "Consultor em Gestão Empresarial..."
```

**Exceções:**
- Nome de empresa: até 35 caracteres
- Cargo: até 40 caracteres
- Slogan: até 30 caracteres
- Serviço individual: até 25 caracteres

---

### 4. Resolução de QR Code

**Problema:** QR Code habilitado mas template não suporta

**Solução:**
```
SE qr.enabled = true E template não tem tag "qr_optimized" ENTÃO:
  - Tenta encontrar template similar com QR
  - Se encontrar: troca template
  - Se NÃO encontrar: DESABILITA QR (registra motivo no log)
  - Avisa no resumo: "QR Code não incluído (espaço limitado)"
```

---

### 5. Resolução de Endereço

**Problema:** Endereço completo muito longo

**Solução:**
```
SE endereço.mode = "full" E texto > 50 caracteres ENTÃO:
  - Converter para mode = "city_state"
  - Extrair apenas "Cidade, UF"
  - Ex: "Av. Paulista, 1000, Bela Vista, São Paulo - SP" → "São Paulo, SP"
```

---

## 🔧 Algoritmo Pseudo-código

```php
function resolveContent(array $intakeData, array $template): array
{
    $resolved = $intakeData;
    $templateCapacity = $template['capacities'];
    
    // === PASSO 1: Resolver Nome ===
    $nameLength = mb_strlen($resolved['front']['full_name']);
    if ($nameLength > $templateCapacity['name_max_length']) {
        if (hasTag($template, 'longname')) {
            // Abrevia nome mantendo primeiros nomes
            $resolved['front']['full_name'] = abbreviateName(
                $resolved['front']['full_name'],
                $templateCapacity['name_max_length']
            );
        } else {
            // Troca para template LONGNAME (feito no Template Selector)
            // Se não possível, truncar
            $resolved['front']['full_name'] = truncateName(
                $resolved['front']['full_name'],
                $templateCapacity['name_max_length']
            );
        }
    }
    
    // === PASSO 2: Truncar Cargo ===
    if (!empty($resolved['front']['job_title'])) {
        $resolved['front']['job_title'] = truncateText(
            $resolved['front']['job_title'],
            40,
            '...'
        );
    }
    
    // === PASSO 3: Truncar Empresa ===
    if (!empty($resolved['front']['company'])) {
        $resolved['front']['company'] = truncateText(
            $resolved['front']['company'],
            35,
            '...'
        );
    }
    
    // === PASSO 4: Resolver Itens do Verso ===
    $backItems = $resolved['back']['items'] ?? [];
    $maxItems = $templateCapacity['back_items_max'];
    
    if (count($backItems) > $maxItems) {
        // Prioriza itens
        $prioritized = prioritizeBackItems($backItems);
        
        // Remove itens de menor prioridade
        $resolved['back']['items'] = array_slice($prioritized, 0, $maxItems);
        
        // Log do que foi removido
        $removed = array_slice($prioritized, $maxItems);
        logRemovedItems($removed);
    }
    
    // === PASSO 5: Resolver QR Code ===
    if (!empty($resolved['back']['qr']['enabled'])) {
        if (!$templateCapacity['supports_qr']) {
            // Template não suporta QR
            $resolved['back']['qr']['enabled'] = false;
            $resolved['back']['qr']['removed_reason'] = 'template_no_qr_support';
            logWarning('QR Code removido: template não suporta');
        } else {
            // Valida URL do QR
            if (empty($resolved['back']['qr']['value'])) {
                $resolved['back']['qr']['enabled'] = false;
                $resolved['back']['qr']['removed_reason'] = 'no_url_provided';
            }
        }
    }
    
    // === PASSO 6: Resolver Endereço ===
    if (!empty($resolved['back']['address']['enabled'])) {
        $addressText = $resolved['back']['address']['value'];
        
        if (mb_strlen($addressText) > 50) {
            // Converte para cidade/estado
            $resolved['back']['address']['mode'] = 'city_state';
            $resolved['back']['address']['value'] = extractCityState($addressText);
        }
    }
    
    // === PASSO 7: Resolver Slogan ===
    if (!empty($resolved['back']['slogan']['enabled'])) {
        $sloganText = $resolved['back']['slogan']['value'];
        
        if (mb_strlen($sloganText) > 30) {
            $resolved['back']['slogan']['value'] = truncateText($sloganText, 30, '...');
        }
        
        // Se ainda não cabe, remove (menor prioridade)
        $currentItemsCount = count($resolved['back']['items']);
        if ($currentItemsCount >= $maxItems) {
            $resolved['back']['slogan']['enabled'] = false;
            $resolved['back']['slogan']['removed_reason'] = 'exceeds_capacity';
        }
    }
    
    // === PASSO 8: Resolver Serviços ===
    if (!empty($resolved['back']['services']) && count($resolved['back']['services']) > 0) {
        $services = $resolved['back']['services'];
        
        // Limita a 3 serviços
        if (count($services) > 3) {
            $services = array_slice($services, 0, 3);
        }
        
        // Trunca cada serviço
        foreach ($services as &$service) {
            if (mb_strlen($service) > 25) {
                $service = truncateText($service, 25, '...');
            }
        }
        
        $resolved['back']['services'] = $services;
        
        // Remove serviços se não cabe
        $remainingCapacity = $maxItems - count($resolved['back']['items']);
        if ($remainingCapacity < count($services)) {
            // Reduz serviços para caber
            $resolved['back']['services'] = array_slice($services, 0, $remainingCapacity);
        }
    }
    
    return $resolved;
}

// === Funções Auxiliares ===

function prioritizeBackItems(array $items): array
{
    $priority = [
        'whatsapp' => 100,
        'name_job' => 90,
        'email' => 80,
        'qr' => 75,
        'site' => 60,
        'instagram' => 50,
        'phone' => 45,
        'address' => 30,
        'slogan' => 20,
        'services' => 10
    ];
    
    usort($items, function($a, $b) use ($priority) {
        $priorityA = $priority[strtolower($a)] ?? 0;
        $priorityB = $priority[strtolower($b)] ?? 0;
        return $priorityB - $priorityA;
    });
    
    return $items;
}

function abbreviateName(string $fullName, int $maxLength): string
{
    $parts = explode(' ', trim($fullName));
    
    if (count($parts) <= 2) {
        return truncateText($fullName, $maxLength);
    }
    
    // Remove preposições
    $prepositions = ['da', 'de', 'do', 'das', 'dos'];
    $parts = array_filter($parts, fn($p) => !in_array(strtolower($p), $prepositions));
    $parts = array_values($parts);
    
    // Se ainda tem mais de 2 partes, pega primeiro + último
    if (count($parts) > 2) {
        $first = $parts[0];
        $last = end($parts);
        $abbreviated = $first . ' ' . $last;
        
        if (mb_strlen($abbreviated) > $maxLength) {
            return truncateText($abbreviated, $maxLength);
        }
        
        return $abbreviated;
    }
    
    return implode(' ', $parts);
}

function truncateText(string $text, int $maxLength, string $suffix = '...'): string
{
    if (mb_strlen($text) <= $maxLength) {
        return $text;
    }
    
    $truncated = mb_substr($text, 0, $maxLength - mb_strlen($suffix));
    return $truncated . $suffix;
}

function extractCityState(string $fullAddress): string
{
    // Tenta extrair "Cidade, UF" do endereço completo
    // Ex: "Av. Paulista, 1000, Bela Vista, São Paulo - SP" → "São Paulo, SP"
    
    // Padrão: "... - UF" ou "... , Cidade - UF"
    if (preg_match('/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s*[-–]\s*([A-Z]{2})\b/', $fullAddress, $matches)) {
        return $matches[1] . ', ' . $matches[2];
    }
    
    // Fallback: pega últimas 2 palavras (assumindo cidade e estado)
    $parts = explode(',', $fullAddress);
    if (count($parts) >= 2) {
        $lastPart = trim(end($parts));
        return $lastPart;
    }
    
    return $fullAddress;
}
```

---

## 📊 Exemplos de Resolução

### Exemplo 1: Verso com 6 Itens, Template Suporta 4

**Input:**
```json
{
  "back": {
    "items": ["WhatsApp", "Email", "Site", "Instagram", "Telefone", "Endereço"]
  }
}
```

**Resolução:**
```json
{
  "back": {
    "items": ["WhatsApp", "Email", "Site", "Instagram"]
  },
  "_metadata": {
    "removed_items": ["Telefone", "Endereço"],
    "removal_reason": "exceeds_capacity"
  }
}
```

### Exemplo 2: Nome Muito Longo

**Input:**
```json
{
  "front": {
    "full_name": "Roberto Carlos Junior da Silva Santos"
  }
}
```

**Template:** `bc_corp_modern_light_001` (capacidade: 60 chars)

**Resolução:**
```json
{
  "front": {
    "full_name": "Roberto Santos"
  },
  "_metadata": {
    "original_name": "Roberto Carlos Junior da Silva Santos",
    "abbreviated_reason": "exceeds_capacity"
  }
}
```

### Exemplo 3: QR Code em Template Sem Suporte

**Input:**
```json
{
  "back": {
    "qr": {
      "enabled": true,
      "value": "https://wa.me/5511999999999"
    }
  }
}
```

**Template:** `bc_elegant_light_001` (não suporta QR)

**Resolução:**
```json
{
  "back": {
    "qr": {
      "enabled": false,
      "removed_reason": "template_no_qr_support",
      "original_value": "https://wa.me/5511999999999"
    }
  }
}
```

### Exemplo 4: Cargo Muito Longo

**Input:**
```json
{
  "front": {
    "job_title": "Consultor em Gestão Empresarial e Estratégia de Negócios"
  }
}
```

**Resolução:**
```json
{
  "front": {
    "job_title": "Consultor em Gestão Empresarial e..."
  }
}
```

---

## ⚠️ Validações Finais

Após a resolução, validar:

1. ✅ **Nome não vazio** (obrigatório)
2. ✅ **Pelo menos 1 contato no verso** (WhatsApp ou Email)
3. ✅ **Itens do verso ≤ capacidade do template**
4. ✅ **Textos truncados mantêm sentido** (não corta palavras pela metade)
5. ✅ **QR Code válido** (se habilitado, URL deve ser válida)

---

## 📝 Logs e Rastreamento

Todas as modificações devem ser registradas:

```json
{
  "_metadata": {
    "resolution_applied": true,
    "changes": [
      {
        "field": "back.items",
        "action": "removed",
        "value": ["Telefone", "Endereço"],
        "reason": "exceeds_capacity"
      },
      {
        "field": "front.full_name",
        "action": "abbreviated",
        "original": "Roberto Carlos Junior da Silva Santos",
        "resolved": "Roberto Santos",
        "reason": "exceeds_capacity"
      }
    ],
    "template_id": "bc_corp_modern_light_001",
    "template_capacity": {
      "name_max_length": 60,
      "back_items_max": 4
    }
  }
}
```

---

## 🔄 Integração com Template Selector

O Content Resolver é executado **DEPOIS** do Template Selector:

```
1. Template Selector escolhe template baseado em mood/background
2. Content Resolver ajusta conteúdo para caber no template escolhido
3. Se após resolver ainda não cabe, Template Selector tenta template alternativo
4. Loop até encontrar combinação válida ou usar fallback
```

---

## 💡 Notas para Implementação

- **Performance:** Resolução deve ser instantânea (<100ms)
- **Reversibilidade:** Metadata mantém dados originais para possível reversão
- **Feedback ao usuário:** Se itens foram removidos, avisar no resumo final
- **Validação rigorosa:** Antes de enviar para Canva, validar todas as regras novamente















