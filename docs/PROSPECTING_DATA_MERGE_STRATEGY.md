# Estratégia de União de Dados - Prospecção Ativa

## Objetivo

Preservar informações de **ambas as fontes** (Minha Receita + Google Maps) em vez de sobrescrever dados ao enriquecer.

## Problema Anterior

**Lógica antiga (sobrescrevia dados):**
```sql
UPDATE prospecting_results SET
    phone = ?,      -- ❌ Perdia telefone da Minha Receita
    website = ?,    -- ❌ Perdia website da Minha Receita
    address = ?     -- ❌ Perdia endereço da Minha Receita
WHERE id = ?
```

**Resultado:**
- Telefone da Minha Receita: `+55 47 3370-0732`
- Após enriquecimento: `(47) 3287-0037` (Google Maps)
- ❌ **Perdeu o telefone original da Minha Receita**

## Solução Implementada

### 1. Campos Separados por Fonte

**Campos duplicados com sufixo indicando origem:**

| Campo Original | Minha Receita | Google Maps |
|---------------|---------------|-------------|
| `phone` | `phone_minhareceita` | `phone_google` |
| `website` | `website_minhareceita` | `website_google` |
| `address` | `address_minhareceita` | `address_google` |

**Campos exclusivos Minha Receita:**
- `email` (Google Maps não fornece)
- `cnae_code`, `cnae_description`
- `qsa` (Quadro de Sócios)
- `razao_social`
- `situacao_cadastral`
- `porte`, `natureza_juridica`
- etc.

**Campos exclusivos Google Maps:**
- `rating` (avaliação 0-5)
- `user_ratings_total` (número de avaliações)
- `google_place_id`
- `lat`, `lng` (coordenadas)

### 2. Lógica de Salvamento

**Ao salvar dados da Minha Receita:**
```php
INSERT INTO prospecting_results (
    name, razao_social,
    phone_minhareceita,      -- Telefone da Minha Receita
    website_minhareceita,    -- Website da Minha Receita
    address_minhareceita,    -- Endereço da Minha Receita
    email,                   -- Email (exclusivo Minha Receita)
    cnae_code,
    qsa,
    source = 'minhareceita'
) VALUES (...)
```

**Ao aplicar enriquecimento Google Maps:**
```php
UPDATE prospecting_results SET
    phone_google = ?,        -- Telefone do Google Maps
    website_google = ?,      -- Website do Google Maps
    address_google = ?,      -- Endereço do Google Maps
    rating = ?,              -- Avaliação (exclusivo Google)
    user_ratings_total = ?,
    google_place_id = ?,
    google_enriched_at = NOW(),
    enrichment_confidence = ?
WHERE id = ?
```

### 3. Exibição na Interface

**Antes (dados misturados):**
```
Telefone: (47) 3287-0037
Website: https://www.lindacasa.com.br/
```
❌ Não sabia qual era de qual fonte

**Agora (dados separados com origem):**
```
📞 Telefones:
   • Minha Receita: +55 47 3370-0732
   • Google Maps: (47) 3287-0037

🌐 Websites:
   • Minha Receita: -
   • Google Maps: https://www.lindacasa.com.br/

📍 Endereços:
   • Minha Receita: RUA WERNER DUWE, 202 — LOJA 17 — BADENFURT — BLUMENAU/SC — CEP 89070700
   • Google Maps: Av. Maria A Siriani Maida, 753 - Distrito Industrial 1, Ibitinga - SP, 14968-530
```
✅ **Origem clara de cada informação**

## Benefícios

### 1. Preservação de Dados
- ✅ Nenhuma informação é perdida
- ✅ Histórico completo de ambas as fontes
- ✅ Possibilidade de comparação e validação

### 2. Rastreabilidade
- ✅ Sabe-se qual telefone veio de qual fonte
- ✅ Possível identificar discrepâncias
- ✅ Facilita auditoria e validação

### 3. Flexibilidade
- ✅ Usuário pode escolher qual dado usar
- ✅ Pode combinar informações (telefone de uma fonte, website de outra)
- ✅ Facilita integração com CRM

### 4. Qualidade de Dados
- ✅ Múltiplos pontos de contato
- ✅ Validação cruzada de informações
- ✅ Maior chance de sucesso na prospecção

## Estrutura da Tabela

```sql
CREATE TABLE prospecting_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Dados básicos
    name VARCHAR(255) NOT NULL,
    razao_social VARCHAR(255) NULL,
    cnpj VARCHAR(20) NULL,
    source VARCHAR(30) NULL COMMENT 'minhareceita ou google_maps',
    
    -- Dados Minha Receita
    phone_minhareceita VARCHAR(50) NULL,
    website_minhareceita VARCHAR(500) NULL,
    address_minhareceita VARCHAR(500) NULL,
    email VARCHAR(255) NULL,
    cnae_code VARCHAR(10) NULL,
    cnae_description VARCHAR(255) NULL,
    qsa JSON NULL,
    situacao_cadastral VARCHAR(50) NULL,
    porte VARCHAR(50) NULL,
    natureza_juridica VARCHAR(100) NULL,
    -- ... outros campos Minha Receita
    
    -- Dados Google Maps
    phone_google VARCHAR(50) NULL,
    website_google VARCHAR(500) NULL,
    address_google VARCHAR(500) NULL,
    google_place_id VARCHAR(255) NULL,
    rating DECIMAL(2,1) NULL,
    user_ratings_total INT UNSIGNED NULL,
    lat DECIMAL(10,7) NULL,
    lng DECIMAL(10,7) NULL,
    
    -- Metadados de enriquecimento
    google_enriched_at DATETIME NULL,
    enrichment_confidence TINYINT NULL,
    google_enrichment_attempted TINYINT(1) DEFAULT 0,
    
    -- Índices
    INDEX idx_phone_minhareceita (phone_minhareceita),
    INDEX idx_phone_google (phone_google),
    INDEX idx_cnpj (cnpj)
);
```

## Exemplo Prático

### Empresa: REBORTE MALHAS

**Dados Minha Receita:**
```json
{
    "name": "REBORTE MALHAS",
    "razao_social": "REBORTE COMERCIO ATACADISTA E VAREJISTA DE MALHAS LTDA",
    "phone_minhareceita": "+554706653848",
    "address_minhareceita": "RUA FREDERICO MOHRDIECK, 726 — SALA 01 E 02 — ITOUPAVA CENTRAL — BLUMENAU/SC — CEP 89052420",
    "email": null,
    "cnae_code": "1340599",
    "situacao_cadastral": "ATIVA",
    "porte": "ME",
    "natureza_juridica": "Sociedade Empresária Limitada"
}
```

**Dados Google Maps (após enriquecimento):**
```json
{
    "name": "Reborte Comércio e Confecções de Malhas",
    "phone_google": "(47) 3287-0037",
    "address_google": "R. George Francis Mordhörst, 726 - Itoupava Central, Blumenau - SC, 89066-520, Brasil",
    "website_google": "https://www.rebortemalhas.com.br/",
    "rating": 4.4,
    "user_ratings_total": 282,
    "google_place_id": "ChIJ...",
    "lat": -26.9123456,
    "lng": -49.0654321
}
```

**Resultado final (união):**
```
📋 REBORTE MALHAS
   Razão Social: REBORTE COMERCIO ATACADISTA E VAREJISTA DE MALHAS LTDA
   CNAE: 1340599 - Fabricação de tecidos de malha
   Situação: ATIVA | Porte: ME

📞 Telefones:
   • Minha Receita: +55 47 0665-3848
   • Google Maps: (47) 3287-0037 ⭐

📍 Endereços:
   • Minha Receita: RUA FREDERICO MOHRDIECK, 726 — SALA 01 E 02
   • Google Maps: R. George Francis Mordhörst, 726 (mesmo local, nome atualizado)

🌐 Website: https://www.rebortemalhas.com.br/ (Google Maps)

⭐ Avaliação: 4.4 (282 avaliações) - Google Maps
```

## Migration

Arquivo: `database/migrations/20260223_alter_prospecting_results_separate_sources.php`

```php
public function up(PDO $db): void
{
    // Renomeia campos existentes
    $db->exec("
        ALTER TABLE prospecting_results
            CHANGE COLUMN phone phone_minhareceita VARCHAR(50) NULL,
            CHANGE COLUMN website website_minhareceita VARCHAR(500) NULL,
            CHANGE COLUMN address address_minhareceita VARCHAR(500) NULL
    ");

    // Adiciona campos Google Maps
    $db->exec("
        ALTER TABLE prospecting_results
            ADD COLUMN phone_google VARCHAR(50) NULL AFTER phone_minhareceita,
            ADD COLUMN website_google VARCHAR(500) NULL AFTER website_minhareceita,
            ADD COLUMN address_google VARCHAR(500) NULL AFTER address_minhareceita
    ");
}
```

## Próximos Passos

1. ✅ Migration criada
2. ✅ Lógica de salvamento atualizada
3. ✅ Lógica de enriquecimento atualizada
4. ⏳ Interface atualizada para exibir ambas as fontes
5. ⏳ Testes com dados reais
6. ⏳ Documentação de API atualizada

## Conclusão

A nova estratégia de união de dados **preserva informações de ambas as fontes** (Minha Receita + Google Maps), permitindo:

- ✅ Rastreabilidade completa
- ✅ Múltiplos pontos de contato
- ✅ Validação cruzada
- ✅ Melhor qualidade de dados
- ✅ Maior taxa de sucesso na prospecção

**Nenhuma informação é perdida no processo de enriquecimento!**
