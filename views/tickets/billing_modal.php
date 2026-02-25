<!-- Modal de Faturamento (Reutilizável) -->
<div id="billingModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; overflow-y: auto;">
    <div style="max-width: 800px; margin: 50px auto; background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <!-- Header -->
        <div style="padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin: 0; color: #023A8D;">Faturamento do Ticket</h2>
            <button onclick="closeBillingModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
        </div>
        
        <!-- Body -->
        <form id="billingModalForm" method="POST" action="<?= pixelhub_url('/tickets/process-billing-and-close') ?>">
            <input type="hidden" name="ticket_id" id="billing_ticket_id" value="">
            
            <div style="padding: 30px;">
                <!-- Pergunta inicial -->
                <div id="billing_question" style="margin-bottom: 30px;">
                    <h3 style="margin: 0 0 15px 0; color: #333;">Este ticket é faturável?</h3>
                    <div style="display: flex; gap: 15px;">
                        <label style="display: flex; align-items: center; cursor: pointer; padding: 15px 25px; border: 2px solid #ddd; border-radius: 8px; transition: all 0.3s;">
                            <input type="radio" name="is_billable" value="1" onchange="toggleBillingFields(true)" style="margin-right: 10px;">
                            <span style="font-weight: 600;">Sim, é faturável</span>
                        </label>
                        <label style="display: flex; align-items: center; cursor: pointer; padding: 15px 25px; border: 2px solid #ddd; border-radius: 8px; transition: all 0.3s;">
                            <input type="radio" name="is_billable" value="0" onchange="toggleBillingFields(false)" style="margin-right: 10px;">
                            <span style="font-weight: 600;">Não, encerrar sem cobrança</span>
                        </label>
                    </div>
                </div>
                
                <!-- Campos de faturamento (aparecem se faturável) -->
                <div id="billing_fields" style="display: none;">
                    <!-- Valor e Vencimento -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                                Valor da Cobrança (R$) *
                            </label>
                            <input 
                                type="number" 
                                name="billed_value" 
                                id="billed_value"
                                step="0.01" 
                                min="0.01"
                                placeholder="150.00"
                                onchange="updateInstallmentOptions()"
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                            >
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                                Data de Vencimento
                            </label>
                            <input 
                                type="date" 
                                name="billing_due_date"
                                value="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                            >
                        </div>
                    </div>
                    
                    <!-- Observações -->
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                            Observações (descrição da cobrança)
                        </label>
                        <textarea 
                            name="billing_notes"
                            rows="3"
                            placeholder="Ex: Serviço de redirect de 3 domínios (301 permanente)"
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; resize: vertical;"
                        ></textarea>
                    </div>
                    
                    <!-- Forma de Pagamento e Parcelas -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                                Forma de Pagamento *
                            </label>
                            <select 
                                name="billing_type"
                                id="modal_billing_type"
                                onchange="toggleModalInstallmentField()"
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                            >
                                <option value="">Selecione</option>
                                <option value="BOLETO">Boleto Bancário / Pix</option>
                                <option value="CREDIT_CARD">Cartão de Crédito</option>
                                <option value="UNDEFINED">Pergunte ao cliente</option>
                            </select>
                        </div>
                        
                        <div id="modal_installment_field" style="display: none;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                                Parcelas
                            </label>
                            <select 
                                name="installment_count"
                                id="modal_installment_count"
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                            >
                                <option value="1">À vista</option>
                                <!-- Opções serão preenchidas via JS -->
                            </select>
                        </div>
                    </div>
                    
                    <!-- Juros e Multa -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                                Juros ao Mês (%)
                            </label>
                            <input 
                                type="number" 
                                name="interest_value"
                                step="0.01"
                                min="0"
                                max="100"
                                value="1.00"
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                            >
                            <small style="color: #666; display: block; margin-top: 3px;">Padrão: 1% a.m.</small>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                                Multa por Atraso (%)
                            </label>
                            <input 
                                type="number" 
                                name="fine_value"
                                step="0.01"
                                min="0"
                                max="100"
                                value="2.00"
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                            >
                            <small style="color: #666; display: block; margin-top: 3px;">Padrão: 2%</small>
                        </div>
                    </div>
                    
                    <!-- Desconto -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                                Tipo de Desconto
                            </label>
                            <select 
                                name="discount_type"
                                id="modal_discount_type"
                                onchange="updateModalDiscountLabel()"
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                            >
                                <option value="">Sem desconto</option>
                                <option value="PERCENTAGE">Percentual</option>
                                <option value="FIXED">Valor fixo</option>
                            </select>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                                <span id="modal_discount_label">Valor do Desconto</span>
                            </label>
                            <input 
                                type="number" 
                                name="discount_value"
                                id="modal_discount_value"
                                step="0.01"
                                min="0"
                                placeholder="Ex: 10.00"
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                            >
                            <small style="color: #666; display: block; margin-top: 3px;" id="modal_discount_hint">Opcional</small>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">
                                Prazo do Desconto (dias)
                            </label>
                            <input 
                                type="number" 
                                name="discount_days_before_due"
                                min="0"
                                placeholder="Ex: 5"
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                            >
                            <small style="color: #666; display: block; margin-top: 3px;">Dias antes do vencimento</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div style="padding: 20px; border-top: 1px solid #ddd; display: flex; justify-content: flex-end; gap: 10px; background: #f9f9f9;">
                <button type="button" onclick="closeBillingModal()" class="btn btn-secondary" style="margin: 0;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary" style="margin: 0;">
                    Processar e Encerrar Ticket
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Abre modal de faturamento
function openBillingModal(ticketId) {
    document.getElementById('billing_ticket_id').value = ticketId;
    document.getElementById('billingModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

// Fecha modal de faturamento
function closeBillingModal() {
    document.getElementById('billingModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    document.getElementById('billingModalForm').reset();
    document.getElementById('billing_fields').style.display = 'none';
}

// Toggle campos de faturamento
function toggleBillingFields(show) {
    const fields = document.getElementById('billing_fields');
    const billedValue = document.getElementById('billed_value');
    
    if (show) {
        fields.style.display = 'block';
        billedValue.setAttribute('required', 'required');
    } else {
        fields.style.display = 'none';
        billedValue.removeAttribute('required');
    }
}

// Toggle campo de parcelas no modal
function toggleModalInstallmentField() {
    const billingType = document.getElementById('modal_billing_type');
    const installmentField = document.getElementById('modal_installment_field');
    
    if (!billingType || !installmentField) return;
    
    const type = billingType.value;
    
    if (type === 'CREDIT_CARD' || type === 'BOLETO') {
        installmentField.style.display = 'block';
    } else {
        installmentField.style.display = 'none';
    }
}

// Atualiza opções de parcelas conforme valor
function updateInstallmentOptions() {
    const value = parseFloat(document.getElementById('billed_value').value) || 0;
    const select = document.getElementById('modal_installment_count');
    
    if (!select || value <= 0) return;
    
    let html = '<option value="1">À vista (R$ ' + value.toFixed(2).replace('.', ',') + ')</option>';
    
    for (let i = 2; i <= 12; i++) {
        const installmentValue = value / i;
        html += '<option value="' + i + '">' + i + 'x de R$ ' + installmentValue.toFixed(2).replace('.', ',') + '</option>';
    }
    
    select.innerHTML = html;
}

// Atualiza label do desconto no modal
function updateModalDiscountLabel() {
    const discountType = document.getElementById('modal_discount_type');
    const discountLabel = document.getElementById('modal_discount_label');
    const discountHint = document.getElementById('modal_discount_hint');
    const discountValue = document.getElementById('modal_discount_value');
    
    if (!discountType || !discountLabel || !discountHint) return;
    
    const type = discountType.value;
    
    if (type === 'PERCENTAGE') {
        discountLabel.textContent = 'Desconto (%)';
        discountHint.textContent = 'Percentual de desconto';
        discountValue.placeholder = 'Ex: 10.00';
        discountValue.max = '100';
    } else if (type === 'FIXED') {
        discountLabel.textContent = 'Desconto (R$)';
        discountHint.textContent = 'Valor fixo em reais';
        discountValue.placeholder = 'Ex: 50.00';
        discountValue.removeAttribute('max');
    } else {
        discountLabel.textContent = 'Valor do Desconto';
        discountHint.textContent = 'Opcional';
        discountValue.placeholder = 'Ex: 10.00';
        discountValue.removeAttribute('max');
    }
}
</script>
