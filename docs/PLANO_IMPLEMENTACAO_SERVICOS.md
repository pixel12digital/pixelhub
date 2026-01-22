# Plano de Implementação: Sistema de Gestão de Serviços

**Data:** 2025-01-07  
**Objetivo:** Implementar sistema de gestão de serviços de forma incremental, sem quebrar código existente.

---

## 🔍 **Análise do Que Já Existe**

### ✅ **O Que Já Temos e Podemos Reaproveitar:**

1. **Tabelas Existentes:**
   - ✅ `projects` - Projetos já existem
   - ✅ `tasks` - Tarefas do Kanban
   - ✅ `billing_invoices` - Faturas (Asaas)
   - ✅ `tenants` - Clientes
   - ✅ `billing_service_types` - **ATENÇÃO:** Já existe, mas é para **contratos recorrentes** (hospedagem, SaaS)

2. **Services Existentes:**
   - ✅ `ProjectService` - CRUD completo de projetos
   - ✅ `TaskService` - Gerenciamento de tarefas
   - ✅ `TicketService` - Tickets de suporte
   - ✅ `DashboardController` - Básico, mas já existe estrutura

3. **Funcionalidades Existentes:**
   - ✅ Sistema de projetos e tarefas funcionando
   - ✅ Kanban board
   - ✅ Integração com Asaas
   - ✅ Painel do cliente (`/tenants/view`)

### ⚠️ **O Que NÃO Existe (Precisa Criar):**

1. **Tabela `services`** - Catálogo de serviços pontuais (DIFERENTE de `billing_service_types`)
2. **Campos em `projects`:** `service_id`, `briefing_status`, `briefing_data`, `payment_status`, `is_blocked_by_payment`
3. **Campo em `billing_invoices`:** `project_id`
4. **Services:** `ServiceService`, `DashboardMetricsService`
5. **Controllers:** `ServicesController` (CRUD)

---

## 🎯 **Estratégia: Implementação Incremental e Segura**

### **Princípios:**
1. ✅ **Campos opcionais primeiro** (NULL) - não quebra queries existentes
2. ✅ **Novas tabelas antes de modificar existentes** - isolamento total
3. ✅ **Novos services antes de modificar existentes** - código novo não afeta código antigo
4. ✅ **Testar cada fase antes de avançar**
5. ✅ **Backward compatible** - tudo funciona sem os novos campos

---

## 📋 **Fases de Implementação**

### **FASE 1: Catálogo de Serviços (Base) ⭐ INICIAR AQUI**

**Objetivo:** Criar estrutura básica do catálogo sem quebrar nada.

#### **1.1. Criar Tabela `services`**
```
Migration: 20250107_create_services_table.php

Campos:
- id (PK)
- name (VARCHAR 255)
- description (TEXT)
- category (VARCHAR 50) - design, dev, marketing, etc
- price (DECIMAL 10,2) NULL - opcional
- estimated_duration (INT) - dias
- tasks_template (JSON) NULL - sequência de tarefas
- briefing_template (JSON) NULL - formulário guiado
- default_timeline (JSON) NULL - prazos padrão
- is_active (BOOLEAN) DEFAULT 1
- created_at, updated_at

Seguro porque: Nova tabela, não afeta nada existente
```

#### **1.2. Criar `ServiceService`**
```
Arquivo: src/Services/ServiceService.php

Métodos:
- getAllServices($category = null, $activeOnly = true): array
- findService(int $id): ?array
- createService(array $data): int
- updateService(int $id, array $data): bool
- toggleStatus(int $id): bool

Seguro porque: Novo service, isolado
```

#### **1.3. Criar `ServicesController`**
```
Arquivo: src/Controllers/ServicesController.php

Rotas:
- GET /services - Lista
- GET /services/create - Formulário
- POST /services/store - Criar
- GET /services/edit?id=X - Editar
- POST /services/update - Atualizar
- POST /services/toggle-status - Ativar/desativar

Seguro porque: Novo controller, novas rotas
```

#### **1.4. Criar Views**
```
Arquivos:
- views/services/index.php - Lista
- views/services/form.php - Criar/Editar

Seguro porque: Novas views, isoladas
```

**✅ Resultado Fase 1:** Catálogo funcionando, pode cadastrar serviços manualmente.  
**⏱️ Tempo estimado:** 2-3 horas  
**🔒 Risco:** Mínimo (tudo novo)

---

### **FASE 2: Vincular Projeto a Serviço (Opcional)**

**Objetivo:** Adicionar campo `service_id` em `projects` (opcional, não quebra nada).

#### **2.1. Migration: Adicionar `service_id` em `projects`**
```
Migration: 20250107_alter_projects_add_service_id.php

ALTER TABLE projects
ADD COLUMN service_id INT UNSIGNED NULL AFTER tenant_id,
ADD INDEX idx_service_id (service_id),
ADD FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL;

Seguro porque:
- Campo NULL (opcional)
- Não quebra queries existentes (podem ignorar)
- Foreign key com SET NULL (não bloqueia exclusões)
```

#### **2.2. Atualizar `ProjectService`**
```
Modificar: src/Services/ProjectService.php

Método createProject():
- Aceitar service_id (opcional)
- Salvar se fornecido

Método updateProject():
- Aceitar service_id (opcional)
- Atualizar se fornecido

Método getAllProjects():
- JOIN opcional com services (se precisar do nome)
- Manter compatibilidade total

Seguro porque:
- Campos opcionais
- Código existente continua funcionando
- Apenas adiciona funcionalidade nova
```

#### **2.3. Atualizar Views de Projetos**
```
Modificar: views/projects/form.php

Adicionar:
- Campo select para escolher serviço do catálogo (opcional)
- Mostrar informações do serviço se selecionado

Seguro porque:
- Campo opcional (não obrigatório)
- Se não preencher, funciona como antes
```

**✅ Resultado Fase 2:** Projetos podem ser vinculados a serviços (opcional).  
**⏱️ Tempo estimado:** 1-2 horas  
**🔒 Risco:** Baixo (campos opcionais)

---

### **FASE 3: Dashboard com Métricas Básicas**

**Objetivo:** Expandir dashboard existente sem quebrar.

#### **3.1. Criar `DashboardMetricsService`**
```
Arquivo: src/Services/DashboardMetricsService.php

Métodos básicos primeiro:
- getActiveProjectsCount(): int
- getMonthlyRevenue(): float
- getPendingRevenue(): float
- getBlockedProjectsCount(): int (retorna 0 por enquanto)

Seguro porque: Novo service, não afeta código existente
```

#### **3.2. Expandir `DashboardController`**
```
Modificar: src/Controllers/DashboardController.php

Adicionar:
- Chamadas para DashboardMetricsService
- Passar métricas para view

Manter:
- Tudo que já existe (tenantsCount, invoicesCount, etc)
- Compatibilidade total

Seguro porque: Apenas adiciona, não remove
```

#### **3.3. Expandir View do Dashboard**
```
Modificar: views/dashboard/index.php

Adicionar:
- Novos cards de métricas
- Manter cards antigos

Seguro porque: Apenas adiciona visual, não remove
```

**✅ Resultado Fase 3:** Dashboard mostra métricas básicas.  
**⏱️ Tempo estimado:** 2-3 horas  
**🔒 Risco:** Baixo (apenas adiciona)

---

### **FASE 4: Campos de Briefing e Pagamento (Opcionais)**

**Objetivo:** Adicionar campos de briefing e pagamento em `projects`.

#### **4.1. Migration: Adicionar Campos em `projects`**
```
Migration: 20250107_alter_projects_add_briefing_and_payment.php

ALTER TABLE projects
ADD COLUMN briefing_status VARCHAR(20) NULL AFTER description,
ADD COLUMN briefing_data JSON NULL AFTER briefing_status,
ADD COLUMN payment_status VARCHAR(20) NULL AFTER briefing_status,
ADD COLUMN is_blocked_by_payment BOOLEAN DEFAULT 0 AFTER payment_status;

Valores padrão:
- briefing_status: NULL (não iniciado)
- briefing_data: NULL
- payment_status: NULL ou 'pendente'
- is_blocked_by_payment: 0 (false)

Seguro porque:
- Todos campos NULL/opcionais
- Valores padrão seguros
- Não afeta projetos existentes
```

#### **4.2. Atualizar `ProjectService`**
```
Modificar: src/Services/ProjectService.php

Métodos:
- Aceitar novos campos (opcionais)
- Salvar se fornecidos
- Retornar valores (NULL se não existir)

Seguro porque: Compatibilidade total mantida
```

**✅ Resultado Fase 4:** Estrutura pronta para briefing e pagamento.  
**⏱️ Tempo estimado:** 1 hora  
**🔒 Risco:** Mínimo (campos opcionais)

---

### **FASE 5: Vincular Faturas a Projetos**

**Objetivo:** Adicionar `project_id` em `billing_invoices`.

#### **5.1. Migration: Adicionar `project_id` em `billing_invoices`**
```
Migration: 20250107_alter_billing_invoices_add_project_id.php

ALTER TABLE billing_invoices
ADD COLUMN project_id INT UNSIGNED NULL AFTER tenant_id,
ADD INDEX idx_project_id (project_id);

Não usar FOREIGN KEY aqui porque:
- billing_invoices pode ter dados do Asaas
- Pode causar problemas de sincronização
- Melhor manter apenas index

Seguro porque:
- Campo NULL (opcional)
- Não afeta faturas existentes
- Queries existentes ignoram
```

#### **5.2. Atualizar `AsaasBillingService` (Opcional)**
```
Modificar: src/Services/AsaasBillingService.php

Método createInvoice():
- Aceitar project_id (opcional)
- Salvar se fornecido

Seguro porque: Opcional, não quebra sincronização
```

**✅ Resultado Fase 5:** Faturas podem ser vinculadas a projetos.  
**⏱️ Tempo estimado:** 30 minutos  
**🔒 Risco:** Mínimo

---

### **FASE 6: Auto-Geração de Tarefas (Funcionalidade Avançada)**

**Objetivo:** Quando criar projeto com serviço, gerar tarefas automaticamente.

#### **6.1. Criar Método em `ServiceService`**
```
Método: ServiceService::createTasksFromTemplate($projectId, $serviceId)

Lógica:
1. Busca serviço
2. Lê tasks_template (JSON)
3. Para cada tarefa no template:
   - Cria tarefa via TaskService
   - Aplica prazos do default_timeline

Seguro porque:
- Usa TaskService existente
- Só roda se service_id estiver preenchido
- Não afeta projetos sem serviço
```

#### **6.2. Integrar em `ProjectService`**
```
Modificar: ProjectService::createProject()

Após criar projeto:
- Se service_id foi fornecido:
  - Chama ServiceService::createTasksFromTemplate()
  - Ignora erros (não quebra criação)

Seguro porque:
- Funcionalidade opcional
- Não quebra se der erro
- Projeto é criado mesmo sem tarefas
```

**✅ Resultado Fase 6:** Projetos com serviço geram tarefas automaticamente.  
**⏱️ Tempo estimado:** 2-3 horas  
**🔒 Risco:** Médio (requer testes)

---

### **FASE 7: Briefing Guiado (Funcionalidade Avançada)**

**Objetivo:** Sistema de briefing conversacional.

#### **7.1. Criar `BriefingController`**
```
Arquivo: src/Controllers/BriefingController.php

Rotas:
- GET /briefing/project/[id] - Mostrar formulário
- POST /briefing/project/[id]/save - Salvar respostas

Seguro porque: Novo controller isolado
```

#### **7.2. Criar Views de Briefing**
```
Arquivos:
- views/briefing/form.php - Formulário conversacional

Seguro porque: Novas views
```

**✅ Resultado Fase 7:** Briefing funcionando.  
**⏱️ Tempo estimado:** 4-5 horas  
**🔒 Risco:** Médio (UI complexa)

---

### **FASE 8: Bloqueio por Pagamento (Lógica de Negócio)**

**Objetivo:** Bloquear projetos se não pagou.

#### **8.1. Criar Método em `ProjectService`**
```
Método: ProjectService::checkAndUpdatePaymentStatus($projectId)

Lógica:
1. Busca faturas vinculadas ao projeto
2. Verifica se há faturas pendentes
3. Atualiza payment_status e is_blocked_by_payment

Seguro porque:
- Método novo
- Não roda automaticamente (precisa chamar)
```

#### **8.2. Integrar em Webhook Asaas (Opcional)**
```
Modificar: AsaasWebhookController::handlePayment()

Quando fatura é paga:
- Se tem project_id:
  - Chama ProjectService::checkAndUpdatePaymentStatus()

Seguro porque:
- Só roda se project_id existir
- Não afeta faturas sem projeto
```

**✅ Resultado Fase 8:** Bloqueio automático funcionando.  
**⏱️ Tempo estimado:** 2 horas  
**🔒 Risco:** Baixo (lógica isolada)

---

## 🚀 **Ordem Recomendada de Implementação**

### **Sprint 1 (Dia 1-2): Base Segura**
1. ✅ **FASE 1** - Catálogo de Serviços (CRUD completo)
2. ✅ **FASE 3** - Dashboard Básico (métricas simples)

**Resultado:** Sistema básico funcionando, sem quebrar nada.

### **Sprint 2 (Dia 3-4): Integração Opcional**
3. ✅ **FASE 2** - Vincular projeto a serviço (opcional)
4. ✅ **FASE 4** - Campos de briefing/pagamento
5. ✅ **FASE 5** - Vincular faturas a projetos

**Resultado:** Estrutura completa, tudo opcional.

### **Sprint 3 (Dia 5+): Funcionalidades Avançadas**
6. ✅ **FASE 6** - Auto-geração de tarefas
7. ✅ **FASE 8** - Bloqueio por pagamento
8. ✅ **FASE 7** - Briefing guiado

**Resultado:** Sistema completo funcionando.

---

## ⚠️ **Checklist de Segurança Antes de Cada Fase**

Antes de implementar qualquer fase:

- [ ] ✅ Backup do banco de dados
- [ ] ✅ Campos novos são opcionais (NULL)
- [ ] ✅ Não remove código existente
- [ ] ✅ Não altera estrutura de dados existente sem backward compatibility
- [ ] ✅ Testa em ambiente de desenvolvimento primeiro
- [ ] ✅ Verifica que queries existentes continuam funcionando
- [ ] ✅ Documenta mudanças

---

## 📝 **Exemplo de Migration Segura**

```php
<?php
/**
 * Migration: Adiciona service_id em projects (OPCIONAL)
 */
class AlterProjectsAddServiceId
{
    public function up(PDO $db): void
    {
        // Verifica se coluna já existe (segurança extra)
        $stmt = $db->query("SHOW COLUMNS FROM projects LIKE 'service_id'");
        if ($stmt->rowCount() === 0) {
            $db->exec("
                ALTER TABLE projects
                ADD COLUMN service_id INT UNSIGNED NULL AFTER tenant_id,
                ADD INDEX idx_service_id (service_id)
            ");
        }
    }

    public function down(PDO $db): void
    {
        // Remove apenas se existir (segurança)
        $stmt = $db->query("SHOW COLUMNS FROM projects LIKE 'service_id'");
        if ($stmt->rowCount() > 0) {
            $db->exec("
                ALTER TABLE projects
                DROP INDEX idx_service_id,
                DROP COLUMN service_id
            ");
        }
    }
}
```

---

## 🎯 **Próximo Passo Imediato**

**Começar pela FASE 1:**
1. Criar migration da tabela `services`
2. Criar `ServiceService`
3. Criar `ServicesController`
4. Criar views básicas
5. Testar CRUD completo

**Por quê começar aqui?**
- ✅ Não afeta nada existente
- ✅ Risco zero
- ✅ Entrega valor imediato (pode cadastrar serviços)
- ✅ Base para próximas fases

---

**Documento criado em:** 2025-01-07  
**Status:** Pronto para implementação

