<?php if ($totalPages > 1): ?>
    <nav aria-label="Paginação de leads">
        <ul style="display: flex; list-style: none; padding: 0; margin: 0; gap: 4px;">
            <?php
            $buildUrl = function (int $p) use ($search, $statusFilter, $sourceFilter) {
                $params = ['page' => $p];
                if (!empty($search))       $params['search'] = $search;
                if (!empty($statusFilter)) $params['status'] = $statusFilter;
                if (!empty($sourceFilter)) $params['source'] = $sourceFilter;
                return pixelhub_url('/leads?' . http_build_query($params));
            };
            ?>

            <li style="<?= $page <= 1 ? 'pointer-events: none; opacity: 0.5;' : '' ?>">
                <a href="<?= $page <= 1 ? '#' : htmlspecialchars($buildUrl($page - 1)) ?>"
                   style="display: inline-block; padding: 8px 12px; text-decoration: none; color: #023A8D; border: 1px solid #ddd; border-radius: 4px; background: white;">«</a>
            </li>

            <?php
            $startPage = max(1, $page - 5);
            $endPage   = min($totalPages, $page + 5);
            if ($startPage === 1)          $endPage   = min($totalPages, 10);
            if ($endPage === $totalPages)  $startPage = max(1, $totalPages - 9);
            ?>

            <?php if ($startPage > 1): ?>
                <li><a href="<?= htmlspecialchars($buildUrl(1)) ?>"
                       style="display: inline-block; padding: 8px 12px; text-decoration: none; color: #023A8D; border: 1px solid #ddd; border-radius: 4px; background: white;">1</a></li>
                <?php if ($startPage > 2): ?>
                    <li style="pointer-events: none;"><span style="display: inline-block; padding: 8px 4px; color: #666;">...</span></li>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                <li><a href="<?= htmlspecialchars($buildUrl($p)) ?>"
                       style="display: inline-block; padding: 8px 12px; text-decoration: none; border-radius: 4px; <?= $p === $page ? 'background: #023A8D; color: white; font-weight: 600;' : 'color: #023A8D; border: 1px solid #ddd; background: white;' ?>"><?= $p ?></a></li>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                    <li style="pointer-events: none;"><span style="display: inline-block; padding: 8px 4px; color: #666;">...</span></li>
                <?php endif; ?>
                <li><a href="<?= htmlspecialchars($buildUrl($totalPages)) ?>"
                       style="display: inline-block; padding: 8px 12px; text-decoration: none; color: #023A8D; border: 1px solid #ddd; border-radius: 4px; background: white;"><?= $totalPages ?></a></li>
            <?php endif; ?>

            <li style="<?= $page >= $totalPages ? 'pointer-events: none; opacity: 0.5;' : '' ?>">
                <a href="<?= $page >= $totalPages ? '#' : htmlspecialchars($buildUrl($page + 1)) ?>"
                   style="display: inline-block; padding: 8px 12px; text-decoration: none; color: #023A8D; border: 1px solid #ddd; border-radius: 4px; background: white;">»</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>
