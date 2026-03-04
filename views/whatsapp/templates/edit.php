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
            <span class="badge me-2" style="background: <?= $statusColors[$template['status']] ?>; font-size: 13px; padding: 6px 12px;">
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
                <div class="card mb-3">
                    <div class="card-body py-3">
                        <h6 class="card-title mb-3">Informações Básicas</h6>
                        
                        <div class="mb-2">
                            <label class="form-label">Nome do Template <span class="text-danger">*</span></label>
                            <input type="text" name="template_name" class="form-control" 
                                   value="<?= htmlspecialchars($template['template_name']) ?>" required
                                   pattern="[a-z0-9_]+" 
                                   title="Apenas letras minúsculas, números e underscore">
                            <small class="text-muted">Apenas letras minúsculas, números e underscore (ex: promocao_verao_2024)</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Categoria <span class="text-danger">*</span></label>
                                <select name="category" class="form-select" required>
                                    <option value="marketing" <?= $template['category'] === 'marketing' ? 'selected' : '' ?>>Marketing</option>
                                    <option value="utility" <?= $template['category'] === 'utility' ? 'selected' : '' ?>>Utilidade</option>
                                    <option value="authentication" <?= $template['category'] === 'authentication' ? 'selected' : '' ?>>Autenticação</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Idioma <span class="text-danger">*</span></label>
                                <select name="language" class="form-select" required>
                                    <option value="pt_BR" <?= $template['language'] === 'pt_BR' ? 'selected' : '' ?>>Português (Brasil)</option>
                                    <option value="en_US" <?= $template['language'] === 'en_US' ? 'selected' : '' ?>>English (US)</option>
                                    <option value="es_ES" <?= $template['language'] === 'es_ES' ? 'selected' : '' ?>>Español</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cabeçalho -->
                <div class="card mb-3">
                    <div class="card-body py-3">
                        <h6 class="card-title mb-3">Cabeçalho (Opcional)</h6>
                        
                        <div class="mb-2">
                            <label class="form-label small">Tipo de Cabeçalho</label>
                            <select name="header_type" id="headerType" class="form-select">
                                <option value="none" <?= $template['header_type'] === 'none' ? 'selected' : '' ?>>Sem cabeçalho</option>
                                <option value="text" <?= $template['header_type'] === 'text' ? 'selected' : '' ?>>Texto</option>
                                <option value="image" <?= $template['header_type'] === 'image' ? 'selected' : '' ?>>Imagem</option>
                                <option value="video" <?= $template['header_type'] === 'video' ? 'selected' : '' ?>>Vídeo</option>
                                <option value="document" <?= $template['header_type'] === 'document' ? 'selected' : '' ?>>Documento</option>
                            </select>
                        </div>

                        <div id="headerContentDiv" style="display: <?= $template['header_type'] !== 'none' ? 'block' : 'none' ?>;">
                            <div class="mb-2">
                                <label class="form-label">Conteúdo do Cabeçalho</label>
                                <textarea name="header_content" id="headerContent" class="form-control" rows="2"><?= htmlspecialchars($template['header_content'] ?? '') ?></textarea>
                                <small class="text-muted">Para texto: digite o conteúdo. Para mídia: URL da imagem/vídeo/documento</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Corpo -->
                <div class="card mb-3">
                    <div class="card-body py-3">
                        <h6 class="card-title mb-3">Corpo da Mensagem <span class="text-danger">*</span></h6>
                        
                        <div class="mb-2">
                            <textarea name="content" class="form-control" rows="6" required><?= htmlspecialchars($template['content']) ?></textarea>
                            <small class="text-muted">
                                Use variáveis: {{1}}, {{2}}, etc. para personalização<br>
                                Exemplo: Olá {{1}}, sua compra de {{2}} foi confirmada!
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Rodapé -->
                <div class="card mb-3">
                    <div class="card-body py-3">
                        <h6 class="card-title mb-3">Rodapé (Opcional)</h6>
                        
                        <div class="mb-2">
                            <input type="text" name="footer_text" class="form-control form-control-sm" 
                                   value="<?= htmlspecialchars($template['footer_text'] ?? '') ?>"
                                   maxlength="60">
                            <small class="text-muted">Texto curto no rodapé (máx. 60 caracteres)</small>
                        </div>
                    </div>
                </div>

                <!-- Botões -->
                <div class="card mb-3">
                    <div class="card-body py-3">
                        <h6 class="card-title mb-3">Botões Interativos (Opcional)</h6>
                        
                        <div id="buttonsContainer">
                            <?php foreach ($buttons as $index => $button): ?>
                                <div class="button-item mb-2 p-2 border rounded">
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
                <div class="card mb-3">
                    <div class="card-body py-3">
                        <h6 class="card-title mb-3">Ações</h6>
                        <button type="submit" class="btn btn-primary btn-sm w-100 mb-2">
                            <i class="fas fa-save"></i> Salvar Alterações
                        </button>
                        
                        <?php if ($template['status'] === 'draft'): ?>
                            <button type="button" class="btn btn-success btn-sm w-100 mb-2" onclick="submitTemplateToMeta()">
                                <i class="fas fa-paper-plane"></i> Enviar para Meta
                            </button>
                        <?php endif; ?>
                        
                        <a href="<?= pixelhub_url('/whatsapp/templates/view?id=' . $template['id']) ?>" class="btn btn-secondary btn-sm w-100">
                            <i class="fas fa-eye"></i> Visualizar
                        </a>
                    </div>
                </div>

                <!-- Ajuda -->
                <div class="card">
                    <div class="card-body py-3">
                        <h6 class="card-title mb-2">Dicas</h6>
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
            alert('✅ ' + data.message);
            window.location.href = '<?= pixelhub_url('/settings/whatsapp-providers') ?>';
        } else {
            alert('❌ Erro: ' + data.message);
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(error => {
        alert('❌ Erro ao enviar template: ' + error.message);
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layout/main.php';
?>
