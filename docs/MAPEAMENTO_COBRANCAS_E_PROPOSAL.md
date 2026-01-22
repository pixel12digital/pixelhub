# Mapeamento de Cobranças + Proposta de Melhorias

**Data:** 19/11/2025  
**Versão:** 1.0

---

## 1. MAPEAMENTO DO QUE JÁ EXISTE

### 1.1. Arquivos Relacionados a Cobranças

#### **Master (Painel Administrativo - Visão de Todos os Clientes)**

**Controllers:**
- `src/Controllers/BillingCollectionsController.php` - Tela principal de cobranças (`/billing/collections`)
- `src/Controllers/AsaasWebhookController.php` - Processa webhooks do Asaas
- `src/Controllers/TenantsController.php` - Lista clientes (`/tenants`) e painel individual (`/tenants/view`)

**Services:**
- `src/Services/AsaasBillingService.php` - Lógica de negócio para integração Asaas
- `src/Services/WhatsAppBillingService.php` - Gerenciamento de cobranças via WhatsApp
- `src/Services/AsaasClient.php` - Cliente HTTP para API do Asaas
- `src/Services/AsaasConfig.php` - Configuração do Asaas

**Views:**
- `views/billing_collections/index.php` - Tela principal de cobranças
- `views/billing_collections/whatsapp_modal.php` - Modal/página de cobrança WhatsApp
- `views/tenants/index.php` - Lista de clientes (sem resumo financeiro)
- `views/tenants/view.php` - Painel do cliente (com aba Financeiro)

**Rotas:**
- `GET /billing/collections` - Tela de cobranças
- `GET /billing/whatsapp-modal` - Modal de cobrança WhatsApp
- `POST /billing/whatsapp-sent` - Marca cobrança como enviada
- `GET /tenants` - Lista clientes
- `GET /tenants/view` - Painel do cliente

#### **Contexto do Cliente (Tenant)**

**Controllers:**
- `src/Controllers/TenantsController.php::show()` - Painel do cliente com aba Financeiro
- `src/Controllers/BillingCollectionsController.php` - Reutilizado (mesma tela, mas filtrada por tenant)

**Views:**
- `views/tenants/view.php` (aba `financial`) - Lista faturas do cliente específico

**Rotas:**
- `GET /tenants/view?id={tenant_id}&tab=financial` - Aba financeiro do cliente

---

### 1.2. Análise das Telas/Listagens Existentes

#### **A) Tela `/billing/collections` (Master - Todas as Cobranças)**

**Query Base:**
```sql
SELECT 
    bi.*,
    t.id as tenant_id,
    t.name as tenant_name,
    t.person_type,
    t.nome_fantasia,
    t.phone,
    DATEDIFF(CURDATE(), bi.due_date) as days_overdue
FROM billing_invoices bi
INNER JOIN tenants t ON bi.tenant_id = t.id
WHERE (bi.is_deleted IS NULL OR bi.is_deleted = 0)
  AND bi.status IN ('pending', 'overdue')  -- Filtro padrão
ORDER BY 
    CASE 
        WHEN bi.status = 'overdue' THEN 1
        WHEN bi.status = 'pending' AND bi.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 2
        ELSE 3
    END,
    bi.due_date ASC
```

**Status de `billing_invoices` que aparecem:**
- `pending` - Pendente
- `overdue` - Vencido
- `paid` - Pago (se filtro específico)
- `canceled` - Cancelado (excluído por padrão via `is_deleted`)

**Uso do campo `is_deleted`:**
- **Sempre excluído** na query base: `WHERE (bi.is_deleted IS NULL OR bi.is_deleted = 0)`
- Filtros específicos também excluem deletadas

**Cálculos de Resumo:**
```sql
SELECT 
    SUM(CASE WHEN status = 'overdue' AND (is_deleted IS NULL OR is_deleted = 0) THEN amount ELSE 0 END) as total_overdue,
    COUNT(DISTINCT CASE WHEN status = 'overdue' AND (is_deleted IS NULL OR is_deleted = 0) THEN tenant_id ELSE NULL END) as clients_overdue,
    COUNT(CASE WHEN status = 'pending' AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND (is_deleted IS NULL OR is_deleted = 0) THEN 1 ELSE NULL END) as invoices_due_soon
FROM billing_invoices
WHERE (is_deleted IS NULL OR is_deleted = 0)
```

**Onde são calculados:**
- **Total em atraso**: Soma de `amount` onde `status = 'overdue'` e `is_deleted = 0`
- **Clientes em atraso**: Contagem DISTINCT de `tenant_id` onde `status = 'overdue'` e `is_deleted = 0`
- **Vencendo em 7 dias**: Contagem de faturas `pending` com `due_date <= CURDATE() + 7 dias` e `is_deleted = 0`

---

#### **B) Tela `/tenants/view?tab=financial` (Contexto Cliente)**

**Query Base:**
```sql
SELECT * FROM billing_invoices
WHERE tenant_id = ?
ORDER BY due_date DESC, created_at DESC
```

**Status que aparecem:**
- Todos os status (não filtra por `is_deleted` na query, mas pode ser adicionado)

**Uso do campo `is_deleted`:**
- **NÃO está sendo usado** atualmente nesta query (pode ser melhorado)

**Cálculos:**
```sql
SELECT COUNT(*) FROM billing_invoices
WHERE tenant_id = ? AND status = 'overdue'
```
- **Faturas em atraso**: Contagem simples de `status = 'overdue'` (não considera `is_deleted`)

---

#### **C) Tela `/tenants` (Lista de Clientes - Master)**

**Query Base:**
```sql
SELECT t.*, 
       COUNT(ha.id) as hosting_count,
       COUNT(CASE WHEN ha.backup_status = 'completo' THEN 1 END) as backups_completos
FROM tenants t
LEFT JOIN hosting_accounts ha ON t.id = ha.tenant_id
GROUP BY t.id
ORDER BY t.name ASC
```

**Status financeiro:**
- **NÃO exibe** informações financeiras nesta tela
- Apenas mostra: Nome, Email, WhatsApp, Sites, Backups, Status (active/suspended)

---

### 1.3. Verificação: Existe Tela Master com Resumo Financeiro por Cliente?

**Resposta: NÃO**

Atualmente:
- `/tenants` - Lista clientes, mas **sem** resumo financeiro
- `/billing/collections` - Lista **faturas** (não agrupadas por cliente)
- `/tenants/view` - Painel **individual** do cliente com resumo financeiro

**Conclusão:** Não existe uma tela no master que liste todos os clientes com resumo financeiro agregado (valor em atraso, qtd faturas vencidas, etc.).

---

## 2. PROPOSTA: CENTRAL DE COBRANÇAS NO MASTER

### 2.1. Análise do que Já Conseguimos Montar

Com base nas tabelas existentes:

**Tabelas disponíveis:**
- `tenants` - Dados dos clientes
- `billing_invoices` - Faturas (com `is_deleted`, `status`, `due_date`, `amount`)
- `billing_contracts` - Contratos (pode ter `plan_snapshot_name`, `billing_mode`)
- `billing_notifications` - Histórico de cobranças WhatsApp (pode ter `sent_at`)

**Campos que conseguimos calcular:**
- ✅ **Cliente (nome + link)** - `tenants.name`, `tenants.nome_fantasia`, `tenants.id`
- ✅ **Valor em atraso** - `SUM(billing_invoices.amount)` onde `status = 'overdue'` e `is_deleted = 0`
- ✅ **Qtd faturas vencidas** - `COUNT(*)` onde `status = 'overdue'` e `is_deleted = 0`
- ✅ **Valor vencendo hoje** - `SUM(amount)` onde `due_date = CURDATE()` e `status = 'pending'`
- ✅ **Valor vencendo próximos X dias** - `SUM(amount)` onde `due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL X DAY)` e `status = 'pending'`
- ⚠️ **Plano/Tipo** - Pode vir de `billing_contracts.plan_snapshot_name` (mas nem toda fatura tem contrato vinculado)
- ✅ **Último contato de cobrança** - `MAX(billing_notifications.sent_at)` ou `MAX(billing_invoices.whatsapp_last_at)`

**Queries/Service que já fazem parte dos cálculos:**
- `BillingCollectionsController::index()` - Já calcula resumos (mas por fatura, não por cliente)
- `AsaasBillingService::refreshTenantBillingStatus()` - Já calcula status financeiro do tenant

---

### 2.2. Proposta de Controller + Query

**Nova Rota:**
- `GET /master/billing/overview` ou `GET /billing/overview` (mais simples)

**Controller: `BillingCollectionsController::overview()`**

**Query Proposta:**
```sql
SELECT 
    t.id as tenant_id,
    t.name as tenant_name,
    t.person_type,
    t.nome_fantasia,
    t.phone,
    t.billing_status,
    
    -- Valor em atraso
    COALESCE(SUM(CASE WHEN bi.status = 'overdue' AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) THEN bi.amount ELSE 0 END), 0) as total_overdue,
    
    -- Qtd faturas vencidas
    COUNT(CASE WHEN bi.status = 'overdue' AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) THEN 1 END) as qtd_invoices_overdue,
    
    -- Valor vencendo hoje
    COALESCE(SUM(CASE WHEN bi.due_date = CURDATE() AND bi.status = 'pending' AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) THEN bi.amount ELSE 0 END), 0) as total_due_today,
    
    -- Valor vencendo próximos 7 dias
    COALESCE(SUM(CASE WHEN bi.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                      AND bi.status = 'pending' 
                      AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) 
                 THEN bi.amount ELSE 0 END), 0) as total_due_next_7d,
    
    -- Último contato WhatsApp (da tabela billing_invoices)
    MAX(bi.whatsapp_last_at) as last_whatsapp_contact,
    
    -- Último contato via billing_notifications (mais completo)
    MAX(bn.sent_at) as last_notification_sent
    
FROM tenants t
LEFT JOIN billing_invoices bi ON t.id = bi.tenant_id
LEFT JOIN billing_notifications bn ON t.id = bn.tenant_id AND bn.status = 'sent_manual'
WHERE t.status = 'active'  -- Apenas clientes ativos
GROUP BY t.id, t.name, t.person_type, t.nome_fantasia, t.phone, t.billing_status
HAVING total_overdue > 0 
    OR qtd_invoices_overdue > 0 
    OR total_due_today > 0 
    OR total_due_next_7d > 0
ORDER BY 
    total_overdue DESC,
    qtd_invoices_overdue DESC,
    total_due_next_7d DESC
```

**Filtros Propostos:**
- Status geral: "Todos / Em atraso / Vencendo hoje / Vencendo até 7 dias"
- Filtro "Somente clientes sem contato recente" (ex.: últimos X dias)

**Query com Filtros:**
```sql
-- Filtro: status_geral
-- 'all' -> mostra todos (com HAVING removido ou mais permissivo)
-- 'em_atraso' -> HAVING total_overdue > 0
-- 'vencendo_hoje' -> HAVING total_due_today > 0
-- 'vencendo_7d' -> HAVING total_due_next_7d > 0

-- Filtro: sem_contato_recente
-- Se ativo: AND (last_whatsapp_contact IS NULL OR last_whatsapp_contact < DATE_SUB(NOW(), INTERVAL X DAY))
```

---

## 3. MENSAGEM ÚNICA DE COBRANÇA POR CLIENTE (WhatsApp)

### 3.1. Verificação: O que Já Existe

**Função que monta texto de mensagem:**
- ✅ `WhatsAppBillingService::buildMessageForInvoice()` - Monta mensagem para **UMA fatura**
- ❌ **NÃO existe** função que monta mensagem com **TODAS as faturas** de um cliente

**Função que gera link de WhatsApp:**
- ✅ Existe no controller `BillingCollectionsController::showWhatsAppModal()`:
  ```php
  $phoneNormalized = WhatsAppBillingService::normalizePhone($phoneRaw);
  $messageEncoded = rawurlencode($message);
  $whatsappLink = "https://wa.me/{$phoneNormalized}?text={$messageEncoded}";
  ```

**Registro de contato:**
- ✅ `billing_notifications` - Registra cada mensagem enviada
- ✅ `billing_invoices.whatsapp_last_at` - Última data de cobrança por fatura
- ✅ `billing_invoices.whatsapp_total_messages` - Contador de mensagens

**Conclusão:**
- Existe infraestrutura para mensagem **por fatura**
- **Falta** função para mensagem **agregada por cliente** (todas as faturas pendentes/vencidas)

---

### 3.2. Proposta: Novo Service + Endpoint

#### **A) Novo Método no Service: `WhatsAppBillingService::buildReminderMessageForTenant()`**

```php
/**
 * Monta mensagem única com todas as faturas pendentes/vencidas do cliente
 * 
 * @param array $tenant Dados do tenant
 * @param array $invoices Array de faturas (pending/overdue, não deletadas)
 * @return string Mensagem formatada
 */
public static function buildReminderMessageForTenant(array $tenant, array $invoices): string
{
    // Nome do cliente
    $clientName = $tenant['name'] ?? 'Cliente';
    if (($tenant['person_type'] ?? 'pf') === 'pj' && !empty($tenant['nome_fantasia'])) {
        $clientName = $tenant['nome_fantasia'];
    } elseif (($tenant['person_type'] ?? 'pf') === 'pj' && !empty($tenant['razao_social'])) {
        $clientName = $tenant['razao_social'];
    }

    // Saudação
    $message = "Olá {$clientName}, tudo bem? 😊\n\n";
    
    // Parágrafo explicativo
    $totalInvoices = count($invoices);
    $message .= "Passando para lembrar que você possui {$totalInvoices} cobrança(s) em aberto na Pixel12 Digital:\n\n";
    
    // Lista de cobranças
    foreach ($invoices as $invoice) {
        $dueDate = $invoice['due_date'] ?? null;
        $dueDateFormatted = 'N/A';
        if ($dueDate) {
            try {
                $date = new \DateTime($dueDate);
                $dueDateFormatted = $date->format('d/m/Y');
            } catch (\Exception $e) {}
        }
        
        $amount = (float) ($invoice['amount'] ?? 0);
        $amountFormatted = 'R$ ' . number_format($amount, 2, ',', '.');
        
        $description = $invoice['description'] ?? 'Cobrança';
        $status = $invoice['status'] ?? 'pending';
        $statusLabel = $status === 'overdue' ? 'Vencida' : 'Pendente';
        
        $invoiceUrl = $invoice['invoice_url'] ?? '';
        
        $message .= "• {$statusLabel} - Vencimento {$dueDateFormatted} - {$amountFormatted} - {$description}";
        
        if ($invoiceUrl) {
            $message .= "\n  Link: {$invoiceUrl}";
        }
        
        $message .= "\n\n";
    }
    
    // Parágrafo final
    $message .= "O pagamento mantém seus serviços ativos. Se já tiver efetuado o pagamento, pode desconsiderar esta mensagem.\n\n";
    $message .= "Em caso de dúvidas, estou à disposição! 😊";
    
    return $message;
}
```

#### **B) Novo Endpoint: `BillingCollectionsController::getTenantReminderData()`**

```php
/**
 * Retorna JSON com dados para modal de cobrança agregada por cliente
 * 
 * GET /billing/tenant-reminder?tenant_id={id}
 */
public function getTenantReminderData(): void
{
    Auth::requireInternal();
    
    $tenantId = $_GET['tenant_id'] ?? null;
    if (!$tenantId) {
        $this->json(['error' => 'tenant_id obrigatório']);
        return;
    }
    
    $db = DB::getConnection();
    
    // Busca tenant
    $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmt->execute([$tenantId]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        $this->json(['error' => 'Cliente não encontrado']);
        return;
    }
    
    // Busca faturas pendentes/vencidas (não deletadas)
    $stmt = $db->prepare("
        SELECT * FROM billing_invoices
        WHERE tenant_id = ?
          AND status IN ('pending', 'overdue')
          AND (is_deleted IS NULL OR is_deleted = 0)
        ORDER BY due_date ASC
    ");
    $stmt->execute([$tenantId]);
    $invoices = $stmt->fetchAll();
    
    if (empty($invoices)) {
        $this->json(['error' => 'Nenhuma cobrança pendente encontrada']);
        return;
    }
    
    // Monta mensagem
    $message = WhatsAppBillingService::buildReminderMessageForTenant($tenant, $invoices);
    
    // Normaliza telefone
    $phoneRaw = $tenant['phone'] ?? $tenant['whatsapp'] ?? null;
    $phoneNormalized = WhatsAppBillingService::normalizePhone($phoneRaw);
    
    // Prepara link WhatsApp
    $whatsappLink = null;
    if ($phoneNormalized) {
        $messageEncoded = rawurlencode($message);
        $whatsappLink = "https://wa.me/{$phoneNormalized}?text={$messageEncoded}";
    }
    
    $this->json([
        'tenant' => [
            'id' => $tenant['id'],
            'name' => $tenant['name'],
            'nome_fantasia' => $tenant['nome_fantasia'] ?? null,
            'phone' => $phoneRaw,
            'phone_normalized' => $phoneNormalized,
        ],
        'invoices' => $invoices,
        'message' => $message,
        'whatsapp_link' => $whatsappLink,
    ]);
}
```

#### **C) Modal HTML (JavaScript para abrir via AJAX)**

**Estrutura do Modal:**
- Lista de faturas (descrição, vencimento, valor, link)
- Textarea com mensagem pronta (editável)
- Botão "Copiar mensagem"
- Botão "Abrir no WhatsApp Web"
- Botão "Salvar / Marcar como Enviado" (atualiza todas as faturas)

**Endpoint para salvar:**
```php
/**
 * Marca todas as faturas do cliente como "cobradas"
 * 
 * POST /billing/tenant-reminder-sent
 */
public function markTenantReminderSent(): void
{
    Auth::requireInternal();
    
    $tenantId = $_POST['tenant_id'] ?? null;
    $message = $_POST['message'] ?? '';
    $phone = $_POST['phone'] ?? null;
    
    if (!$tenantId) {
        $this->redirect('/billing/overview?error=missing_tenant_id');
        return;
    }
    
    $db = DB::getConnection();
    
    try {
        $db->beginTransaction();
        
        // Busca faturas pendentes/vencidas do tenant
        $stmt = $db->prepare("
            SELECT id FROM billing_invoices
            WHERE tenant_id = ?
              AND status IN ('pending', 'overdue')
              AND (is_deleted IS NULL OR is_deleted = 0)
        ");
        $stmt->execute([$tenantId]);
        $invoiceIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Atualiza cada fatura
        $phoneNormalized = WhatsAppBillingService::normalizePhone($phone);
        
        foreach ($invoiceIds as $invoiceId) {
            // Atualiza fatura
            $stmt = $db->prepare("
                UPDATE billing_invoices
                SET whatsapp_last_at = NOW(),
                    whatsapp_total_messages = whatsapp_total_messages + 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$invoiceId]);
            
            // Cria notificação
            $stmt = $db->prepare("
                INSERT INTO billing_notifications
                (tenant_id, invoice_id, channel, template, status, message, phone_raw, phone_normalized, sent_at, created_at, updated_at)
                VALUES (?, ?, 'whatsapp_web', 'bulk_reminder', 'sent_manual', ?, ?, ?, NOW(), NOW(), NOW())
            ");
            $stmt->execute([$tenantId, $invoiceId, $message, $phone, $phoneNormalized]);
        }
        
        $db->commit();
        
        $this->redirect('/billing/overview?success=reminder_sent');
    } catch (\Exception $e) {
        $db->rollBack();
        error_log("Erro ao marcar lembrete como enviado: " . $e->getMessage());
        $this->redirect('/billing/overview?error=save_failed');
    }
}
```

---

## 4. LAYOUT MAIS PROFISSIONAL

### 4.1. Análise do Layout Atual

#### **Badges/Cores/Ícones Usados Hoje:**

**Status de Fatura:**
- `pending` → Badge amarelo (`#ffc107`) - "Pendente"
- `overdue` → Badge vermelho (`#dc3545`) - "Vencido"
- `paid` → Badge verde (`#28a745`) - "Pago"
- `canceled` → Badge cinza (`#999`) - "Cancelado"

**WhatsApp:**
- Ícones emoji: ✅ (pre_due), 📞 (overdue_3d), ⚠️ (overdue_7d), 📱 (sem contato)
- Badges de texto: "Pré-vencimento", "Cobrança 1", "Cobrança 2", "Sem contato"

**Ações:**
- Botão verde (`#25D366`) com emoji 📱 - "Cobrar"
- Botão azul (`#023A8D`) - "Ver Fatura"
- Botão cinza (`#666`) - "Editar"

**Problemas Identificados:**
1. Muitos emojis (✅, 📞, ⚠️, 📱) - pode parecer pouco profissional
2. Cores muito vibrantes (amarelo `#ffc107` para pendente)
3. Badges com cores sólidas muito chamativas
4. Falta hierarquia visual clara

---

### 4.2. Proposta de Padrão Mais Clean

#### **A) Status de Fatura**

**Antes:**
```html
<span style="background: #ffc107; color: white; padding: 4px 10px; border-radius: 12px;">
    Pendente
</span>
```

**Depois:**
```html
<span class="badge badge-pending">Pendente</span>
```

**CSS Proposto:**
```css
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-pending {
    background: #f8f9fa;
    color: #6c757d;
    border: 1px solid #dee2e6;
}

.badge-overdue {
    background: #fff5f5;
    color: #c92a2a;
    border: 1px solid #ffc9c9;
}

.badge-paid {
    background: #f0f9ff;
    color: #1971c2;
    border: 1px solid #a5d8ff;
}
```

#### **B) WhatsApp Status**

**Antes:**
```html
<span style="font-size: 14px;">✅</span>
<br><small>Pré-vencimento</small>
```

**Depois:**
```html
<span class="whatsapp-status whatsapp-status-pre-due">
    <span class="whatsapp-icon">●</span>
    Pré-vencimento
</span>
```

**CSS Proposto:**
```css
.whatsapp-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #6c757d;
}

.whatsapp-icon {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

.whatsapp-status-pre-due .whatsapp-icon {
    background: #51cf66;
}

.whatsapp-status-overdue-3d .whatsapp-icon {
    background: #ffc107;
}

.whatsapp-status-overdue-7d .whatsapp-icon {
    background: #dc3545;
}
```

#### **C) Ações**

**Antes:**
```html
<a href="..." style="background: #25D366; color: white; padding: 6px 12px; border-radius: 4px;">
    📱 Cobrar
</a>
```

**Depois:**
```html
<button class="btn btn-primary btn-sm" data-action="charge" data-tenant-id="123">
    Cobrar
</button>
```

**CSS Proposto:**
```css
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-primary {
    background: #023A8D;
    color: white;
}

.btn-primary:hover {
    background: #022a6d;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}

/* Menu de ações agrupadas */
.actions-menu {
    position: relative;
    display: inline-block;
}

.actions-menu-toggle {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
}

.actions-menu-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    margin-top: 4px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    min-width: 150px;
    z-index: 100;
}

.actions-menu-dropdown a {
    display: block;
    padding: 8px 16px;
    color: #495057;
    text-decoration: none;
    font-size: 14px;
}

.actions-menu-dropdown a:hover {
    background: #f8f9fa;
}
```

#### **D) Reorganização de Elementos**

**Sugestões:**
1. **Remover emojis** de badges/status (manter apenas no botão "Cobrar" se necessário)
2. **Agrupar ações** em menu dropdown "Mais ações" (editar, imprimir, deletar)
3. **Destacar ação principal** (botão "Cobrar" maior, outras ações menores)
4. **Simplificar cores** - usar tons mais discretos
5. **Usar ícones SVG** ao invés de emojis (opcional, mas mais profissional)

---

## 5. RESUMO DAS IMPLEMENTAÇÕES NECESSÁRIAS

### 5.1. Central de Cobranças no Master

**Arquivos a criar/modificar:**
1. `src/Controllers/BillingCollectionsController.php` - Adicionar método `overview()`
2. `views/billing_collections/overview.php` - Nova view
3. `public/index.php` - Adicionar rota `GET /billing/overview`

**Queries a implementar:**
- Query principal com agregação por tenant
- Filtros (status geral, sem contato recente)

---

### 5.2. Mensagem Única por Cliente

**Arquivos a criar/modificar:**
1. `src/Services/WhatsAppBillingService.php` - Adicionar `buildReminderMessageForTenant()`
2. `src/Controllers/BillingCollectionsController.php` - Adicionar:
   - `getTenantReminderData()` (JSON endpoint)
   - `markTenantReminderSent()` (POST endpoint)
3. `views/billing_collections/tenant_reminder_modal.php` - Modal HTML
4. `public/index.php` - Adicionar rotas:
   - `GET /billing/tenant-reminder`
   - `POST /billing/tenant-reminder-sent`

---

### 5.3. Layout Profissional

**Arquivos a criar/modificar:**
1. `views/layout/main.php` - Adicionar CSS para badges/buttons
2. `views/billing_collections/index.php` - Aplicar novos estilos
3. `views/billing_collections/overview.php` - Usar novos estilos
4. `views/tenants/view.php` (aba financial) - Aplicar novos estilos

---

## 6. PRÓXIMOS PASSOS

1. **Implementar Central de Cobranças** (`/billing/overview`)
2. **Implementar mensagem única por cliente** (service + endpoints + modal)
3. **Refatorar layout** (CSS + remover emojis + agrupar ações)
4. **Testar fluxo completo** (abrir modal, copiar mensagem, abrir WhatsApp, salvar)
5. **Adicionar filtro "sem contato recente"** na Central de Cobranças

---

**Documento criado em:** 19/11/2025  
**Autor:** Análise do sistema Pixel Hub

