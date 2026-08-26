<?php
/**
 * View: Dashboard Logistik & Gudang CKT Lampung
 * PT Cipta Karya Teknologi
 */

$pageTitle = "Dashboard Gudang & Bon Material";
$pageHeaderTitle = "Dashboard Logistik Gudang";
$pageHeaderSubtitle = "Monitoring stok real-time, pengeluaran kabel drop core, ONT, dan surat bon teknisi.";

require_once __DIR__ . '/config/database.php';

$currentUser = getCurrentUser($pdo);
$isAdmin = in_array($currentUser['role'] ?? '', ['admin_gudang', 'admin']);

// Teknisi hanya berhak mengakses tugas / bon mereka di bon.php
if (!$isAdmin) {
    header("Location: bon.php");
    exit;
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// 1. KPI Queries
$totalMaterials = (int)$pdo->query("SELECT COUNT(*) FROM materials")->fetchColumn();
$totalStockOntBesar = (int)$pdo->query("SELECT stock_current FROM materials WHERE code = 'MAT-ONT-BSR'")->fetchColumn();
$totalStockOntKecil = (int)$pdo->query("SELECT stock_current FROM materials WHERE code = 'MAT-ONT-KCL'")->fetchColumn();

$cableStats = $pdo->query("
    SELECT code, name, cable_length, stock_current, stock_min 
    FROM materials 
    WHERE category_id = 2 
    ORDER BY cable_length DESC
")->fetchAll();

$totalCablesRoll = (int)$pdo->query("SELECT SUM(stock_current) FROM materials WHERE category_id = 2")->fetchColumn();

// Bon Stats
$totalBonCount = (int)$pdo->query("SELECT COUNT(*) FROM bon_requests")->fetchColumn();
$bonActiveCount = (int)$pdo->query("SELECT COUNT(*) FROM bon_requests WHERE status = 'approved'")->fetchColumn();
$bonCompletedCount = (int)$pdo->query("SELECT COUNT(*) FROM bon_requests WHERE status = 'completed'")->fetchColumn();

// Low Stock Alert Materials
$lowStockItems = $pdo->query("
    SELECT m.*, c.name as category_name 
    FROM materials m 
    LEFT JOIN categories c ON m.category_id = c.id 
    WHERE m.stock_current <= m.stock_min 
    ORDER BY m.stock_current ASC
")->fetchAll();

// Recent Bon Requests (Last 6)
$recentBonSql = "
    SELECT b.*, u.name as technician_name, u.nik as technician_nik
    FROM bon_requests b
    JOIN users u ON b.user_id = u.id
";
if ($isTeknisi) {
    $recentBonSql .= " WHERE b.user_id = " . (int)$currentUser['id'];
}
$recentBonSql .= " ORDER BY b.created_at DESC LIMIT 6";
$recentBons = $pdo->query($recentBonSql)->fetchAll();

?>

<div class="main-wrapper">
  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <main class="content-body">
    <!-- Header Banner -->
    <div class="page-header-row">
      <div>
        <div class="page-title-heading">
          <?= $isTeknisi ? 'Selamat Datang, ' . htmlspecialchars($currentUser['name']) : 'Dashboard Operasional Gudang CKT' ?>
        </div>
        <div class="page-title-subheading">
          <?= $isTeknisi ? 'Lihat status material yang Anda bawa dan konfirmasi penyelesaian pemasangan di lokasi pelanggan.' : 'Ringkasan stok barang, alokasi ONT, roll kabel drop core, dan penerbitan bon teknisi.' ?>
        </div>
      </div>

      <?php if ($isAdmin): ?>
        <div style="display: flex; gap: 10px;">
          <button type="button" class="btn-primary" onclick="openModalBon()">
            <i class="bi bi-person-check-fill me-1"></i> Input Bon Teknisi
          </button>
          <button type="button" class="btn-secondary" onclick="openRestockModal()">
            <i class="bi bi-plus-circle me-1 text-success"></i> Restock Barang
          </button>
        </div>
      <?php endif; ?>
    </div>

    <!-- Low Stock Alert Banner (If Any) -->
    <?php if (!empty($lowStockItems)): ?>
      <div style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.08) 0%, rgba(245, 158, 11, 0.08) 100%); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: var(--radius-lg); padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
          <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--danger-bg); color: var(--danger); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="bi bi-exclamation-triangle-fill"></i>
          </div>
          <div>
            <div style="font-weight: 800; color: var(--danger); font-size: 0.92rem;">
              Perhatian: Ada <?= count($lowStockItems) ?> Material dengan Stok Menipis / Kritis!
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted);">
              <?php 
                $alertNames = array_map(function($i) { return $i['name'] . ' (Sisa ' . $i['stock_current'] . ' ' . $i['unit'] . ')'; }, array_slice($lowStockItems, 0, 2));
                echo htmlspecialchars(implode(', ', $alertNames));
                if (count($lowStockItems) > 2) echo ' dan ' . (count($lowStockItems) - 2) . ' lainnya.';
              ?>
            </div>
          </div>
        </div>
        <a href="stok.php?filter=kritis" class="btn-secondary" style="font-size: 0.78rem; padding: 6px 14px; border-color: rgba(239, 68, 68, 0.3); color: var(--danger);">
          <i class="bi bi-arrow-right-circle me-1"></i> Periksa Stok Kritis
        </a>
      </div>
    <?php endif; ?>

    <!-- Stat KPI Cards Grid -->
    <div class="stat-grid">
      <!-- ONT Stock Card -->
      <div class="stat-card">
        <div class="stat-card-top">
          <div class="stat-icon ont">
            <i class="bi bi-router"></i>
          </div>
          <span class="badge badge-ont">ONT & Modem</span>
        </div>
        <div class="stat-label">Total Stok ONT Wi-Fi</div>
        <div class="stat-value text-primary">
          <?= ($totalStockOntBesar + $totalStockOntKecil) ?> <span style="font-size: 1rem; font-weight: 600; color: var(--text-muted);">Unit</span>
        </div>
        <div class="stat-desc">
          <span>ONT Besar: <strong><?= $totalStockOntBesar ?></strong></span> &bull; 
          <span>ONT Kecil: <strong><?= $totalStockOntKecil ?></strong></span>
        </div>
      </div>

      <!-- Cable Drop Core Card -->
      <div class="stat-card">
        <div class="stat-card-top">
          <div class="stat-icon cable">
            <i class="bi bi-bezier2"></i>
          </div>
          <span class="badge badge-cable">4 Varian Panjang</span>
        </div>
        <div class="stat-label">Kabel Drop Core FO</div>
        <div class="stat-value" style="color: #0284c7;">
          <?= $totalCablesRoll ?> <span style="font-size: 1rem; font-weight: 600; color: var(--text-muted);">Roll</span>
        </div>
        <div class="stat-desc">
          150M, 100M, 75M, 50M siap digunakan
        </div>
      </div>
    </div>

    <!-- Cable 4 Varian Progress Card -->
    <div class="table-card" style="margin-bottom: 28px;">
      <div class="table-card-header">
        <div class="table-card-title">
          <i class="bi bi-bezier2 text-primary"></i> Ketersediaan Roll Kabel Drop Core
        </div>
        <a href="stok.php?cat=CAT-KBL" style="font-size: 0.78rem; font-weight: 700;">Lihat Detail &rarr;</a>
      </div>
      <div style="padding: 20px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px;">
          <?php foreach ($cableStats as $c): 
            $percent = min(100, round(($c['stock_current'] / 150) * 100));
            $isLow = $c['stock_current'] <= $c['stock_min'];
          ?>
            <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 16px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <div style="font-weight: 700; font-size: 0.85rem; color: var(--text-main);">
                  Kabel Drop Core <?= $c['cable_length'] ?> Meter
                  <?php if ($isLow): ?>
                    <span class="badge" style="background: var(--danger-bg); color: var(--danger); font-size: 0.68rem; margin-left: 6px;">Stok Kritis</span>
                  <?php endif; ?>
                </div>
                <div style="font-family: var(--font-mono); font-weight: 800; font-size: 0.88rem; color: <?= $isLow ? 'var(--danger)' : 'var(--primary)' ?>;">
                  <?= $c['stock_current'] ?> Roll
                </div>
              </div>
              <div style="background-color: #e2e8f0; height: 8px; border-radius: var(--radius-full); overflow: hidden; margin-bottom: 6px;">
                <div style="background: <?= $isLow ? 'var(--danger)' : 'linear-gradient(90deg, #0284c7, #38bdf8)' ?>; height: 100%; width: <?= $percent ?>%; border-radius: var(--radius-full); transition: width 0.4s ease;"></div>
              </div>
              <div style="font-size: 0.72rem; color: var(--text-dim); text-align: right;">
                Min: <?= $c['stock_min'] ?> Roll
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Recent Bon Table -->
    <div class="table-card">
      <div class="table-card-header">
        <div class="table-card-title">
          <i class="bi bi-clock-history text-primary"></i> <?= $isTeknisi ? 'Surat Bon Terbaru Saya' : 'Surat Bon Pengeluaran Material Terbaru' ?>
        </div>
        <a href="bon.php" class="btn-secondary" style="font-size: 0.8rem; padding: 6px 12px;">
          Buka Semua Bon <i class="bi bi-arrow-right ms-1"></i>
        </a>
      </div>

      <div class="table-responsive mobile-cards-view">
        <table class="custom-table">
          <thead>
            <tr>
              <th>No. Surat Bon</th>
              <th>Teknisi Pengambil</th>
              <th>Material Diserahkan</th>
              <th>Status</th>
              <th>Waktu</th>
              <th style="text-align: right;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentBons)): ?>
              <tr>
                <td colspan="6" style="text-align: center; padding: 30px; color: var(--text-muted);">
                  Belum ada surat bon yang diterbitkan.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($recentBons as $rb): 
                $stIt = $pdo->prepare("SELECT m.name, bi.quantity_approved, bi.quantity_requested, m.unit FROM bon_items bi JOIN materials m ON bi.material_id = m.id WHERE bi.bon_id = ?");
                $stIt->execute([$rb['id']]);
                $rbItems = $stIt->fetchAll();
                $rbSummary = [];
                foreach ($rbItems as $it) {
                    $qty = $it['quantity_approved'] > 0 ? $it['quantity_approved'] : $it['quantity_requested'];
                    $rbSummary[] = $it['name'] . ' (' . $qty . ' ' . $it['unit'] . ')';
                }
                $rbSummaryText = implode(', ', $rbSummary);
              ?>
                <tr>
                  <td data-label="No. Surat Bon">
                    <a href="bon.php?id=<?= $rb['id'] ?>" style="font-family: var(--font-mono); font-weight: 700; color: var(--primary);">
                      <?= htmlspecialchars($rb['bon_number']) ?>
                    </a>
                  </td>
                  <td data-label="Teknisi">
                    <div style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($rb['technician_name']) ?></div>
                    <small style="color: var(--text-dim);"><?= htmlspecialchars($rb['technician_nik']) ?></small>
                  </td>
                  <td data-label="Material">
                    <div style="max-width: 280px; font-size: 0.8rem; color: var(--text-muted); line-height: 1.3;" title="<?= htmlspecialchars($rbSummaryText) ?>">
                      <?= htmlspecialchars($rbSummaryText ?: 'Tidak ada barang') ?>
                    </div>
                  </td>
                  <td data-label="Status">
                    <?php if ($rb['status'] === 'approved'): ?>
                      <span class="status-pill status-approved"><i class="bi bi-hourglass-split"></i> Proses</span>
                    <?php elseif ($rb['status'] === 'completed'): ?>
                      <span class="status-pill status-completed"><i class="bi bi-patch-check-fill"></i> Selesai</span>
                    <?php elseif ($rb['status'] === 'pending'): ?>
                      <span class="status-pill status-pending"><i class="bi bi-hourglass-split"></i> Pending</span>
                    <?php else: ?>
                      <span class="status-pill status-rejected"><i class="bi bi-x-circle"></i> Dibatalkan</span>
                    <?php endif; ?>
                  </td>
                  <td data-label="Waktu" style="color: var(--text-dim); font-size: 0.78rem;">
                    <?= formatTanggalSingkat($rb['created_at']) ?>
                  </td>
                  <td data-label="Aksi" style="text-align: right;">
                    <div class="btn-action-group" style="justify-content: flex-end; width: 100%;">
                      <a href="bon.php?id=<?= $rb['id'] ?>" class="btn-icon-action" title="Lihat Rincian & Status SN">
                        <i class="bi bi-eye"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>


<?php require_once __DIR__ . '/includes/footer.php'; ?>
