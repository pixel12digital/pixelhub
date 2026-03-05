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

<style>
/* Cards profissionais e compactos */
.template-view-card {
    background: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}

.template-view-card .card-body {
    padding: 20px;
}

.template-view-card .card-title {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}

/* Grid compacto para informações */
.info-grid {
    display: grid;
    grid-template-columns: 140px 1fr;
    gap: 12px 16px;
    font-size: 14px;
}

.info-grid .label {
    color: #6c757d;
    font-weight: 500;
}

.info-grid .value {
    color: #2c3e50;
    font-weight: 400;
}

/* Conteúdo da mensagem formatado */
.message-content {
    background: #f8f9fa;
    border-left: 4px solid #4a90e2;
    padding: 16px;
    border-radius: 6px;
    line-height: 1.6;
    white-space: pre-wrap;
    font-size: 14px;
    color: #2c3e50;
}

/* Botões interativos melhorados */
.button-preview {
    background: #ffffff;
    border: 2px solid #4a90e2;
    border-radius: 6px;
    padding: 12px 16px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s ease;
}

.button-preview:hover {
    background: #f0f7ff;
    transform: translateY(-1px);
}

.button-preview .button-text {
    font-weight: 600;
    color: #2c3e50;
    font-size: 14px;
}

.button-preview .button-type {
    font-size: 12px;
    color: #6c757d;
    background: #e9ecef;
    padding: 4px 8px;
    border-radius: 4px;
}

/* Preview WhatsApp compacto */
.whatsapp-mock {
    max-width: 380px;
    margin: 0 auto;
    background: #e5ddd5;
    padding: 16px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.whatsapp-bubble {
    background: #ffffff;
    padding: 12px 14px;
    border-radius: 8px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    position: relative;
}

.whatsapp-bubble::before {
    content: '';
    position: absolute;
    top: 0;
    right: -8px;
    width: 0;
    height: 0;
    border-left: 8px solid #ffffff;
    border-top: 8px solid transparent;
}

.whatsapp-header {
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 14px;
    color: #2c3e50;
}

.whatsapp-body {
    margin-bottom: 8px;
    line-height: 1.4;
    font-size: 14px;
    color: #2c3e50;
}

.whatsapp-footer {
    font-size: 12px;
    color: #667781;
    margin-top: 8px;
}

.whatsapp-buttons {
    margin-top: 12px;
    border-top: 1px solid #e9ecef;
    padding-top: 8px;
}

.whatsapp-button {
    text-align: center;
    padding: 10px;
    color: #00a5f4;
    font-weight: 500;
    cursor: pointer;
    border-bottom: 1px solid #e9ecef;
    font-size: 14px;
}

.whatsapp-button:last-child {
    border-bottom: none;
}

.whatsapp-button:hover {
    background: #f0f7ff;
}

/* Metadados colapsáveis */
.metadata-toggle {
    cursor: pointer;
    user-select: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #e0e0e0;
}

.metadata-toggle:hover {
    color: #4a90e2;
}

.metadata-content {
    padding-top: 16px;
    display: none;
}

.metadata-content.show {
    display: block;
}

.metadata-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: 13px;
}

.metadata-item:last-child {
    border-bottom: none;
}

.metadata-item .label {
    color: #6c757d;
    font-weight: 500;
}

.metadata-item .value {
    color: #2c3e50;
}

/* Ações rápidas */
.quick-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 24px;
}

.btn-action {
    padding: 10px 16px;
    border-radius: 6px;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.2s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-action-primary {
    background: #4a90e2;
    color: white;
    border: none;
}

.btn-action-primary:hover {
    background: #357abd;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(74, 144, 226, 0.3);
}

.btn-action-secondary {
    background: transparent;
    color: #6c757d;
    border: 2px solid #6c757d;
}

.btn-action-secondary:hover {
    background: #6c757d;
    color: white;
    transform: translateY(-1px);
}

/* Badges melhorados */
.status-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    display: inline-block;
}

.category-badge {
    background: #e7f3ff;
    color: #2c5282;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    display: inline-block;
}
</style>

<div class="container-fluid py-3">
    <!-- Cabeçalho com título e ações rápidas -->
    <div class="row mb-3">
        <div class="col-12">
            <h1 class="h4 mb-2">
                <i class="fas fa-eye text-primary"></i>
                <?= htmlspecialchars($template['template_name']) ?>
            </h1>
            <p class="text-muted small mb-3">Visualização do template WhatsApp Business</p>
            
            <div class="quick-actions">
                <a href="<?= pixelhub_url('/settings/whatsapp-providers') ?>" class="btn-action btn-action-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
                <?php if ($template['status'] !== 'approved'): ?>
                    <a href="<?= pixelhub_url('/whatsapp/templates/edit?id=' . $template['id']) ?>" class="btn-action btn-action-primary">
                        <i class="fas fa-edit"></i> Editar Template
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Informações do Template -->
            <div class="card template-view-card">
                <div class="card-body">
                    <h6 class="card-title">Informações do Template</h6>
                    <div class="info-grid">
                        <div class="label">Nome</div>
                        <div class="value"><code><?= htmlspecialchars($template['template_name']) ?></code></div>
                        
                        <div class="label">Categoria</div>
                        <div class="value">
                            <span class="category-badge">
                                <?= $categoryLabels[$template['category']] ?? $template['category'] ?>
                            </span>
                        </div>
                        
                        <div class="label">Idioma</div>
                        <div class="value"><?= htmlspecialchars($template['language']) ?></div>
                        
                        <div class="label">Status</div>
                        <div class="value">
                            <span class="status-badge" style="background: <?= $statusColors[$template['status']] ?>; color: white;">
                                <?= $statusLabels[$template['status']] ?? $template['status'] ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($template['meta_template_id'])): ?>
                            <div class="label">ID Meta</div>
                            <div class="value"><code><?= htmlspecialchars($template['meta_template_id']) ?></code></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Conteúdo da Mensagem -->
            <div class="card template-view-card">
                <div class="card-body">
                    <h6 class="card-title">Conteúdo da Mensagem</h6>
                    
                    <?php if (!empty($template['header_type']) && $template['header_type'] !== 'none'): ?>
                        <div class="mb-3">
                            <small class="text-muted d-block mb-2">Cabeçalho (<?= ucfirst($template['header_type']) ?>)</small>
                            <div class="message-content">
                                <?php if ($template['header_type'] === 'text'): ?>
                                    <?= nl2br(htmlspecialchars($template['header_content'] ?? '')) ?>
                                <?php else: ?>
                                    <em>Mídia: <?= htmlspecialchars($template['header_content'] ?? 'URL da mídia') ?></em>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <small class="text-muted d-block mb-2">Corpo da Mensagem</small>
                        <div class="message-content"><?= htmlspecialchars($template['content']) ?></div>
                    </div>

                    <?php if (!empty($template['footer_text'])): ?>
                        <div>
                            <small class="text-muted d-block mb-2">Rodapé</small>
                            <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; font-size: 13px; color: #6c757d;">
                                <?= htmlspecialchars($template['footer_text']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Botões Interativos -->
            <?php if (!empty($buttons)): ?>
                <div class="card template-view-card">
                    <div class="card-body">
                        <h6 class="card-title">Botões Interativos</h6>
                        <?php foreach ($buttons as $button): ?>
                            <div class="button-preview">
                                <div>
                                    <div class="button-text"><?= htmlspecialchars($button['text']) ?></div>
                                    <?php if (!empty($button['id'])): ?>
                                        <small class="text-muted" style="font-size: 11px;">ID: <code><?= htmlspecialchars($button['id']) ?></code></small>
                                    <?php endif; ?>
                                </div>
                                <span class="button-type"><?= ucfirst($button['type']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <!-- Preview WhatsApp -->
            <div class="card template-view-card">
                <div class="card-body">
                    <h6 class="card-title">Preview WhatsApp</h6>
                    <div class="whatsapp-mock">
                        <div class="whatsapp-bubble">
                            <?php if (!empty($template['header_type']) && $template['header_type'] === 'text'): ?>
                                <div class="whatsapp-header">
                                    <?= nl2br(htmlspecialchars($template['header_content'] ?? '')) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="whatsapp-body">
                                <?= nl2br(htmlspecialchars($template['content'])) ?>
                            </div>
                            
                            <?php if (!empty($template['footer_text'])): ?>
                                <div class="whatsapp-footer">
                                    <?= htmlspecialchars($template['footer_text']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($buttons)): ?>
                                <div class="whatsapp-buttons">
                                    <?php foreach ($buttons as $button): ?>
                                        <div class="whatsapp-button">
                                            <?= htmlspecialchars($button['text']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Metadados Colapsáveis -->
            <div class="card template-view-card">
                <div class="card-body">
                    <div class="metadata-toggle" onclick="toggleMetadata()">
                        <h6 class="mb-0">Metadados</h6>
                        <i class="fas fa-chevron-down" id="metadataIcon"></i>
                    </div>
                    <div class="metadata-content" id="metadataContent">
                        <div class="metadata-item">
                            <span class="label">Criado em</span>
                            <span class="value"><?= date('d/m/Y H:i', strtotime($template['created_at'])) ?></span>
                        </div>
                        <div class="metadata-item">
                            <span class="label">Atualizado em</span>
                            <span class="value"><?= date('d/m/Y H:i', strtotime($template['updated_at'])) ?></span>
                        </div>
                        <?php if ($template['submitted_at']): ?>
                            <div class="metadata-item">
                                <span class="label">Submetido em</span>
                                <span class="value"><?= date('d/m/Y H:i', strtotime($template['submitted_at'])) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($template['approved_at']): ?>
                            <div class="metadata-item">
                                <span class="label">Aprovado em</span>
                                <span class="value"><?= date('d/m/Y H:i', strtotime($template['approved_at'])) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($template['rejected_at']): ?>
                            <div class="metadata-item">
                                <span class="label">Rejeitado em</span>
                                <span class="value"><?= date('d/m/Y H:i', strtotime($template['rejected_at'])) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($template['rejection_reason'])): ?>
                            <div class="metadata-item">
                                <span class="label">Motivo</span>
                                <span class="value text-danger"><?= htmlspecialchars($template['rejection_reason']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleMetadata() {
    const content = document.getElementById('metadataContent');
    const icon = document.getElementById('metadataIcon');
    
    if (content.classList.contains('show')) {
        content.classList.remove('show');
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    } else {
        content.classList.add('show');
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    }
}
</script>

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
require __DIR__ . '/../../partials/new_message_modal.php';
$modal = ob_get_clean();

$content .= $modal;

require __DIR__ . '/../../layout/main.php';
?>
