# Tickets Faturáveis - Sistema de Cobrança de Serviços

## Visão Geral

Sistema completo para registrar receitas provenientes de serviços cobrados via tickets de suporte, com integração automática ao Asaas para geração de cobranças.

**Implementado em:** 25/02/2026

---

## Problema Resolvido

Anteriormente, o sistema tinha 3 fluxos de receita:
1. **Oportunidades (CRM)** → gera `service_order` ao ganhar
2. **Faturas Recorrentes** → contratos de hospedagem/SaaS
3. **Serviços Pontuais** → pedidos de serviço do catálogo

**Faltava:** Registrar receitas de tickets cobrados (ex: redirect de domínio R$ 150, configuração de e-mail R$ 80, suporte técnico avulso).

---

## Solução Implementada

### Arquitetura

**Tickets Faturáveis** = Tickets marcados como cobráveis que geram cobrança automática no Asaas e registram receita em `billing_invoices`.

**Fluxo:**
1. Criar ticket de suporte normalmente
2. Marcar como "faturável" e definir valor
3. Gerar cobrança no Asaas (cria payment + registra invoice)
4. Cliente recebe boleto/PIX
5. Webhook do Asaas atualiza status automaticamente
6. Receita registrada e rastreável

---

## Estrutura de Dados

### Novos Campos em `tickets`

```sql
is_billable TINYINT(1) DEFAULT 0           -- Ticket é faturável?
service_id INT UNSIGNED NULL               -- Vincula a serviço do catálogo (opcional)
billed_value DECIMAL(10,2) NULL            -- Valor a cobrar
billing_status ENUM('pending','billed','paid','canceled') NULL
billing_invoice_id INT UNSIGNED NULL       -- FK para billing_invoices
billing_due_date DATE NULL                 -- Vencimento da cobrança
billed_at DATETIME NULL                    -- Data de geração no Asaas
billing_notes TEXT NULL                    -- Observações sobre faturamento
```

### Relacionamentos

- `tickets.billing_invoice_id` → `billing_invoices.id`
- `tickets.service_id` → `services.id` (opcional)
- `billing_invoices.external_reference` = `"TICKET_{ticket_id}"`

---

## API - TicketService

### 1. Marcar Ticket como Faturável

```php
TicketService::markAsBillable(int $ticketId, array $billingData): bool
```

**Parâmetros:**
```php
$billingData = [
    'billed_value' => 150.00,                    // OBRIGATÓRIO
    'service_id' => 5,                           // Opcional
    'billing_due_date' => '2026-03-05',          // Opcional (padrão: +7 dias)
    'billing_notes' => 'Redirect de 3 domínios'  // Opcional
];
```

**Exemplo:**
```php
try {
    TicketService::markAsBillable(123, [
        'billed_value' => 150.00,
        'billing_due_date' => '2026-03-10',
        'billing_notes' => 'Redirect de domínio exemplo.com.br'
    ]);
    
    echo "Ticket marcado como faturável";
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage();
}
```

---

### 2. Gerar Cobrança no Asaas

```php
TicketService::generateBilling(int $ticketId, array $options = []): array
```

**Fluxo interno:**
1. Valida se ticket é faturável e não foi cobrado
2. Garante que cliente tem `asaas_customer_id`
3. Cria payment no Asaas via `AsaasClient::createPayment()`
4. Registra em `billing_invoices`
5. Atualiza ticket: `billing_status='billed'`, vincula `invoice_id`
6. Atualiza `billing_status` do tenant

**Opções:**
```php
$options = [
    'billing_type' => 'BOLETO',  // 'BOLETO' | 'PIX' | 'CREDIT_CARD'
    'description' => 'Descrição customizada'  // Opcional
];
```

**Retorno:**
```php
[
    'success' => true,
    'invoice_id' => 456,
    'asaas_payment_id' => 'pay_abc123',
    'invoice_url' => 'https://www.asaas.com/i/abc123',
    'message' => 'Cobrança gerada com sucesso'
]
```

**Exemplo:**
```php
try {
    $result = TicketService::generateBilling(123, [
        'billing_type' => 'PIX'
    ]);
    
    echo "Cobrança gerada! URL: " . $result['invoice_url'];
    
    // Enviar URL para cliente via WhatsApp/Email
    
} catch (\RuntimeException $e) {
    echo "Erro: " . $e->getMessage();
}
```

---

### 3. Cancelar Cobrança

```php
TicketService::cancelBilling(int $ticketId, string $reason = ''): bool
```

**Ações:**
- Cancela payment no Asaas (DELETE /payments/{id})
- Marca invoice como `canceled` e `is_deleted=1`
- Atualiza ticket: `billing_status='canceled'`
- Adiciona motivo em `billing_notes`

**Exemplo:**
```php
try {
    TicketService::cancelBilling(123, 'Cliente desistiu do serviço');
    echo "Cobrança cancelada";
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage();
}
```

---

### 4. Atualizar Status via Webhook

```php
TicketService::updateBillingStatusFromInvoice(int $ticketId): bool
```

**Chamado automaticamente pelo webhook do Asaas** quando status do payment muda.

**Mapeamento:**
- `paid/received/confirmed` → `billing_status='paid'`
- `canceled/refunded` → `billing_status='canceled'`
- `overdue/pending` → `billing_status='billed'`

---

### 5. Listar Tickets Pendentes de Cobrança

```php
TicketService::getBillablePendingTickets(array $filters = []): array
```

**Filtros:**
```php
$filters = [
    'tenant_id' => 25  // Opcional
];
```

**Retorna:** Tickets com `is_billable=1`, `billing_status='pending'`, `billing_invoice_id IS NULL`

**Exemplo:**
```php
$pending = TicketService::getBillablePendingTickets(['tenant_id' => 25]);

foreach ($pending as $ticket) {
    echo "Ticket #{$ticket['id']}: {$ticket['titulo']} - R$ {$ticket['billed_value']}\n";
}
```

---

## Fluxo Completo de Uso

### Cenário: Redirect de Domínio (R$ 150)

**1. Cliente abre ticket via WhatsApp**
```
Cliente: "Preciso fazer redirect do domínio antigo.com.br para novo.com.br"
```

**2. Atendente cria ticket no sistema**
```php
$ticketId = TicketService::createTicket([
    'tenant_id' => 25,
    'titulo' => 'Redirect de domínio antigo.com.br → novo.com.br',
    'descricao' => 'Cliente solicitou redirect 301 permanente',
    'prioridade' => 'media',
    'origem' => 'whatsapp'
]);
```

**3. Atendente marca como faturável**
```php
TicketService::markAsBillable($ticketId, [
    'billed_value' => 150.00,
    'billing_due_date' => '2026-03-05',
    'billing_notes' => 'Serviço: Redirect de domínio (301 permanente)'
]);
```

**4. Atendente gera cobrança**
```php
$result = TicketService::generateBilling($ticketId, [
    'billing_type' => 'PIX'
]);

// Envia link de pagamento para cliente
$invoiceUrl = $result['invoice_url'];
```

**5. Cliente paga via PIX**
- Asaas detecta pagamento
- Webhook chama PixelHub: `POST /api/asaas/webhook`
- Sistema atualiza `billing_invoices.status='paid'`
- Sistema atualiza `tickets.billing_status='paid'`

**6. Atendente resolve ticket**
```php
TicketService::updateTicket($ticketId, [
    'status' => 'resolvido'
]);
```

**7. Receita registrada!**
- `billing_invoices`: R$ 150,00 pago
- `tickets`: vinculado à invoice
- Relatórios financeiros: receita de serviços avulsos

---

## Integração com Asaas

### Customer Management

O sistema garante automaticamente que o cliente (tenant) tem `asaas_customer_id`:

```php
// Dentro de generateBilling()
$customerId = AsaasBillingService::ensureCustomerForTenant($ticket);
```

**Fluxo:**
1. Se tenant já tem `asaas_customer_id` → usa
2. Senão, busca no Asaas por CPF/CNPJ
3. Se encontrar → salva ID no tenant
4. Se não encontrar → cria customer no Asaas

### Payment Creation

```php
$paymentData = [
    'customer' => $customerId,
    'billingType' => 'BOLETO',  // ou 'PIX', 'CREDIT_CARD'
    'dueDate' => '2026-03-05',
    'value' => 150.00,
    'description' => 'Ticket #123: Redirect de domínio',
    'externalReference' => 'TICKET_123',
    'notes' => 'Observações internas'
];

$asaasPayment = AsaasClient::createPayment($paymentData);
```

### Webhook Handling

Quando status do payment muda no Asaas:

```
POST /api/asaas/webhook
{
    "event": "PAYMENT_RECEIVED",
    "payment": {
        "id": "pay_abc123",
        "status": "RECEIVED",
        "externalReference": "TICKET_123"
    }
}
```

**Processamento:**
1. Webhook atualiza `billing_invoices`
2. Busca tickets com `external_reference='TICKET_123'`
3. Chama `TicketService::updateBillingStatusFromInvoice()`

---

## Relatórios e Consultas

### Receitas por Tipo

```sql
-- Receitas de Tickets (Serviços Avulsos)
SELECT 
    SUM(amount) as total_tickets,
    COUNT(*) as qtd_tickets
FROM billing_invoices
WHERE external_reference LIKE 'TICKET_%'
AND status = 'paid'
AND MONTH(paid_at) = MONTH(CURDATE());

-- Receitas de Oportunidades (Vendas CRM)
SELECT 
    SUM(estimated_value) as total_vendas,
    COUNT(*) as qtd_vendas
FROM opportunities
WHERE status = 'won'
AND MONTH(won_at) = MONTH(CURDATE());

-- Receitas Recorrentes (Contratos)
SELECT 
    SUM(amount) as total_recorrente,
    COUNT(*) as qtd_faturas
FROM billing_invoices bi
INNER JOIN billing_contracts bc ON bi.billing_contract_id = bc.id
WHERE bi.status = 'paid'
AND MONTH(bi.paid_at) = MONTH(CURDATE());
```

### Tickets Faturáveis por Cliente

```sql
SELECT 
    t.name as cliente,
    COUNT(tk.id) as qtd_tickets_cobrados,
    SUM(tk.billed_value) as total_cobrado,
    SUM(CASE WHEN tk.billing_status = 'paid' THEN tk.billed_value ELSE 0 END) as total_pago
FROM tenants t
INNER JOIN tickets tk ON t.id = tk.tenant_id
WHERE tk.is_billable = 1
GROUP BY t.id
ORDER BY total_cobrado DESC;
```

### Tickets Pendentes de Pagamento

```sql
SELECT 
    tk.id,
    tk.titulo,
    t.name as cliente,
    tk.billed_value,
    tk.billing_due_date,
    DATEDIFF(CURDATE(), tk.billing_due_date) as dias_atraso,
    bi.invoice_url
FROM tickets tk
INNER JOIN tenants t ON tk.tenant_id = t.id
LEFT JOIN billing_invoices bi ON tk.billing_invoice_id = bi.id
WHERE tk.billing_status = 'billed'
AND bi.status IN ('pending', 'overdue')
ORDER BY tk.billing_due_date ASC;
```

---

## Vantagens da Solução

### ✅ Simplicidade Operacional
- Ticket já está aberto → só marca como faturável e define valor
- Não precisa criar service_order separado
- Processo natural do fluxo de suporte

### ✅ Flexibilidade
- Nem todo ticket é cobrado (suporte gratuito continua normal)
- Valor pode variar por ticket (não precisa cadastrar no catálogo)
- Pode vincular a serviço do catálogo se quiser padronizar

### ✅ Rastreabilidade
- Vínculo direto: ticket → invoice → payment Asaas
- Histórico completo de cobrança no ticket
- Relatórios consolidados de receita

### ✅ Integração Automática
- Customer criado/atualizado automaticamente
- Webhook sincroniza status em tempo real
- Status financeiro do tenant atualizado automaticamente

### ✅ Catálogo Limpo
- Serviços no catálogo ficam para vendas estruturadas (sites, logos, etc.)
- Não polui com "Redirect de Domínio", "Config E-mail", etc.
- Tickets faturáveis são ad-hoc por natureza

---

## Próximos Passos (Opcional)

### UI/UX
- [ ] Adicionar toggle "Faturável" no formulário de criação de ticket
- [ ] Campos de valor e vencimento no formulário
- [ ] Botão "Gerar Cobrança" na tela de visualização do ticket
- [ ] Badge de status de cobrança (Pendente/Cobrado/Pago/Cancelado)
- [ ] Link direto para invoice do Asaas

### Automação
- [ ] Template de mensagem WhatsApp com link de pagamento
- [ ] Lembrete automático de cobrança pendente (X dias antes do vencimento)
- [ ] Notificação quando ticket faturável é pago

### Relatórios
- [ ] Dashboard de receitas consolidado (Vendas + Recorrente + Tickets)
- [ ] Gráfico de receitas por tipo de serviço
- [ ] Ranking de serviços avulsos mais cobrados

---

## Arquivos Criados/Modificados

### Criados
- `database/migrations/20260225_alter_tickets_add_billing_fields.php`
- `docs/TICKETS_FATURAVEIS.md` (este arquivo)

### Modificados
- `src/Services/TicketService.php` - Adicionados 5 métodos de faturamento

### Dependências Existentes (Reutilizadas)
- `src/Services/AsaasBillingService.php` - Gerenciamento de customers
- `src/Services/AsaasClient.php` - Comunicação com API Asaas
- `src/Core/AsaasHelper.php` - Helpers Asaas
- Tabela `billing_invoices` - Registro de cobranças
- Webhook `/api/asaas/webhook` - Sincronização de status

---

## Exemplo Completo de Implementação

```php
<?php
// Arquivo: scripts/exemplo_ticket_faturavel.php

require_once __DIR__ . '/../vendor/autoload.php';

use PixelHub\Services\TicketService;

// 1. Criar ticket de suporte
$ticketId = TicketService::createTicket([
    'tenant_id' => 25,  // Charles Dietrich
    'titulo' => 'Redirect de domínio antigo.com.br → novo.com.br',
    'descricao' => 'Cliente solicitou redirect 301 permanente do domínio antigo para o novo',
    'prioridade' => 'media',
    'origem' => 'whatsapp',
    'created_by' => 1
]);

echo "✓ Ticket #{$ticketId} criado\n";

// 2. Marcar como faturável
TicketService::markAsBillable($ticketId, [
    'billed_value' => 150.00,
    'billing_due_date' => date('Y-m-d', strtotime('+7 days')),
    'billing_notes' => 'Serviço: Redirect de domínio (301 permanente)'
]);

echo "✓ Ticket marcado como faturável (R$ 150,00)\n";

// 3. Gerar cobrança no Asaas
try {
    $result = TicketService::generateBilling($ticketId, [
        'billing_type' => 'PIX'
    ]);
    
    echo "✓ Cobrança gerada no Asaas!\n";
    echo "  Invoice ID: {$result['invoice_id']}\n";
    echo "  Asaas Payment ID: {$result['asaas_payment_id']}\n";
    echo "  URL: {$result['invoice_url']}\n";
    
    // Aqui você enviaria o link para o cliente via WhatsApp
    
} catch (\Exception $e) {
    echo "✗ Erro ao gerar cobrança: " . $e->getMessage() . "\n";
}

// 4. Listar tickets pendentes de cobrança
$pending = TicketService::getBillablePendingTickets();
echo "\n=== Tickets Pendentes de Cobrança ===\n";
foreach ($pending as $ticket) {
    echo "Ticket #{$ticket['id']}: {$ticket['titulo']} - R$ {$ticket['billed_value']}\n";
}
```

---

## Suporte e Manutenção

**Logs:**
- Geração de cobrança: `[TicketBilling] Cobrança gerada: ticket_id=X, invoice_id=Y`
- Erro na geração: `[TicketBilling] Erro ao gerar cobrança: ticket_id=X, erro=...`

**Troubleshooting:**
- Se cobrança não for gerada: verificar se tenant tem CPF/CNPJ cadastrado
- Se webhook não atualizar: verificar `webhook_raw_logs` e `billing_invoices.updated_at`
- Se customer não for criado: verificar logs do Asaas e dados do tenant

**Contato:**
- Documentação Asaas: https://docs.asaas.com
- Suporte interno: verificar `AsaasBillingService` e `AsaasClient`
