# Ajuste do Intervalo de Polling para 10 Segundos

## 📋 Resumo

Ajustado o intervalo de polling do Communication Hub para **10 segundos** (em vez de 12 segundos), com configuração centralizada em uma constante `HUB_POLLING_MS` para facilitar futuras alterações.

---

## ✅ Alterações Implementadas

### 1. Constante Central de Configuração

**Arquivos alterados:**
- `views/communication_hub/index.php`
- `views/communication_hub/thread.php`

**Constante criada:**
```javascript
const HUB_POLLING_MS = 10000; // 10 segundos - Intervalo de polling configurável
```

### 2. Substituição de Valores Hardcoded

#### `views/communication_hub/index.php`

**Antes:**
```javascript
HubState.pollingInterval = setInterval(() => {
    // ...
}, 12000); // 12 segundos ao invés de 3
```

**Depois:**
```javascript
HubState.pollingInterval = setInterval(() => {
    // ...
}, HUB_POLLING_MS);
```

**Antes:**
```javascript
ConversationState.pollingInterval = setInterval(() => {
    // ...
}, 12000);
```

**Depois:**
```javascript
ConversationState.pollingInterval = setInterval(() => {
    // ...
}, HUB_POLLING_MS);
```

#### `views/communication_hub/thread.php`

**Antes:**
```javascript
const THREAD_CONFIG = {
    pollInterval: 12000, // 12 segundos quando ativo
    pollIntervalInactive: 30000, // 30 segundos quando inativo
};
```

**Depois:**
```javascript
const HUB_POLLING_MS = 10000; // 10 segundos - Intervalo de polling configurável

const THREAD_CONFIG = {
    pollInterval: HUB_POLLING_MS, // Intervalo quando ativo (configurável via HUB_POLLING_MS)
    pollIntervalInactive: HUB_POLLING_MS * 3, // 3x o intervalo ativo quando inativo (30s com padrão de 10s)
};
```

---

## 📊 Locais Atualizados

### 1. Polling da Lista de Conversas
- **Arquivo:** `views/communication_hub/index.php`
- **Função:** `startListPolling()`
- **Intervalo:** `HUB_POLLING_MS` (10 segundos)

### 2. Polling da Conversa Ativa
- **Arquivo:** `views/communication_hub/index.php`
- **Função:** `startConversationPolling()`
- **Intervalo:** `HUB_POLLING_MS` (10 segundos)

### 3. Polling da Thread (Página Separada)
- **Arquivo:** `views/communication_hub/thread.php`
- **Função:** `startPolling()`
- **Intervalo ativo:** `HUB_POLLING_MS` (10 segundos)
- **Intervalo inativo:** `HUB_POLLING_MS * 3` (30 segundos)

---

## 🔧 Comportamento Mantido

- ✅ Polling inteligente (pausa quando página está oculta)
- ✅ Respeita interação do usuário (não faz polling durante interação)
- ✅ Intervalo inativo é 3x o intervalo ativo (30s quando padrão é 10s)
- ✅ Primeiro check após 2 segundos (mantido)
- ✅ Verificação de interação antes de fazer polling (mantido)

---

## 📝 Como Alterar o Intervalo no Futuro

Para alterar o intervalo de polling no futuro, basta modificar a constante `HUB_POLLING_MS` em ambos os arquivos:

1. `views/communication_hub/index.php` (linha ~873)
2. `views/communication_hub/thread.php` (linha ~173)

**Exemplo:** Para 15 segundos:
```javascript
const HUB_POLLING_MS = 15000; // 15 segundos
```

O intervalo inativo será automaticamente ajustado para `HUB_POLLING_MS * 3` (45 segundos no exemplo acima).

---

## ✅ Validação

- ✅ Nenhum valor hardcoded de intervalo restante
- ✅ Todos os intervalos usam a constante `HUB_POLLING_MS`
- ✅ Comportamento atual mantido (apenas intervalo alterado)
- ✅ Configuração centralizada e fácil de modificar

---

**Data da Implementação:** 16/01/2026  
**Status:** ✅ Implementado e Testado

