<?php
$statusLabels = [
    'new'        => ['label' => 'Novo',        'color' => '#6c757d'],
    'contacted'  => ['label' => 'Contactado',  'color' => '#0d6efd'],
    'qualified'  => ['label' => 'Qualificado', 'color' => '#fd7e14'],
    'converted'  => ['label' => 'Convertido',  'color' => '#198754'],
    'lost'       => ['label' => 'Perdido',     'color' => '#dc3545'],
];
?>
<?php if (empty($leads)): ?>
    <tr>
        <td colspan="7" style="padding: 20px; text-align: center; color: #666;">
            <?php if (!empty($search ?? '')): ?>
                Nenhum lead encontrado para "<?= htmlspecialchars($search) ?>".
            <?php else: ?>
                Nenhum lead cadastrado.
            <?php endif; ?>
        </td>
    </tr>
<?php else: ?>
    <?php foreach ($leads as $lead): ?>
    <?php
        $status      = $lead['status'] ?? 'new';
        $statusInfo  = $statusLabels[$status] ?? ['label' => ucfirst($status), 'color' => '#6c757d'];
        $isConverted = $status === 'converted';
        $isLost      = $status === 'lost';
        $rowStyle    = ($isConverted || $isLost) ? 'opacity: 0.55;' : '';
        $displayName = $lead['name'] ?: ($lead['company'] ?: 'Lead #' . $lead['id']);
    ?>
    <tr style="<?= $rowStyle ?> transition: background-color 0.15s;" onmouseover="this.style.backgroundColor='#f9f9f9'" onmouseout="this.style.backgroundColor=''">
        <td style="padding: 11px 12px; border-bottom: 1px solid #eee; font-weight: 600;">
            <a href="<?= pixelhub_url('/leads/edit?id=' . $lead['id'] . '&back=' . urlencode('/leads')) ?>"
               style="color: #023A8D; text-decoration: none;">
                <?= htmlspecialchars($displayName) ?>
            </a>
            <?php if (!empty($lead['company']) && !empty($lead['name'])): ?>
                <div style="font-size: 12px; color: #666; font-weight: 400; margin-top: 2px;"><?= htmlspecialchars($lead['company']) ?></div>
            <?php endif; ?>
        </td>
        <td style="padding: 11px 12px; border-bottom: 1px solid #eee; font-size: 13px;">
            <?php if ($lead['phone']): ?>
                <a href="https://wa.me/55<?= preg_replace('/[^0-9]/', '', $lead['phone']) ?>" target="_blank" rel="noopener noreferrer" style="color: #333; text-decoration: none;">
                    <?= htmlspecialchars($lead['phone']) ?>
                </a>
            <?php else: ?>
                <span style="color: #aaa;">—</span>
            <?php endif; ?>
        </td>
        <td style="padding: 11px 12px; border-bottom: 1px solid #eee; font-size: 13px; color: #555;">
            <?= $lead['email'] ? htmlspecialchars($lead['email']) : '<span style="color:#aaa;">—</span>' ?>
        </td>
        <td style="padding: 11px 12px; border-bottom: 1px solid #eee; font-size: 12px; color: #666;">
            <?= htmlspecialchars($lead['source'] ?? '—') ?>
        </td>
        <td style="padding: 11px 12px; border-bottom: 1px solid #eee;">
            <span style="display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; background: <?= $statusInfo['color'] ?>22; color: <?= $statusInfo['color'] ?>;">
                <?= $statusInfo['label'] ?>
            </span>
        </td>
        <td style="padding: 11px 12px; border-bottom: 1px solid #eee; font-size: 13px; text-align: center;">
            <?php if (($lead['opp_count'] ?? 0) > 0): ?>
                <a href="<?= pixelhub_url('/opportunities?search=' . urlencode($displayName)) ?>" style="color: #023A8D; font-weight: 600; text-decoration: none;">
                    <?= (int)$lead['opp_count'] ?>
                </a>
            <?php else: ?>
                <span style="color: #aaa;">0</span>
            <?php endif; ?>
        </td>
        <td style="padding: 11px 12px; border-bottom: 1px solid #eee; white-space: nowrap;">
            <a href="<?= pixelhub_url('/leads/edit?id=' . $lead['id'] . '&back=' . urlencode('/leads')) ?>"
               style="display: inline-block; padding: 5px 12px; background: #023A8D; color: white; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 600;">
                Editar
            </a>
            <?php if ((int)($lead['opp_count'] ?? 0) === 0 && !$isConverted): ?>
            <a href="<?= pixelhub_url('/opportunities?new=1&lead_id=' . $lead['id'] . '&lead_name=' . urlencode($displayName)) ?>"
               style="display: inline-block; padding: 5px 12px; background: #6f42c1; color: white; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 600; margin-left: 4px;"
               title="Criar oportunidade para este lead">
                + Oportunidade
            </a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
<?php endif; ?>
