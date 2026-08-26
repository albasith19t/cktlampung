<?php
/**
 * View: Riwayat Pemasangan & Pekerjaan Lapangan Teknisi
 * PT Cipta Karya Teknologi (CKT Lampung)
 */

$pageTitle = "Riwayat Pemasangan";
$pageHeaderTitle = "Riwayat Pemasangan Teknisi";
$pageHeaderSubtitle = "Daftar histori pelaporan ONT, hasil pemasangan pelanggan, unit bad, dan penggantian perangkat (change).";

require_once __DIR__ . '/config/database.php';

$currentUser = getCurrentUser($pdo);
if (empty($currentUser)) {
    header("Location: login.php");
    exit;
}

$isAdmin = in_array($currentUser['role'] ?? '', ['admin_gudang', 'admin']);
$isTeknisi = (($currentUser['role'] ?? '') === 'teknisi');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Filter Parameters
$statusFilter = $_GET['status'] ?? ''; // 'installed', 'bad', 'change'
$searchFilter = trim($_GET['search'] ?? '');
$techFilter = (int)($_GET['tech_id'] ?? 0);
$monthFilter = $_GET['month'] ?? ''; // YYYY-MM

// Base Query
$sql = "
    SELECT ms.*, m.name as material_name, m.code as material_code, m.brand, m.model_type,
           b.bon_number, b.id as bon_id, b.request_type, b.area_zone,
           u.name as technician_name, u.nik as technician_nik
    FROM material_serials ms
    JOIN materials m ON ms.material_id = m.id
    JOIN bon_requests b ON ms.bon_id = b.id
    JOIN users u ON b.user_id = u.id
    WHERE ms.status IN ('installed', 'bad', 'change')
";
$params = [];

if ($isTeknisi) {
    $sql .= " AND b.user_id = ?";
    $params[] = $currentUser['id'];
} elseif ($techFilter > 0) {
    $sql .= " AND b.user_id = ?";
    $params[] = $techFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND ms.status = ?";
    $params[] = $statusFilter;
}

if (!empty($monthFilter)) {
    $sql .= " AND ms.installed_at LIKE ?";
    $params[] = $monthFilter . '%';
}

if (!empty($searchFilter)) {
    $sql .= " AND (ms.serial_number LIKE ? OR ms.customer_name LIKE ? OR ms.customer_id LIKE ? OR ms.customer_address LIKE ? OR b.bon_number LIKE ? OR u.name LIKE ? OR ms.installed_notes LIKE ?)";
    $like = '%' . $searchFilter . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like]);
}

$sql .= " ORDER BY ms.installed_at DESC, ms.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$historyList = $stmt->fetchAll();

// KPI Stats Query (for current user/filter)
$kpiSql = "
    SELECT ms.status, COUNT(*) as total
    FROM material_serials ms
    JOIN bon_requests b ON ms.bon_id = b.id
    WHERE ms.status IN ('installed', 'bad', 'change')
";
$kpiParams = [];
if ($isTeknisi) {
    $kpiSql .= " AND b.user_id = ?";
    $kpiParams[] = $currentUser['id'];
} elseif ($techFilter > 0) {
    $kpiSql .= " AND b.user_id = ?";
    $kpiParams[] = $techFilter;
}
$kpiSql .= " GROUP BY ms.status";
$stmtKpi = $pdo->prepare($kpiSql);
$stmtKpi->execute($kpiParams);
$kpiRows = $stmtKpi->fetchAll();

$totalInstalled = 0;
$totalBad = 0;
$totalChange = 0;
foreach ($kpiRows as $kr) {
    if ($kr['status'] === 'installed') $totalInstalled = (int)$kr['total'];
    if ($kr['status'] === 'bad') $totalBad = (int)$kr['total'];
    if ($kr['status'] === 'change') $totalChange = (int)$kr['total'];
}
$totalAll = $totalInstalled + $totalBad + $totalChange;

$allTechnicians = $isAdmin ? $pdo->query("SELECT id, name, nik FROM users WHERE role = 'teknisi' ORDER BY name ASC")->fetchAll() : [];
?>

<div class="main-wrapper">
  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <main class="content-body">
    <!-- Header Row -->
    <div class="page-header-row">
      <div>
        <div class="page-title-heading">
          <i class="bi bi-clock-history text-primary me-2"></i> Riwayat Pemasangan & Laporan Lapangan
        </div>
        <div class="page-title-subheading">
          <?= $isTeknisi ? 'Catatan seluruh pekerjaan pemasangan ONT ke rumah pelanggan, unit bad, dan penggantian unit (change) yang telah Anda selesaikan.' : 'Monitoring rekapitulasi pekerjaan seluruh teknisi lapangan, detail pemasangan pelanggan, unit bad, dan change.' ?>
        </div>
      </div>

      <div style="display: flex; gap: 10px;">
        <a href="bon.php" class="btn-secondary" style="font-size: 0.85rem;">
          <i class="bi bi-card-checklist me-1 text-primary"></i> <?= $isTeknisi ? 'Buka Tugas & Bon Aktif' : 'Kelola Surat Bon' ?>
        </a>
      </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
      <!-- Total Dilaporkan -->
      <div class="kpi-card" style="border-left: 4px solid var(--primary);">
        <div class="kpi-icon" style="background: rgba(2, 132, 199, 0.12); color: var(--primary);">
          <i class="bi bi-journal-check"></i>
        </div>
        <div class="kpi-content">
          <div class="kpi-value"><?= $totalAll ?></div>
          <div class="kpi-label">Total Unit Dilaporkan</div>
        </div>
      </div>

      <!-- Terpasang Sukses -->
      <div class="kpi-card" style="border-left: 4px solid #10b981;">
        <div class="kpi-icon" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">
          <i class="bi bi-patch-check-fill"></i>
        </div>
        <div class="kpi-content">
          <div class="kpi-value" style="color: #10b981;"><?= $totalInstalled ?></div>
          <div class="kpi-label">Terpasang di Pelanggan</div>
        </div>
      </div>

      <!-- Unit Bad -->
      <div class="kpi-card" style="border-left: 4px solid #ef4444;">
        <div class="kpi-icon" style="background: rgba(239, 68, 68, 0.12); color: #ef4444;">
          <i class="bi bi-x-octagon-fill"></i>
        </div>
        <div class="kpi-content">
          <div class="kpi-value" style="color: #ef4444;"><?= $totalBad ?></div>
          <div class="kpi-label">Unit Kendala / Bad</div>
        </div>
      </div>

      <!-- Unit Change -->
      <div class="kpi-card" style="border-left: 4px solid #f59e0b;">
        <div class="kpi-icon" style="background: rgba(245, 158, 11, 0.12); color: #f59e0b;">
          <i class="bi bi-arrow-repeat"></i>
        </div>
        <div class="kpi-content">
          <div class="kpi-value" style="color: #f59e0b;"><?= $totalChange ?></div>
          <div class="kpi-label">Ganti Unit (Change)</div>
        </div>
      </div>
    </div>

    <!-- Filter & Search Card -->
    <div style="background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 18px 20px; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
      <form method="GET" action="riwayat.php" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        
        <!-- Keyword Search -->
        <div style="flex: 1; min-width: 220px;">
          <input 
            type="text" 
            name="search" 
            class="form-control font-mono" 
            placeholder="Cari Pelanggan, SN, No Bon, Alamat..." 
            value="<?= htmlspecialchars($searchFilter) ?>"
          >
        </div>

        <!-- Filter Status -->
        <div style="min-width: 160px;">
          <select name="status" class="form-control" onchange="this.form.submit()">
            <option value="">-- Semua Status --</option>
            <option value="installed" <?= ($statusFilter === 'installed') ? 'selected' : '' ?>>🟢 Terpasang (<?= $totalInstalled ?>)</option>
            <option value="bad" <?= ($statusFilter === 'bad') ? 'selected' : '' ?>>🔴 Bad (<?= $totalBad ?>)</option>
            <option value="change" <?= ($statusFilter === 'change') ? 'selected' : '' ?>>🟡 Change (<?= $totalChange ?>)</option>
          </select>
        </div>

        <!-- Filter Bulan -->
        <div style="min-width: 150px;">
          <input 
            type="month" 
            name="month" 
            class="form-control" 
            value="<?= htmlspecialchars($monthFilter) ?>" 
            onchange="this.form.submit()"
            title="Filter Bulan Pemasangan"
          >
        </div>

        <!-- Filter Teknisi (Admin Only) -->
        <?php if ($isAdmin && !empty($allTechnicians)): ?>
          <div style="min-width: 180px;">
            <select name="tech_id" class="form-control" onchange="this.form.submit()">
              <option value="">-- Semua Teknisi --</option>
              <?php foreach ($allTechnicians as $t): ?>
                <option value="<?= $t['id'] ?>" <?= ($techFilter == $t['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($t['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <button type="submit" class="btn-primary" style="padding: 9px 18px; font-size: 0.85rem;">
          <i class="bi bi-funnel-fill me-1"></i> Filter
        </button>

        <?php if (!empty($searchFilter) || !empty($statusFilter) || !empty($monthFilter) || $techFilter > 0): ?>
          <a href="riwayat.php" class="btn-secondary" style="padding: 9px 16px; font-size: 0.85rem;">Reset</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- History Table Card -->
    <div class="table-card">
      <div class="table-card-header">
        <div class="table-card-title">
          <i class="bi bi-list-check text-primary"></i> Rekapitulasi Riwayat Pemasangan
        </div>
        <span style="font-size: 0.82rem; color: var(--text-dim);">Menampilkan <?= count($historyList) ?> pekerjaan</span>
      </div>

      <div class="table-responsive mobile-cards-view">
        <table class="custom-table">
          <thead>
            <tr>
              <th style="width: 130px;">Waktu Lapor</th>
              <th>Serial Number & Model ONT</th>
              <th>Status</th>
              <th>Data Pelanggan & Lokasi</th>
              <th>Kabel Digunakan</th>
              <th>No. Surat Bon</th>
              <?php if ($isAdmin): ?>
                <th>Teknisi</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($historyList)): ?>
              <tr>
                <td colspan="<?= $isAdmin ? 7 : 6 ?>" style="text-align: center; padding: 48px; color: var(--text-muted);">
                  <i class="bi bi-inbox" style="font-size: 2.2rem; display: block; margin-bottom: 8px; opacity: 0.4;"></i>
                  Belum ada riwayat pemasangan yang sesuai dengan filter.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($historyList as $row): 
                $st = $row['status'];
                $isInstalled = ($st === 'installed');
                $isBad = ($st === 'bad');
                $isChange = ($st === 'change');
              ?>
                <tr>
                  <!-- Tanggal Pasang -->
                  <td data-label="Waktu Lapor" style="font-size: 0.78rem; color: var(--text-muted); font-family: var(--font-mono);">
                    <?= !empty($row['installed_at']) ? date('d/m/Y H:i', strtotime($row['installed_at'])) : '-' ?>
                  </td>

                  <!-- Serial Number & Model -->
                  <td data-label="Perangkat ONT">
                    <div style="font-weight: 800; font-family: var(--font-mono); font-size: 0.88rem; color: #0284c7;">
                      <?= htmlspecialchars($row['serial_number']) ?>
                    </div>
                    <div style="font-size: 0.76rem; color: var(--text-main); font-weight: 600; margin-top: 2px;">
                      <?= htmlspecialchars($row['material_name']) ?>
                    </div>
                    <?php if (!empty($row['mac_address'])): ?>
                      <div style="font-size: 0.7rem; color: var(--text-dim); font-family: var(--font-mono);">
                        MAC: <?= htmlspecialchars($row['mac_address']) ?>
                      </div>
                    <?php endif; ?>
                  </td>

                  <!-- Status Pill -->
                  <td data-label="Status">
                    <?php if ($isInstalled): ?>
                      <span class="status-pill status-completed">
                        <i class="bi bi-patch-check-fill"></i> Terpasang
                      </span>
                    <?php elseif ($isBad): ?>
                      <span class="status-pill" style="background: rgba(239, 68, 68, 0.12); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); font-weight: 700;">
                        <i class="bi bi-x-octagon-fill"></i> Bad
                      </span>
                    <?php elseif ($isChange): ?>
                      <span class="status-pill" style="background: rgba(245, 158, 11, 0.12); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); font-weight: 700;">
                        <i class="bi bi-arrow-repeat"></i> Change
                      </span>
                    <?php endif; ?>
                  </td>

                  <!-- Pelanggan & Alamat & Catatan -->
                  <td data-label="Pelanggan & Lokasi">
                    <?php if (!empty($row['customer_name']) && $row['customer_name'] !== 'Unit Bad Lapangan'): ?>
                      <div style="font-weight: 700; font-size: 0.86rem; color: var(--text-main);">
                        <i class="bi bi-person-fill text-primary me-1"></i> <?= htmlspecialchars($row['customer_name']) ?>
                        <?php if (!empty($row['customer_id'])): ?>
                          <span style="font-size: 0.72rem; color: var(--text-dim); font-family: var(--font-mono);">(<?= htmlspecialchars($row['customer_id']) ?>)</span>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>

                    <?php if (!empty($row['customer_address'])): ?>
                      <div style="font-size: 0.76rem; color: var(--text-muted); margin-top: 2px;">
                        <i class="bi bi-geo-alt-fill text-dim me-1"></i> <?= htmlspecialchars($row['customer_address']) ?>
                      </div>
                    <?php endif; ?>

                    <?php if (!empty($row['installed_notes'])): ?>
                      <div style="font-size: 0.74rem; margin-top: 4px; padding: 4px 8px; border-radius: 4px; background: <?= $isBad ? 'rgba(239, 68, 68, 0.08)' : ($isChange ? 'rgba(245, 158, 11, 0.08)' : 'rgba(2, 132, 199, 0.05)') ?>; color: <?= $isBad ? '#dc2626' : ($isChange ? '#d97706' : 'var(--text-muted)') ?>; font-weight: <?= ($isBad || $isChange) ? '600' : 'normal' ?>;">
                        <i class="bi <?= $isBad ? 'bi-exclamation-triangle-fill' : ($isChange ? 'bi-arrow-repeat' : 'bi-chat-left-text') ?> me-1"></i> <?= htmlspecialchars($row['installed_notes']) ?>
                      </div>
                    <?php endif; ?>
                  </td>

                  <!-- Kabel Dipakai -->
                  <td data-label="Kabel Drop Core">
                    <?php if (!empty($row['cable_used'])): ?>
                      <span class="badge font-mono" style="background: rgba(2, 132, 199, 0.1); color: #0284c7; font-size: 0.74rem; font-weight: 700; border: 1px solid rgba(2, 132, 199, 0.25);">
                        <i class="bi bi-bezier2 me-1"></i> <?= htmlspecialchars($row['cable_used']) ?>
                      </span>
                    <?php else: ?>
                      <span style="color: var(--text-dim); font-size: 0.75rem;">-</span>
                    <?php endif; ?>
                  </td>

                  <!-- No Surat Bon -->
                  <td data-label="Surat Bon">
                    <a href="bon.php?id=<?= $row['bon_id'] ?>" style="font-family: var(--font-mono); font-weight: 700; color: var(--primary); font-size: 0.82rem;">
                      <?= htmlspecialchars($row['bon_number']) ?>
                    </a>
                  </td>

                  <!-- Teknisi (Admin Only) -->
                  <?php if ($isAdmin): ?>
                    <td data-label="Teknisi">
                      <div style="font-weight: 600; font-size: 0.82rem; color: var(--text-main);">
                        <?= htmlspecialchars($row['technician_name']) ?>
                      </div>
                      <small style="color: var(--text-dim); font-family: var(--font-mono);"><?= htmlspecialchars($row['technician_nik']) ?></small>
                    </td>
                  <?php endif; ?>
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
