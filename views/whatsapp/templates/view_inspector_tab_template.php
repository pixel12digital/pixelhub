<?php
// Tab Template - Conteúdo Estático do Template
// Este arquivo é incluído dentro de view_inspector.php
?>

<style>
/* Template Tab Specific Styles */
.template-info-grid {
    display: grid;
    grid-template-columns: 140px 1fr;
    gap: 12px 16px;
    font-size: 14px;
}

.template-info-grid .label {
    color: #6c757d;
    font-weight: 500;
}

.template-info-grid .value {
    color: #2c3e50;
    font-weight: 400;
}

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

<div class="row">
    <div class="col-md-8">
        <!-- Informações do Template -->
        <div class="inspector-card">
            <h6 class="inspector-card-title">Informações do Template</h6>
            <div class="template-info-grid">
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

        <!-- Conteúdo da Mensagem -->
        <div class="inspector-card">
            <h6 class="inspector-card-title">Conteúdo da Mensagem</h6>
            
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

        <!-- Botões Interativos -->
        <?php if (!empty($buttons)): ?>
            <div class="inspector-card">
                <h6 class="inspector-card-title">Botões Interativos</h6>
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
        <?php endif; ?>
    </div>

    <div class="col-md-4">
        <!-- Preview WhatsApp -->
        <div class="inspector-card">
            <h6 class="inspector-card-title">Preview WhatsApp</h6>
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

        <!-- Metadados Colapsáveis -->
        <div class="inspector-card">
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
