# Plano de Unificação Visual - PixelHub

## 📋 Objetivo

Unificar o visual de todas as páginas do sistema usando a página **Projetos & Tarefas** (`/projects`) como referência aprovada, garantindo que todas as telas tenham a mesma identidade visual profissional.

---

## ✅ Padrão de Referência (Aprovado)

**Página:** `/projects` (Lista de Projetos)

### Características Aprovadas:

1. **Botões de Ação:**
   - Tamanho: 32x32px
   - Border-radius: 6px (não circular)
   - Cores suaves com fundo claro:
     - Detalhes: `#f3f4f6` (cinza claro) + ícone cinza
     - Ver quadro: `#eff6ff` (azul claro) + ícone azul
     - Abrir ticket: `#fffbeb` (amarelo claro) + ícone laranja
     - Editar: `#f3f4f6` (cinza claro) + ícone cinza
     - Arquivar: outline vermelho discreto + ícone X
   - Ícones: 14x14px, opacidade 0.85 (1.0 no hover)
   - Hover: apenas `brightness(0.95)`, sem movimento

2. **Tooltip:**
   - Posição: acima do botão, centralizado
   - Cor: `#374151` (cinza escuro)
   - Tamanho: `font-size: 11px`, `padding: 5px 9px`
   - Aparece instantaneamente (0.12s)
   - Sem tooltip nativo do navegador

3. **Tabelas:**
   - Cabeçalho: `#f3f4f6` (cinza claro)
   - Texto cabeçalho: `#374151`, `font-weight: 600`
   - Bordas: `#e5e7eb`
   - Hover nas linhas: `#f9fafb`
   - Zebra discreto: `#fcfcfd` (linhas pares)

4. **Badges:**
   - Prioridade: cores suaves (fundo claro + texto escuro)
   - Status: badges com bordas discretas
   - Border-radius: `999px` (pill)
   - Padding: `4px 8px`
   - Font-size: `12px`

5. **Espaçamentos:**
   - Gap entre botões: 6px
   - Padding de células: 12px
   - Margin-bottom de cards: 20px

---

## 🔍 Análise das Páginas Principais

### Páginas Analisadas:

1. ✅ **Projetos** (`/projects`) - **REFERÊNCIA APROVADA**
2. ⚠️ **Clientes** (`/tenants`)
3. ⚠️ **Dashboard** (`/dashboard`)
4. ⚠️ **Hospedagem** (`/hosting`)
5. ⚠️ **Financeiro** (`/billing/collections`)
6. ⚠️ **Serviços** (`/services`)
7. ⚠️ **Tickets** (`/tickets`)
8. ⚠️ **Agenda** (`/agenda`)
9. ⚠️ **Configurações** (várias páginas)

---

## 📊 Mapeamento de Diferenças

### 1. Botões de Ação

#### ❌ Problemas Identificados:

**Páginas com botões grandes/chamativos:**
- `/hosting`: Botões com `padding: 6px 12px`, cores chapadas (`#023A8D`)
- `/services`: Botões com estilo diferente
- `/tenants`: Botões inline com estilos variados
- `/billing/collections`: Botões com cores diferentes

**Padrão atual (não aprovado):**
- Botões grandes (padding 8-10px)
- Cores chapadas (azul `#023A8D`, verde `#28a745`)
- Sem ícones ou ícones grandes
- Hover com transform/translateY

**Padrão desejado (aprovado):**
- Botões 32x32px, border-radius 6px
- Cores suaves com fundo claro
- Ícones 14x14px
- Hover discreto (apenas brightness)

---

### 2. Tabelas

#### ❌ Problemas Identificados:

**Páginas com tabelas diferentes:**
- `/hosting`: Cabeçalho `#f5f5f5` (ok), mas sem hover/zebra
- `/services`: Cabeçalho `#f8f9fa`, bordas `#dee2e6`
- `/billing/collections`: Estilo diferente
- `/tenants`: Estilo básico, sem hover

**Padrão atual (não aprovado):**
- Cabeçalhos variados (`#f5f5f5`, `#f8f9fa`)
- Sem hover consistente
- Sem zebra striping
- Bordas inconsistentes

**Padrão desejado (aprovado):**
- Cabeçalho: `#f3f4f6`, texto `#374151`, `font-weight: 600`
- Hover: `#f9fafb`
- Zebra: `#fcfcfd` (linhas pares)
- Bordas: `#e5e7eb`

---

### 3. Badges

#### ❌ Problemas Identificados:

**Páginas com badges diferentes:**
- `/tickets`: Badges com cores diferentes (`#e3f2fd`, `#fff3e0`)
- `/services`: Badges com estilo diferente
- `/hosting`: Status com cores chapadas (`#3c3`, `#c33`)

**Padrão atual (não aprovado):**
- Cores saturadas
- Sem bordas
- Tamanhos variados

**Padrão desejado (aprovado):**
- Cores suaves (fundo claro + texto escuro)
- Bordas discretas
- Border-radius: `999px`
- Padding: `4px 8px`
- Font-size: `12px`

---

### 4. Tooltips

#### ❌ Problemas Identificados:

**Páginas sem tooltip customizado:**
- Todas as outras páginas ainda usam tooltip nativo (se houver)
- Botões sem `data-tooltip`

**Padrão desejado (aprovado):**
- Tooltip customizado acima do botão
- Cor: `#374151`
- Tamanho: `11px`
- Instantâneo (0.12s)
- Sem tooltip nativo

---

### 5. Cabeçalhos de Página

#### ❌ Problemas Identificados:

**Inconsistências:**
- Algumas páginas têm apenas `<h2>`
- Outras têm `<h2>` + `<p>` com estilos diferentes
- Botões de ação no cabeçalho com estilos diferentes

**Padrão desejado:**
- Estrutura: `<h2>` + `<p>` (subtítulo)
- Botões: estilo padrão unificado

---

### 6. Cards/Containers

#### ❌ Problemas Identificados:

**Inconsistências:**
- Alguns cards sem padding consistente
- Bordas e sombras diferentes

**Padrão desejado:**
- Border-radius: `8px`
- Padding: `20px`
- Box-shadow: `0 2px 4px rgba(0,0,0,0.05)`
- Border: `1px solid #e5e7eb`

---

## 🎯 Plano de Implementação

### Fase 1: Estilos Globais (CSS Base)

**Arquivo:** `public/assets/css/app-overrides.css`

#### 1.1. Padronizar Tabelas Globalmente

```css
/* Aplicar em TODAS as tabelas */
table {
  border-color: #e5e7eb !important;
}

thead th {
  background: #f3f4f6 !important;
  color: #374151 !important;
  font-weight: 600 !important;
  border-bottom: 1px solid #e5e7eb !important;
  padding: 12px !important;
}

tbody td {
  border-top: 1px solid #e5e7eb !important;
  vertical-align: middle !important;
  padding: 12px !important;
}

tbody tr:hover {
  background: #f9fafb !important;
}

tbody tr:nth-child(even) {
  background: #fcfcfd !important;
}
```

#### 1.2. Padronizar Botões de Ação Globalmente

```css
/* Botões de ação em tabelas (coluna Ações) */
td:last-child .btn,
.acoes .btn,
.actions .btn {
  /* Estilo aprovado já implementado */
  /* Replicar para todas as páginas */
}
```

#### 1.3. Padronizar Badges Globalmente

```css
/* Badges de status/prioridade */
.badge, .label, .status-badge, .priority-badge {
  font-weight: 600 !important;
  padding: 4px 8px !important;
  border-radius: 999px !important;
  font-size: 12px !important;
  border: 1px solid transparent !important;
}

/* Cores suaves padrão */
.badge-success { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
.badge-danger { background: #fef2f2; color: #7f1d1d; border-color: #fecaca; }
.badge-warning { background: #fffbeb; color: #92400e; border-color: #fde68a; }
.badge-info { background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe; }
```

#### 1.4. Padronizar Cards

```css
.card {
  background: white !important;
  border-radius: 8px !important;
  padding: 20px !important;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05) !important;
  border: 1px solid #e5e7eb !important;
  margin-bottom: 20px !important;
}
```

---

### Fase 2: Páginas Prioritárias

#### 2.1. Clientes (`/tenants`)

**Arquivo:** `views/tenants/index.php`

**Ajustes necessários:**
- [ ] Aplicar padrão de botões de ação (se houver coluna Ações)
- [ ] Padronizar tabela (cabeçalho, hover, zebra)
- [ ] Padronizar badges de status
- [ ] Adicionar tooltips customizados nos botões

**Prioridade:** Alta (página principal)

---

#### 2.2. Hospedagem (`/hosting`)

**Arquivo:** `views/hosting/index.php`

**Ajustes necessários:**
- [ ] Transformar botão "Backups" em botão de ação padrão
- [ ] Padronizar tabela
- [ ] Padronizar badges de status
- [ ] Adicionar tooltips

**Prioridade:** Alta

---

#### 2.3. Serviços (`/services`)

**Arquivo:** `views/services/index.php`

**Ajustes necessários:**
- [ ] Padronizar botões de ação na coluna Ações
- [ ] Padronizar tabela
- [ ] Padronizar badges de status
- [ ] Adicionar tooltips

**Prioridade:** Média

---

#### 2.4. Financeiro (`/billing/collections`)

**Arquivo:** `views/billing_collections/index.php`

**Ajustes necessários:**
- [ ] Padronizar botões de filtro
- [ ] Padronizar tabela
- [ ] Padronizar badges de status
- [ ] Padronizar cards de resumo

**Prioridade:** Alta

---

#### 2.5. Tickets (`/tickets`)

**Arquivo:** `views/tickets/index.php`

**Ajustes necessários:**
- [ ] Padronizar badges de prioridade/status
- [ ] Padronizar cards de ticket (se houver)
- [ ] Padronizar botões

**Prioridade:** Média

---

#### 2.6. Dashboard (`/dashboard`)

**Arquivo:** `views/dashboard/index.php`

**Ajustes necessários:**
- [ ] Padronizar cards de estatísticas
- [ ] Padronizar espaçamentos

**Prioridade:** Média

---

### Fase 3: Páginas Secundárias

#### 3.1. Agenda (`/agenda`)

**Ajustes necessários:**
- [ ] Padronizar tabelas
- [ ] Padronizar badges
- [ ] Padronizar botões

**Prioridade:** Baixa

---

#### 3.2. Configurações (várias páginas)

**Ajustes necessários:**
- [ ] Padronizar formulários
- [ ] Padronizar botões
- [ ] Padronizar tabelas (se houver)

**Prioridade:** Baixa

---

## 📝 Checklist de Implementação

### Estilos Globais
- [ ] Tabelas padronizadas (cabeçalho, hover, zebra)
- [ ] Botões de ação padronizados (32x32px, cores suaves)
- [ ] Badges padronizados (cores suaves, bordas)
- [ ] Tooltips customizados (acima, discreto)
- [ ] Cards padronizados (border-radius, padding, sombra)

### Páginas Prioritárias
- [ ] `/tenants` - Clientes
- [ ] `/hosting` - Hospedagem
- [ ] `/billing/collections` - Financeiro
- [ ] `/services` - Serviços
- [ ] `/tickets` - Tickets
- [ ] `/dashboard` - Dashboard

### Páginas Secundárias
- [ ] `/agenda` - Agenda
- [ ] `/settings/*` - Configurações
- [ ] Outras páginas conforme necessário

---

## 🎨 Guia de Cores Padrão

### Botões de Ação:
- **Detalhes/Editar:** `#f3f4f6` (fundo) + `#6b7280` (ícone)
- **Ver quadro:** `#eff6ff` (fundo) + `#1d4ed8` (ícone)
- **Abrir ticket:** `#fffbeb` (fundo) + `#f59e0b` (ícone)
- **Arquivar:** transparente + `#dc2626` (ícone/borda)

### Badges:
- **Sucesso/Ativo:** `#ecfdf5` (fundo) + `#065f46` (texto)
- **Aviso/Média:** `#fffbeb` (fundo) + `#92400e` (texto)
- **Erro/Alta:** `#fef2f2` (fundo) + `#7f1d1d` (texto)
- **Info:** `#eff6ff` (fundo) + `#1e3a8a` (texto)
- **Neutro/Arquivado:** `#f3f4f6` (fundo) + `#4b5563` (texto)

### Tabelas:
- **Cabeçalho:** `#f3f4f6` (fundo) + `#374151` (texto)
- **Hover:** `#f9fafb`
- **Zebra:** `#fcfcfd` (linhas pares)
- **Bordas:** `#e5e7eb`

---

## ⚠️ Regras Importantes

1. **Apenas CSS/Estilos:** Não alterar lógica, rotas, queries ou comportamento
2. **Usar `!important` quando necessário:** Para sobrescrever estilos inline existentes
3. **Manter acessibilidade:** `aria-label` nos botões, tooltips informativos
4. **Testar em todas as páginas:** Garantir que não quebrou nada
5. **Priorizar páginas principais:** Começar pelas mais usadas

---

## 📅 Ordem de Execução Sugerida

1. **Fase 1:** Estilos globais (tabelas, badges, cards)
2. **Fase 2.1:** Clientes (`/tenants`)
3. **Fase 2.2:** Hospedagem (`/hosting`)
4. **Fase 2.3:** Financeiro (`/billing/collections`)
5. **Fase 2.4:** Serviços (`/services`)
6. **Fase 2.5:** Tickets (`/tickets`)
7. **Fase 2.6:** Dashboard (`/dashboard`)
8. **Fase 3:** Páginas secundárias conforme necessidade

---

## ✅ Critérios de Sucesso

- [ ] Todas as tabelas têm o mesmo visual (cabeçalho, hover, zebra)
- [ ] Todos os botões de ação seguem o padrão (32x32px, cores suaves)
- [ ] Todos os badges seguem o padrão (cores suaves, bordas)
- [ ] Tooltips customizados em todos os botões de ação
- [ ] Cards com estilo consistente
- [ ] Nenhuma página parece "de outro sistema"
- [ ] Visual profissional e unificado em todo o sistema

---

**Última atualização:** 2025-01-09
**Status:** Planejamento completo, pronto para implementação



