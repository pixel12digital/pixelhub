<?php
use PixelHub\Core\Auth;
use PixelHub\Services\MetaTemplateService;

Auth::requireInternal();

$templateId = (int) ($_GET['id'] ?? 0);

if (!$templateId) {
    header('Location: ' . pixelhub_url('/settings/whatsapp-providers'));
    exit;
}

$template = MetaTemplateService::getById($templateId);

if (!$template) {
    $_SESSION['error'] = 'Template não encontrado';
    header('Location: ' . pixelhub_url('/settings/whatsapp-providers'));
    exit;
}

ob_start();

$statusColors = [
    'draft' => '#6c757d',
    'pending' => '#ffc107',
    'approved' => '#28a745',
    'rejected' => '#dc3545'
];

$statusLabels = [
    'draft' => 'Rascunho',
    'pending' => 'Pendente',
    'approved' => 'Aprovado',
    'rejected' => 'Rejeitado'
];

$categoryLabels = [
    'marketing' => 'Marketing',
    'utility' => 'Utilidade',
    'authentication' => 'Autenticação'
];

$buttons = !empty($template['buttons']) ? json_decode($template['buttons'], true) : [];
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0">
                <i class="fas fa-eye text-muted"></i>
                <?= htmlspecialchars($template['template_name']) ?>
            </h1>
            <p class="text-muted mb-0">Visualização do template WhatsApp Business</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?= pixelhub_url('/settings/whatsapp-providers') ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            <?php if ($template['status'] !== 'approved'): ?>
                <a href="<?= pixelhub_url('/whatsapp/templates/edit?id=' . $template['id']) ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Editar
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Informações Básicas -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Informações Básicas</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Nome do Template</label>
                            <div class="fw-bold"><?= htmlspecialchars($template['template_name']) ?></div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="text-muted small">Categoria</label>
                            <div>
                                <span class="badge bg-info">
                                    <?= $categoryLabels[$template['category']] ?? $template['category'] ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="text-muted small">Idioma</label>
                            <div class="fw-bold"><?= htmlspecialchars($template['language']) ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Status</label>
                            <div>
                                <span class="badge" style="background: <?= $statusColors[$template['status']] ?>;">
                                    <?= $statusLabels[$template['status']] ?? $template['status'] ?>
                                </span>
                            </div>
                        </div>
                        <?php if (!empty($template['meta_template_id'])): ?>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small">ID Meta</label>
                                <div class="fw-bold font-monospace"><?= htmlspecialchars($template['meta_template_id']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Conteúdo -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Conteúdo da Mensagem</h5>
                    
                    <?php if (!empty($template['header_type']) && $template['header_type'] !== 'none'): ?>
                        <div class="mb-3">
                            <label class="text-muted small">Cabeçalho (<?= ucfirst($template['header_type']) ?>)</label>
                            <div class="p-3 bg-light rounded">
                                <?php if ($template['header_type'] === 'text'): ?>
                                    <?= nl2br(htmlspecialchars($template['header_content'] ?? '')) ?>
                                <?php else: ?>
                                    <em>Mídia: <?= htmlspecialchars($template['header_content'] ?? 'URL da mídia') ?></em>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="text-muted small">Corpo da Mensagem</label>
                        <div class="p-3 bg-light rounded" style="white-space: pre-wrap;"><?= htmlspecialchars($template['content']) ?></div>
                    </div>

                    <?php if (!empty($template['footer_text'])): ?>
                        <div class="mb-3">
                            <label class="text-muted small">Rodapé</label>
                            <div class="p-2 bg-light rounded text-muted small">
                                <?= htmlspecialchars($template['footer_text']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Botões -->
            <?php if (!empty($buttons)): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Botões Interativos</h5>
                        <div class="list-group">
                            <?php foreach ($buttons as $button): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= htmlspecialchars($button['text']) ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                Tipo: <?= htmlspecialchars($button['type']) ?>
                                                <?php if (!empty($button['id'])): ?>
                                                    | ID: <code><?= htmlspecialchars($button['id']) ?></code>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-secondary"><?= ucfirst($button['type']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <!-- Preview -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Preview WhatsApp</h5>
                    <div class="whatsapp-preview" style="background: #e5ddd5; padding: 20px; border-radius: 8px;">
                        <div style="background: white; padding: 12px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                            <?php if (!empty($template['header_type']) && $template['header_type'] === 'text'): ?>
                                <div style="font-weight: bold; margin-bottom: 8px;">
                                    <?= nl2br(htmlspecialchars($template['header_content'] ?? '')) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div style="margin-bottom: 8px; line-height: 1.4;">
                                <?= nl2br(htmlspecialchars($template['content'])) ?>
                            </div>
                            
                            <?php if (!empty($template['footer_text'])): ?>
                                <div style="font-size: 12px; color: #667781; margin-top: 8px;">
                                    <?= htmlspecialchars($template['footer_text']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($buttons)): ?>
                                <div style="margin-top: 12px; border-top: 1px solid #e9ecef; padding-top: 8px;">
                                    <?php foreach ($buttons as $button): ?>
                                        <div style="text-align: center; padding: 8px; color: #00a5f4; font-weight: 500; cursor: pointer;">
                                            <?= htmlspecialchars($button['text']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Metadados -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Metadados</h5>
                    <div class="mb-2">
                        <small class="text-muted">Criado em</small>
                        <div><?= date('d/m/Y H:i', strtotime($template['created_at'])) ?></div>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">Atualizado em</small>
                        <div><?= date('d/m/Y H:i', strtotime($template['updated_at'])) ?></div>
                    </div>
                    <?php if ($template['submitted_at']): ?>
                        <div class="mb-2">
                            <small class="text-muted">Submetido em</small>
                            <div><?= date('d/m/Y H:i', strtotime($template['submitted_at'])) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($template['approved_at']): ?>
                        <div class="mb-2">
                            <small class="text-muted">Aprovado em</small>
                            <div><?= date('d/m/Y H:i', strtotime($template['approved_at'])) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($template['rejected_at']): ?>
                        <div class="mb-2">
                            <small class="text-muted">Rejeitado em</small>
                            <div><?= date('d/m/Y H:i', strtotime($template['rejected_at'])) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($template['rejection_reason'])): ?>
                        <div class="mb-2">
                            <small class="text-muted">Motivo da Rejeição</small>
                            <div class="text-danger"><?= htmlspecialchars($template['rejection_reason']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Prepara dados para o modal Nova Mensagem
use PixelHub\Core\DB;
$db = DB::getConnection();

// Busca tenants para o dropdown
$stmt = $db->query("SELECT id, name, phone FROM tenants WHERE status = 'active' ORDER BY name");
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca sessões WhatsApp
$stmt = $db->query("SELECT id, channel_id, is_enabled FROM tenant_message_channels WHERE provider = 'wpp_gateway' ORDER BY channel_id");
$whatsapp_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Inclui o modal
ob_start();
require __DIR__ . '/../partials/new_message_modal.php';
$modal = ob_get_clean();

$content .= $modal;

require __DIR__ . '/../../layout/main.php';
?>
