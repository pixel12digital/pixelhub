# Fontes de Dados - Prospecção Ativa

## Hierarquia de Fontes

### 1. **CNPJ.ws** - Fonte da Verdade ⭐
**API:** https://www.cnpj.ws  
**Rate Limit:** 3 requisições/minuto (gratuito)  
**Comportamento:** **SUBSTITUI** dados existentes

#### Dados fornecidos:
- ✅ Email
- ✅ Telefone principal
- ✅ Telefone secundário
- ✅ Website
- ✅ Dados cadastrais completos

#### Quando usar:
- **Atualização manual** via botão "Atualizar Dados"
- Quando precisar de dados de contato atualizados
- Para corrigir informações desatualizadas

#### Lógica de atualização:
```php
// CNPJ.ws SEMPRE SUBSTITUI (fonte da verdade)
if (cnpjws_email) {
    email = cnpjws_email  // Substitui
}
if (cnpjws_phone) {
    phone_minhareceita = cnpjws_phone  // Substitui
}
```

---

### 2. **Minha Receita** - Busca Inicial
**API:** https://minhareceita.org  
**Rate Limit:** Sem limite documentado  
**Comportamento:** Salvamento inicial (primeira vez)

#### Dados fornecidos:
- ✅ Razão social, nome fantasia
- ✅ CNPJ, CNAE
- ✅ Endereço completo
- ✅ Telefone (quando disponível)
- ✅ QSA (quadro de sócios)
- ✅ Situação cadastral, porte, natureza jurídica
- ❌ Email (não disponível)
- ❌ Website (raramente disponível)

#### Quando usar:
- **Busca automática** ao executar receita de prospecção
- Para obter dados cadastrais completos
- Para filtrar empresas ativas/inativas

#### Lógica de salvamento:
```php
// Minha Receita salva em campos específicos
INSERT INTO prospecting_results (
    phone_minhareceita,
    website_minhareceita,
    address_minhareceita,
    cnae_code,
    qsa,
    ...
)
```

---

### 3. **Google Maps** - Enriquecimento Adicional
**API:** Google Places API  
**Rate Limit:** Conforme plano Google Cloud  
**Comportamento:** **ADICIONA** em campos separados (não substitui)

#### Dados fornecidos:
- ✅ Nome comercial
- ✅ Telefone
- ✅ Website
- ✅ Endereço formatado
- ✅ Avaliação (rating)
- ✅ Número de avaliações
- ✅ Coordenadas (lat/lng)
- ✅ Google Place ID

#### Quando usar:
- **Enriquecimento opcional** via botão "Google Maps"
- Para obter avaliações e reputação
- Para validar dados de contato
- Para obter coordenadas geográficas

#### Lógica de salvamento:
```php
// Google Maps ADICIONA em campos separados (não substitui)
UPDATE prospecting_results SET
    phone_google = ?,        // Campo separado
    website_google = ?,      // Campo separado
    address_google = ?,      // Campo separado
    rating = ?,
    google_place_id = ?
// phone_minhareceita permanece intacto
```

---

## Comparação de Fontes

| Característica | CNPJ.ws | Minha Receita | Google Maps |
|----------------|---------|---------------|-------------|
| **Email** | ✅ Sim | ❌ Não | ❌ Não |
| **Telefone** | ✅ Sim | ⚠️ Às vezes | ✅ Sim |
| **Website** | ✅ Sim | ⚠️ Raramente | ✅ Sim |
| **Endereço** | ✅ Sim | ✅ Sim | ✅ Sim |
| **CNAE** | ✅ Sim | ✅ Sim | ❌ Não |
| **QSA** | ✅ Sim | ✅ Sim | ❌ Não |
| **Avaliações** | ❌ Não | ❌ Não | ✅ Sim |
| **Coordenadas** | ❌ Não | ❌ Não | ✅ Sim |
| **Rate Limit** | 3/min | Sem limite | Conforme plano |
| **Custo** | Gratuito | Gratuito | Pago |
| **Comportamento** | **SUBSTITUI** | Inicial | **ADICIONA** |

---

## Fluxo Recomendado

### 1. Busca Inicial (Automática)
```
Receita de Prospecção
    ↓
Minha Receita API
    ↓
Salva em prospecting_results
    ↓
Campos: phone_minhareceita, address_minhareceita, cnae_code, qsa, etc.
```

### 2. Atualização de Contato (Manual)
```
Botão "Atualizar Dados"
    ↓
CNPJ.ws API
    ↓
SUBSTITUI: email, phone_minhareceita, telefone_secundario, website_minhareceita
```

### 3. Enriquecimento Adicional (Opcional)
```
Botão "Google Maps"
    ↓
Google Places API
    ↓
ADICIONA: phone_google, website_google, address_google, rating, etc.
```

---

## Estrutura de Dados Resultante

Após todas as etapas, uma empresa terá:

```json
{
  "name": "COMERCIAL MARIMAR DE AVIAMENTOS LTDA",
  "cnpj": "15.354.872/0002-06",
  
  // Minha Receita (inicial)
  "phone_minhareceita": "+554730422778",
  "address_minhareceita": "RUA GENERAL OSORIO, 1995",
  "website_minhareceita": null,
  "cnae_code": "1340599",
  "qsa": {...},
  
  // CNPJ.ws (atualização - SUBSTITUI)
  "email": "financeiro@marimartextil.com.br",  // ← Atualizado
  "phone_minhareceita": "+554730422778",        // ← Confirmado/atualizado
  "telefone_secundario": "+554730422779",       // ← Novo
  "website_minhareceita": "https://marimar.com.br", // ← Novo
  
  // Google Maps (enriquecimento - ADICIONA)
  "phone_google": "(47) 3042-2778",
  "website_google": "https://www.marimar.com.br",
  "address_google": "R. General Osório, 1995 - Água Verde",
  "rating": 4.5,
  "user_ratings_total": 123,
  "google_place_id": "ChIJ..."
}
```

---

## Regras de Negócio

### ✅ CNPJ.ws (Fonte da Verdade)
- **SEMPRE substitui** dados existentes
- Usado para **atualização manual**
- Dados mais confiáveis e atualizados
- Rate limit: 3/min (aguardar 20s entre requisições)

### ✅ Minha Receita (Busca Inicial)
- Usado apenas no **salvamento inicial**
- Não substitui dados após primeira busca
- Fonte de dados cadastrais (CNAE, QSA, etc.)

### ✅ Google Maps (Enriquecimento)
- **NUNCA substitui** dados da Minha Receita
- **ADICIONA** em campos separados (`_google`)
- Usado para validação e dados adicionais
- Fornece avaliações e reputação

---

## Exemplo de Uso

### Cenário 1: Busca Nova Empresa
```
1. Executar receita → Minha Receita
   ✓ Salva: CNPJ, endereço, CNAE, telefone (se disponível)
   ✗ Email: NULL

2. Clicar "Atualizar Dados" → CNPJ.ws
   ✓ Atualiza: email, telefone, website
   ✓ SUBSTITUI dados anteriores

3. Clicar "Google Maps" → Google Places
   ✓ Adiciona: phone_google, rating, avaliações
   ✓ NÃO substitui phone_minhareceita
```

### Cenário 2: Atualizar Dados Desatualizados
```
1. Empresa já existe no banco
   - Email: antigo@empresa.com
   - Telefone: (47) 3333-3333

2. Clicar "Atualizar Dados" → CNPJ.ws
   ✓ SUBSTITUI email: novo@empresa.com
   ✓ SUBSTITUI telefone: (47) 4444-4444
   ✓ Dados atualizados da Receita Federal
```

---

## Conclusão

- **CNPJ.ws** = Fonte da verdade, sempre substitui
- **Minha Receita** = Busca inicial, dados cadastrais
- **Google Maps** = Enriquecimento adicional, não substitui

**Prioridade de confiança:**
1. CNPJ.ws (dados da Receita Federal)
2. Minha Receita (dados cadastrais)
3. Google Maps (dados comerciais)
