<?php if ($totalPages > 1): ?>
    <nav aria-label="Paginação de cobranças">
        <ul class="pagination pagination-sm mb-0 justify-content-end" style="display: flex; list-style: none; padding: 0; margin: 0; gap: 4px;">
            <?php
            // Helper para manter os filtros na URL
            $buildUrl = function (int $p) use ($statusGeral, $semContatoRecente, $diasSemContato, $ordenacao) {
                $params = [
                    'page' => $p,
                ];
                if (!empty($statusGeral) && $statusGeral !== 'all') {
                    $params['status_geral'] = $statusGeral;
                }
                if ($semContatoRecente) {
                    $params['sem_contato_recente'] = '1';
                    $params['dias_sem_contato'] = $diasSemContato;
                }
                if (!empty($ordenacao) && $ordenacao !== 'mais_vencidas') {
                    $params['ordenacao'] = $ordenacao;
                }
                return pixelhub_url('/billing/overview?' . http_build_query($params));
            };
            ?>

            <!-- Anterior -->
            <li class="page-item" style="<?= $page <= 1 ? 'pointer-events: none; opacity: 0.5;' : '' ?>">
                <a class="page-link"
                   href="<?= $page <= 1 ? '#' : htmlspecialchars($buildUrl($page - 1)) ?>"
                   style="display: inline-block; padding: 8px 12px; text-decoration: none; color: #023A8D; border: 1px solid #ddd; border-radius: 4px; background: white; <?= $page <= 1 ? 'cursor: not-allowed;' : '' ?>">
                    «
                </a>
            </li>

            <?php 
            // Mostra até 10 páginas ao redor da página atual
            $startPage = max(1, $page - 5);
            $endPage = min($totalPages, $page + 5);
            
            // Se estiver no início, mostra mais páginas à frente
            if ($startPage === 1) {
                $endPage = min($totalPages, 10);
            }
            
            // Se estiver no final, mostra mais páginas atrás
            if ($endPage === $totalPages) {
                $startPage = max(1, $totalPages - 9);
            }
            
            // Sempre mostra primeira página se não estiver no range
            if ($startPage > 1): ?>
                <li class="page-item">
                    <a class="page-link"
                       href="<?= htmlspecialchars($buildUrl(1)) ?>"
                       style="display: inline-block; padding: 8px 12px; text-decoration: none; color: #023A8D; border: 1px solid #ddd; border-radius: 4px; background: white;">
                        1
                    </a>
                </li>
                <?php if ($startPage > 2): ?>
                    <li class="page-item" style="pointer-events: none;">
                        <span style="display: inline-block; padding: 8px 4px; color: #666;">...</span>
                    </li>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                <li class="page-item">
                    <a class="page-link"
                       href="<?= htmlspecialchars($buildUrl($p)) ?>"
                       style="display: inline-block; padding: 8px 12px; text-decoration: none; <?= $p === $page ? 'background: #023A8D; color: white; font-weight: 600;' : 'color: #023A8D; border: 1px solid #ddd; background: white;' ?> border-radius: 4px;">
                        <?= $p ?>
                    </a>
                </li>
            <?php endfor; ?>

            <?php 
            // Sempre mostra última página se não estiver no range
            if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                    <li class="page-item" style="pointer-events: none;">
                        <span style="display: inline-block; padding: 8px 4px; color: #666;">...</span>
                    </li>
                <?php endif; ?>
                <li class="page-item">
                    <a class="page-link"
                       href="<?= htmlspecialchars($buildUrl($totalPages)) ?>"
                       style="display: inline-block; padding: 8px 12px; text-decoration: none; color: #023A8D; border: 1px solid #ddd; border-radius: 4px; background: white;">
                        <?= $totalPages ?>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Próxima -->
            <li class="page-item" style="<?= $page >= $totalPages ? 'pointer-events: none; opacity: 0.5;' : '' ?>">
                <a class="page-link"
                   href="<?= $page >= $totalPages ? '#' : htmlspecialchars($buildUrl($page + 1)) ?>"
                   style="display: inline-block; padding: 8px 12px; text-decoration: none; color: #023A8D; border: 1px solid #ddd; border-radius: 4px; background: white; <?= $page >= $totalPages ? 'cursor: not-allowed;' : '' ?>">
                    »
                </a>
            </li>
        </ul>
    </nav>
<?php endif; ?>
















