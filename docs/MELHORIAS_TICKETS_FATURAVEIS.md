# Melhorias Pendentes - Tickets Faturáveis

## Solicitações do Usuário (25/02/2026)

### 1. ✅ Remover Emoji Colorido
- **Atual:** 💰 Faturamento
- **Novo:** Faturamento (sem emoji ou emoji monocromático ▸)

### 2. ✅ Toggle "Faturável" no Header
- **Localização:** Ao lado do dropdown de Status
- **Comportamento:** 
  - Checkbox "Faturável" sempre visível
  - Quando marcado → seção de faturamento aparece
  - Quando desmarcado → seção de faturamento oculta

### 3. ✅ Seção Colapsável
- **Comportamento:**
  - Só aparece se `is_billable = 1`
  - Título clicável com seta (▼/▸)
  - Conteúdo expansível/colapsável
  - Estado padrão: expandido

### 4. ⚠️ Bloquear Encerramento sem Cobrança
**CRÍTICO:** Ticket faturável NÃO pode ser encerrado sem cobrança gerada e paga.

**Regra de Negócio:**
```
SE ticket.is_billable = 1 E ticket.billing_status != 'paid'
ENTÃO bloquear encerramento (status → resolvido/cancelado)
```

**Mensagem de Erro:**
```
"Este ticket é faturável e ainda não foi cobrado/pago. 
Gere a cobrança antes de encerrar o ticket."
```

**Implementação:**
- Validação no `TicketService::updateTicket()`
- Validação no `TicketService::closeTicket()`
- Mensagem de alerta na UI

### 5. ⚠️ Campos do Asaas (Juros, Multa, Desconto)

**Campos a Adicionar no Formulário "Gerar Cobrança":**

#### 5.1. Juros ao Mês (%)
```php
'interest' => [
    'value' => 1.00,  // Percentual mensal (ex: 1%)
]
```

#### 5.2. Multa por Atraso (%)
```php
'fine' => [
    'value' => 2.00,  // Percentual fixo (ex: 2%)
]
```

#### 5.3. Desconto
```php
'discount' => [
    'value' => 10.00,              // Valor fixo em R$
    'dueDateLimitDays' => 5,       // Dias antes do vencimento
    'type' => 'FIXED',             // FIXED ou PERCENTAGE
]
```

**Referência API Asaas:**
```json
POST /payments
{
  "customer": "cus_000000000000",
  "billingType": "BOLETO",
  "dueDate": "2026-03-05",
  "value": 150.00,
  "description": "Serviço de redirect de domínios",
  
  "fine": {
    "value": 2.00
  },
  "interest": {
    "value": 1.00
  },
  "discount": {
    "value": 10.00,
    "dueDateLimitDays": 5,
    "type": "FIXED"
  }
}
```

### 6. ⚠️ Descrição da Cobrança = Observações

**Atual:** Campo "Descrição (opcional)" no formulário de gerar cobrança

**Novo:** Usar automaticamente o campo "Observações sobre o Faturamento"

**Lógica:**
```php
$description = !empty($ticket['billing_notes']) 
    ? $ticket['billing_notes'] 
    : "Ticket #{$ticketId}: " . $ticket['titulo'];
```

**Benefício:** 
- Usuário preenche observações UMA VEZ (ao marcar como faturável)
- Observações vão direto para a descrição da cobrança no Asaas
- Cliente vê descrição detalhada no boleto/PIX

---

## Implementação Técnica

### Arquivos a Modificar

#### 1. `views/tickets/show.php`
```php
// Header: adicionar toggle faturável
<div style="display: flex; align-items: center; gap: 8px;">
    <label>
        <input type="checkbox" id="billable-toggle" 
               onchange="toggleBillable(<?= $ticket['id'] ?>, this.checked)">
        Faturável
    </label>
</div>

// Seção faturamento: só aparece se is_billable = 1
<?php if ($isBillable): ?>
<div class="ticket-section">
    <h3 onclick="toggleBillingSection()" style="cursor: pointer;">
        <span id="billing-icon">▼</span> Faturamento
    </h3>
    <div id="billing-content">
        <!-- Conteúdo colapsável -->
    </div>
</div>
<?php endif; ?>

// Formulário gerar cobrança: adicionar campos Asaas
<input type="number" name="interest_value" placeholder="Juros ao mês (%)">
<input type="number" name="fine_value" placeholder="Multa por atraso (%)">
<input type="number" name="discount_value" placeholder="Desconto (R$)">
<input type="number" name="discount_days_before_due" placeholder="Dias para desconto">
```

#### 2. `src/Services/TicketService.php`

**Método: `generateBilling()`**
```php
// Usar billing_notes como descrição
$description = !empty($ticket['billing_notes']) 
    ? $ticket['billing_notes'] 
    : "Ticket #{$ticketId}: " . $ticket['titulo'];

// Adicionar campos Asaas ao payload
$paymentData = [
    'customer' => $customerId,
    'billingType' => $options['billing_type'] ?? 'BOLETO',
    'dueDate' => $dueDate,
    'value' => $amount,
    'description' => $description,
    'externalReference' => "TICKET_{$ticketId}",
];

// Adicionar juros se informado
if (!empty($options['interest_value'])) {
    $paymentData['interest'] = [
        'value' => (float)$options['interest_value'],
    ];
}

// Adicionar multa se informado
if (!empty($options['fine_value'])) {
    $paymentData['fine'] = [
        'value' => (float)$options['fine_value'],
    ];
}

// Adicionar desconto se informado
if (!empty($options['discount_value'])) {
    $paymentData['discount'] = [
        'value' => (float)$options['discount_value'],
        'dueDateLimitDays' => (int)($options['discount_days_before_due'] ?? 0),
        'type' => 'FIXED',
    ];
}
```

**Método: `updateTicket()` - Bloquear encerramento**
```php
// Antes de mudar status para resolvido/cancelado
if (in_array($newStatus, ['resolvido', 'cancelado'])) {
    // Verifica se é faturável e não foi pago
    if ($ticket['is_billable'] == 1 && $ticket['billing_status'] !== 'paid') {
        throw new \InvalidArgumentException(
            'Este ticket é faturável e ainda não foi pago. ' .
            'Gere a cobrança e aguarde o pagamento antes de encerrar.'
        );
    }
}
```

**Método: `closeTicket()` - Bloquear encerramento**
```php
// No início do método
if ($ticket['is_billable'] == 1 && $ticket['billing_status'] !== 'paid') {
    return [
        'success' => false,
        'message' => 'Este ticket é faturável e ainda não foi pago. ' .
                     'Gere a cobrança e aguarde o pagamento antes de encerrar.',
    ];
}
```

#### 3. `src/Controllers/TicketController.php`

**Método: `generateBilling()`**
```php
$options = [
    'billing_type' => $_POST['billing_type'] ?? 'BOLETO',
    'interest_value' => $_POST['interest_value'] ?? null,
    'fine_value' => $_POST['fine_value'] ?? null,
    'discount_value' => $_POST['discount_value'] ?? null,
    'discount_days_before_due' => $_POST['discount_days_before_due'] ?? null,
];
```

**Novo Método: `toggleBillable()`**
```php
public function toggleBillable(): void
{
    Auth::requireInternal();
    
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $isBillable = (bool)($_POST['is_billable'] ?? false);
    
    if ($ticketId <= 0) {
        $this->json(['error' => 'ID inválido'], 400);
        return;
    }
    
    try {
        $db = DB::getConnection();
        
        if ($isBillable) {
            // Marcar como faturável com valores padrão
            $stmt = $db->prepare("
                UPDATE tickets 
                SET is_billable = 1, 
                    billing_status = 'pending',
                    updated_at = NOW()
                WHERE id = ?
            ");
        } else {
            // Desmarcar como faturável (só se não tiver cobrança gerada)
            $ticket = TicketService::findTicket($ticketId);
            if (!empty($ticket['billing_invoice_id'])) {
                $this->json(['error' => 'Não é possível desmarcar. Cobrança já foi gerada.'], 400);
                return;
            }
            
            $stmt = $db->prepare("
                UPDATE tickets 
                SET is_billable = 0, 
                    billing_status = NULL,
                    billed_value = NULL,
                    billing_due_date = NULL,
                    billing_notes = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ");
        }
        
        $stmt->execute([$ticketId]);
        $this->json(['success' => true]);
        
    } catch (\Exception $e) {
        error_log("Erro ao alternar faturável: " . $e->getMessage());
        $this->json(['error' => 'Erro ao atualizar ticket'], 500);
    }
}
```

#### 4. `public/index.php`
```php
$router->post('/tickets/toggle-billable', 'TicketController@toggleBillable');
```

#### 5. JavaScript (views/tickets/show.php)
```javascript
function toggleBillable(ticketId, isBillable) {
    const formData = new FormData();
    formData.append('ticket_id', ticketId);
    formData.append('is_billable', isBillable ? '1' : '0');
    
    fetch('<?= pixelhub_url('/tickets/toggle-billable') ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Erro ao atualizar');
            document.getElementById('billable-toggle').checked = !isBillable;
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao atualizar');
        document.getElementById('billable-toggle').checked = !isBillable;
    });
}

function toggleBillingSection() {
    const content = document.getElementById('billing-content');
    const icon = document.getElementById('billing-icon');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.textContent = '▼';
    } else {
        content.style.display = 'none';
        icon.textContent = '▸';
    }
}
```

---

## Checklist de Implementação

- [ ] Remover emoji colorido 💰
- [ ] Adicionar toggle "Faturável" no header
- [ ] Tornar seção faturamento colapsável
- [ ] Adicionar campo "Juros ao Mês (%)"
- [ ] Adicionar campo "Multa por Atraso (%)"
- [ ] Adicionar campo "Desconto (R$)"
- [ ] Adicionar campo "Dias para Desconto"
- [ ] Usar `billing_notes` como `description` no Asaas
- [ ] Passar campos Asaas (fine, interest, discount) ao createPayment
- [ ] Bloquear encerramento em `updateTicket()`
- [ ] Bloquear encerramento em `closeTicket()`
- [ ] Criar método `toggleBillable()` no controller
- [ ] Adicionar rota `/tickets/toggle-billable`
- [ ] Adicionar JavaScript `toggleBillable()`
- [ ] Adicionar JavaScript `toggleBillingSection()`
- [ ] Testar fluxo completo
- [ ] Documentar no README

---

## Testes Necessários

### Teste 1: Toggle Faturável
1. Abrir ticket não faturável
2. Marcar checkbox "Faturável"
3. Verificar se seção aparece
4. Desmarcar checkbox
5. Verificar se seção desaparece

### Teste 2: Gerar Cobrança com Juros/Multa/Desconto
1. Marcar ticket como faturável
2. Preencher valor R$ 150,00
3. Preencher observações: "Redirect de 3 domínios"
4. Gerar cobrança:
   - Forma: PIX
   - Juros: 1%
   - Multa: 2%
   - Desconto: R$ 10,00
   - Dias desconto: 5
5. Verificar no Asaas se campos foram enviados
6. Verificar se descrição = observações

### Teste 3: Bloquear Encerramento
1. Marcar ticket como faturável
2. Tentar mudar status para "Resolvido"
3. Verificar mensagem de erro
4. Gerar cobrança
5. Tentar encerrar novamente
6. Verificar mensagem de erro (ainda não pago)
7. Simular pagamento (webhook)
8. Encerrar ticket → deve funcionar

---

## Referências

- **API Asaas:** https://docs.asaas.com/reference/criar-nova-cobranca
- **Código Existente:** `src/Services/AsaasClient.php`
- **Billing Service:** `src/Services/BillingSenderService.php`
- **Documentação:** `docs/TICKETS_FATURAVEIS.md`
