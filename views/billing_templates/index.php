<?php
$pageTitle = 'Templates de Cobrança';
include __DIR__ . '/../layout/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-file-alt me-2"></i>
                        Templates de Cobrança
                    </h5>
                    <small class="text-muted">Visualização dos templates usados no sistema</small>
                </div>
                
                <div class="card-body">
                    <!-- Filtros -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label for="channel" class="form-label">Canal</label>
                            <select class="form-select" id="channel" name="channel">
                                <option value="all" <?= $channel === 'all' ? 'selected' : '' ?>>Todos</option>
                                <option value="WhatsApp" <?= $channel === 'WhatsApp' ? 'selected' : '' ?>>WhatsApp</option>
                                <option value="E-mail" <?= $channel === 'E-mail' ? 'selected' : '' ?>>E-mail</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="search" class="form-label">Buscar</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Buscar por nome ou estágio..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" class="btn btn-primary" onclick="filterTemplates()">
                                <i class="fas fa-search me-1"></i>
                                Filtrar
                            </button>
                        </div>
                    </div>
                    
                    <!-- Tabela de Templates -->
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Canal</th>
                                    <th>Estágio</th>
                                    <th>Nome</th>
                                    <th>Formato</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($templates)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">
                                            Nenhum template encontrado
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($templates as $template): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?= $template['channel'] === 'WhatsApp' ? 'success' : 'primary' ?>">
                                                    <?= htmlspecialchars($template['channel']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <code><?= htmlspecialchars($template['stage']) ?></code>
                                            </td>
                                            <td><?= htmlspecialchars($template['label']) ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= htmlspecialchars($template['format']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="viewTemplate('<?= htmlspecialchars($template['key']) ?>')">
                                                    <i class="fas fa-eye me-1"></i>
                                                    Ver
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Visualização -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="templateModalTitle">Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="templateContent">
                    <!-- Conteúdo será carregado via JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" id="copyBtn">
                    <i class="fas fa-copy me-1"></i>
                    Copiar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.template-body {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 15px;
    font-family: 'Courier New', monospace;
    white-space: pre-wrap;
    max-height: 400px;
    overflow-y: auto;
}

.placeholder-chip {
    display: inline-block;
    background: #e9ecef;
    border: 1px solid #ced4da;
    border-radius: 15px;
    padding: 4px 10px;
    margin: 2px;
    font-size: 12px;
    font-family: monospace;
}

.placeholder-list {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 10px;
    margin-top: 10px;
}
</style>

<script>
function filterTemplates() {
    const channel = document.getElementById('channel').value;
    const search = document.getElementById('search').value;
    
    const params = new URLSearchParams({
        channel: channel,
        search: search
    });
    
    window.location.href = '?'+params.toString();
}

function viewTemplate(key) {
    fetch(`/billing/templates/view?key=${encodeURIComponent(key)}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert('Erro: ' + data.error);
                return;
            }
            
            const template = data.template;
            const modal = new bootstrap.Modal(document.getElementById('templateModal'));
            
            // Título
            document.getElementById('templateModalTitle').innerHTML = 
                `${template.channel} — ${template.label}`;
            
            // Conteúdo
            let content = '';
            
            // Subject (se e-mail)
            if (template.subject) {
                content += `
                    <div class="mb-3">
                        <label class="form-label fw-bold">Assunto:</label>
                        <div class="template-body">${template.subject}</div>
                    </div>
                `;
            }
            
            // Body
            content += `
                <div class="mb-3">
                    <label class="form-label fw-bold">Mensagem:</label>
                    <div class="template-body">${template.body}</div>
                </div>
            `;
            
            // Placeholders
            content += `
                <div class="mb-3">
                    <label class="form-label fw-bold">Variáveis Disponíveis:</label>
                    <div class="placeholder-list">
                        ${Object.entries(template.placeholders).map(([key, desc]) => 
                            `<span class="placeholder-chip" title="${desc}">${key}</span>`
                        ).join('')}
                    </div>
                </div>
            `;
            
            document.getElementById('templateContent').innerHTML = content;
            
            // Botão copiar
            document.getElementById('copyBtn').onclick = function() {
                const textToCopy = template.subject ? 
                    `Assunto: ${template.subject}\n\n${template.body}` : 
                    template.body;
                
                navigator.clipboard.writeText(textToCopy).then(() => {
                    this.innerHTML = '<i class="fas fa-check me-1"></i>Copiado!';
                    setTimeout(() => {
                        this.innerHTML = '<i class="fas fa-copy me-1"></i>Copiar';
                    }, 2000);
                });
            };
            
            modal.show();
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao carregar template');
        });
}

// Auto-filtrar ao mudar selects
document.getElementById('channel').addEventListener('change', filterTemplates);
document.getElementById('search').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        filterTemplates();
    }
});
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
