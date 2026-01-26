<?php
// Arquivo parcial para ações dos clientes (reutilizável)
if (!isset($tenant) || empty($tenant)) {
    return;
}
?>
<div style="display: flex; gap: 5px; flex-wrap: nowrap;">
    <a href="<?= pixelhub_url('/tenants/view?id=' . $tenant['id']) ?>" 
       class="btn btn-small"
       style="background: #6c757d; color: white; text-decoration: none;"
       data-tooltip="Ver Detalhes"
       aria-label="Ver Detalhes">
        Ver Detalhes
    </a>
    <button onclick="openWhatsAppModal(<?= $tenant['id'] ?>)" 
            class="btn btn-small"
            style="background: #28a745; color: white; border: none; cursor: pointer; font-size: 13px; font-weight: 600;"
            data-tooltip="WhatsApp"
            aria-label="WhatsApp">
        WhatsApp
    </button>
    <a href="<?= pixelhub_url('/tenants/edit?id=' . $tenant['id']) ?>" 
       class="btn btn-small"
       style="background: #6c757d; color: white; text-decoration: none;"
       data-tooltip="Editar"
       aria-label="Editar">
        Editar
    </a>
</div>














