<?php
/**
 * Modal/Página para cobrança via WhatsApp
 */
$baseUrl = pixelhub_url('');
?>

<div class="content-header">
    <h2>Cobrança por WhatsApp</h2>
    <?php if (($redirectTo ?? 'collections') === 'tenant'): ?>
        <a href="<?= pixelhub_url('/tenants/view?id=' . $tenant['id'] . '&tab=financial') ?>" style="color: #6c757d; text-decoration: none; font-size: 14px;">← Voltar para Cliente</a>
    <?php else: ?>
        <a href="<?= pixelhub_url('/billing/collections') ?>" style="color: #6c757d; text-decoration: none; font-size: 14px;">← Voltar para Cobranças</a>
    <?php endif; ?>
</div>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <h3 style="margin-bottom: 20px; color: #333; font-size: 18px;">Informações da Cobrança</h3>
    
    <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
        <div style="margin-bottom: 10px;">
            <strong>Cliente:</strong> 
            <a href="<?= pixelhub_url('/tenants/view?id=' . $tenant['id']) ?>" style="color: #023A8D; text-decoration: none;">
                <?= htmlspecialchars($tenant['name'] ?? 'N/A') ?>
            </a>
        </div>
        <div style="margin-bottom: 10px;">
            <strong>Fatura:</strong> 
            <?php
            $dueDate = $invoice['due_date'] ?? null;
            $dueDateFormatted = 'N/A';
            if ($dueDate) {
                try {
                    $date = new DateTime($dueDate);
                    $dueDateFormatted = $date->format('d/m/Y');
                } catch (Exception $e) {}
            }
            $amount = (float) ($invoice['amount'] ?? 0);
            ?>
            Vencimento: <?= $dueDateFormatted ?> | 
            Valor: R$ <?= number_format($amount, 2, ',', '.') ?>
        </div>
        <div>
            <strong>Estágio sugerido:</strong> 
            <span style="background: #023A8D; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px;">
                <?= htmlspecialchars($stageInfo['label']) ?>
            </span>
        </div>
    </div>

    <form method="POST" action="<?= pixelhub_url('/billing/whatsapp-sent') ?>">
        <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
        <input type="hidden" name="stage" value="<?= htmlspecialchars($stageInfo['stage']) ?>">
        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirectTo ?? 'collections') ?>">

        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555;">
                Telefone (WhatsApp):
            </label>
            <input type="text" 
                   name="phone" 
                   value="<?= htmlspecialchars($phoneNormalized ?? $phoneRaw ?? '') ?>" 
                   placeholder="5511999999999"
                   required
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            <small style="color: #6c757d; font-size: 12px;">
                Formato: 5511999999999 (DDI + DDD + número)
            </small>
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #555;">
                Mensagem:
            </label>
            <textarea name="message" 
                      rows="8" 
                      required
                      style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; font-family: inherit; resize: vertical;"><?= htmlspecialchars($message) ?></textarea>
            <small style="color: #6c757d; font-size: 12px;">
                Você pode editar a mensagem antes de enviar.
            </small>
        </div>

        <!-- Envio via Inbox (PRINCIPAL) -->
        <div style="margin-bottom: 20px; padding: 15px; background: #e8f5e9; border-left: 4px solid #4caf50; border-radius: 4px;">
            <strong style="display: block; margin-bottom: 10px; color: #2e7d32;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle; margin-right: 4px;">
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
                Enviar via Inbox (sessão pixel12digital)
            </strong>
            <p style="margin: 0 0 10px 0; color: #555; font-size: 13px;">
                Envia a mensagem automaticamente pelo WhatsApp Inbox, sem precisar abrir o WhatsApp Web.
                A mensagem será registrada e rastreável.
            </p>
            <button type="button" id="btnSendInbox"
                    style="background: #4caf50; color: white; padding: 12px 24px; border: none; border-radius: 4px; font-weight: 600; font-size: 16px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
                Enviar via Inbox
            </button>
            <div id="inboxSendResult" style="margin-top: 10px; display: none;"></div>
        </div>

        <!-- Fallback: WhatsApp Web (SECUNDÁRIO) -->
        <details style="margin-bottom: 20px;">
            <summary style="cursor: pointer; color: #6c757d; font-size: 13px; font-weight: 500; padding: 8px 0;">
                Fallback: Abrir no WhatsApp Web (manual)
            </summary>
            <div style="margin-top: 10px; padding: 15px; background: #e7f3ff; border-left: 4px solid #023A8D; border-radius: 4px;">
                <?php if ($whatsappLink): ?>
                    <a href="<?= htmlspecialchars($whatsappLink) ?>" 
                       target="_blank"
                       rel="noopener noreferrer"
                       style="display: inline-flex; align-items: center; gap: 8px; background: #023A8D; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: 500; font-size: 14px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                        Abrir WhatsApp Web
                    </a>
                    <p style="margin-top: 8px; color: #555; font-size: 12px;">
                        Após enviar pelo WhatsApp Web, volte e clique em "Salvar / Marcar como Enviado" abaixo.
                    </p>
                <?php else: ?>
                    <p style="color: #856404; font-size: 13px; margin: 0;">
                        Telefone não disponível para gerar link do WhatsApp Web.
                    </p>
                <?php endif; ?>
            </div>
        </details>

        <div style="margin-bottom: 20px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" 
                       name="mark_as_sent" 
                       value="1" 
                       checked
                       style="width: 18px; height: 18px; cursor: pointer;">
                <span style="font-weight: 500; color: #555;">Marcar como enviado ao salvar (apenas para envio manual via WhatsApp Web)</span>
            </label>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" 
                    style="background: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 4px; font-weight: 500; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                Salvar / Marcar como Enviado (manual)
            </button>
            <?php if (($redirectTo ?? 'collections') === 'tenant'): ?>
                <a href="<?= pixelhub_url('/tenants/view?id=' . $tenant['id'] . '&tab=financial') ?>" 
                   style="background: #e0e0e0; color: #333; padding: 12px 24px; border-radius: 4px; text-decoration: none; font-weight: 500; font-size: 14px; display: inline-block;">
                    Cancelar
                </a>
            <?php else: ?>
                <a href="<?= pixelhub_url('/billing/collections') ?>" 
                   style="background: #e0e0e0; color: #333; padding: 12px 24px; border-radius: 4px; text-decoration: none; font-weight: 500; font-size: 14px; display: inline-block;">
                    Cancelar
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
document.getElementById('btnSendInbox').addEventListener('click', function() {
    const btn = this;
    const resultDiv = document.getElementById('inboxSendResult');
    const message = document.querySelector('textarea[name="message"]').value;
    const tenantId = '<?= (int) $tenant['id'] ?>';
    const invoiceId = '<?= (int) $invoice['id'] ?>';
    const redirectTo = '<?= htmlspecialchars($redirectTo ?? 'collections') ?>';

    if (!message.trim()) {
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<div style="color: #c33; padding: 8px; background: #fee; border-radius: 4px;">Mensagem não pode estar vazia.</div>';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg> Enviando...';
    resultDiv.style.display = 'none';

    const formData = new FormData();
    formData.append('tenant_id', tenantId);
    formData.append('invoice_ids[]', invoiceId);
    formData.append('message', message);
    formData.append('redirect_to', redirectTo);

    fetch('<?= pixelhub_url('/billing/send-via-inbox') ?>', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        resultDiv.style.display = 'block';
        if (data.success) {
            resultDiv.innerHTML = '<div style="color: #2e7d32; padding: 10px; background: #e8f5e9; border-radius: 4px; font-weight: 500;">' +
                '&#10004; ' + (data.message || 'Enviado com sucesso!') + '</div>';
            btn.style.background = '#81c784';
            btn.innerHTML = '&#10004; Enviado';
        } else {
            resultDiv.innerHTML = '<div style="color: #c33; padding: 10px; background: #fee; border-radius: 4px;">' +
                '&#10008; ' + (data.message || 'Erro ao enviar') + '</div>';
            btn.disabled = false;
            btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Tentar Novamente';
        }
    })
    .catch(err => {
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<div style="color: #c33; padding: 10px; background: #fee; border-radius: 4px;">Erro de conexão: ' + err.message + '</div>';
        btn.disabled = false;
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Tentar Novamente';
    });
});
</script>
<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>

