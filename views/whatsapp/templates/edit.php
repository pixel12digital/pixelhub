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

if ($template['status'] === 'approved') {
    $_SESSION['error'] = 'Templates aprovados não podem ser editados';
    header('Location: ' . pixelhub_url('/whatsapp/templates/view?id=' . $templateId));
    exit;
}

ob_start();

$buttons = !empty($template['buttons']) ? json_decode($template['buttons'], true) : [];

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
?>

<style>
/* Cards profissionais */
.template-card {
    background: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    margin-bottom: 20px;
    transition: box-shadow 0.2s ease;
}

.template-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.12);
}

.template-card .card-body {
    padding: 20px;
}

.template-card .card-title {
    font-size: 15px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}

/* Labels profissionais */
.template-label {
    font-size: 13px;
    font-weight: 600;
    color: #495057;
    margin-bottom: 6px;
    display: block;
}

/* Inputs e selects melhorados */
.template-input,
.template-select,
.template-textarea {
    height: 40px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.template-textarea {
    height: auto;
    resize: vertical;
}

.template-input:focus,
.template-select:focus,
.template-textarea:focus {
    border-color: #4a90e2;
    box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
    outline: none;
}

/* Botões de ação com hierarquia */
.btn-action-primary {
    background: #4a90e2;
    border: none;
    color: white;
    padding: 10px 16px;
    border-radius: 6px;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.2s ease;
}

.btn-action-primary:hover {
    background: #357abd;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(74, 144, 226, 0.3);
}

.btn-action-success {
    background: #28a745;
    border: none;
    color: white;
    padding: 10px 16px;
    border-radius: 6px;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.2s ease;
}

.btn-action-success:hover {
    background: #218838;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
}

.btn-action-secondary {
    background: transparent;
    border: 2px solid #6c757d;
    color: #6c757d;
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.2s ease;
}

.btn-action-secondary:hover {
    background: #6c757d;
    color: white;
    transform: translateY(-1px);
}

/* Card de botão interativo */
.button-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 14px;
    margin-bottom: 12px;
    transition: background 0.2s ease;
}

.button-card:hover {
    background: #e9ecef;
}

/* Info box de dicas */
.info-box {
    background: #e7f3ff;
    border-left: 4px solid #4a90e2;
    border-radius: 6px;
    padding: 16px;
}

.info-box .card-title {
    color: #2c5282;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 10px;
    border: none;
    padding: 0;
}

.info-box ul {
    margin: 0;
    padding-left: 20px;
}

.info-box li {
    font-size: 13px;
    color: #2c5282;
    margin-bottom: 6px;
}

/* Badge de status */
.status-badge {
    font-size: 13px;
    padding: 6px 14px;
    border-radius: 20px;
    font-weight: 500;
}

/* Small text melhorado */
.helper-text {
    font-size: 12px;
    color: #6c757d;
    margin-top: 4px;
    display: block;
}
</style>

<div class="container-fluid py-3">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1 class="h4 mb-1">
                <i class="fas fa-edit text-muted"></i>
                Editar Template
            </h1>
            <p class="text-muted small mb-0"><?= htmlspecialchars($template['template_name']) ?></p>
        </div>
        <div class="col-md-6 text-end">
            <span class="badge status-badge me-2" style="background: <?= $statusColors[$template['status']] ?>">
                <?= $statusLabels[$template['status']] ?>
            </span>
            <a href="<?= pixelhub_url('/settings/whatsapp-providers') ?>" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show py-2">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form method="POST" action="<?= pixelhub_url('/whatsapp/templates/update') ?>">
        <input type="hidden" name="id" value="<?= $template['id'] ?>">
        
        <div class="row">
            <div class="col-md-8">
                <!-- Informações Básicas -->
                <div class="card template-card">
                    <div class="card-body">
                        <h6 class="card-title">Informações Básicas</h6>
                        
                        <div class="mb-3">
                            <label class="template-label">Nome do Template <span class="text-danger">*</span></label>
                            <input type="text" name="template_name" class="form-control template-input" 
                                   value="<?= htmlspecialchars($template['template_name']) ?>" required
                                   pattern="[a-z0-9_]+" 
                                   title="Apenas letras minúsculas, números e underscore">
                            <small class="helper-text">Apenas letras minúsculas, números e underscore (ex: promocao_verao_2024)</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="template-label">Categoria <span class="text-danger">*</span></label>
                                <select name="category" class="form-select template-select" required>
                                    <option value="marketing" <?= $template['category'] === 'marketing' ? 'selected' : '' ?>>Marketing</option>
                                    <option value="utility" <?= $template['category'] === 'utility' ? 'selected' : '' ?>>Utilidade</option>
                                    <option value="authentication" <?= $template['category'] === 'authentication' ? 'selected' : '' ?>>Autenticação</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="template-label">Idioma <span class="text-danger">*</span></label>
                                <select name="language" class="form-select template-select" required>
                                    <option value="pt_BR" <?= $template['language'] === 'pt_BR' ? 'selected' : '' ?>>Português (Brasil)</option>
                                    <option value="en_US" <?= $template['language'] === 'en_US' ? 'selected' : '' ?>>English (US)</option>
                                    <option value="es_ES" <?= $template['language'] === 'es_ES' ? 'selected' : '' ?>>Español</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cabeçalho -->
                <div class="card template-card">
                    <div class="card-body">
                        <h6 class="card-title">Cabeçalho (Opcional)</h6>
                        
                        <div class="mb-3">
                            <label class="template-label">Tipo de Cabeçalho</label>
                            <select name="header_type" id="headerType" class="form-select template-select">
                                <option value="none" <?= $template['header_type'] === 'none' ? 'selected' : '' ?>>Sem cabeçalho</option>
                                <option value="text" <?= $template['header_type'] === 'text' ? 'selected' : '' ?>>Texto</option>
                                <option value="image" <?= $template['header_type'] === 'image' ? 'selected' : '' ?>>Imagem</option>
                                <option value="video" <?= $template['header_type'] === 'video' ? 'selected' : '' ?>>Vídeo</option>
                                <option value="document" <?= $template['header_type'] === 'document' ? 'selected' : '' ?>>Documento</option>
                            </select>
                        </div>

                        <div id="headerContentDiv" style="display: <?= $template['header_type'] !== 'none' ? 'block' : 'none' ?>;">
                            <div class="mb-3">
                                <label class="template-label">Conteúdo do Cabeçalho</label>
                                <textarea name="header_content" id="headerContent" class="form-control template-textarea" rows="2"><?= htmlspecialchars($template['header_content'] ?? '') ?></textarea>
                                <small class="helper-text">Para texto: digite o conteúdo. Para mídia: URL da imagem/vídeo/documento</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Corpo -->
                <div class="card template-card">
                    <div class="card-body">
                        <h6 class="card-title">Corpo da Mensagem <span class="text-danger">*</span></h6>
                        
                        <div class="mb-3">
                            <textarea name="content" class="form-control template-textarea" rows="6" required><?= htmlspecialchars($template['content']) ?></textarea>
                            <small class="helper-text">
                                Use variáveis: {{1}}, {{2}}, etc. para personalização<br>
                                Exemplo: Olá {{1}}, sua compra de {{2}} foi confirmada!
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Rodapé -->
                <div class="card template-card">
                    <div class="card-body">
                        <h6 class="card-title">Rodapé (Opcional)</h6>
                        
                        <div class="mb-3">
                            <input type="text" name="footer_text" class="form-control template-input" 
                                   value="<?= htmlspecialchars($template['footer_text'] ?? '') ?>"
                                   maxlength="60">
                            <small class="helper-text">Texto curto no rodapé (máx. 60 caracteres)</small>
                        </div>
                    </div>
                </div>

                <!-- Botões -->
                <div class="card template-card">
                    <div class="card-body">
                        <h6 class="card-title">Botões Interativos (Opcional)</h6>
                        
                        <div id="buttonsContainer">
                            <?php foreach ($buttons as $index => $button): ?>
                                <div class="button-item button-card">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label">Tipo</label>
                                            <select name="buttons[<?= $index ?>][type]" class="form-select">
                                                <option value="quick_reply" <?= $button['type'] === 'quick_reply' ? 'selected' : '' ?>>Quick Reply</option>
                                                <option value="url" <?= $button['type'] === 'url' ? 'selected' : '' ?>>URL</option>
                                                <option value="phone" <?= $button['type'] === 'phone' ? 'selected' : '' ?>>Telefone</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Texto</label>
                                            <input type="text" name="buttons[<?= $index ?>][text]" class="form-control" 
                                                   value="<?= htmlspecialchars($button['text']) ?>" maxlength="20">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">ID/Payload</label>
                                            <input type="text" name="buttons[<?= $index ?>][id]" class="form-control" 
                                                   value="<?= htmlspecialchars($button['id'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-1 d-flex align-items-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeButton(this)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addButton()">
                            <i class="fas fa-plus"></i> Adicionar Botão
                        </button>
                        <small class="text-muted d-block mt-2">Máximo de 3 botões</small>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Ações -->
                <div class="card template-card">
                    <div class="card-body">
                        <h6 class="card-title">Ações</h6>
                        <button type="submit" class="btn btn-action-primary w-100 mb-2">
                            <i class="fas fa-save"></i> Salvar Alterações
                        </button>
                        
                        <?php if ($template['status'] === 'draft' || $template['status'] === 'rejected'): ?>
                            <button type="button" class="btn btn-action-success w-100 mb-2" onclick="submitTemplateToMeta()">
                                <i class="fas fa-paper-plane"></i> <?= $template['status'] === 'rejected' ? 'Reenviar para Meta' : 'Enviar para Meta' ?>
                            </button>
                        <?php endif; ?>
                        
                        <a href="<?= pixelhub_url('/whatsapp/templates/view?id=' . $template['id']) ?>" class="btn btn-action-secondary w-100 text-decoration-none">
                            <i class="fas fa-eye"></i> Visualizar
                        </a>
                    </div>
                </div>

                <!-- Ajuda -->
                <div class="card info-box">
                    <div class="card-body">
                        <h6 class="card-title">💡 Dicas</h6>
                        <ul class="small mb-0">
                            <li>Templates aprovados não podem ser editados</li>
                            <li>Após editar, será necessário submeter novamente</li>
                            <li>Use variáveis {{1}}, {{2}} para personalização</li>
                            <li>Botões Quick Reply são ideais para chatbots</li>
                            <li>Máximo de 3 botões por template</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let buttonIndex = <?= count($buttons) ?>;

document.getElementById('headerType').addEventListener('change', function() {
    const contentDiv = document.getElementById('headerContentDiv');
    contentDiv.style.display = this.value !== 'none' ? 'block' : 'none';
});

function addButton() {
    const container = document.getElementById('buttonsContainer');
    const buttonCount = container.querySelectorAll('.button-item').length;
    
    if (buttonCount >= 3) {
        alert('Máximo de 3 botões permitidos');
        return;
    }
    
    const html = `
        <div class="button-item mb-3 p-3 border rounded">
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label">Tipo</label>
                    <select name="buttons[${buttonIndex}][type]" class="form-select">
                        <option value="quick_reply">Quick Reply</option>
                        <option value="url">URL</option>
                        <option value="phone">Telefone</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Texto</label>
                    <input type="text" name="buttons[${buttonIndex}][text]" class="form-select" maxlength="20">
                </div>
                <div class="col-md-3">
                    <label class="form-label">ID/Payload</label>
                    <input type="text" name="buttons[${buttonIndex}][id]" class="form-control">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeButton(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
    buttonIndex++;
}

function removeButton(btn) {
    btn.closest('.button-item').remove();
}

function submitTemplateToMeta() {
    if (!confirm('Deseja enviar este template para aprovação no Meta?\n\nApós o envio, o template será analisado em 24-48h.')) {
        return;
    }
    
    const templateId = <?= $template['id'] ?>;
    const btn = event.target;
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
    
    fetch('<?= pixelhub_url('/whatsapp/templates/submit') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id: templateId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mostra mensagem de sucesso
            showSuccessMessage(data.message, data.meta_template_id);
            
            // Remove o botão "Enviar para Meta" (template agora está pending)
            btn.closest('.mb-2').remove();
        } else {
            showErrorMessage(data.message);
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(error => {
        showErrorMessage('Erro ao enviar template: ' + error.message);
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

function showSuccessMessage(message, metaTemplateId) {
    const alertHtml = `
        <div class="alert alert-success alert-dismissible fade show" role="alert" style="position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
            <h5 class="alert-heading"><i class="fas fa-check-circle"></i> Template Enviado!</h5>
            <p class="mb-2">${message}</p>
            ${metaTemplateId ? `<small class="text-muted">Meta Template ID: ${metaTemplateId}</small>` : ''}
            <hr>
            <p class="mb-0"><small><i class="fas fa-clock"></i> O Meta analisará seu template em 24-48h. Você receberá uma notificação quando for aprovado ou rejeitado.</small></p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // Remove automaticamente após 10 segundos
    setTimeout(() => {
        const alert = document.querySelector('.alert-success');
        if (alert) {
            alert.remove();
        }
    }, 10000);
}

function showErrorMessage(message) {
    const alertHtml = `
        <div class="alert alert-danger alert-dismissible fade show" role="alert" style="position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
            <h5 class="alert-heading"><i class="fas fa-exclamation-circle"></i> Erro ao Enviar</h5>
            <p class="mb-0">${message}</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // Remove automaticamente após 8 segundos
    setTimeout(() => {
        const alert = document.querySelector('.alert-danger');
        if (alert) {
            alert.remove();
        }
    }, 8000);
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layout/main.php';
?>
