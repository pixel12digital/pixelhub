<?php
ob_start();
$baseUrl = pixelhub_url('');
$lead = $lead ?? [];
$opportunities = $opportunities ?? [];
$backUrl = $backUrl ?? pixelhub_url('/opportunities');
$tenantId = $tenantId ?? null;
?>

<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <div>
        <a href="<?= htmlspecialchars($backUrl) ?>" style="color: #023A8D; text-decoration: none; font-size: 13px;">&larr; Voltar</a>
        <h2 style="margin-top: 6px;">
            <?= !empty($lead['name']) ? htmlspecialchars($lead['name']) : 'Lead #' . $lead['id'] ?>
        </h2>
    </div>
    <div>
        <span style="background: #1565c0; color: white; padding: 6px 16px; border-radius: 16px; font-size: 13px; font-weight: 600;">
            Lead
        </span>
    </div>
</div>

<?php if (isset($_GET['notice']) && $_GET['notice'] === 'no_opportunity'): ?>
    <div class="card" style="background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <p style="color: #856404; margin: 0; font-size:13px;">
            Este lead ainda não possui uma oportunidade vinculada.
        </p>
<?php
$_newOppUrl = '/opportunities?new=1&lead_id=' . $lead['id']
    . '&lead_name=' . urlencode($lead['name'] ?? $lead['company'] ?? 'Lead #' . $lead['id']);
if ($tenantId) {
    $_newOppUrl .= '&tenant_id=' . $tenantId;
}
?>
        <a href="<?= pixelhub_url($_newOppUrl) ?>"
           style="padding:8px 16px;background:#023A8D;color:#fff;border-radius:5px;font-size:13px;font-weight:600;text-decoration:none;white-space:nowrap;">
            + Criar Oportunidade
        </a>
    </div>
<?php endif; ?>

<?php if (isset($_GET['success'])): ?>
    <div class="card" style="background: #d4edda; border-left: 4px solid #28a745; margin-bottom: 20px;">
        <p style="color: #155724; margin: 0;">
            <?php
            if ($_GET['success'] === 'lead_updated') echo 'Lead atualizado com sucesso!';
            ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #f8d7da; border-left: 4px solid #dc3545; margin-bottom: 20px;">
        <p style="color: #721c24; margin: 0;">
            <?php
            if ($_GET['error'] === 'contact_required') echo 'Informe pelo menos um telefone ou e-mail.';
            elseif ($_GET['error'] === 'database_error') echo 'Erro ao salvar. Tente novamente.';
            elseif ($_GET['error'] === 'delete_failed') {
                $message = isset($_GET['message']) ? urldecode($_GET['message']) : 'Erro ao excluir lead.';
                echo htmlspecialchars($message);
            }
            else echo 'Erro desconhecido.';
            ?>
        </p>
    </div>
<?php endif; ?>

<div style="display: flex; gap: 20px; flex-wrap: wrap;">
    <!-- Coluna esquerda: Formulário -->
    <div style="flex: 2; min-width: 300px;">
        <div class="card">
            <h3 style="margin: 0 0 16px 0; font-size: 16px; color: #333;">Dados do Lead</h3>
            
            <form method="POST" action="<?= pixelhub_url('/leads/update') ?>">
                <input type="hidden" name="id" value="<?= $lead['id'] ?>">
                <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($backUrl) ?>">
                
                <div style="margin-bottom: 14px;">
                    <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555;">Nome</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($lead['name'] ?? '') ?>" 
                           style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;"
                           placeholder="Nome do lead">
                </div>
                
                <div style="margin-bottom: 14px;">
                    <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555;">Empresa</label>
                    <input type="text" name="company" value="<?= htmlspecialchars($lead['company'] ?? '') ?>" 
                           style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;"
                           placeholder="Nome da empresa (opcional)">
                </div>
                
                <div style="display: flex; gap: 12px; margin-bottom: 14px;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555;">Telefone *</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($lead['phone'] ?? '') ?>" 
                               style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;"
                               placeholder="(00) 00000-0000">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555;">E-mail *</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($lead['email'] ?? '') ?>" 
                               style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;"
                               placeholder="email@exemplo.com">
                    </div>
                </div>
                
                <?php
use PixelHub\Services\OriginCatalog;
?>
                <div style="margin-bottom: 14px;">
                    <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555;">Origem</label>
                    <select name="source" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                        <option value="">Selecione...</option>
                        <?php
                        $origins = OriginCatalog::getForSelect($lead['source'] ?? '');
                        foreach ($origins as $key => $label):
                            if ($key === '') continue; // Pular opção vazia
                        ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= ($key === ($lead['source'] ?? '')) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-bottom: 14px;">
                    <label style="display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #555;">Observações</label>
                    <textarea name="notes" rows="4" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; resize: vertical;"
                              placeholder="Notas sobre este lead..."><?= htmlspecialchars($lead['notes'] ?? '') ?></textarea>
                </div>
                
                <div style="margin-top: 16px;">
                    <button type="submit" style="padding: 10px 24px; background: #023A8D; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                        Salvar Alterações
                    </button>
                    <a href="<?= htmlspecialchars($backUrl) ?>" 
                       style="margin-left: 12px; padding: 10px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Coluna direita: Informações -->
    <div style="flex: 1; min-width: 280px;">
        <div class="card">
            <h3 style="margin: 0 0 16px 0; font-size: 16px; color: #333;">Informações</h3>
            
            <div style="margin-bottom: 16px;">
                <div style="font-size: 12px; color: #666; margin-bottom: 4px;">ID do Lead</div>
                <div style="font-weight: 600; color: #333;">#<?= $lead['id'] ?></div>
            </div>
            
            <div style="margin-bottom: 16px;">
                <div style="font-size: 12px; color: #666; margin-bottom: 4px;">Status</div>
                <div style="font-weight: 600; color: #333; text-transform: capitalize;"><?= $lead['status'] ?? 'new' ?></div>
            </div>
            
            <div style="margin-bottom: 16px;">
                <div style="font-size: 12px; color: #666; margin-bottom: 4px;">Criado em</div>
                <div style="font-weight: 600; color: #333;">
                    <?= date('d/m/Y H:i', strtotime($lead['created_at'])) ?>
                </div>
            </div>
            
            <div style="margin-bottom: 16px;">
                <div style="font-size: 12px; color: #666; margin-bottom: 4px;">Atualizado em</div>
                <div style="font-weight: 600; color: #333;">
                    <?= date('d/m/Y H:i', strtotime($lead['updated_at'])) ?>
                </div>
            </div>
            
            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 16px 0;">
            
            <div>
                <button type="button" onclick="confirmDeleteLead()" 
                        style="width: 100%; padding: 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px;">
                    Excluir Lead
                </button>
            </div>
        </div>
        
        <!-- Oportunidades vinculadas -->
        <?php if (!empty($opportunities)): ?>
        <div class="card" style="margin-top: 20px;">
            <h3 style="margin: 0 0 16px 0; font-size: 16px; color: #333;">Oportunidades Vinculadas</h3>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($opportunities as $opp): ?>
                    <div style="padding: 10px; background: #f8f9fa; border-left: 3px solid #023A8D; border-radius: 4px;">
                        <div style="font-weight: 600; font-size: 13px; color: #333;">
                            <a href="<?= pixelhub_url('/opportunities/view?id=' . $opp['id']) ?>" 
                               style="color: #023A8D; text-decoration: none;">
                                <?= htmlspecialchars($opp['name']) ?>
                            </a>
                        </div>
                        <div style="font-size: 12px; color: #666; margin-top: 4px;">
                            <?= ucfirst($opp['stage'] ?? 'new') ?> • <?= ucfirst($opp['status'] ?? 'active') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}
</style>

<!-- Hidden form for deletion -->
<form id="deleteLeadForm" method="POST" action="<?= pixelhub_url('/leads/delete') ?>" style="display: none;">
    <input type="hidden" name="id" value="<?= $lead['id'] ?>">
    <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($backUrl) ?>">
</form>

<script>
function confirmDeleteLead() {
    const leadName = <?= json_encode($lead['name'] ?? 'Lead #' . $lead['id']) ?>;
    const hasOpportunities = <?= !empty($opportunities) ? 'true' : 'false' ?>;
    
    let message = 'Tem certeza que deseja excluir o lead "' + leadName + '"?\n\n';
    
    if (hasOpportunities) {
        message += 'ATENÇÃO: Este lead possui oportunidades vinculadas que também serão excluídas.\n\n';
    }
    
    message += 'Esta ação não pode ser desfeita.';
    
    if (confirm(message)) {
        document.getElementById('deleteLeadForm').submit();
    }
}
</script>

<?php
$content = ob_get_clean();
$title = (!empty($lead['name']) ? htmlspecialchars($lead['name']) : 'Lead #' . $lead['id']) . ' — Lead — Pixel Hub';
require __DIR__ . '/../layout/main.php';
?>
