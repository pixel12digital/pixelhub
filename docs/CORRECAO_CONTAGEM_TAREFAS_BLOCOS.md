# Correção: Divergência na Contagem de Tarefas dos Blocos

**Data:** 2025-01-25  
**Objetivo:** Corrigir divergência entre a contagem de tarefas exibida no card da Agenda e a listagem real de tarefas do bloco.

---

## 1. Problema Identificado

### 1.1. Sintoma
- Na **Agenda Diária**, o bloco das 07:00–09:00 do dia 01/12/2025 (block_id = 1) aparecia com badge **"4 tarefa(s)"**.
- Ao abrir o bloco em `/agenda/bloco?id=1`, na tabela **"Tarefas do Bloco"** apareciam apenas **3 tarefas**.

### 1.2. Causa Raiz
A divergência ocorria porque:

1. **Contagem no card (métodos `getBlocksByDate()`, `getBlocksForPeriod()`, `getAvailableBlocks()`)**:
   - Usava: `(SELECT COUNT(*) FROM agenda_block_tasks WHERE bloco_id = b.id)`
   - **Problemas:**
     - Contava **todas as linhas** da pivot, incluindo duplicidades
     - **Não filtrava** tarefas soft-deletadas (`deleted_at IS NULL`)
     - Não usava `DISTINCT`, então se a mesma tarefa aparecesse múltiplas vezes na pivot, era contada múltiplas vezes

2. **Listagem de tarefas (método `getTasksByBlock()`)**:
   - Usava: `INNER JOIN tasks t ON abt.task_id = t.id WHERE abt.bloco_id = ? AND t.deleted_at IS NULL`
   - **Correto:**
     - Filtrava tarefas soft-deletadas
     - Usava `JOIN` com `tasks`, então apenas tarefas válidas apareciam
     - Não havia duplicidades na listagem final

### 1.3. Cenários que Causavam Divergência

1. **Tarefas soft-deletadas**: Se uma tarefa tinha `deleted_at IS NOT NULL`, ela:
   - ❌ Era contada na badge (contagem antiga)
   - ✅ Não aparecia na listagem (filtro correto)

2. **Duplicidades na pivot**: Se `agenda_block_tasks` tinha múltiplas linhas para o mesmo `(bloco_id, task_id)`:
   - ❌ Cada linha era contada separadamente na badge (contagem antiga)
   - ✅ Apenas uma tarefa aparecia na listagem (JOIN natural elimina duplicidades)

---

## 2. Solução Implementada

### 2.1. Método Auxiliar para Verificação de Coluna

Criado método privado `hasDeletedAtColumn()` com cache estático para evitar múltiplas queries:

```php
private static $hasDeletedAtColumn = null;

private static function hasDeletedAtColumn(PDO $db): bool
{
    if (self::$hasDeletedAtColumn !== null) {
        return self::$hasDeletedAtColumn;
    }
    
    try {
        $stmt = $db->query("SHOW COLUMNS FROM tasks LIKE 'deleted_at'");
        self::$hasDeletedAtColumn = $stmt->rowCount() > 0;
    } catch (\PDOException $e) {
        self::$hasDeletedAtColumn = false;
    }
    
    return self::$hasDeletedAtColumn;
}
```

### 2.2. Subquery de Contagem Corrigida

A nova subquery de contagem segue **exatamente os mesmos filtros** de `getTasksByBlock()`:

**Com coluna `deleted_at`:**
```sql
(SELECT COUNT(DISTINCT abt.task_id)
 FROM agenda_block_tasks abt
 INNER JOIN tasks t ON abt.task_id = t.id
 WHERE abt.bloco_id = b.id
 AND t.deleted_at IS NULL
)
```

**Sem coluna `deleted_at` (compatibilidade):**
```sql
(SELECT COUNT(DISTINCT abt.task_id)
 FROM agenda_block_tasks abt
 INNER JOIN tasks t ON abt.task_id = t.id
 WHERE abt.bloco_id = b.id
)
```

**Melhorias:**
- ✅ Usa `COUNT(DISTINCT abt.task_id)` para evitar duplicidades
- ✅ Faz `INNER JOIN` com `tasks` para garantir que apenas tarefas válidas sejam contadas
- ✅ Filtra `t.deleted_at IS NULL` se a coluna existir
- ✅ Compatível com bancos que não têm a coluna `deleted_at`

### 2.3. Métodos Corrigidos

#### 2.3.1. `getBlocksByDate()`
**Arquivo:** `src/Services/AgendaService.php` (linha ~839)

**Antes:**
```sql
(SELECT COUNT(*) FROM agenda_block_tasks WHERE bloco_id = b.id) as tarefas_count
```

**Depois:**
```sql
{$tasksCountSubquery} as tarefas_count
```
(onde `$tasksCountSubquery` é a subquery corrigida)

#### 2.3.2. `getBlocksForPeriod()`
**Arquivo:** `src/Services/AgendaService.php` (linha ~466)

**Antes:**
```sql
(SELECT COUNT(*) FROM agenda_block_tasks WHERE bloco_id = b.id) as total_tarefas
```

**Depois:**
```sql
{$tasksCountSubquery} as total_tarefas
```

#### 2.3.3. `getAvailableBlocks()`
**Arquivo:** `src/Services/AgendaService.php` (linha ~533)

**Antes:**
```sql
(SELECT COUNT(*) FROM agenda_block_tasks WHERE bloco_id = b.id) as tasks_count
```

**Depois:**
```sql
{$tasksCountSubquery} as tasks_count
```

---

## 3. Script de Investigação

Criado script `database/investigate-block-tasks.php` para investigar duplicidades e divergências:

### 3.1. Funcionalidades

1. **Verifica duplicidades globais** na tabela `agenda_block_tasks`
2. **Investiga um bloco específico** (se fornecido como argumento)
3. **Compara contagens** (antiga vs nova vs listagem)
4. **Lista todas as linhas** da pivot para o bloco
5. **Mostra script de limpeza** (não executa automaticamente)

### 3.2. Uso

```bash
# Investigar duplicidades globais
php database/investigate-block-tasks.php

# Investigar bloco específico (ex: bloco ID 1)
php database/investigate-block-tasks.php 1
```

### 3.3. Exemplo de Saída

```
=== Investigação: Duplicidades e Divergências na Contagem de Tarefas ===

✓ Coluna 'deleted_at' existe: SIM

=== 1. Duplicidades na Tabela agenda_block_tasks ===
⚠ Encontradas 2 duplicidades:

  Bloco ID: 1, Task ID: 5, Ocorrências: 2
  Bloco ID: 3, Task ID: 8, Ocorrências: 2

=== 2. Investigação do Bloco ID: 1 ===

Informações do Bloco:
  Data: 2025-12-01
  Horário: 07:00:00 - 09:00:00
  Tipo: FUTURE (FUTURE)
  Status: planned

2.1. Todas as linhas na pivot (agenda_block_tasks):
  Total de linhas na pivot: 4

  - Pivot ID: 1, Task ID: 3, Título: Tarefa A, Status: em_andamento
  - Pivot ID: 2, Task ID: 5, Título: Tarefa B, Status: em_andamento
  - Pivot ID: 3, Task ID: 5, Título: Tarefa B, Status: em_andamento (DUPLICADA)
  - Pivot ID: 4, Task ID: 7, Título: Tarefa C, Status: concluida (DELETADA: 2025-01-20 10:00:00)

2.2. Contagem ANTIGA (COUNT(*) sem filtros):
  Resultado: 4 tarefa(s)

2.3. Contagem NOVA (COUNT(DISTINCT) + filtro deleted_at):
  Resultado: 2 tarefa(s)

2.4. Listagem de tarefas (getTasksByBlock):
  Total de tarefas na listagem: 2

  - Task ID: 3, Título: Tarefa A, Status: em_andamento
  - Task ID: 5, Título: Tarefa B, Status: em_andamento

2.5. Análise de Divergência:
  Contagem ANTIGA: 4
  Contagem NOVA: 2
  Listagem: 2

  ✓ Contagem NOVA e Listagem estão alinhadas!
  ⚠ Diferença entre contagem ANTIGA e NOVA: 2 tarefa(s)
     (Provavelmente tarefas deletadas ou duplicidades)
```

---

## 4. Limpeza de Duplicidades (Opcional)

Se o script de investigação identificar duplicidades, você pode limpar manualmente:

### 4.1. SQL para Limpar Duplicidades

**Manter apenas a primeira ocorrência de cada par (bloco_id, task_id):**

```sql
DELETE abt1 
FROM agenda_block_tasks abt1
INNER JOIN agenda_block_tasks abt2
WHERE abt1.bloco_id = abt2.bloco_id
  AND abt1.task_id = abt2.task_id
  AND abt1.id > abt2.id;
```

**⚠️ ATENÇÃO:**
- Faça **backup** antes de executar qualquer `DELETE`
- Teste em ambiente de desenvolvimento primeiro
- Verifique os resultados com o script de investigação antes e depois

### 4.2. Verificação Pós-Limpeza

```bash
# Verificar se ainda há duplicidades
php database/investigate-block-tasks.php
```

---

## 5. Critérios de Aceitação

### 5.1. Para o Bloco ID 1 (07:00–09:00 do dia 01/12/2025)
- ✅ A badge da Agenda Diária deve mostrar **3 tarefa(s)** se na lista aparecem 3
- ✅ A contagem deve bater exatamente com a listagem

### 5.2. Para Qualquer Bloco
- ✅ A contagem de tarefas no card da **Agenda Diária** deve sempre bater com a tabela "Tarefas do Bloco"
- ✅ A contagem de tarefas no card da **Agenda Semanal** deve sempre bater com a tabela "Tarefas do Bloco"
- ✅ A lógica de contagem está centralizada e compartilhada entre todos os métodos

### 5.3. Regras de Negócio Mantidas
- ✅ Tarefas soft-deletadas (`deleted_at IS NOT NULL`) **não são contadas**
- ✅ Duplicidades na pivot **não são contadas** (usa `COUNT(DISTINCT)`)
- ✅ Apenas tarefas válidas e não deletadas aparecem na contagem e na listagem

---

## 6. Arquivos Alterados

### 6.1. Código Fonte
- **`src/Services/AgendaService.php`**
  - Adicionado método privado `hasDeletedAtColumn()` com cache estático
  - Corrigido método `getBlocksByDate()` (linha ~839)
  - Corrigido método `getBlocksForPeriod()` (linha ~466)
  - Corrigido método `getAvailableBlocks()` (linha ~533)

### 6.2. Scripts de Investigação
- **`database/investigate-block-tasks.php`** (novo arquivo)
  - Script para investigar duplicidades e divergências
  - Compara contagens antiga vs nova vs listagem
  - Mostra script de limpeza (não executa automaticamente)

### 6.3. Documentação
- **`docs/CORRECAO_CONTAGEM_TAREFAS_BLOCOS.md`** (este arquivo)

---

## 7. Como Testar

### 7.1. Teste Manual

1. **Acesse a Agenda Diária** (`/agenda`)
2. **Verifique um bloco** que tenha tarefas vinculadas
3. **Anote o número** exibido no badge (ex: "3 tarefa(s)")
4. **Clique no bloco** para abrir `/agenda/bloco?id=X`
5. **Conte as tarefas** na tabela "Tarefas do Bloco"
6. **Verifique se os números batem**

### 7.2. Teste com Script

```bash
# Investigar bloco específico
php database/investigate-block-tasks.php 1

# Verificar se há duplicidades globais
php database/investigate-block-tasks.php
```

### 7.3. Teste de Regressão

- ✅ Agenda Diária carrega normalmente
- ✅ Agenda Semanal carrega normalmente
- ✅ Resumo Semanal carrega normalmente
- ✅ Modal "Vincular tarefa existente" continua funcionando
- ✅ Listagem de tarefas do bloco continua funcionando

---

## 8. Notas Técnicas

### 8.1. Compatibilidade
- A solução é **compatível** com bancos que **não têm** a coluna `deleted_at`
- A verificação é feita uma vez e cacheada para melhor performance

### 8.2. Performance
- A subquery de contagem é executada para cada bloco na query principal
- O uso de `COUNT(DISTINCT)` e `INNER JOIN` pode ser ligeiramente mais lento que `COUNT(*)`, mas garante precisão
- O cache da verificação de coluna evita múltiplas queries `SHOW COLUMNS`

### 8.3. Futuras Melhorias
- Considerar adicionar índice em `agenda_block_tasks(bloco_id, task_id)` se houver muitas duplicidades
- Considerar adicionar constraint `UNIQUE(bloco_id, task_id)` na pivot para prevenir duplicidades futuras
- Considerar soft delete na pivot (`agenda_block_tasks.deleted_at`) se necessário

---

## 9. Conclusão

A correção garante que:
- ✅ A contagem de tarefas no card da Agenda **sempre bate** com a listagem real
- ✅ Tarefas soft-deletadas **não são contadas**
- ✅ Duplicidades na pivot **não são contadas**
- ✅ A lógica está **centralizada** e **compartilhada** entre todos os métodos
- ✅ O código é **compatível** com diferentes versões do schema do banco

**Status:** ✅ Implementado e testado










