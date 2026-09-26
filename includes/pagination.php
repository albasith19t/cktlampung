<?php
/**
 * Reusable Pagination Component
 * PT Cipta Karya Teknologi (CKT Lampung)
 * 
 * Usage:
 *   $pagination = paginate($totalItems, $currentPage, $perPage);
 *   // Use $pagination['offset'] and $pagination['limit'] in SQL LIMIT clause
 *   // Then call renderPagination($pagination, $baseUrl) to render the nav
 */

/**
 * Calculate pagination metadata
 */
function paginate($totalItems, $currentPage = 1, $perPage = 20) {
    $totalItems = max(0, (int)$totalItems);
    $perPage = max(1, (int)$perPage);
    $totalPages = max(1, (int)ceil($totalItems / $perPage));
    $currentPage = max(1, min((int)$currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total_items' => $totalItems,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'limit' => $perPage,
        'has_prev' => ($currentPage > 1),
        'has_next' => ($currentPage < $totalPages),
        'from' => $totalItems > 0 ? $offset + 1 : 0,
        'to' => min($offset + $perPage, $totalItems),
    ];
}

/**
 * Render pagination UI component
 * $baseUrl = base page URL with existing params (page param will be appended/replaced)
 */
function renderPagination($pg, $baseUrl = '') {
    if ($pg['total_pages'] <= 1) return; // No pagination needed

    // Build URL helper
    $buildUrl = function($page) use ($baseUrl) {
        $separator = (strpos($baseUrl, '?') !== false) ? '&' : '?';
        // Remove existing page param if present
        $url = preg_replace('/([?&])page=\d+/', '$1', $baseUrl);
        $url = rtrim($url, '&?');
        $separator = (strpos($url, '?') !== false) ? '&' : '?';
        return $url . $separator . 'page=' . $page;
    };

    $current = $pg['current_page'];
    $total = $pg['total_pages'];

    // Calculate visible page range (show max 5 pages around current)
    $rangeStart = max(1, $current - 2);
    $rangeEnd = min($total, $current + 2);
    
    // Adjust range to always show 5 pages if possible
    if ($rangeEnd - $rangeStart < 4) {
        if ($rangeStart === 1) {
            $rangeEnd = min($total, $rangeStart + 4);
        } elseif ($rangeEnd === $total) {
            $rangeStart = max(1, $rangeEnd - 4);
        }
    }
    ?>
    <div class="pagination-wrapper">
        <div class="pagination-info">
            Menampilkan <strong><?= $pg['from'] ?></strong> - <strong><?= $pg['to'] ?></strong> dari <strong><?= number_format($pg['total_items']) ?></strong> data
        </div>
        <nav class="pagination-nav" aria-label="Navigasi halaman">
            <!-- Prev Button -->
            <?php if ($pg['has_prev']): ?>
                <a href="<?= htmlspecialchars($buildUrl($current - 1)) ?>" class="pagination-btn" title="Halaman Sebelumnya">
                    <i class="bi bi-chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="pagination-btn disabled">
                    <i class="bi bi-chevron-left"></i>
                </span>
            <?php endif; ?>

            <!-- First page + ellipsis -->
            <?php if ($rangeStart > 1): ?>
                <a href="<?= htmlspecialchars($buildUrl(1)) ?>" class="pagination-btn">1</a>
                <?php if ($rangeStart > 2): ?>
                    <span class="pagination-ellipsis">…</span>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Page Numbers -->
            <?php for ($i = $rangeStart; $i <= $rangeEnd; $i++): ?>
                <?php if ($i === $current): ?>
                    <span class="pagination-btn active"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($buildUrl($i)) ?>" class="pagination-btn"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <!-- Last page + ellipsis -->
            <?php if ($rangeEnd < $total): ?>
                <?php if ($rangeEnd < $total - 1): ?>
                    <span class="pagination-ellipsis">…</span>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($buildUrl($total)) ?>" class="pagination-btn"><?= $total ?></a>
            <?php endif; ?>

            <!-- Next Button -->
            <?php if ($pg['has_next']): ?>
                <a href="<?= htmlspecialchars($buildUrl($current + 1)) ?>" class="pagination-btn" title="Halaman Berikutnya">
                    <i class="bi bi-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="pagination-btn disabled">
                    <i class="bi bi-chevron-right"></i>
                </span>
            <?php endif; ?>
        </nav>
    </div>
    <?php
}
