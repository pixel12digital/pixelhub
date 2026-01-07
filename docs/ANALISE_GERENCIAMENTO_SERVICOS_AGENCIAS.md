# Análise: Gerenciamento de Serviços em Agências

**Data:** 2025-01-07  
**Objetivo:** Definir modelo simples e escalável para gerenciar múltiplos serviços por cliente, integrando financeiro, entregas e portal do cliente.

---

## 🎯 Problema

Cliente contrata múltiplos serviços distintos (ex: arte cartão visita + logo + criação de site). Precisamos:
- ✅ Catálogo de serviços pré-cadastrados
- ✅ **Tarefas pré-definidas por serviço** (cada serviço tem sequência única)
- ✅ **Briefing automatizado e guiado** (formulário conversacional tipo quiz)
- ✅ Vincular serviços ao cliente
- ✅ Gerar faturas automaticamente
- ✅ **Bloqueio por pagamento** (não paga = trava execução)
- ✅ Centralizar entregas (cliente acessa tudo no sistema)
- ✅ Portal do cliente com situação financeira, entregas e observações
- ✅ Escalar sem complexidade
- ✅ **Tudo centralizado no sistema** (não depender do WhatsApp)

---

## 📊 O Que Já Existe no Sistema

### 1. **Tabelas e Estrutura**

#### ✅ `billing_service_types` (Categorias de Serviços)
- Existe, mas usado apenas para **contratos recorrentes** (hospedagem, SaaS)
- Campos: `slug`, `name`, `is_active`, `sort_order`
- Limitação: Não tem preço, descrição, prazo padrão

#### ✅ `billing_contracts` (Contratos)
- Vincula cliente a serviço recorrente
- Campos: `tenant_id`, `service_type`, `amount`, `billing_mode`
- Limitação: Focado em serviços recorrentes (mensal/anual)

#### ✅ `projects` (Projetos)
- Pode representar serviços pontuais
- Campos: `name`, `tenant_id`, `type`, `status`, `due_date`
- Limitação: Não tem vínculo direto com financeiro/catálogo

#### ✅ `billing_invoices` (Faturas)
- Faturas individuais do Asaas
- Campos: `tenant_id`, `amount`, `status`, `due_date`
- Limitação: Não tem vínculo com serviço específico

#### ✅ `is_customer_visible` (Projetos)
- Campo em `projects` que indica se cliente pode ver
- Base para portal do cliente

---

## 🌐 Como Sistemas de Mercado Fazem

### 1. **Monday.com** ⭐
**Conceito:** Boards = Projetos, Items = Tarefas/Serviços
- Catálogo de templates (serviços pré-configurados)
- Cada item pode ter status, responsável, prazo
- Documentos/entregas anexados ao item
- Cliente vê board compartilhado

### 2. **ClickUp**
**Conceito:** Spaces = Clientes, Projects = Serviços
- Templates de projeto (ex: "Website Launch")
- Checklist padrão para cada tipo de serviço
- Documentos vinculados ao projeto
- Portal do cliente (views compartilhadas)

### 3. **Asana**
**Conceito:** Projects = Serviços
- Templates de projeto reutilizáveis
- Tarefas pré-definidas por template
- Portfolio para visão do cliente
- Entregas como anexos/comentários

### 4. **Notion** (Agencies)
**Conceito:** Database de Serviços + Projects
- Database de serviços com preços/prazos
- Ao criar projeto, seleciona serviços do catálogo
- Cada serviço gera conjunto de tarefas
- Páginas compartilhadas com cliente

### 5. **Sistemas Especializados (Tally, Dubsado)**
**Conceito:** Services Catalog + Contracts
- Catálogo de serviços com preços fixos/pacotes
- Proposta gera contrato automaticamente
- Contrato gera invoice
- Entregas vinculadas ao contrato
- Portal do cliente com tudo

---

## 🏢 Como Agências Reais Se Organizam

### Padrão Comum:
1. **Catálogo de Serviços** (planilha/documento)
   - Lista de serviços oferecidos
   - Preços/pacotes
   - Prazo médio

2. **Projeto = Serviço Contratado**
   - Cliente contrata "Criação de Site"
   - Cria-se projeto "Site - Cliente X"
   - Projeto tem tarefas padrão

3. **Financeiro Separado**
   - Orçamento/Proposta
   - Fatura gerada após aprovação
   - Controle manual ou sistema separado

4. **Entregas Via Email/WhatsApp**
   - Arquivos enviados manualmente
   - Sem rastreamento centralizado
   - Cliente não tem acesso autônomo

---

## 💡 Proposta: Modelo Unificado Simples

### Conceito: **Serviços = Templates de Projeto**

#### 1. **Catálogo de Serviços** (`services`)

```
services
├─ id
├─ name (ex: "Criação de Site", "Cartão de Visita")
├─ description
├─ price (opcional - pode variar)
├─ estimated_duration (dias)
├─ category (design, dev, marketing, etc)
├─ tasks_template (JSON) ← Sequência de tarefas pré-definidas
├─ briefing_template (JSON) ← Formulário/briefing guiado
├─ default_timeline (JSON) ← Prazos padrão por etapa
└─ is_active
```

**Uso:**
- Lista de serviços oferecidos
- Base para orçamentos
- **Templates completos**: ao criar projeto, aplica tarefas + briefing + prazos automaticamente

#### 2. **Projeto Vincula Serviço**

```
projects
├─ ... (campos existentes)
├─ service_id (FK opcional) ← NOVO
├─ contract_value (preço acordado)
├─ briefing_status (pendente, preenchido, aprovado) ← NOVO
├─ briefing_data (JSON) ← Respostas do briefing ← NOVO
├─ payment_status (pendente, parcial, pago) ← NOVO
└─ is_blocked_by_payment (boolean) ← NOVO (bloqueia se não pagou)
```

**Fluxo:**
1. Cliente contrata "Criação de Site" + "Logo"
2. Sistema cria 2 projetos vinculados aos serviços
3. **Aplica template de tarefas automaticamente** (sequência pré-definida)
4. **Envia briefing guiado para cliente** (formulário conversacional)
5. Gera faturas vinculadas aos projetos
6. **Bloqueia execução se pagamento pendente**

#### 3. **Entregas no Projeto**

```
project_deliverables (ou usar task_attachments)
├─ project_id
├─ file_path
├─ description
├─ delivered_at
└─ customer_visible
```

**Portal Cliente:**
- Vê projetos visíveis (`is_customer_visible = 1`)
- Acessa entregas de cada projeto
- Vê status e progresso

#### 4. **Financeiro Integrado com Bloqueio**

```
billing_invoices
├─ ... (campos existentes)
├─ project_id (FK opcional) ← NOVO
└─ service_id (FK opcional) ← NOVO

projects
└─ payment_status → Controla is_blocked_by_payment
```

**Fluxo:**
- Projeto aprovado → Gera fatura
- Fatura vinculada ao projeto/serviço
- **Cliente não pagou → `is_blocked_by_payment = true`**
- **Sistema bloqueia/oculta tarefas até pagamento**
- Cliente vê fatura no portal junto com entregas

#### 5. **Briefing Guiado** (`service_briefings` ou JSON em `projects`)

```
projects.briefing_data (JSON)
├─ questions: [
│   ├─ id
│   ├─ type (text, textarea, select, file, checkbox)
│   ├─ label (pergunta)
│   ├─ placeholder/dica
│   ├─ required
│   └─ order
│  ]
├─ responses: {
│   └─ question_id: resposta
│  }
└─ completed_at
```

**Exemplo para "Cartão de Visita":**
1. "Qual o nome da empresa?" (text)
2. "Descreva o negócio" (textarea)
3. "Envie referências visuais" (file upload)
4. "Preferências de cores?" (select: Claras, Escuras, Neutras)
5. "Informações para o verso?" (textarea)

**Interface:** Formulário conversacional (uma pergunta por vez, tipo quiz)

---

## 🎨 Estrutura Proposta (Simples)

### **Camada 1: Catálogo** (O que vendemos)
```
Serviços Cadastrados
├─ Criação de Site
├─ Logo + Identidade Visual
├─ Arte para Cartão de Visita
├─ Manutenção Mensal
└─ ...
```

### **Camada 2: Execução** (O que fazemos)
```
Cliente → Projeto → Tarefas → Entregas
```
- **Projeto** = Serviço em execução
- **Tarefas** = Etapas do serviço
- **Entregas** = Arquivos/deliverables

### **Camada 3: Financeiro** (O que cobramos)
```
Projeto → Fatura → Pagamento
```
- Fatura pode ser única ou parcelada
- Vinculada ao projeto/serviço

### **Camada 4: Portal** (O que cliente vê)
```
Cliente Acessa:
├─ Meus Projetos (visíveis)
├─ Entregas de cada projeto
├─ Faturas e situação financeira
└─ Histórico completo
```

---

## 🔄 Fluxo Completo Otimizado (Requisitos Críticos)

### **Cenário: Cliente contrata "Cartão de Visita" + "Criação de Site"**

```
┌─────────────────────────────────────────────────────────────┐
│ 1. CADASTRO DE CLIENTE                                      │
└─────────────────────────────────────────────────────────────┘
   → Sistema: /tenants/create ou /tenants/view
   → Cadastra cliente (PF/PJ)

┌─────────────────────────────────────────────────────────────┐
│ 2. ADIÇÃO DE SERVIÇO                                        │
└─────────────────────────────────────────────────────────────┘
   → Sistema: Seleciona serviço do catálogo
   → Seleciona: "Cartão de Visita" (R$ 500) + "Site" (R$ 2.500)
   → Ação: Cria 2 projetos vinculados aos serviços

┌─────────────────────────────────────────────────────────────┐
│ 3. GERAÇÃO AUTOMÁTICA DE TAREFAS                            │
└─────────────────────────────────────────────────────────────┘
   → Sistema aplica tasks_template do serviço automaticamente:
   
   Cartão de Visita:
   ├─ 1. Briefing do Cliente (status: pendente)
   ├─ 2. Pesquisa de Referências
   ├─ 3. Proposta de Layout
   ├─ 4. Aprovação do Cliente
   ├─ 5. Arte Final (Frente)
   ├─ 6. Arte Final (Verso)
   ├─ 7. Aprovação Final
   └─ 8. Entrega (PDF + Arquivos Fonte)
   
   Criação de Site:
   ├─ 1. Briefing do Cliente (status: pendente)
   ├─ 2. Pesquisa de Referências
   ├─ 3. Wireframe / Protótipo
   ├─ 4. Design Visual
   ├─ 5. Desenvolvimento Frontend
   ├─ 6. Desenvolvimento Backend
   ├─ 7. Testes e Ajustes
   ├─ 8. Aprovação do Cliente
   ├─ 9. Deploy / Publicação
   └─ 10. Entrega e Documentação

┌─────────────────────────────────────────────────────────────┐
│ 4. GERAÇÃO AUTOMÁTICA DE PRAZOS                             │
└─────────────────────────────────────────────────────────────┘
   → Sistema aplica default_timeline do serviço:
   → Cada tarefa recebe prazo calculado automaticamente
   → Baseado em estimated_duration do serviço

┌─────────────────────────────────────────────────────────────┐
│ 5. BRIEFING GUIADO PARA CLIENTE                             │
└─────────────────────────────────────────────────────────────┘
   → Sistema: Cliente recebe notificação (email/sistema)
   → Cliente acessa: /client-portal/projects/[id]/briefing
   → Formulário conversacional (quiz guiado):
   
   Exemplo "Cartão de Visita":
   ┌─────────────────────────────────┐
   │ 🎨 Briefing - Cartão de Visita  │
   ├─────────────────────────────────┤
   │                                 │
   │ 1/5 - Qual o nome da empresa?  │
   │ ┌─────────────────────────┐     │
   │ │ [Digite aqui...]        │     │
   │ └─────────────────────────┘     │
   │                                 │
   │        [Próxima Pergunta →]     │
   └─────────────────────────────────┘
   
   → Cliente preenche passo a passo (não cansativo)
   → Sistema salva respostas em projects.briefing_data
   → Briefing completo → Projeto desbloqueia para execução

┌─────────────────────────────────────────────────────────────┐
│ 6. FINANCEIRO INTEGRADO + BLOQUEIO                          │
└─────────────────────────────────────────────────────────────┘
   → Sistema gera faturas:
   - Fatura 1: R$ 500 (Cartão de Visita) - Projeto vinculado
   - Fatura 2: R$ 2.500 (Site) - Projeto vinculado
   
   → REGRA CRÍTICA: Bloqueio por Pagamento
   ├─ Fatura pendente → projects.payment_status = 'pendente'
   ├─ Sistema define: projects.is_blocked_by_payment = true
   ├─ Tarefas ficam ocultas/bloqueadas para equipe
   ├─ Cliente vê aviso: "Aguardando pagamento para iniciar"
   ├─ Após pagamento → Sistema atualiza automaticamente
   └─ projects.is_blocked_by_payment = false → Desbloqueia

┌─────────────────────────────────────────────────────────────┐
│ 7. EXECUÇÃO E ANDAMENTO                                     │
└─────────────────────────────────────────────────────────────┘
   → Equipe trabalha nas tarefas (desbloqueadas após pagamento)
   → Cliente acompanha progresso no portal
   → Sistema atualiza status automaticamente

┌─────────────────────────────────────────────────────────────┐
│ 8. APROVAÇÃO E ENTREGA                                      │
└─────────────────────────────────────────────────────────────┘
   → Cliente aprova etapas no portal (sem WhatsApp)
   → Equipe faz upload de entregas no projeto
   → Cliente acessa e baixa arquivos diretamente

┌─────────────────────────────────────────────────────────────┐
│ 9. PORTAL DO CLIENTE UNIFICADO                              │
└─────────────────────────────────────────────────────────────┘
   Cliente acessa /client-portal e vê:
   ├─ 📋 Meus Projetos
   │   ├─ Cartão de Visita (60% completo)
   │   └─ Site (30% completo)
   ├─ 📎 Entregas
   │   ├─ Cartão - Versão 1.pdf
   │   └─ Logo - PNG + AI.zip
   ├─ 💰 Financeiro
   │   ├─ Fatura #123 - R$ 500 (Pago ✅)
   │   └─ Fatura #124 - R$ 2.500 (Pendente ⚠️)
   ├─ 💬 Observações / Comentários
   │   └─ Cliente pode adicionar feedback direto no projeto
   └─ 📊 Planejamento
       └─ Timeline visual de cada projeto
```

---

## 📋 Implementação Mínima Viável (Priorizada)

### **Fase 1: Catálogo de Serviços com Templates**
```
Tabela: services
├─ id
├─ name
├─ description
├─ category
├─ tasks_template (JSON) ← Sequência de tarefas
├─ briefing_template (JSON) ← Formulário guiado
├─ default_timeline (JSON) ← Prazos padrão
└─ is_active

Tela: /services (CRUD + Editor de templates)
```

### **Fase 2: Projeto Vincula Serviço + Auto-Geração**
```
Adicionar em projects:
├─ service_id (FK)
├─ briefing_status (pendente, preenchido, aprovado)
├─ briefing_data (JSON)
├─ payment_status (pendente, parcial, pago)
└─ is_blocked_by_payment (boolean)

Ao criar projeto com serviço:
→ Aplica tasks_template automaticamente
→ Cria tarefas com prazos do default_timeline
→ Gera briefing_template para cliente
```

### **Fase 3: Briefing Guiado (Formulário Conversacional)**
```
Tela: /client-portal/projects/[id]/briefing

Interface:
- Uma pergunta por vez (tipo quiz)
- Progresso visual (1/5, 2/5...)
- Upload de arquivos (referências)
- Salvar rascunho
- Finalizar briefing

Salvar em: projects.briefing_data (JSON)
```

### **Fase 4: Bloqueio por Pagamento**
```
Lógica:
- Verificar billing_invoices vinculadas ao project_id
- Se status != 'paid' → is_blocked_by_payment = true
- Tarefas bloqueadas não aparecem no Kanban
- Portal cliente mostra: "Aguardando pagamento"

Integração:
- Webhook Asaas atualiza automaticamente
- Ou verificação periódica (cron)
```

### **Fase 5: Entregas e Portal do Cliente**
```
Tabela: project_deliverables (ou usar task_attachments)
├─ project_id
├─ file_path
├─ description
├─ delivered_at
└─ customer_visible

Portal: /client-portal
├─ Meus Projetos (com progresso)
├─ Briefings pendentes
├─ Entregas (download)
├─ Financeiro (faturas + status)
└─ Observações (comentários no projeto)
```

---

## 🎯 Modelo Simplificado Recomendado

### **Para Escalar Rápido:**

1. **Serviços = Templates de Projeto**
   - Catálogo simples (nome, descrição, categoria)
   - Ao criar projeto, seleciona serviço
   - Aplica checklist/tarefas padrão automaticamente

2. **Projeto = Unidade de Execução**
   - Um projeto = um serviço
   - Projeto tem entregas, tarefas, faturas
   - Cliente vê projeto no portal

3. **Financeiro Integrado**
   - Fatura pode ser vinculada a projeto
   - Cliente vê fatura junto com projeto no portal
   - Tudo centralizado

4. **Portal Unificado**
   - Cliente acessa `/client-portal` (ou `/tenants/view` como cliente)
   - Vê tudo: projetos, entregas, faturas
   - Baixa arquivos, acompanha progresso

---

## ✅ Checklist Completo (Requisitos Críticos)

### **Catálogo e Templates**
- [ ] Tabela `services` com `tasks_template`, `briefing_template`, `default_timeline`
- [ ] Editor de templates (interface para criar sequência de tarefas)
- [ ] Editor de briefing (criar formulário guiado)

### **Integração Projeto-Serviço**
- [ ] Campo `service_id` em `projects`
- [ ] Auto-geração de tarefas ao criar projeto com serviço
- [ ] Auto-cálculo de prazos baseado em `default_timeline`
- [ ] Campo `project_id` em `billing_invoices`

### **Briefing Guiado**
- [ ] Campo `briefing_data` (JSON) em `projects`
- [ ] Campo `briefing_status` em `projects`
- [ ] Interface de briefing conversacional (`/client-portal/projects/[id]/briefing`)
- [ ] Notificação para cliente quando briefing estiver disponível

### **Bloqueio Financeiro** 🔒
- [ ] Campos `payment_status` e `is_blocked_by_payment` em `projects`
- [ ] Lógica de bloqueio (verifica faturas vinculadas)
- [ ] Tarefas bloqueadas não aparecem no Kanban
- [ ] Integração com webhook Asaas para atualização automática
- [ ] Aviso visual no portal do cliente

### **Entregas e Portal**
- [ ] Sistema de entregas (`project_deliverables` ou `task_attachments`)
- [ ] Portal do cliente (`/client-portal`)
- [ ] Visualização de projetos, entregas, financeiro
- [ ] Sistema de observações/comentários no projeto
- [ ] Permissões: cliente só vê projetos `is_customer_visible = 1`

### **Centralização**
- [ ] Tudo acessível via portal (sem depender WhatsApp)
- [ ] Notificações no sistema (email ou in-app)
- [ ] Histórico completo de interações

---

## 🚀 Vantagens do Modelo

1. **Simples**: Não cria complexidade desnecessária
2. **Escalável**: Funciona com 10 ou 1000 clientes
3. **Integrado**: Tudo no mesmo lugar
4. **Cliente autônomo**: Acessa sem depender de WhatsApp/Email
5. **Rastreável**: Histórico completo de entregas e pagamentos

---

---

## 🎯 Principais Diferenciais da Proposta

### 1. **Automação Total**
- Serviço cadastrado → Tarefas + Prazos + Briefing gerados automaticamente
- Não precisa configurar tudo manualmente a cada projeto

### 2. **Briefing Inteligente e Conversacional**
- Formulário guiado tipo quiz (não cansativo)
- Uma pergunta por vez
- Cliente preenche no próprio ritmo
- Dados estruturados (JSON) para uso automático

### 3. **Bloqueio Financeiro Integrado** 🔒
- **Regra crítica**: Não paga = Não trabalha
- Sistema bloqueia automaticamente até pagamento
- Integrado com Asaas (atualização em tempo real)
- Cliente vê status claramente no portal

### 4. **Tudo Centralizado**
- Não depende do WhatsApp
- Cliente acessa tudo no portal
- Histórico completo de interações
- Entregas, financeiro, observações tudo junto

### 5. **Escalável**
- Um serviço = uma sequência única
- Reutiliza templates infinitamente
- Mesma rotina para cada tipo de serviço
- Otimiza processo repetitivo

---

## 🏗️ Arquitetura Técnica Sugerida

### **Estrutura de Dados**

```sql
-- Serviços com templates
CREATE TABLE services (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    description TEXT,
    tasks_template JSON,        -- [{title, order, estimated_days}]
    briefing_template JSON,     -- [{question, type, required, order}]
    default_timeline JSON,      -- {start_offset, duration_per_task}
    is_active BOOLEAN
);

-- Projetos vinculados a serviços
ALTER TABLE projects ADD COLUMN service_id INT;
ALTER TABLE projects ADD COLUMN briefing_status VARCHAR(20);
ALTER TABLE projects ADD COLUMN briefing_data JSON;
ALTER TABLE projects ADD COLUMN payment_status VARCHAR(20);
ALTER TABLE projects ADD COLUMN is_blocked_by_payment BOOLEAN DEFAULT 0;

-- Faturas vinculadas a projetos
ALTER TABLE billing_invoices ADD COLUMN project_id INT;
```

### **Fluxo de Código**

```
1. ServiceService::createProjectFromService($serviceId, $tenantId)
   → Cria projeto
   → Aplica tasks_template (gera tarefas)
   → Calcula prazos (default_timeline)
   → Gera briefing_template

2. BriefingController::show($projectId)
   → Renderiza formulário conversacional
   → Salva respostas em projects.briefing_data

3. PaymentService::checkProjectPayment($projectId)
   → Verifica billing_invoices vinculadas
   → Atualiza is_blocked_by_payment
   → Bloqueia/desbloqueia tarefas

4. TaskService::getTasksForProject($projectId)
   → Filtra tarefas bloqueadas se is_blocked_by_payment = true
```

---

## 📊 Dashboard e Métricas para Gestão

### **Visão Geral: KPIs Críticos**

O dashboard deve fornecer visão rápida e acionável do estado da operação. Métricas devem ser **simples, relevantes e acionáveis**.

---

### 🎯 **Métricas Principais (Cards Superiores)**

#### **1. Operacional**
```
┌─────────────────────────────────────┐
│ 📋 PROJETOS EM ANDAMENTO            │
│ 12 projetos                         │
│ ↑ +3 este mês                       │
└─────────────────────────────────────┘

Cálculo:
- COUNT(*) FROM projects WHERE status = 'ativo'
- Comparar com mês anterior
- Cor: Verde (saudável), Amarelo (atenção), Vermelho (sobrecarga)
```

#### **2. Financeiro - Receita Recebida**
```
┌─────────────────────────────────────┐
│ 💰 RECEITA DO MÊS (RECEBIDA)        │
│ R$ 45.230,00                        │
│ ↑ +15% vs mês anterior              │
└─────────────────────────────────────┘

Cálculo:
- SUM(amount) FROM billing_invoices 
  WHERE status = 'paid' 
  AND MONTH(paid_at) = MONTH(NOW())
  AND YEAR(paid_at) = YEAR(NOW())
- Comparar com mês anterior
```

#### **3. Financeiro - Receita Pendente**
```
┌─────────────────────────────────────┐
│ ⚠️ A RECEBER                        │
│ R$ 18.500,00                        │
│ 8 faturas pendentes                 │
└─────────────────────────────────────┘

Cálculo:
- SUM(amount) FROM billing_invoices 
  WHERE status IN ('pending', 'overdue')
- COUNT(*) de faturas pendentes
```

#### **4. Operacional - Projetos Bloqueados**
```
┌─────────────────────────────────────┐
│ 🔒 BLOQUEADOS POR PAGAMENTO         │
│ 3 projetos                          │
│ ⚠️ Requer atenção                   │
└─────────────────────────────────────┘

Cálculo:
- COUNT(*) FROM projects 
  WHERE is_blocked_by_payment = 1
- Cor: Vermelho se > 0
```

#### **5. Suporte - Tickets de Suporte**
```
┌─────────────────────────────────────┐
│ 🎫 TICKETS DE SUPORTE               │
│ 8 abertos | 5 resolvidos (hoje)     │
│ ⏱️ Tempo médio: 2h 30min            │
└─────────────────────────────────────┘

Cálculo:
- Tickets abertos: COUNT(*) WHERE status IN ('aberto', 'em_atendimento')
- Resolvidos hoje: COUNT(*) WHERE status = 'resolvido' AND DATE(resolved_at) = CURDATE()
- Tempo médio: AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at))
```

#### **6. Serviços - Total Prestados**
```
┌─────────────────────────────────────┐
│ 📦 SERVIÇOS PRESTADOS (MÊS)         │
│ 24 serviços                         │
│ ↑ +6 vs mês anterior                │
└─────────────────────────────────────┘

Cálculo:
- COUNT(*) FROM projects 
  WHERE status = 'concluido'
  AND MONTH(delivered_at) = MONTH(NOW())
  AND YEAR(delivered_at) = YEAR(NOW())
- Comparar com mês anterior
```

---

### 📈 **Métricas Secundárias (Tabelas/Gráficos)**

#### **A. Projetos por Status**
```
┌─────────────┬──────────┬───────────┐
│ Status      │ Qtd      │ %         │
├─────────────┼──────────┼───────────┤
│ Em Andamento│ 12       │ 60%       │
│ Aguardando  │ 3        │ 15%       │
│ Briefing    │ 2        │ 10%       │
│ Finalizados │ 3        │ 15%       │
└─────────────┴──────────┴───────────┘

Fonte: projects.status
```

#### **B. Receita por Tipo de Serviço (Mês Atual)**
```
┌─────────────────────┬──────────────┬──────────┐
│ Serviço             │ Receita      │ % Total  │
├─────────────────────┼──────────────┼──────────┤
│ Criação de Site     │ R$ 25.000    │ 55%      │
│ Logo + Identidade   │ R$ 12.000    │ 27%      │
│ Cartão de Visita    │ R$ 5.000     │ 11%      │
│ Manutenção          │ R$ 3.230     │ 7%       │
└─────────────────────┴──────────────┴──────────┘

Cálculo:
- JOIN projects → services → billing_invoices
- GROUP BY service_id
- Apenas faturas pagas do mês atual
```

#### **C. Projetos com Briefing Pendente**
```
┌─────────────────────┬──────────────┐
│ Cliente             │ Projeto      │
├─────────────────────┼──────────────┤
│ Empresa ABC         │ Site         │
│ Cliente XYZ         │ Logo         │
└─────────────────────┴──────────────┘

Cálculo:
- SELECT p.*, t.name as client_name
  FROM projects p
  JOIN tenants t ON p.tenant_id = t.id
  WHERE p.briefing_status = 'pendente'
  ORDER BY p.created_at ASC
```

#### **D. Tempo Médio de Execução por Serviço**
```
┌─────────────────────┬──────────────┬──────────┐
│ Serviço             │ Tempo Médio  │ Meta     │
├─────────────────────┼──────────────┼──────────┤
│ Cartão de Visita    │ 8 dias       │ 7 dias   │
│ Logo                │ 12 dias      │ 10 dias  │
│ Site                │ 25 dias      │ 20 dias  │
└─────────────────────┴──────────────┴──────────┘

Cálculo:
- AVG(DATEDIFF(delivered_at, created_at))
- GROUP BY service_id
- Apenas projetos finalizados dos últimos 3 meses
- Comparar com estimated_duration do serviço
```

#### **E. Clientes com Pagamento em Atraso**
```
┌─────────────────────┬──────────────┬──────────┐
│ Cliente             │ Valor Atraso │ Dias     │
├─────────────────────┼──────────────┼──────────┤
│ Empresa ABC         │ R$ 2.500     │ 15 dias  │
│ Cliente XYZ         │ R$ 1.200     │ 8 dias   │
└─────────────────────┴──────────────┴──────────┘

Cálculo:
- JOIN billing_invoices → projects → tenants
- WHERE status = 'overdue'
- ORDER BY DATEDIFF(NOW(), due_date) DESC
```

#### **F. Taxa de Aprovação de Entregas**
```
┌─────────────────────┬──────────────┬──────────┐
│ Serviço             │ Aprovação 1ª │ Revisões │
├─────────────────────┼──────────────┼──────────┤
│ Cartão de Visita    │ 85%          │ 1.2      │
│ Logo                │ 70%          │ 1.5      │
│ Site                │ 60%          │ 2.1      │
└─────────────────────┴──────────────┴──────────┘

Cálculo:
- Contar tarefas "Aprovação" que foram concluídas
- Comparar com tarefas que precisaram de revisão
- Baseado em histórico de tarefas
```

#### **G. Tickets de Suporte por Status**
```
┌─────────────────────┬──────────────┬──────────┐
│ Status              │ Quantidade   │ %        │
├─────────────────────┼──────────────┼──────────┤
│ Abertos             │ 8            │ 40%      │
│ Em Atendimento      │ 5            │ 25%      │
│ Aguardando Cliente  │ 4            │ 20%      │
│ Resolvidos          │ 3            │ 15%      │
└─────────────────────┴──────────────┴──────────┘

Cálculo:
- COUNT(*) FROM tickets GROUP BY status
- Apenas tickets do mês atual ou últimos 30 dias
```

#### **H. Tempo Médio de Resolução de Tickets**
```
┌─────────────────────┬──────────────┬──────────┐
│ Categoria           │ Tempo Médio  │ Meta     │
├─────────────────────┼──────────────┼──────────┤
│ Urgente             │ 1h 15min     │ 2h       │
│ Normal              │ 3h 45min     │ 8h       │
│ Baixa Prioridade    │ 12h 30min    │ 24h      │
└─────────────────────┴──────────────┴──────────┘

Cálculo:
- AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at))
- GROUP BY priority
- Apenas tickets resolvidos dos últimos 30 dias
```

#### **I. Serviços Prestados por Tipo**
```
┌─────────────────────┬──────────────┬──────────┐
│ Tipo de Serviço     │ Quantidade   │ % Total  │
├─────────────────────┼──────────────┼──────────┤
│ Criação de Site     │ 8            │ 33%      │
│ Logo + Identidade   │ 6            │ 25%      │
│ Cartão de Visita    │ 5            │ 21%      │
│ Manutenção          │ 3            │ 13%      │
│ Outros              │ 2            │ 8%       │
└─────────────────────┴──────────────┴──────────┘

Cálculo:
- JOIN projects → services
- COUNT(*) WHERE status = 'concluido'
- GROUP BY service_id
- Período: mês atual ou configurável
```

#### **J. Taxa de Conversão: Briefing → Projeto → Entrega**
```
┌─────────────────────┬──────────────┬──────────┐
│ Etapa               │ Quantidade   │ Taxa     │
├─────────────────────┼──────────────┼──────────┤
│ Briefings Enviados  │ 30           │ 100%     │
│ Briefings Preenchidos│ 28          │ 93%      │
│ Projetos Iniciados  │ 26           │ 87%      │
│ Projetos Entregues  │ 24           │ 80%      │
└─────────────────────┴──────────────┴──────────┘

Cálculo:
- Briefings enviados: COUNT(*) WHERE briefing_status != NULL
- Briefings preenchidos: COUNT(*) WHERE briefing_status = 'preenchido'
- Projetos iniciados: COUNT(*) WHERE status = 'ativo'
- Projetos entregues: COUNT(*) WHERE status = 'concluido'
```

#### **K. Projetos por Fase do Fluxo**
```
┌─────────────────────┬──────────────┬──────────┐
│ Fase                │ Quantidade   │ %        │
├─────────────────────┼──────────────┼──────────┤
│ Aguardando Briefing │ 3            │ 12%      │
│ Aguardando Pagamento│ 2            │ 8%       │
│ Em Execução         │ 12           │ 48%      │
│ Aguardando Aprovação│ 5            │ 20%      │
│ Concluídos          │ 3            │ 12%      │
└─────────────────────┴──────────────┴──────────┘

Cálculo:
- Lógica condicional baseada em:
  - briefing_status = 'pendente' → "Aguardando Briefing"
  - is_blocked_by_payment = 1 → "Aguardando Pagamento"
  - status = 'ativo' AND briefing_status = 'preenchido' → "Em Execução"
  - Tem tarefas em "aguardando_cliente" → "Aguardando Aprovação"
  - status = 'concluido' → "Concluídos"
```

---

### 📅 **Métricas Temporais (Gráficos)**

#### **1. Receita Recebida - Últimos 6 Meses**
```
Gráfico de linha ou colunas:
- Eixo X: Meses
- Eixo Y: Valor (R$)
- Comparar receita mensal
- Mostrar tendência (↑ ou ↓)
```

#### **2. Projetos Finalizados vs Iniciados - Últimos 3 Meses**
```
Gráfico de colunas agrupadas:
- Mês
- Projetos Iniciados (azul)
- Projetos Finalizados (verde)
- Saldo líquido (iniciados - finalizados)
```

#### **3. Receita por Status de Fatura - Mês Atual**
```
Gráfico de pizza ou rosca:
- Pago (verde)
- Pendente (amarelo)
- Atrasado (vermelho)
```

---

### 🎯 **Alertas e Ações Rápidas**

#### **Alertas Críticos (Topo do Dashboard)**
```
⚠️ ALERTAS
├─ 3 projetos bloqueados por pagamento
├─ 5 briefings pendentes há mais de 3 dias
├─ 2 clientes com pagamento em atraso
└─ 1 projeto atrasado no prazo
```

#### **Ações Rápidas (Botões)**
```
┌─────────────────────────────────────┐
│ [Criar Novo Projeto]                │
│ [Ver Projetos Bloqueados]           │
│ [Briefings Pendentes]               │
│ [Faturas em Atraso]                 │
└─────────────────────────────────────┘
```

---

### 🔍 **Filtros e Períodos**

#### **Filtros Disponíveis**
- Período: Hoje | Esta Semana | Este Mês | Últimos 3 Meses | Ano Todo
- Tipo de Serviço: Todos | Site | Logo | Cartão | etc.
- Cliente: Todos | Selecionar cliente específico
- Status: Todos | Ativos | Bloqueados | Finalizados

---

### 🔄 **Visão Analítica do Fluxo Completo**

#### **Fluxo End-to-End: Da Contratação à Entrega**

```
┌─────────────────────────────────────────────────────────────────┐
│ VISÃO ANALÍTICA DO FUNNEL - ÚLTIMOS 30 DIAS                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ 1. NOVOS CLIENTES                                              │
│    30 clientes cadastrados                                     │
│    ↓ (100%)                                                     │
│                                                                 │
│ 2. SERVIÇOS CONTRATADOS                                        │
│    45 serviços contratados (1,5 por cliente)                   │
│    ↓ (100%)                                                     │
│                                                                 │
│ 3. BRIEFINGS ENVIADOS                                          │
│    42 briefings enviados (93% dos contratados)                 │
│    ↓ (93%)                                                      │
│                                                                 │
│ 4. BRIEFINGS PREENCHIDOS                                       │
│    38 briefings preenchidos (84% dos enviados)                 │
│    ↓ (84%)                                                      │
│                                                                 │
│ 5. PAGAMENTOS RECEBIDOS                                        │
│    35 projetos pagos (78% dos preenchidos)                     │
│    ↓ (78%)                                                      │
│                                                                 │
│ 6. PROJETOS EM EXECUÇÃO                                        │
│    30 projetos ativos (86% dos pagos)                          │
│    ↓ (86%)                                                      │
│                                                                 │
│ 7. PROJETOS CONCLUÍDOS                                         │
│    24 projetos entregues (69% dos pagos)                       │
│    ↓ (69%)                                                      │
│                                                                 │
│ 8. APROVAÇÃO DO CLIENTE                                        │
│    22 projetos aprovados (92% dos entregues)                   │
│    ↓ (92%)                                                      │
│                                                                 │
│ RECEITA GERADA: R$ 67.500,00                                   │
│ TAXA DE CONVERSÃO GERAL: 69%                                   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

Gargalos Identificados:
⚠️ 7 briefings não preenchidos (16%)
⚠️ 5 projetos aguardando pagamento (14%)
⚠️ 2 projetos não aprovados (8%)
```

#### **Gráfico de Funil (Funnel Chart)**
```
Visualização gráfica das etapas:
┌─────────────────────────────────────────┐
│ Clientes Cadastrados    ████████████ 30 │
│ Serviços Contratados    ████████████ 45 │
│ Briefings Enviados      ██████████░░ 42 │
│ Briefings Preenchidos   █████████░░░ 38 │
│ Pagamentos Recebidos    ████████░░░░ 35 │
│ Em Execução             ███████░░░░░ 30 │
│ Concluídos              ██████░░░░░░ 24 │
│ Aprovados               █████░░░░░░░ 22 │
└─────────────────────────────────────────┘
```

#### **Tempo Médio em Cada Fase**
```
┌──────────────────────────────┬──────────────┬──────────┐
│ Fase                         │ Tempo Médio  │ Meta     │
├──────────────────────────────┼──────────────┼──────────┤
│ Briefing Pendente            │ 2,5 dias     │ 1 dia    │
│ Aguardando Pagamento         │ 3 dias       │ 1 dia    │
│ Execução                     │ 18 dias      │ 15 dias  │
│ Aguardando Aprovação         │ 2 dias       │ 1 dia    │
│ Tempo Total (Médio)          │ 25,5 dias    │ 18 dias  │
└──────────────────────────────┴──────────────┴──────────┘

Cálculo:
- Briefing: AVG(DATEDIFF(briefing_completed_at, created_at))
- Pagamento: AVG(DATEDIFF(payment_received_at, briefing_completed_at))
- Execução: AVG(DATEDIFF(delivered_at, payment_received_at))
- Aprovação: AVG(DATEDIFF(approved_at, delivered_at))
```

#### **Análise de Gargalos**
```
Gargalos Mais Comuns (Top 5):

1. ⚠️ Briefing não preenchido (16% dos projetos)
   → Ação: Enviar lembrete após 24h, 48h, 72h

2. ⚠️ Aguardando pagamento (14% dos projetos)
   → Ação: Cobrança automatizada via WhatsApp

3. ⚠️ Tarefas aguardando cliente (20% dos projetos)
   → Ação: Notificação no portal + email

4. ⚠️ Atraso na execução (tempo médio acima da meta)
   → Ação: Revisar prazos ou capacidade da equipe

5. ⚠️ Baixa taxa de aprovação (8% precisam de revisão)
   → Ação: Melhorar briefing ou processo de qualidade
```

#### **Receita por Fase do Funil**
```
┌──────────────────────────────┬──────────────┬──────────┐
│ Fase                         │ Receita      │ %        │
├──────────────────────────────┼──────────────┼──────────┤
│ Contratados                  │ R$ 90.000    │ 100%     │
│ Briefings Preenchidos        │ R$ 84.000    │ 93%      │
│ Pagos                        │ R$ 67.500    │ 75%      │
│ Em Execução                  │ R$ 45.000    │ 50%      │
│ Concluídos                   │ R$ 45.000    │ 50%      │
│ Aprovados/Finalizados        │ R$ 41.250    │ 46%      │
└──────────────────────────────┴──────────────┴──────────┘

Observação: Receita "realizada" apenas quando projeto é aprovado/finalizado
```

---

### 💡 **Métricas Adicionais (Opcionais - Fase 2)**

#### **1. Eficiência Operacional**
- Taxa de utilização da equipe
- Projetos por membro da equipe
- Horas trabalhadas vs horas estimadas

#### **2. Margem por Tipo de Serviço**
- Custo vs Receita
- Margem de lucro por serviço
- Serviços mais rentáveis

#### **3. Saúde do Pipeline**
- Projetos no pipeline (por status)
- Projeção de receita (baseado em projetos aprovados)
- Conversão: Briefings → Projetos → Finalizados

#### **4. Satisfação do Cliente**
- Tempo médio de resposta a solicitações
- Taxa de retenção de clientes
- Número de reclamações/ajustes

---

### 🏗️ **Implementação Técnica**

#### **Service: DashboardMetricsService**

```php
class DashboardMetricsService
{
    // Métricas principais
    public static function getActiveProjectsCount(): int
    public static function getMonthlyRevenue(): float
    public static function getPendingRevenue(): float
    public static function getBlockedProjectsCount(): int
    
    // Métricas secundárias
    public static function getProjectsByStatus(): array
    public static function getRevenueByService(int $month, int $year): array
    public static function getPendingBriefings(): array
    public static function getAverageExecutionTimeByService(): array
    public static function getClientsWithOverduePayments(): array
    
    // Métricas de suporte
    public static function getSupportTicketsCount(): array // {open, in_progress, resolved_today, avg_time}
    public static function getTicketsByStatus(): array
    public static function getAverageTicketResolutionTime(): array // Por prioridade
    
    // Métricas de serviços prestados
    public static function getServicesDeliveredCount(int $month, int $year): int
    public static function getServicesDeliveredByType(int $month, int $year): array
    
    // Visão analítica do fluxo
    public static function getFunnelAnalytics(int $days = 30): array // Funnel completo
    public static function getAverageTimePerPhase(): array
    public static function getBottlenecksAnalysis(): array
    public static function getConversionRates(): array
    
    // Métricas temporais
    public static function getMonthlyRevenueLast6Months(): array
    public static function getProjectsStartedVsFinished(int $months = 3): array
    
    // Alertas
    public static function getCriticalAlerts(): array
}
```

#### **Queries SQL Exemplo**

```sql
-- Receita do mês (recebida)
SELECT 
    COALESCE(SUM(amount), 0) as total
FROM billing_invoices
WHERE status = 'paid'
  AND MONTH(paid_at) = MONTH(NOW())
  AND YEAR(paid_at) = YEAR(NOW());

-- Projetos bloqueados por pagamento
SELECT COUNT(*) as total
FROM projects
WHERE is_blocked_by_payment = 1
  AND status = 'ativo';

-- Receita por serviço (mês atual)
SELECT 
    s.name as service_name,
    COALESCE(SUM(bi.amount), 0) as revenue,
    COUNT(DISTINCT p.id) as projects_count
FROM services s
LEFT JOIN projects p ON s.id = p.service_id
LEFT JOIN billing_invoices bi ON p.id = bi.project_id
    AND bi.status = 'paid'
    AND MONTH(bi.paid_at) = MONTH(NOW())
    AND YEAR(bi.paid_at) = YEAR(NOW())
GROUP BY s.id, s.name
ORDER BY revenue DESC;

-- Tempo médio de execução por serviço
SELECT 
    s.name as service_name,
    AVG(DATEDIFF(
        (SELECT MAX(updated_at) FROM tasks WHERE project_id = p.id AND status = 'concluida'),
        p.created_at
    )) as avg_days
FROM services s
JOIN projects p ON s.id = p.service_id
WHERE p.status = 'concluido'
  AND p.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
GROUP BY s.id, s.name;

-- Tickets de suporte - resumo
SELECT 
    COUNT(CASE WHEN status IN ('aberto', 'em_atendimento') THEN 1 END) as open_count,
    COUNT(CASE WHEN status = 'resolvido' AND DATE(resolved_at) = CURDATE() THEN 1 END) as resolved_today,
    AVG(CASE WHEN status = 'resolvido' 
        THEN TIMESTAMPDIFF(MINUTE, created_at, resolved_at) 
        END) as avg_resolution_minutes
FROM tickets
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Tickets por status
SELECT 
    status,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM tickets), 2) as percentage
FROM tickets
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY status;

-- Serviços prestados no mês
SELECT 
    COUNT(*) as total_services,
    s.name as service_name,
    COUNT(p.id) as quantity,
    ROUND(COUNT(p.id) * 100.0 / (SELECT COUNT(*) FROM projects WHERE status = 'concluido' 
        AND MONTH(delivered_at) = MONTH(NOW())), 2) as percentage
FROM services s
LEFT JOIN projects p ON s.id = p.service_id
    AND p.status = 'concluido'
    AND MONTH(p.delivered_at) = MONTH(NOW())
    AND YEAR(p.delivered_at) = YEAR(NOW())
GROUP BY s.id, s.name
ORDER BY quantity DESC;

-- Funil analítico (últimos 30 dias)
SELECT 
    (SELECT COUNT(*) FROM tenants WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_clients,
    (SELECT COUNT(*) FROM projects WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as services_contracted,
    (SELECT COUNT(*) FROM projects WHERE briefing_status != NULL 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as briefings_sent,
    (SELECT COUNT(*) FROM projects WHERE briefing_status = 'preenchido' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as briefings_completed,
    (SELECT COUNT(*) FROM projects WHERE payment_status = 'pago' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as payments_received,
    (SELECT COUNT(*) FROM projects WHERE status = 'ativo' 
        AND payment_status = 'pago' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as in_execution,
    (SELECT COUNT(*) FROM projects WHERE status = 'concluido' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as completed,
    (SELECT COALESCE(SUM(bi.amount), 0) FROM billing_invoices bi
        JOIN projects p ON bi.project_id = p.id
        WHERE bi.status = 'paid' 
        AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as revenue_generated;

-- Tempo médio por fase
SELECT 
    AVG(DATEDIFF(briefing_completed_at, created_at)) as avg_briefing_days,
    AVG(DATEDIFF(payment_received_at, briefing_completed_at)) as avg_payment_wait_days,
    AVG(DATEDIFF(delivered_at, payment_received_at)) as avg_execution_days,
    AVG(DATEDIFF(approved_at, delivered_at)) as avg_approval_days
FROM projects
WHERE status = 'concluido'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH);
```

#### **Controller: DashboardController**

```php
public function index(): void
{
    Auth::requireInternal();
    
    $metrics = [
        'main' => [
            'active_projects' => DashboardMetricsService::getActiveProjectsCount(),
            'monthly_revenue' => DashboardMetricsService::getMonthlyRevenue(),
            'pending_revenue' => DashboardMetricsService::getPendingRevenue(),
            'blocked_projects' => DashboardMetricsService::getBlockedProjectsCount(),
        ],
        'secondary' => [
            'projects_by_status' => DashboardMetricsService::getProjectsByStatus(),
            'revenue_by_service' => DashboardMetricsService::getRevenueByService(date('n'), date('Y')),
            'pending_briefings' => DashboardMetricsService::getPendingBriefings(),
            'avg_execution_time' => DashboardMetricsService::getAverageExecutionTimeByService(),
            'overdue_clients' => DashboardMetricsService::getClientsWithOverduePayments(),
        ],
        'temporal' => [
            'monthly_revenue_6m' => DashboardMetricsService::getMonthlyRevenueLast6Months(),
            'projects_flow' => DashboardMetricsService::getProjectsStartedVsFinished(3),
        ],
        'support' => [
            'tickets_summary' => DashboardMetricsService::getSupportTicketsCount(),
            'tickets_by_status' => DashboardMetricsService::getTicketsByStatus(),
            'avg_resolution_time' => DashboardMetricsService::getAverageTicketResolutionTime(),
        ],
        'services' => [
            'delivered_count' => DashboardMetricsService::getServicesDeliveredCount(date('n'), date('Y')),
            'delivered_by_type' => DashboardMetricsService::getServicesDeliveredByType(date('n'), date('Y')),
        ],
        'analytics' => [
            'funnel' => DashboardMetricsService::getFunnelAnalytics(30),
            'time_per_phase' => DashboardMetricsService::getAverageTimePerPhase(),
            'bottlenecks' => DashboardMetricsService::getBottlenecksAnalysis(),
            'conversion_rates' => DashboardMetricsService::getConversionRates(),
        ],
        'alerts' => DashboardMetricsService::getCriticalAlerts(),
    ];
    
    $this->view('dashboard.index', $metrics);
}
```

---

### ✅ **Checklist de Implementação - Dashboard**

- [ ] Service `DashboardMetricsService` com métodos principais
- [ ] Controller `DashboardController` expandido
- [ ] View `dashboard/index.php` com cards de métricas
- [ ] Gráficos (Chart.js ou similar) para métricas temporais
- [ ] Filtros de período e tipo de serviço
- [ ] Alertas críticos no topo
- [ ] Ações rápidas (botões de navegação)
- [ ] Gráfico de funil (Funnel Chart)
- [ ] Gráfico de tempo médio por fase
- [ ] Análise de gargalos automatizada
- [ ] Cache de métricas (opcional - para performance)

### **Métricas de Suporte**
- [ ] Contagem de tickets por status
- [ ] Tempo médio de resolução
- [ ] Tickets resolvidos hoje
- [ ] Gráfico de tickets ao longo do tempo

### **Métricas de Serviços Prestados**
- [ ] Contador de serviços prestados no mês
- [ ] Distribuição por tipo de serviço
- [ ] Comparativo com mês anterior
- [ ] Gráfico de serviços prestados ao longo do tempo

### **Visão Analítica do Fluxo**
- [ ] Funil completo (funnel chart)
- [ ] Taxa de conversão por etapa
- [ ] Tempo médio em cada fase
- [ ] Identificação automática de gargalos
- [ ] Receita por fase do funil

---

**Próximo Passo:** Validar arquitetura e iniciar Fase 1 (Catálogo de Serviços com Templates).

---

**Documento atualizado em:** 2025-01-07  
**Revisão:** 
- Adicionados requisitos críticos (briefing guiado, bloqueio financeiro, tarefas pré-definidas, fluxo completo)
- Adicionada seção completa de Dashboard e Métricas para Gestão

