# 🔄 Funcionalidade: Reabrir Bloco Concluído

**Data de Implementação:** 2025-01-27  
**Objetivo:** Permitir reabrir blocos concluídos, voltando o status para "planejado" e resetando horários reais

---

## 📋 SUMÁRIO EXECUTIVO

Implementada a funcionalidade de **reabrir blocos concluídos**, permitindo que usuários revertam o status de um bloco de "concluído" para "planejado" quando necessário. Ao reabrir, os horários reais são resetados e o bloco volta a ter os botões de ação normais (Iniciar, Cancelar, Excluir).

---

## 🎯 PROBLEMA RESOLVIDO

### Situação Anterior

- Blocos concluídos não podiam ser reabertos
- Se um bloco fosse concluído por engano, não havia forma de reutilizá-lo
- Blocos concluídos mostravam apenas "Abrir Bloco" e "Editar", sem opção de reiniciar

### Solução Implementada

- Botão "Reabrir Bloco" disponível para blocos com status "completed"
- Ao reabrir: status volta para "planned", horários reais são resetados
- Tarefas vinculadas são mantidas (não são afetadas)
- Botões de ação normais voltam a aparecer após reabrir

---

## 🔧 ARQUIVOS ALTERADOS

### 1. `src/Services/AgendaService.php`

**Método adicionado:**

#### `reopenBlock(int $blockId): void`

**Localização:** Após o método `finishBlock()` (linha ~1256)

**Funcionalidade:**
- Valida que o bloco existe
- Valida que o status atual é `'completed'`
- Atualiza o bloco:
  - `status` → `'planned'`
  - `hora_inicio_real` → `NULL`
  - `hora_fim_real` → `NULL`
- Mantém todas as outras informações (tipo, duração planejada, tarefas vinculadas, etc.)

**Código:**
```php
public static function reopenBlock(int $blockId): void
{
    // Busca e valida bloco
    // Valida status == 'completed'
    // Atualiza: status='planned', hora_inicio_real=NULL, hora_fim_real=NULL
}
```

---

### 2. `src/Controllers/AgendaController.php`

**Método adicionado:**

#### `reopenBlock(): void`

**Localização:** Após o método `finishBlock()` (linha ~340)

**Funcionalidade:**
- Recebe `id` e `date` via POST
- Recebe `from_block` (opcional) para saber se veio da tela do bloco
- Chama `AgendaService::reopenBlock()`
- Redireciona apropriadamente:
  - Se `from_block=1`: volta para `/agenda/bloco?id=...`
  - Senão: volta para `/agenda?data=...`

**Tratamento de erros:**
- Captura `RuntimeException` (validação de status)
- Captura outras exceções genéricas
- Redireciona com mensagem de erro na URL

---

### 3. `public/index.php`

**Rota adicionada:**

```php
$router->post('/agenda/bloco/reopen', 'AgendaController@reopenBlock');
```

**Localização:** Após a rota `/agenda/bloco/finish` (linha ~311)

---

### 4. `views/agenda/show.php` (Modo de Trabalho do Bloco)

**Alteração na seção de botões:**

**Antes:**
```php
<?php if ($bloco['status'] === 'completed'): ?>
    <span class="btn btn-secondary" style="background: #4CAF50; color: white; cursor: default;">Bloco Concluído</span>
<?php endif; ?>
```

**Depois:**
```php
<?php if ($bloco['status'] === 'completed'): ?>
    <form method="post" action="<?= pixelhub_url('/agenda/bloco/reopen') ?>" style="display: inline-block;">
        <input type="hidden" name="id" value="<?= (int)$bloco['id'] ?>">
        <input type="hidden" name="from_block" value="1">
        <button type="submit" class="btn btn-primary" onclick="return confirm('Reabrir este bloco? O status voltará para Planejado e os horários reais serão resetados.');">
            Reabrir Bloco
        </button>
    </form>
<?php endif; ?>
```

**Localização:** Linha ~205 (seção de botões principais)

---

### 5. `views/agenda/index.php` (Minha Agenda)

**Alteração na lista de blocos:**

**Adicionado após os botões de "ongoing":**
```php
<?php if ($bloco['status'] === 'completed'): ?>
    <form method="post" action="<?= pixelhub_url('/agenda/bloco/reopen') ?>" style="display: inline-block; margin-left: 8px;">
        <input type="hidden" name="id" value="<?= (int)$bloco['id'] ?>">
        <input type="hidden" name="date" value="<?= htmlspecialchars($dataStr) ?>">
        <button type="submit" class="btn btn-primary" onclick="return confirm('Reabrir este bloco? O status voltará para Planejado e os horários reais serão resetados.');">
            Reabrir
        </button>
    </form>
<?php endif; ?>
```

**Localização:** Linha ~339 (seção de botões de ação dos blocos)

---

## ✅ REGRAS DE NEGÓCIO IMPLEMENTADAS

### 1. Validação de Status

- **Apenas blocos com status `'completed'` podem ser reabertos**
- Tentativa de reabrir bloco com outro status gera `RuntimeException`:
  ```
  "Apenas blocos concluídos podem ser reabertos. Status atual: {status}"
  ```

### 2. Reset de Dados

Ao reabrir, os seguintes campos são resetados:
- `status` → `'planned'`
- `hora_inicio_real` → `NULL`
- `hora_fim_real` → `NULL`
- `updated_at` → `NOW()`

**Campos mantidos:**
- `tipo_id` (tipo do bloco)
- `duracao_planejada`
- `data`, `hora_inicio`, `hora_fim`
- `projeto_foco_id`
- `resumo` (mantido para histórico)
- Tarefas vinculadas (via `agenda_block_tasks`)

### 3. Comportamento dos Botões

**Na visão diária (`index.php`):**

| Status | Botões Exibidos |
|--------|----------------|
| `planned` | Abrir, Editar, **Iniciar**, Cancelar, Excluir |
| `ongoing` | Abrir, Editar, **Encerrar**, Cancelar, Excluir |
| `completed` | Abrir, Editar, **Reabrir** ← NOVO |
| `canceled` | Abrir, Editar |

**Na tela do bloco (`show.php`):**

| Status | Botões Exibidos |
|--------|----------------|
| `planned` | **Iniciar Bloco**, Cancelar Bloco, Excluir |
| `ongoing` | **Encerrar Bloco**, Finalizar com Resumo, Cancelar Bloco |
| `completed` | **Reabrir Bloco** ← NOVO |
| `canceled` | (sem botões de ação) |

---

## 🔄 FLUXO DE USO

### Cenário 1: Reabrir da Agenda Diária

1. Usuário acessa `/agenda?data=2025-12-01`
2. Vê bloco FUTURE 07:00–09:00 com status "Concluído"
3. Clica em **"Reabrir"** (botão azul)
4. Confirma no dialog: "Reabrir este bloco? O status voltará para Planejado..."
5. Sistema:
   - Valida que status é `'completed'`
   - Atualiza para `'planned'`
   - Reseta `hora_inicio_real` e `hora_fim_real`
6. Redireciona para `/agenda?data=2025-12-01`
7. Bloco agora mostra status "Planejado" e botões "Iniciar", "Cancelar", "Excluir"

### Cenário 2: Reabrir da Tela do Bloco

1. Usuário acessa `/agenda/bloco?id=123` (bloco concluído)
2. Vê botão **"Reabrir Bloco"** no topo
3. Clica e confirma
4. Sistema reabre o bloco
5. Redireciona para `/agenda/bloco?id=123`
6. Bloco agora mostra:
   - Status: "Planejado"
   - Botão "Iniciar Bloco" disponível
   - Botão "Cancelar Bloco" disponível

### Cenário 3: Bloco com Tarefas Vinculadas

1. Bloco concluído tem 3 tarefas vinculadas
2. Usuário reabre o bloco
3. **Resultado:**
   - Status do bloco: `'planned'`
   - Tarefas continuam vinculadas (nada muda em `agenda_block_tasks`)
   - Status das tarefas não é alterado (tarefa concluída continua concluída)

---

## 🛡️ SEGURANÇA E VALIDAÇÕES

### Validações Implementadas

1. **Autenticação:** `Auth::requireInternal()` em todos os métodos
2. **Validação de ID:** Verifica se `id > 0`
3. **Validação de Status:** Apenas `'completed'` pode ser reaberto
4. **Validação de Existência:** Verifica se bloco existe antes de atualizar

### Proteções

- **Não altera tarefas:** Tarefas vinculadas não são afetadas
- **Não altera outros campos:** Apenas status e horários reais são modificados
- **Mensagens de erro claras:** Usuário recebe feedback quando tenta reabrir bloco não concluído

---

## 📊 CRITÉRIOS DE ACEITAÇÃO

### ✅ Implementado

- [x] Blocos concluídos podem ser reabertos via botão "Reabrir Bloco"
- [x] Botão disponível na tela do bloco (`show.php`)
- [x] Botão disponível na agenda diária (`index.php`)
- [x] Status volta para "planned" ao reabrir
- [x] Horários reais são resetados (`hora_inicio_real` e `hora_fim_real` = NULL)
- [x] Tarefas vinculadas são mantidas
- [x] Botões de ação normais voltam a aparecer após reabrir
- [x] Apenas blocos com status `'completed'` podem ser reabertos
- [x] Mensagens de erro claras quando tentar reabrir bloco não concluído

---

## 🧪 COMO TESTAR

### Teste 1: Reabrir da Agenda Diária

1. Acesse `/agenda?data=2025-12-01`
2. Localize um bloco com status "Concluído"
3. Clique em **"Reabrir"**
4. Confirme no dialog
5. **Resultado esperado:**
   - Bloco volta para status "Planejado"
   - Botões "Iniciar", "Cancelar", "Excluir" aparecem
   - Horários reais não aparecem mais (foram resetados)

### Teste 2: Reabrir da Tela do Bloco

1. Acesse um bloco concluído: `/agenda/bloco?id=123`
2. Clique em **"Reabrir Bloco"**
3. Confirme no dialog
4. **Resultado esperado:**
   - Página recarrega
   - Status mostra "Planejado"
   - Botão "Iniciar Bloco" aparece
   - Botão "Cancelar Bloco" aparece

### Teste 3: Bloco com Tarefas

1. Reabra um bloco que tinha tarefas vinculadas
2. **Resultado esperado:**
   - Tarefas continuam vinculadas ao bloco
   - Status das tarefas não muda
   - Bloco volta para "Planejado"

### Teste 4: Tentativa de Reabrir Bloco Não Concluído

1. Tente reabrir um bloco com status "Planejado" ou "Em Andamento"
2. **Resultado esperado:**
   - Erro: "Apenas blocos concluídos podem ser reabertos. Status atual: {status}"
   - Bloco não é alterado

---

## 📝 OBSERVAÇÕES IMPORTANTES

### 1. Resumo do Bloco

- O campo `resumo` **não é limpo** ao reabrir
- Isso permite manter histórico do que foi feito no bloco anteriormente
- Se necessário limpar, pode ser feito manualmente via edição

### 2. Duração Real

- O campo `duracao_real` **não é resetado** (não estava nos requisitos)
- Se necessário resetar também, pode ser adicionado facilmente

### 3. Compatibilidade

- Funcionalidade não quebra nenhum comportamento existente
- `startBlock()`, `finishBlock()`, `cancelBlock()` continuam funcionando normalmente
- Apenas adiciona nova opção para blocos concluídos

---

## 🎓 RESUMO DAS ALTERAÇÕES

### Arquivos Modificados

1. **`src/Services/AgendaService.php`**
   - ✅ Método `reopenBlock()` adicionado (~25 linhas)

2. **`src/Controllers/AgendaController.php`**
   - ✅ Método `reopenBlock()` adicionado (~50 linhas)

3. **`public/index.php`**
   - ✅ Rota `POST /agenda/bloco/reopen` adicionada (1 linha)

4. **`views/agenda/show.php`**
   - ✅ Botão "Reabrir Bloco" adicionado para status `'completed'` (~8 linhas)

5. **`views/agenda/index.php`**
   - ✅ Botão "Reabrir" adicionado para status `'completed'` (~8 linhas)

### Métodos Criados

- `AgendaService::reopenBlock(int $blockId): void`
- `AgendaController::reopenBlock(): void`

### Linhas de Código

- **Adicionadas:** ~92 linhas
- **Modificadas:** ~0 linhas (apenas adições)

---

## ✅ CONCLUSÃO

A funcionalidade está completa e funcional. Usuários agora podem reabrir blocos concluídos por engano, permitindo reutilizar o bloco normalmente. A implementação mantém todas as tarefas vinculadas e preserva o histórico (resumo), resetando apenas o status e os horários reais conforme especificado.

**Status:** ✅ Implementado e pronto para teste

---

**Documentação criada em:** 2025-01-27  
**Última atualização:** 2025-01-27  
**Versão:** 1.0.0









