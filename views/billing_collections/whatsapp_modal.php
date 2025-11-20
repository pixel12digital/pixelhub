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

        <?php if ($whatsappLink): ?>
            <div style="margin-bottom: 20px; padding: 15px; background: #e7f3ff; border-left: 4px solid #023A8D; border-radius: 4px;">
                <strong style="display: block; margin-bottom: 10px; color: #023A8D;">Passo 1: Abrir WhatsApp Web</strong>
                <a href="<?= htmlspecialchars($whatsappLink) ?>" 
                   target="_blank"
                   rel="noopener noreferrer"
                   style="display: inline-block; background: #25D366; color: white; padding: 12px 24px; border-radius: 4px; text-decoration: none; font-weight: 500; font-size: 16px;">
                    📱 Abrir WhatsApp Web
                </a>
                <p style="margin-top: 10px; color: #555; font-size: 13px;">
                    Isso abrirá uma nova aba com a conversa pronta. Após enviar a mensagem, volte aqui e clique em "Salvar".
                </p>
            </div>
        <?php else: ?>
            <div style="margin-bottom: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                <strong style="color: #856404;">⚠️ Telefone não disponível</strong>
                <p style="margin-top: 5px; color: #856404; font-size: 13px;">
                    O cliente não possui telefone cadastrado. Adicione um telefone válido acima para gerar o link do WhatsApp.
                </p>
            </div>
        <?php endif; ?>

        <div style="margin-bottom: 20px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" 
                       name="mark_as_sent" 
                       value="1" 
                       checked
                       style="width: 18px; height: 18px; cursor: pointer;">
                <span style="font-weight: 500; color: #555;">Marcar como enviado ao salvar</span>
            </label>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" 
                    style="background: #023A8D; color: white; padding: 12px 24px; border: none; border-radius: 4px; font-weight: 500; font-size: 16px; cursor: pointer;">
                💾 Salvar / Marcar como Enviado
            </button>
            <?php if (($redirectTo ?? 'collections') === 'tenant'): ?>
                <a href="<?= pixelhub_url('/tenants/view?id=' . $tenant['id'] . '&tab=financial') ?>" 
                   style="background: #6c757d; color: white; padding: 12px 24px; border-radius: 4px; text-decoration: none; font-weight: 500; font-size: 16px; display: inline-block;">
                    Cancelar
                </a>
            <?php else: ?>
                <a href="<?= pixelhub_url('/billing/collections') ?>" 
                   style="background: #6c757d; color: white; padding: 12px 24px; border-radius: 4px; text-decoration: none; font-weight: 500; font-size: 16px; display: inline-block;">
                    Cancelar
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

