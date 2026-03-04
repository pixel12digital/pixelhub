<?php
use PixelHub\Core\Auth;

Auth::requireInternal();

$pageTitle = 'Templates WhatsApp Business';
require_once __DIR__ . '/../../layouts/header.php';

$templates = $templates ?? [];
$statusColors = [
    'draft' => 'secondary',
    'pending' => 'warning',
    'approved' => 'success',
    'rejected' => 'danger'
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
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0">
                <i class="fas fa-comments text-primary"></i>
                Templates WhatsApp Business API
            </h1>
            <p class="text-muted mb-0">Gerencie templates aprovados pelo Meta para envio em massa</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?= pixelhub_url('/whatsapp/templates/create') ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Novo Template
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="draft" <?= ($_GET['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Rascunho</option>
                        <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pendente</option>
                        <option value="approved" <?= ($_GET['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Aprovado</option>
                        <option value="rejected" <?= ($_GET['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejeitado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Categoria</label>
                    <select name="category" class="form-select">
                        <option value="">Todas</option>
                        <option value="marketing" <?= ($_GET['category'] ?? '') === 'marketing' ? 'selected' : '' ?>>Marketing</option>
                        <option value="utility" <?= ($_GET['category'] ?? '') === 'utility' ? 'selected' : '' ?>>Utilidade</option>
                        <option value="authentication" <?= ($_GET['category'] ?? '') === 'authentication' ? 'selected' : '' ?>>Autenticação</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tenant</label>
                    <select name="tenant_id" class="form-select">
                        <option value="">Todos</option>
                        <!-- TODO: Carregar lista de tenants -->
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <a href="<?= pixelhub_url('/whatsapp/templates') ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Templates -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($templates)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Nenhum template encontrado</h5>
                    <p class="text-muted">Crie seu primeiro template para começar a enviar mensagens em massa</p>
                    <a href="<?= pixelhub_url('/whatsapp/templates/create') ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Criar Template
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Categoria</th>
                                <th>Idioma</th>
                                <th>Status</th>
                                <th>Tenant</th>
                                <th>Criado em</th>
                                <th width="200">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $template): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($template['template_name']) ?></strong>
                                        <?php if (!empty($template['meta_template_id'])): ?>
                                            <br><small class="text-muted">ID Meta: <?= htmlspecialchars($template['meta_template_id']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?= $categoryLabels[$template['category']] ?? $template['category'] ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($template['language']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $statusColors[$template['status']] ?? 'secondary' ?>">
                                            <?= $statusLabels[$template['status']] ?? $template['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($template['tenant_name']): ?>
                                            <?= htmlspecialchars($template['tenant_name']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Global</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($template['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= pixelhub_url('/whatsapp/templates/view?id=' . $template['id']) ?>" 
                                               class="btn btn-outline-primary" title="Visualizar">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($template['status'] !== 'approved'): ?>
                                                <a href="<?= pixelhub_url('/whatsapp/templates/edit?id=' . $template['id']) ?>" 
                                                   class="btn btn-outline-secondary" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($template['status'] === 'draft'): ?>
                                                <button type="button" class="btn btn-outline-success" 
                                                        onclick="submitTemplate(<?= $template['id'] ?>)" title="Submeter para Aprovação">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($template['status'] !== 'approved'): ?>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="deleteTemplate(<?= $template['id'] ?>)" title="Deletar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info Card -->
    <div class="card mt-4 border-info">
        <div class="card-body">
            <h5 class="card-title">
                <i class="fas fa-info-circle text-info"></i>
                Sobre Templates WhatsApp Business
            </h5>
            <p class="mb-2"><strong>Status dos Templates:</strong></p>
            <ul class="mb-3">
                <li><strong>Rascunho:</strong> Template em edição, não enviado para aprovação</li>
                <li><strong>Pendente:</strong> Aguardando aprovação do Meta (24-48h)</li>
                <li><strong>Aprovado:</strong> Pronto para uso em campanhas</li>
                <li><strong>Rejeitado:</strong> Não aprovado pelo Meta (verifique o motivo)</li>
            </ul>
            <p class="mb-2"><strong>Categorias:</strong></p>
            <ul class="mb-0">
                <li><strong>Marketing:</strong> Promoções, ofertas, novidades</li>
                <li><strong>Utilidade:</strong> Confirmações, atualizações de pedido, lembretes</li>
                <li><strong>Autenticação:</strong> Códigos de verificação, senhas temporárias</li>
            </ul>
        </div>
    </div>
</div>

<script>
function submitTemplate(id) {
    if (!confirm('Deseja submeter este template para aprovação no Meta?\n\nO template será enviado para revisão e você receberá o resultado em 24-48h.')) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= pixelhub_url('/whatsapp/templates/submit') ?>';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'id';
    input.value = id;
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

function deleteTemplate(id) {
    if (!confirm('Deseja realmente deletar este template?\n\nEsta ação não pode ser desfeita.')) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= pixelhub_url('/whatsapp/templates/delete') ?>';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'id';
    input.value = id;
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
