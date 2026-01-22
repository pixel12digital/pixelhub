<?php
use PixelHub\Core\Storage;

// Lista de Documentos Gerais
$tenantDocuments = $tenantDocuments ?? [];
if (empty($tenantDocuments)):
?>
    <p style="color: #666;">Nenhum documento cadastrado.</p>
<?php else: ?>
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f5f5f5;">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Título</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Categoria</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Arquivo/Link</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Tamanho</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Enviado em</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Notas</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tenantDocuments as $doc): ?>
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <strong><?= htmlspecialchars($doc['title']) ?></strong>
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <?php
                    $categoryLabels = [
                        'contrato' => 'Contratos',
                        'assets_site' => 'Assets do Site',
                        'banco_dados' => 'Banco de Dados',
                        'midia' => 'Mídias (imagens/vídeos)',
                        'documentos' => 'Documentos',
                        'outros' => 'Outros',
                    ];
                    $categoryLabel = $categoryLabels[$doc['category']] ?? ($doc['category'] ?: '-');
                    echo htmlspecialchars($categoryLabel);
                    ?>
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <?php if (!empty($doc['file_name']) && !empty($doc['stored_path'])): ?>
                        <?php if ($doc['file_exists']): ?>
                            <span style="color: #023A8D; font-weight: 600;">📄 <?= htmlspecialchars($doc['original_name'] ?? $doc['file_name']) ?></span>
                        <?php else: ?>
                            <span style="color: #999; font-style: italic;" title="Arquivo indisponível (feito em outro ambiente)">
                                📄 <?= htmlspecialchars($doc['original_name'] ?? $doc['file_name']) ?> (indisponível)
                            </span>
                        <?php endif; ?>
                    <?php elseif (!empty($doc['link_url'])): ?>
                        <a href="<?= htmlspecialchars($doc['link_url']) ?>" target="_blank" style="color: #023A8D; text-decoration: none; font-weight: 600;">
                            🔗 Abrir link
                        </a>
                    <?php else: ?>
                        <span style="color: #999;">-</span>
                    <?php endif; ?>
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <?php if (!empty($doc['file_size'])): ?>
                        <?= Storage::formatFileSize($doc['file_size']) ?>
                    <?php else: ?>
                        <span style="color: #999;">-</span>
                    <?php endif; ?>
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <?= $doc['created_at'] ? date('d/m/Y H:i', strtotime($doc['created_at'])) : 'N/A' ?>
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <?php if (!empty($doc['notes'])): ?>
                        <span title="<?= htmlspecialchars($doc['notes']) ?>">
                            <?= htmlspecialchars(mb_substr($doc['notes'], 0, 50)) ?><?= mb_strlen($doc['notes']) > 50 ? '...' : '' ?>
                        </span>
                    <?php else: ?>
                        <span style="color: #999;">-</span>
                    <?php endif; ?>
                </td>
                <td style="padding: 12px; border-bottom: 1px solid #eee;">
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <?php if (!empty($doc['file_name']) && !empty($doc['stored_path']) && $doc['file_exists']): ?>
                            <a href="<?= pixelhub_url('/tenants/documents/download?id=' . $doc['id']) ?>" 
                               style="color: #023A8D; text-decoration: none; font-weight: 600;">
                                Download
                            </a>
                        <?php endif; ?>
                        
                        <form method="POST" action="<?= pixelhub_url('/tenants/documents/delete') ?>" 
                              style="display: inline-block; margin: 0;">
                            <input type="hidden" name="id" value="<?= $doc['id'] ?>">
                            <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                            <button type="submit" 
                                    data-action="delete-document"
                                    data-target-container="tenant-documents"
                                    style="background: #c33; color: white; padding: 4px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600;">
                                Excluir
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

