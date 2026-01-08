<?php if (empty($tenants)): ?>
    <tr>
        <td colspan="7" style="padding: 20px; text-align: center; color: #666;">
            <?php if (!empty($search ?? '')): ?>
                Nenhum cliente encontrado para a busca "<?= htmlspecialchars($search) ?>".
            <?php else: ?>
                Nenhum cliente cadastrado.
            <?php endif; ?>
        </td>
    </tr>
<?php else: ?>
    <?php foreach ($tenants as $tenant): ?>
    <tr>
        <td style="padding: 12px; border-bottom: 1px solid #eee;">
            <a href="<?= pixelhub_url('/tenants/view?id=' . $tenant['id']) ?>" 
               style="color: #023A8D; text-decoration: none; font-weight: 600;">
                <?= htmlspecialchars($tenant['name']) ?>
            </a>
        </td>
        <td style="padding: 12px; border-bottom: 1px solid #eee;">
            <?= $tenant['email'] ? htmlspecialchars($tenant['email']) : '-' ?>
        </td>
        <td style="padding: 12px; border-bottom: 1px solid #eee;">
            <?php if ($tenant['phone']): ?>
                <a href="https://wa.me/55<?= preg_replace('/[^0-9]/', '', $tenant['phone']) ?>" target="_blank" rel="noopener noreferrer">
                    <?= htmlspecialchars($tenant['phone']) ?>
                </a>
            <?php else: ?>
                -
            <?php endif; ?>
        </td>
        <td style="padding: 12px; border-bottom: 1px solid #eee;">
            <?= $tenant['hosting_count'] ?? 0 ?>
        </td>
        <td style="padding: 12px; border-bottom: 1px solid #eee;">
            <?php
            $backupsCompletos = $tenant['backups_completos'] ?? 0;
            $hostingCount = $tenant['hosting_count'] ?? 0;
            if ($hostingCount > 0) {
                echo $backupsCompletos . ' / ' . $hostingCount;
            } else {
                echo '-';
            }
            ?>
        </td>
        <td style="padding: 12px; border-bottom: 1px solid #eee;">
            <?php
            $statusColor = $tenant['status'] === 'active' ? '#3c3' : '#c33';
            $statusLabel = $tenant['status'] === 'active' ? 'Ativo' : 'Inativo';
            echo '<span style="color: ' . $statusColor . '; font-weight: 600;">' . $statusLabel . '</span>';
            ?>
        </td>
        <td style="padding: 12px; border-bottom: 1px solid #eee;">
            <div style="display: flex; gap: 5px;">
                <a href="<?= pixelhub_url('/tenants/view?id=' . $tenant['id']) ?>" 
                   style="background: #023A8D; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block;">
                    Ver Detalhes
                </a>
                <button onclick="openWhatsAppModal(<?= $tenant['id'] ?>)" 
                        style="background: #023A8D; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    WhatsApp
                </button>
                <a href="<?= pixelhub_url('/tenants/edit?id=' . $tenant['id']) ?>" 
                   style="background: #666; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block;">
                    Editar
                </a>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
<?php endif; ?>

