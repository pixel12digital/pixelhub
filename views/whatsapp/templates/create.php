<?php
use PixelHub\Core\Auth;
use PixelHub\Core\DB;

Auth::requireInternal();

$pageTitle = 'Novo Template WhatsApp';
require_once __DIR__ . '/../../layouts/header.php';

// Busca lista de tenants para seleção
$db = DB::getConnection();
$tenants = $db->query("SELECT id, name FROM tenants WHERE is_active = 1 ORDER BY name ASC")->fetchAll() ?: [];
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
                <i class="fas fa-plus-circle text-primary"></i>
                Novo Template WhatsApp
            </h1>
            <p class="text-muted small mb-0">Crie um template para aprovação no Meta</p>
        </div>
        <div class="col-md-6 text-end">
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

    <form method="POST" action="<?= pixelhub_url('/whatsapp/templates/create') ?>">
        <div class="row">
            <div class="col-md-8">
                <!-- Informações Básicas -->
                <div class="card template-card">
                    <div class="card-body">
                        <h6 class="card-title">Informações Básicas</h6>
                        
                        <div class="mb-3">
                            <label class="template-label">Cliente (Tenant)</label>
                            <select name="tenant_id" class="form-select template-select">
                                <option value="">Template Global (todos os clientes)</option>
                                <?php foreach ($tenants as $tenant): ?>
                                    <option value="<?= $tenant['id'] ?>"><?= htmlspecialchars($tenant['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="helper-text">Templates globais podem ser usados por qualquer cliente</small>
                        </div>

                        <div class="mb-3">
                            <label class="template-label">Nome do Template <span class="text-danger">*</span></label>
                            <input type="text" name="template_name" class="form-control template-input" 
                                   required pattern="[a-z0-9_]+" 
                                   title="Apenas letras minúsculas, números e underscore">
                            <small class="helper-text">Apenas letras minúsculas, números e underscore (ex: promocao_verao_2024)</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="template-label">Categoria <span class="text-danger">*</span></label>
                                <select name="category" class="form-select template-select" required>
                                    <option value="marketing">Marketing</option>
                                    <option value="utility">Utilidade</option>
                                    <option value="authentication">Autenticação</option>
                                </select>
                                <small class="helper-text">Marketing: promoções, novidades. Utility: atualizações, confirmações</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="template-label">Idioma <span class="text-danger">*</span></label>
                                <select name="language" class="form-select template-select" required>
                                    <option value="pt_BR" selected>Português (Brasil)</option>
                                    <option value="en_US">English (US)</option>
                                    <option value="es_ES">Español</option>
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
                                <option value="none" selected>Sem cabeçalho</option>
                                <option value="text">Texto</option>
                                <option value="image">Imagem</option>
                                <option value="video">Vídeo</option>
                                <option value="document">Documento</option>
                            </select>
                        </div>

                        <div id="headerContentDiv" style="display: none;">
                            <div class="mb-3">
                                <label class="template-label">Conteúdo do Cabeçalho</label>
                                <textarea name="header_content" id="headerContent" class="form-control template-textarea" rows="2"></textarea>
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
                            <textarea name="content" class="form-control template-textarea" rows="6" required placeholder="Digite o conteúdo da mensagem..."></textarea>
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
                                   maxlength="60" placeholder="Texto do rodapé (máx. 60 caracteres)">
                            <small class="helper-text">Texto curto no rodapé (máx. 60 caracteres)</small>
                        </div>
                    </div>
                </div>

                <!-- Botões -->
                <div class="card template-card">
                    <div class="card-body">
                        <h6 class="card-title">Botões Interativos (Opcional)</h6>
                        
                        <div id="buttonsContainer">
                            <!-- Botões serão adicionados aqui dinamicamente -->
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
                            <i class="fas fa-save"></i> Criar Template
                        </button>
                        
                        <a href="<?= pixelhub_url('/settings/whatsapp-providers') ?>" class="btn btn-action-secondary w-100 text-decoration-none">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </div>

                <!-- Ajuda -->
                <div class="card info-box">
                    <div class="card-body">
                        <h6 class="card-title">💡 Dicas</h6>
                        <ul class="small mb-0">
                            <li>Nome do template deve ser único e descritivo</li>
                            <li>Use variáveis {{1}}, {{2}} para personalização</li>
                            <li>Templates de Marketing precisam aprovação do Meta</li>
                            <li>Templates de Utilidade são aprovados mais rápido</li>
                            <li>Máximo de 3 botões por template</li>
                            <li>Após criar, você poderá enviar para aprovação</li>
                        </ul>
                    </div>
                </div>

                <!-- Exemplo de Variáveis -->
                <div class="card template-card">
                    <div class="card-body">
                        <h6 class="card-title">📝 Exemplo de Uso</h6>
                        <div class="small">
                            <strong>Template:</strong><br>
                            <code>Olá {{1}}, seu pedido {{2}} foi confirmado!</code>
                            <hr class="my-2">
                            <strong>Ao enviar:</strong><br>
                            <code>Olá João, seu pedido #12345 foi confirmado!</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let buttonIndex = 0;

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
        <div class="button-item button-card">
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label small">Tipo</label>
                    <select name="buttons[${buttonIndex}][type]" class="form-select form-select-sm">
                        <option value="quick_reply">Quick Reply</option>
                        <option value="url">URL</option>
                        <option value="phone">Telefone</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Texto</label>
                    <input type="text" name="buttons[${buttonIndex}][text]" class="form-control form-control-sm" maxlength="20" placeholder="Texto do botão">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">ID/Payload</label>
                    <input type="text" name="buttons[${buttonIndex}][id]" class="form-control form-control-sm" placeholder="id_botao">
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

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
