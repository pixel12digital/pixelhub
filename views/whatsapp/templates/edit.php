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
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0">
                <i class="fas fa-edit text-muted"></i>
                Editar Template
            </h1>
            <p class="text-muted mb-0">Modifique o template WhatsApp Business</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?= pixelhub_url('/settings/whatsapp-providers') ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancelar
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
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
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Informações Básicas</h5>
                        
                        <div class="mb-3">
                            <label class="form-label">Nome do Template <span class="text-danger">*</span></label>
                            <input type="text" name="template_name" class="form-control" 
                                   value="<?= htmlspecialchars($template['template_name']) ?>" required
                                   pattern="[a-z0-9_]+" 
                                   title="Apenas letras minúsculas, números e underscore">
                            <small class="text-muted">Apenas letras minúsculas, números e underscore (ex: promocao_verao_2024)</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
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
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Cabeçalho (Opcional)</h5>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Cabeçalho</label>
                            <select name="header_type" id="headerType" class="form-select">
                                <option value="none" <?= $template['header_type'] === 'none' ? 'selected' : '' ?>>Sem cabeçalho</option>
                                <option value="text" <?= $template['header_type'] === 'text' ? 'selected' : '' ?>>Texto</option>
                                <option value="image" <?= $template['header_type'] === 'image' ? 'selected' : '' ?>>Imagem</option>
                                <option value="video" <?= $template['header_type'] === 'video' ? 'selected' : '' ?>>Vídeo</option>
                                <option value="document" <?= $template['header_type'] === 'document' ? 'selected' : '' ?>>Documento</option>
                            </select>
                        </div>

                        <div id="headerContentDiv" style="display: <?= $template['header_type'] !== 'none' ? 'block' : 'none' ?>;">
                            <div class="mb-3">
                                <label class="form-label">Conteúdo do Cabeçalho</label>
                                <textarea name="header_content" id="headerContent" class="form-control" rows="2"><?= htmlspecialchars($template['header_content'] ?? '') ?></textarea>
                                <small class="text-muted">Para texto: digite o conteúdo. Para mídia: URL da imagem/vídeo/documento</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Corpo -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Corpo da Mensagem <span class="text-danger">*</span></h5>
                        
                        <div class="mb-3">
                            <textarea name="content" class="form-control" rows="8" required><?= htmlspecialchars($template['content']) ?></textarea>
                            <small class="text-muted">
                                Use variáveis: {{1}}, {{2}}, etc. para personalização<br>
                                Exemplo: Olá {{1}}, sua compra de {{2}} foi confirmada!
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Rodapé -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Rodapé (Opcional)</h5>
                        
                        <div class="mb-3">
                            <input type="text" name="footer_text" class="form-control" 
                                   value="<?= htmlspecialchars($template['footer_text'] ?? '') ?>"
                                   maxlength="60">
                            <small class="text-muted">Texto curto no rodapé (máx. 60 caracteres)</small>
                        </div>
                    </div>
                </div>

                <!-- Botões -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Botões Interativos (Opcional)</h5>
                        
                        <div id="buttonsContainer">
                            <?php foreach ($buttons as $index => $button): ?>
                                <div class="button-item mb-3 p-3 border rounded">
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
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Ações</h5>
                        <button type="submit" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-save"></i> Salvar Alterações
                        </button>
                        <a href="<?= pixelhub_url('/whatsapp/templates/view?id=' . $template['id']) ?>" class="btn btn-secondary w-100">
                            <i class="fas fa-eye"></i> Visualizar
                        </a>
                    </div>
                </div>

                <!-- Ajuda -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Dicas</h5>
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
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layout/main.php';
?>
