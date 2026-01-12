# Template Selector — Cartão de Visita Express

**Objetivo:** Selecionar o template ideal baseado nos dados coletados do briefing, garantindo compatibilidade visual e funcional.

---

## 📋 Regras de Seleção

### 1. Seleção Principal (Primary Template)

**Critério 1: Mood + Background** (obrigatório)
- Combinação de `style.mood` + `style.background` define o template base
- Ex: `corporate_modern` + `light` → `bc_corp_modern_light_001`

**Critério 2: Densidade de Conteúdo** (ajuste automático)
- Se nome/cargo/empresa > 60 caracteres → template `LONGNAME`
- Se mais de 4 itens no verso → template `COMPACT`
- Se QR habilitado → garantir espaço adequado

**Critério 3: Paleta de Cores** (refinamento)
- Se `accent_color` fornecido → verificar compatibilidade
- Templates com paletas fixas têm prioridade menor se cor customizada

---

## 🗂️ Tabela de Templates (Catálogo)

### Template IDs e Tags

| Template ID | Nome | Mood | Background | Tags | Capacidade Nome | Capacidade Verso | QR Otimizado |
|------------|------|------|------------|------|----------------|------------------|--------------|
| `bc_corp_modern_light_001` | Corporativo Moderno - Claro | `corporate_modern` | `light` | `standard`, `minimalist` | ≤60 chars | 4 itens | ✅ |
| `bc_corp_modern_dark_001` | Corporativo Moderno - Escuro | `corporate_modern` | `dark` | `standard`, `minimalist` | ≤60 chars | 4 itens | ✅ |
| `bc_corp_modern_light_002` | Corporativo Moderno - Claro (Long) | `corporate_modern` | `light` | `longname`, `standard` | ≤90 chars | 4 itens | ✅ |
| `bc_creative_modern_light_001` | Criativo Moderno - Claro | `creative_modern` | `light` | `creative`, `colorful` | ≤60 chars | 5 itens | ✅ |
| `bc_creative_modern_dark_001` | Criativo Moderno - Escuro | `creative_modern` | `dark` | `creative`, `colorful` | ≤60 chars | 5 itens | ✅ |
| `bc_elegant_light_001` | Elegante - Claro | `elegant_sophisticated` | `light` | `elegant`, `luxury` | ≤50 chars | 3 itens | ❌ |
| `bc_elegant_dark_001` | Elegante - Escuro | `elegant_sofisticado` | `dark` | `elegant`, `luxury` | ≤50 chars | 3 itens | ❌ |
| `bc_minimalist_light_001` | Minimalista - Claro | `minimalist` | `light` | `minimalist`, `clean` | ≤40 chars | 2 itens | ❌ |
| `bc_compact_light_001` | Compacto - Claro | `corporate_modern` | `light` | `compact`, `dense` | ≤60 chars | 6 itens | ✅ |
| `bc_compact_dark_001` | Compacto - Escuro | `corporate_modern` | `dark` | `compact`, `dense` | ≤60 chars | 6 itens | ✅ |

---

## 🏷️ Sistema de Tags

### Tags por Categoria

**Por Capacidade:**
- `standard` - Capacidade normal (nome ≤60, verso 4 itens)
- `longname` - Nome longo suportado (nome ≤90)
- `compact` - Verso denso (6+ itens)
- `minimal` - Verso minimalista (2-3 itens)

**Por Estilo:**
- `minimalist` - Design minimalista
- `colorful` - Cores vibrantes
- `luxury` - Visual premium/elegante
- `clean` - Visual limpo e simples

**Por Funcionalidade:**
- `qr_optimized` - Espaço otimizado para QR Code
- `logo_space` - Espaço dedicado para logo grande

---

## 🔄 Algoritmo de Seleção

### Passo 1: Seleção por Mood + Background

```php
$primaryCandidate = findTemplateByMoodAndBackground($mood, $background);

// Se não encontrado, usa fallback
if (!$primaryCandidate) {
    $primaryCandidate = findTemplateByMoodAndBackground('corporate_modern', 'light');
}
```

### Passo 2: Verificação de Capacidade

```php
$nameLength = mb_strlen($fullName);
$backItemsCount = count($back['items']);

// Verifica se precisa de template LONGNAME
if ($nameLength > 60 && hasTag($primaryCandidate, 'standard')) {
    $longTemplate = findTemplateByTags(['longname'], $mood, $background);
    if ($longTemplate) {
        $primaryCandidate = $longTemplate;
    }
}

// Verifica se precisa de template COMPACT
if ($backItemsCount > 4 && !hasTag($primaryCandidate, 'compact')) {
    $compactTemplate = findTemplateByTags(['compact'], $mood, $background);
    if ($compactTemplate) {
        $primaryCandidate = $compactTemplate;
    }
}
```

### Passo 3: Verificação de QR Code

```php
if ($back['qr']['enabled'] && !hasTag($primaryCandidate, 'qr_optimized')) {
    // Tenta encontrar template similar com QR otimizado
    $qrTemplate = findTemplateByTags(['qr_optimized'], $mood, $background);
    if ($qrTemplate) {
        $primaryCandidate = $qrTemplate;
    }
}
```

### Passo 4: Verificação de Paleta (opcional)

```php
if (!empty($style['accent_color'])) {
    // Verifica se cor é compatível com template
    // Se não for, mantém template escolhido (cores podem ser ajustadas no Canva)
    // Por enquanto, não força mudança de template
}
```

### Passo 5: Fallback Final

```php
// Fallback sempre disponível
$fallbackTemplate = findTemplateByMoodAndBackground('corporate_modern', 'light');
if (!$fallbackTemplate) {
    $fallbackTemplate = [
        'id' => 'bc_corp_modern_light_001',
        'name' => 'Corporativo Moderno - Claro',
        'mood' => 'corporate_modern',
        'background' => 'light',
        'tags' => ['standard', 'minimalist']
    ];
}
```

---

## 📊 Exemplos de Seleção

### Exemplo 1: Nome Curto, Verso Padrão
```json
{
  "front": { "full_name": "João Silva" },
  "back": { "items": ["WhatsApp", "Email"] },
  "style": { "mood": "corporate_modern", "background": "light" }
}
```
**Resultado:** `bc_corp_modern_light_001` ✅

### Exemplo 2: Nome Longo, Verso Padrão
```json
{
  "front": { "full_name": "Roberto Carlos Junior da Silva Santos" },
  "back": { "items": ["WhatsApp", "Email", "Site"] },
  "style": { "mood": "corporate_modern", "background": "light" }
}
```
**Resultado:** `bc_corp_modern_light_002` (LONGNAME) ✅

### Exemplo 3: Verso Denso, QR Habilitado
```json
{
  "front": { "full_name": "Maria Santos" },
  "back": { 
    "items": ["WhatsApp", "Email", "Site", "Instagram", "Endereço"],
    "qr": { "enabled": true }
  },
  "style": { "mood": "corporate_modern", "background": "light" }
}
```
**Resultado:** `bc_compact_light_001` (COMPACT + QR) ✅

### Exemplo 4: Estilo Criativo, QR Habilitado
```json
{
  "front": { "full_name": "Ana Costa" },
  "back": { 
    "items": ["WhatsApp", "Email", "Instagram"],
    "qr": { "enabled": true }
  },
  "style": { "mood": "creative_modern", "background": "light" }
}
```
**Resultado:** `bc_creative_modern_light_001` ✅

### Exemplo 5: Estilo Elegante, Verso Minimalista
```json
{
  "front": { "full_name": "Dr. Carlos Mendes" },
  "back": { "items": ["WhatsApp", "Email"] },
  "style": { "mood": "elegant_sophisticated", "background": "light" }
}
```
**Resultado:** `bc_elegant_light_001` ✅

---

## 🔧 Implementação no Código

### Estrutura de Dados do Template

```php
[
    'id' => 'bc_corp_modern_light_001',
    'name' => 'Corporativo Moderno - Claro',
    'mood' => 'corporate_modern',
    'background' => 'light',
    'tags' => ['standard', 'minimalist', 'qr_optimized'],
    'capacities' => [
        'name_max_length' => 60,
        'back_items_max' => 4,
        'supports_qr' => true,
        'supports_logo' => true
    ],
    'canva_template_id' => 'abc123xyz', // ID do template no Canva (futuro)
    'canva_brand_id' => 'brand_001' // ID da brand no Canva (futuro)
]
```

### Função de Seleção

```php
public static function selectTemplate(array $intakeData): array
{
    // Passo 1: Mood + Background
    $mood = $intakeData['style']['mood'] ?? 'corporate_modern';
    $background = $intakeData['style']['background'] ?? 'light';
    
    $primary = self::findTemplateByMoodAndBackground($mood, $background);
    
    // Passo 2: Verifica capacidade
    $nameLength = mb_strlen($intakeData['front']['full_name'] ?? '');
    $backItemsCount = count($intakeData['back']['items'] ?? []);
    
    if ($nameLength > 60 && !self::hasTag($primary, 'longname')) {
        $longTemplate = self::findTemplateByTags(['longname'], $mood, $background);
        if ($longTemplate) $primary = $longTemplate;
    }
    
    if ($backItemsCount > 4 && !self::hasTag($primary, 'compact')) {
        $compactTemplate = self::findTemplateByTags(['compact'], $mood, $background);
        if ($compactTemplate) $primary = $compactTemplate;
    }
    
    // Passo 3: QR Code
    if (!empty($intakeData['back']['qr']['enabled']) && 
        !self::hasTag($primary, 'qr_optimized')) {
        $qrTemplate = self::findTemplateByTags(['qr_optimized'], $mood, $background);
        if ($qrTemplate) $primary = $qrTemplate;
    }
    
    // Fallback sempre disponível
    $fallback = self::findTemplateByMoodAndBackground('corporate_modern', 'light');
    
    return [
        'primary' => $primary,
        'fallback' => $fallback
    ];
}
```

---

## ⚠️ Regras de Validação

1. **Template sempre deve existir:** Se não encontrar por mood/background, usa fallback
2. **Capacidade não pode exceder:** Se conteúdo > capacidade, ContentResolver deve ajustar
3. **Tags são mutuamente exclusivas por categoria:** `standard` vs `longname` vs `compact`
4. **QR Code requer template otimizado:** Se não encontrar, avisa mas não bloqueia

---

## 📝 Notas para Expansão Futura

- **Templates dinâmicos:** No futuro, templates podem vir de banco de dados ou Canva API
- **A/B Testing:** Sistema pode rotacionar entre templates similares para otimização
- **Preferências por segmento:** Templates podem ser sugeridos baseado em `segment` (advocacia, medicina, etc.)
- **Versionamento:** Templates podem ter versões (v1, v2) para evolução sem quebrar gerações antigas




