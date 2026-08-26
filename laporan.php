<?php
/**
 * View: Laporan Logistik & Rekapitulasi Bon Material
 * PT Cipta Karya Teknologi (CKT Lampung)
 */

require_once __DIR__ . '/config/database.php';

$currentUser = getCurrentUser($pdo);
$isAdmin = in_array($currentUser['role'] ?? '', ['admin_gudang', 'admin']);

if (!$isAdmin) {
    $_SESSION['flash_message'] = [
        'type' => 'warning',
        'title' => 'Akses Terbatas',
        'text' => 'Menu Laporan & Mutasi hanya dapat diakses oleh Admin Gudang.'
    ];
    header("Location: bon.php");
    exit;
}
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$techFilter = (int)($_GET['tech_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$export = $_GET['export'] ?? '';

// Build Query
$sql = "
    SELECT b.*, u.name as technician_name, u.nik as technician_nik,
           ua.name as approver_name
    FROM bon_requests b
    JOIN users u ON b.user_id = u.id
    LEFT JOIN users ua ON b.approved_by = ua.id
    WHERE DATE(b.created_at) >= ? AND DATE(b.created_at) <= ?
";
$params = [$startDate, $endDate];

if ($techFilter > 0) {
    $sql .= " AND b.user_id = ?";
    $params[] = $techFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND b.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY b.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reportBons = $stmt->fetchAll();

// Handle CSV Export
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Laporan_Bon_CKT_Lampung_' . date('Ymd_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['No. Bon', 'Tanggal', 'Teknisi', 'NIK', 'No. SPK', 'Nama Pelanggan', 'Alamat', 'Wilayah', 'Status', 'Rincian Material']);

    foreach ($reportBons as $rb) {
        $stIt = $pdo->prepare("SELECT m.name, bi.quantity_approved, bi.quantity_requested, m.unit, bi.serial_numbers FROM bon_items bi JOIN materials m ON bi.material_id = m.id WHERE bi.bon_id = ?");
        $stIt->execute([$rb['id']]);
        $items = $stIt->fetchAll();
        $itemsSummary = [];
        foreach ($items as $it) {
            $qty = $it['quantity_approved'] > 0 ? $it['quantity_approved'] : $it['quantity_requested'];
            $txt = $it['name'] . ' (' . $qty . ' ' . $it['unit'] . ')';
            if ($it['serial_numbers']) $txt .= ' [SN: ' . $it['serial_numbers'] . ']';
            $itemsSummary[] = $txt;
        }

        fputcsv($output, [
            $rb['bon_number'],
            $rb['created_at'],
            $rb['technician_name'],
            $rb['technician_nik'],
            $rb['work_order_number'],
            $rb['customer_name'],
            $rb['customer_address'],
            $rb['area_zone'],
            $rb['status'],
            implode('; ', $itemsSummary)
        ]);
    }
    fclose($output);
    exit;
}

$pageTitle = "Laporan & Rekapitulasi Bon";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Fetch technicians for filter
$allTechs = $pdo->query("SELECT id, name, nik FROM users WHERE role = 'teknisi' ORDER BY name ASC")->fetchAll();

// Calculate Totals for Filtered Period
$totalBonCount = count($reportBons);
$totalOntCount = 0;
$totalCablesCount = 0;
$totalAccCount = 0;

if (!empty($reportBons)) {
    $bonIds = array_column($reportBons, 'id');
    $inPlaceholders = implode(',', array_fill(0, count($bonIds), '?'));
    
    $stmtMatCount = $pdo->prepare("
        SELECT m.category_id, SUM(COALESCE(bi.quantity_approved, bi.quantity_requested)) as total_qty
        FROM bon_items bi
        JOIN materials m ON bi.material_id = m.id
        WHERE bi.bon_id IN ($inPlaceholders)
        GROUP BY m.category_id
    ");
    $stmtMatCount->execute($bonIds);
    $catCounts = $stmtMatCount->fetchAll();
    foreach ($catCounts as $cc) {
        if ($cc['category_id'] == 1) $totalOntCount = (int)$cc['total_qty'];
        if ($cc['category_id'] == 2) $totalCablesCount = (int)$cc['total_qty'];
        if ($cc['category_id'] == 3) $totalAccCount = (int)$cc['total_qty'];
    }
}
?>

<div class="main-wrapper">
  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <main class="content-body">
    
    <!-- Printable Header (Visible Only When Printing) -->
    <div style="display: none;" class="print-only">
      <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 12px;">
        <h2 style="font-size: 16pt; margin: 0; text-transform: uppercase;">PT CIPTA KARYA TEKNOLOGI</h2>
        <div style="font-size: 11pt; font-weight: 700;">DIVISI LOGISTIK & GUDANG FIBER OPTIC WILAYAH LAMPUNG</div>
        <div style="font-size: 9pt; color: #555;">Jl. Raden Intan No. 45, Bandar Lampung &bull; Telp: (0721) 789011</div>
        <div style="font-size: 10pt; font-weight: 700; margin-top: 8px;">
          REKAPITULASI PENGELUARAN BON MATERIAL (Periode: <?= date('d/m/Y', strtotime($startDate)) ?> s/d <?= date('d/m/Y', strtotime($endDate)) ?>)
        </div>
      </div>
    </div>

    <!-- Web Header Row -->
    <div class="page-header-row no-print">
      <div>
        <div class="page-title-heading">Laporan & Rekapitulasi Bon Material</div>
        <div class="page-title-subheading">Audit trail pengeluaran material ke teknisi dan laporan pemakaian di lapangan.</div>
      </div>

      <div style="display: flex; gap: 10px;">
        <button type="button" class="btn-secondary" onclick="window.print()">
          <i class="bi bi-printer-fill me-1"></i> Cetak Laporan
        </button>
        <a href="laporan.php?export=csv&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&tech_id=<?= $techFilter ?>&type=<?= urlencode($typeFilter) ?>&status=<?= urlencode($statusFilter) ?>" class="btn-primary">
          <i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Ekspor CSV / Excel
        </a>
      </div>
    </div>

    <!-- Filter Card -->
    <div class="table-card no-print" style="margin-bottom: 24px;">
      <div style="padding: 20px;">
        <form method="GET" action="laporan.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) auto; gap: 14px; align-items: flex-end;">
          <div>
            <label class="form-label">Dari Tanggal</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
          </div>
          <div>
            <label class="form-label">Sampai Tanggal</label>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
          </div>
          <div>
            <label class="form-label">Teknisi</label>
            <select name="tech_id" class="form-select">
              <option value="">-- Semua Teknisi --</option>
              <?php foreach ($allTechs as $at): ?>
                <option value="<?= $at['id'] ?>" <?= ($techFilter == $at['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($at['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="">-- Semua Status --</option>
              <option value="approved" <?= ($statusFilter === 'approved') ? 'selected' : '' ?>>Dikeluarkan</option>
              <option value="completed" <?= ($statusFilter === 'completed') ? 'selected' : '' ?>>Selesai</option>
              <option value="cancelled" <?= ($statusFilter === 'cancelled') ? 'selected' : '' ?>>Dibatalkan</option>
            </select>
          </div>
          <div>
            <button type="submit" class="btn-primary" style="height: 38px; width: 100%;">
              <i class="bi bi-funnel-fill me-1"></i> Terapkan
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Summary Metrics for this report -->
    <div class="stat-grid" style="margin-bottom: 24px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
      <div class="stat-card" style="padding: 16px;">
        <div class="stat-label">Total Surat Bon</div>
        <div class="stat-value text-primary"><?= $totalBonCount ?></div>
        <div class="stat-desc">Periode terpilih</div>
      </div>
      <div class="stat-card" style="padding: 16px;">
        <div class="stat-label">ONT Modem Keluar</div>
        <div class="stat-value" style="color: var(--ont-color);"><?= $totalOntCount ?> <span style="font-size: 0.9rem; color: var(--text-muted);">Unit</span></div>
        <div class="stat-desc">ONT Besar & Kecil</div>
      </div>
      <div class="stat-card" style="padding: 16px;">
        <div class="stat-label">Kabel Drop Core</div>
        <div class="stat-value" style="color: var(--cable-color);"><?= $totalCablesCount ?> <span style="font-size: 0.9rem; color: var(--text-muted);">Roll</span></div>
        <div class="stat-desc">150M, 100M, 75M, 50M</div>
      </div>
    </div>

    <!-- Table of Report Records -->
    <div class="table-card">
      <div class="table-card-header no-print">
        <div class="table-card-title">
          <i class="bi bi-table text-primary"></i> Data Rincian Bon Material Lapangan
        </div>
        <span style="font-size: 0.8rem; color: var(--text-dim);"><?= count($reportBons) ?> Data Ditemukan</span>
      </div>

      <div class="table-responsive mobile-cards-view">
        <table class="custom-table">
          <thead>
            <tr>
              <th>No. Surat Bon</th>
              <th>Tanggal</th>
              <th>Teknisi Lapangan</th>
              <th>No. SPK / WO</th>
              <th>Pelanggan & Area</th>
              <th>Rincian Material yang Diambil</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($reportBons)): ?>
              <tr>
                <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                  Tidak ada data bon pada rentang tanggal dan filter ini.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($reportBons as $rb): 
                $stIt = $pdo->prepare("SELECT m.name, bi.quantity_approved, bi.quantity_requested, m.unit, bi.serial_numbers FROM bon_items bi JOIN materials m ON bi.material_id = m.id WHERE bi.bon_id = ?");
                $stIt->execute([$rb['id']]);
                $bItems = $stIt->fetchAll();
              ?>
                <tr>
                  <td data-label="No. Surat Bon">
                    <span class="font-mono" style="font-weight: 700; color: var(--primary);">
                      <?= htmlspecialchars($rb['bon_number']) ?>
                    </span>
                  </td>
                  <td data-label="Tanggal" style="font-size: 0.78rem; color: var(--text-dim); white-space: nowrap;">
                    <?= formatTanggalSingkat($rb['created_at']) ?>
                  </td>
                  <td data-label="Teknisi">
                    <div style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($rb['technician_name']) ?></div>
                    <small style="color: var(--text-dim);"><?= htmlspecialchars($rb['technician_nik']) ?></small>
                  </td>
                  <td data-label="No. SPK / WO">
                    <div style="font-weight: 700; color: var(--text-main); font-family: var(--font-mono); font-size: 0.84rem;">
                      <?= htmlspecialchars($rb['work_order_number'] ?: '-') ?>
                    </div>
                  </td>
                  <td data-label="Pelanggan & Area">
                    <div style="font-weight: 600;"><?= htmlspecialchars($rb['customer_name'] ?: '-') ?></div>
                    <small style="color: var(--text-dim);"><?= htmlspecialchars($rb['area_zone']) ?></small>
                  </td>
                  <td data-label="Rincian Material">
                    <ul style="list-style: none; padding-left: 0; font-size: 0.8rem; line-height: 1.4; margin-bottom: 0;">
                      <?php foreach ($bItems as $it): 
                        $qty = $it['quantity_approved'] > 0 ? $it['quantity_approved'] : $it['quantity_requested'];
                      ?>
                        <li>
                          &bull; <strong><?= htmlspecialchars($it['name']) ?></strong>: <?= $qty ?> <?= htmlspecialchars($it['unit']) ?>
                          <?php if (!empty($it['serial_numbers'])): ?>
                            <div class="font-mono" style="color: var(--primary); font-size: 0.72rem; margin-left: 8px;">
                              SN: <?= htmlspecialchars($it['serial_numbers']) ?>
                            </div>
                          <?php endif; ?>
                        </li>
                      <?php endforeach; ?>
                    </ul>
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
