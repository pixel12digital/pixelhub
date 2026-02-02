<?php
// Arquivo parcial para ações dos projetos (reutilizável)
if (!isset($project) || empty($project)) {
    return;
}
?>
<div style="display: flex; gap: 5px; flex-wrap: nowrap;">
    <a href="<?= pixelhub_url('/projects/show?id=' . $project['id']) ?>" 
       class="btn btn-small"
       style="background: #6c757d; color: white; text-decoration: none;"
       data-tooltip="Detalhes"
       aria-label="Detalhes">
        Detalhes
    </a>
    <a href="<?= pixelhub_url('/projects/board?project_id=' . $project['id']) ?>" 
       class="btn btn-primary btn-small"
       style="text-decoration: none;"
       data-tooltip="Ver quadro"
       aria-label="Ver quadro">
        Ver quadro
    </a>
    <?php if (!empty($project['tenant_id'])): ?>
    <a href="<?= pixelhub_url('/tickets/create?project_id=' . $project['id'] . '&tenant_id=' . $project['tenant_id']) ?>" 
       class="btn btn-small"
       style="background: #28a745; color: white; text-decoration: none;"
       data-tooltip="Abrir ticket"
       aria-label="Abrir ticket">
        Abrir ticket
    </a>
    <?php endif; ?>
    <button class="btn btn-secondary btn-small btn-edit-project"
            data-id="<?= $project['id'] ?>"
            data-name="<?= htmlspecialchars($project['name'] ?? '') ?>"
            data-description="<?= htmlspecialchars($project['description'] ?? '') ?>"
            data-tenant-id="<?= $project['tenant_id'] ?? '' ?>"
            data-type="<?= htmlspecialchars($project['type'] ?? 'interno') ?>"
            data-is-customer-visible="<?= (int) ($project['is_customer_visible'] ?? 0) ?>"
            data-priority="<?= htmlspecialchars($project['priority'] ?? 'media') ?>"
            data-due-date="<?= !empty($project['due_date']) ? date('Y-m-d', strtotime($project['due_date'])) : '' ?>"
            data-status="<?= htmlspecialchars($project['status'] ?? 'ativo') ?>"
            data-slug="<?= htmlspecialchars($project['slug'] ?? '') ?>"
            data-base-url="<?= htmlspecialchars($project['base_url'] ?? '') ?>"
            data-external-project-id="<?= htmlspecialchars($project['external_project_id'] ?? '') ?>"
            data-tooltip="Editar"
            aria-label="Editar">
        Editar
    </button>
    <?php if (($project['status'] ?? 'ativo') === 'ativo'): ?>
    <button class="btn btn-secondary btn-small btn-archive-project"
            data-id="<?= $project['id'] ?>"
            data-name="<?= htmlspecialchars($project['name'] ?? '') ?>"
            data-tooltip="Arquivar"
            aria-label="Arquivar">
        Arquivar
    </button>
    <?php else: ?>
    <form method="POST" action="<?= pixelhub_url('/projects/archive') ?>" style="display: inline;">
        <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
        <input type="hidden" name="action" value="unarchive">
        <button type="submit" class="btn btn-small" style="background: #28a745; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600;"
                data-tooltip="Desarquivar"
                aria-label="Desarquivar">
            Desarquivar
        </button>
    </form>
    <?php endif; ?>
</div>

