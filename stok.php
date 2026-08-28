<?php
/**
 * View: Manajemen Stok Gudang, Stok ONT & Kabel Drop Core
 * PT Cipta Karya Teknologi (CKT Lampung)
 */

$pageTitle = "Stok & Logistik Gudang";
$pageHeaderTitle = "Manajemen Stok ONT & Kabel Drop Core";
$pageHeaderSubtitle = "Data real-time Serial Number ONT (Stok Gudang / Dibawa Teknisi) dan ketersediaan kabel fiber optic.";

require_once __DIR__ . '/config/database.php';

$currentUser = getCurrentUser($pdo);
$isAdmin = in_array($currentUser['role'] ?? '', ['admin_gudang', 'admin']);

if (!$isAdmin) {
    $_SESSION['flash_message'] = [
        'type' => 'warning',
        'title' => 'Akses Terbatas',
        'text' => 'Menu Stok & Material hanya dapat diakses oleh Admin Gudang.'
    ];
    header("Location: bon.php");
    exit;
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Active Tab: 'ont' (default), 'kabel', 'mutasi'
$activeTab = $_GET['tab'] ?? 'ont';
if ($activeTab === 'materials') $activeTab = 'ont';
if ($activeTab === 'serials') $activeTab = 'ont';

// Filters for Tab 1 (Stok ONT)
$snSearch = trim($_GET['sn'] ?? ($_GET['search'] ?? ''));
$ontMaterialId = (int)($_GET['material_id'] ?? 0);
$ontStatusFilter = trim($_GET['status'] ?? '');

// Filters for Tab 2 (Kabel Drop Core)
$cableSearch = trim($_GET['cable_search'] ?? '');
$cableFilter = trim($_GET['cable_filter'] ?? '');

// ---------------------------------------------------------
// 1. QUERY STOK ONT (TAB 1)
// ---------------------------------------------------------
$ontSql = "
    SELECT ms.*, 
           m.name as material_name, 
           m.code as material_code, 
           m.brand as material_brand,
           m.model_type as material_model,
           b.bon_number, 
           b.status as bon_status,
           b.customer_name as bon_customer_name,
           u.name as technician_name,
           u.nik as technician_nik
    FROM material_serials ms
    JOIN materials m ON ms.material_id = m.id
    LEFT JOIN bon_requests b ON ms.bon_id = b.id
    LEFT JOIN users u ON b.user_id = u.id
    WHERE 1=1
";
$ontParams = [];

if ($ontMaterialId > 0) {
    $ontSql .= " AND ms.material_id = ?";
    $ontParams[] = $ontMaterialId;
}

if (!empty($ontStatusFilter)) {
    if ($ontStatusFilter === 'gudang') {
        $ontSql .= " AND ms.status = 'available'";
    } elseif ($ontStatusFilter === 'teknisi') {
        $ontSql .= " AND (ms.status = 'allocated' OR (ms.bon_id IS NOT NULL AND ms.status != 'installed' AND ms.status != 'bad'))";
    } elseif ($ontStatusFilter === 'installed') {
        $ontSql .= " AND ms.status = 'installed'";
    } elseif ($ontStatusFilter === 'bad') {
        $ontSql .= " AND ms.status = 'bad'";
    } else {
        $ontSql .= " AND ms.status = ?";
        $ontParams[] = $ontStatusFilter;
    }
}

if (!empty($snSearch)) {
    $ontSql .= " AND (ms.serial_number LIKE ? OR ms.mac_address LIKE ? OR b.bon_number LIKE ? OR m.name LIKE ? OR m.brand LIKE ? OR u.name LIKE ? OR ms.customer_name LIKE ?)";
    $like = '%' . $snSearch . '%';
    $ontParams = array_merge($ontParams, [$like, $like, $like, $like, $like, $like, $like]);
}

$ontSql .= " ORDER BY CASE WHEN ms.status = 'available' THEN 1 WHEN ms.status = 'allocated' THEN 2 ELSE 3 END, ms.received_date DESC, ms.id DESC";
$stmtOnt = $pdo->prepare($ontSql);
$stmtOnt->execute($ontParams);
$ontList = $stmtOnt->fetchAll();

// KPI Stats ONT
$totalOntAll = (int)$pdo->query("SELECT COUNT(*) FROM material_serials")->fetchColumn();
$totalOntGudang = (int)$pdo->query("SELECT COUNT(*) FROM material_serials WHERE status = 'available'")->fetchColumn();
$totalOntTeknisi = (int)$pdo->query("SELECT COUNT(*) FROM material_serials WHERE status = 'allocated'")->fetchColumn();
$totalOntInstalled = (int)$pdo->query("SELECT COUNT(*) FROM material_serials WHERE status = 'installed'")->fetchColumn();

// ONT Materials for Dropdown
$serializedMaterials = $pdo->query("SELECT id, code, name, brand FROM materials WHERE is_serialized = 1 ORDER BY name ASC")->fetchAll();

// ---------------------------------------------------------
// 2. QUERY KABEL DROP CORE (TAB 2)
// ---------------------------------------------------------
$cableSql = "
    SELECT m.*, c.name as category_name
    FROM materials m
    LEFT JOIN categories c ON m.category_id = c.id
    WHERE m.category_id = 2
";
$cableParams = [];
if (!empty($cableSearch)) {
    $cableSql .= " AND (m.name LIKE ? OR m.code LIKE ?)";
    $likeC = '%' . $cableSearch . '%';
    $cableParams = [$likeC, $likeC];
}
$cableSql .= " ORDER BY m.cable_length DESC";
$stmtCable = $pdo->prepare($cableSql);
$stmtCable->execute($cableParams);
$cablesList = $stmtCable->fetchAll();

$totalCablesRoll = (int)$pdo->query("SELECT SUM(stock_current) FROM materials WHERE category_id = 2")->fetchColumn();

// ---------------------------------------------------------
// 3. QUERY MUTASI STOK (TAB 3)
// ---------------------------------------------------------
$mutations = [];
if ($activeTab === 'mutasi') {
    $mutSql = "
        SELECT sm.*, m.name as material_name, m.code as material_code, m.unit, u.name as user_name
        FROM stock_mutations sm
        JOIN materials m ON sm.material_id = m.id
        JOIN users u ON sm.user_id = u.id
        ORDER BY sm.created_at DESC LIMIT 60
    ";
    $mutations = $pdo->query($mutSql)->fetchAll();
}
?>

<div class="main-wrapper">
  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <main class="content-body">
    <!-- Header Row -->
    <div class="page-header-row">
      <div>
        <div class="page-title-heading">Manajemen Stok & Logistik Gudang</div>
        <div class="page-title-subheading">Monitoring stok Serial Number ONT (Stok Gudang vs Dibawa Teknisi) dan roll kabel drop core.</div>
      </div>

      <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button type="button" class="btn-primary" onclick="openAddOntModal()">
          <i class="bi bi-plus-circle-fill me-1"></i> Tambah SN ONT Baru
        </button>
        <button type="button" class="btn-secondary" onclick="openRestockModal()">
          <i class="bi bi-box-arrow-in-down me-1 text-success"></i> Restock Barang Masuk
        </button>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div style="display: flex; gap: 12px; border-bottom: 2px solid var(--border-color); margin-bottom: 24px; overflow-x: auto;">
      <a href="stok.php?tab=ont" style="padding: 12px 20px; font-weight: 700; font-size: 0.92rem; border-bottom: 3px solid <?= ($activeTab === 'ont') ? 'var(--primary)' : 'transparent' ?>; color: <?= ($activeTab === 'ont') ? 'var(--primary)' : 'var(--text-muted)' ?>; text-decoration: none; margin-bottom: -2px; display: inline-flex; align-items: center; gap: 8px;">
        <i class="bi bi-router"></i> Stok ONT & Serial Number (<?= $totalOntAll ?> Unit)
      </a>
      <a href="stok.php?tab=kabel" style="padding: 12px 20px; font-weight: 700; font-size: 0.92rem; border-bottom: 3px solid <?= ($activeTab === 'kabel') ? 'var(--primary)' : 'transparent' ?>; color: <?= ($activeTab === 'kabel') ? 'var(--primary)' : 'var(--text-muted)' ?>; text-decoration: none; margin-bottom: -2px; display: inline-flex; align-items: center; gap: 8px;">
        <i class="bi bi-bezier2"></i> Stok Kabel Drop Core (<?= $totalCablesRoll ?> Roll)
      </a>
      <a href="stok.php?tab=mutasi" style="padding: 12px 20px; font-weight: 700; font-size: 0.92rem; border-bottom: 3px solid <?= ($activeTab === 'mutasi') ? 'var(--primary)' : 'transparent' ?>; color: <?= ($activeTab === 'mutasi') ? 'var(--primary)' : 'var(--text-muted)' ?>; text-decoration: none; margin-bottom: -2px; display: inline-flex; align-items: center; gap: 8px;">
        <i class="bi bi-journal-check"></i> Riwayat & Mutasi Stok
      </a>
    </div>

    <!-- =========================================================
         TAB 1: STOK ONT & SERIAL NUMBER
         ========================================================= -->
    <?php if ($activeTab === 'ont'): ?>


      <!-- Filter & Search Toolbar -->
      <div style="background-color: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 18px 20px; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
        <form method="GET" action="stok.php" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
          <input type="hidden" name="tab" value="ont">
          
          <div style="flex: 1; min-width: 240px; position: relative;">
            <input type="text" name="sn" class="form-control font-mono" placeholder="Cari SN ZTE/Huawei, MAC, Teknisi, No Bon, Pelanggan..." value="<?= htmlspecialchars($snSearch) ?>" style="padding-left: 36px;">
            <i class="bi bi-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-dim);"></i>
          </div>

          <div style="min-width: 190px;">
            <select name="status" class="form-select" onchange="this.form.submit()">
              <option value="">-- Semua Status Lokasi --</option>
              <option value="gudang" <?= ($ontStatusFilter === 'gudang') ? 'selected' : '' ?>>📦 Stok Gudang</option>
              <option value="teknisi" <?= ($ontStatusFilter === 'teknisi') ? 'selected' : '' ?>>🚚 Di Teknisi</option>
              <option value="installed" <?= ($ontStatusFilter === 'installed') ? 'selected' : '' ?>>🏠 Selesai</option>
              <option value="bad" <?= ($ontStatusFilter === 'bad') ? 'selected' : '' ?>>⚠️ Unit Rusak / Bad</option>
            </select>
          </div>

          <div style="min-width: 210px;">
            <select name="material_id" class="form-select" onchange="this.form.submit()">
              <option value="">-- Semua Tipe ONT --</option>
              <?php foreach ($serializedMaterials as $sm): ?>
                <option value="<?= $sm['id'] ?>" <?= ($ontMaterialId == $sm['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sm['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <button type="submit" class="btn-primary" style="padding: 10px 18px;">
            <i class="bi bi-filter me-1"></i> Filter
          </button>

          <?php if (!empty($snSearch) || !empty($ontStatusFilter) || $ontMaterialId > 0): ?>
            <a href="stok.php?tab=ont" class="btn-secondary" style="padding: 10px 14px;" title="Reset Filter">
              <i class="bi bi-arrow-counterclockwise"></i> Reset
            </a>
          <?php endif; ?>
        </form>
      </div>

      <!-- ONT Stock Table -->
      <div style="background-color: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm);">
        <div style="padding: 18px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #fafbfc;">
          <div style="font-weight: 800; font-size: 1rem; color: var(--text-main);">
            <i class="bi bi-router text-primary me-2"></i> Daftar Stok Serial Number ONT (<?= count($ontList) ?>)
          </div>
          <span style="font-size: 0.8rem; color: var(--text-dim);">Menampilkan unit terverifikasi</span>
        </div>

        <div class="table-responsive mobile-cards-view">
          <table class="data-table">
            <thead>
              <tr>
                <th style="width: 50px;">No</th>
                <th>Serial Number (SN) & MAC</th>
                <th>Merek & Tipe ONT</th>
                <th>Status & Lokasi Unit</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($ontList)): ?>
                <tr>
                  <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <i class="bi bi-search" style="font-size: 2.2rem; display: block; margin-bottom: 8px; opacity: 0.4;"></i>
                    Tidak ada Serial Number ONT yang cocok dengan pencarian / filter Anda.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($ontList as $idx => $sn): 
                  $isGudang = ($sn['status'] === 'available');
                  $isTeknisi = ($sn['status'] === 'allocated' || (!empty($sn['bon_id']) && $sn['status'] !== 'installed' && $sn['status'] !== 'bad'));
                  $isInstalled = ($sn['status'] === 'installed');
                  $isBad = ($sn['status'] === 'bad');
                ?>
                  <tr>
                    <td data-label="No" style="color: var(--text-dim); font-size: 0.82rem;"><?= $idx + 1 ?></td>
                    <td data-label="Serial Number (SN)">
                      <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="stat-icon" style="width: 36px; height: 36px; font-size: 1.1rem; border-radius: 8px; background: rgba(2, 132, 199, 0.08); color: var(--primary);">
                          <i class="bi bi-upc-scan"></i>
                        </div>
                        <div>
                          <div class="font-mono" style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">
                            <?= htmlspecialchars($sn['serial_number']) ?>
                          </div>
                          <?php if (!empty($sn['mac_address'])): ?>
                            <small style="color: var(--text-muted); font-family: var(--font-mono); font-size: 0.76rem;">
                              MAC: <?= htmlspecialchars($sn['mac_address']) ?>
                            </small>
                          <?php endif; ?>
                        </div>
                      </div>
                    </td>
                    <td data-label="Merek & Tipe ONT">
                      <div style="font-weight: 700; color: var(--text-main); font-size: 0.88rem;">
                        <?= htmlspecialchars($sn['material_name']) ?>
                      </div>
                      <small style="color: var(--text-dim); font-size: 0.78rem;">
                        <?= htmlspecialchars($sn['material_brand'] ?: 'ZTE / Huawei / Fiberhome') ?> &bull; <?= htmlspecialchars($sn['material_model'] ?: '-') ?>
                      </small>
                    </td>
                    <td data-label="Status & Lokasi">
                      <?php if ($isGudang): ?>
                        <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-weight: 700; padding: 6px 12px; font-size: 0.82rem;">
                          <i class="bi bi-box-seam-fill me-1"></i> Stok Gudang
                        </span>
                      <?php elseif ($isTeknisi): ?>
                        <span class="badge" style="background: rgba(245, 158, 11, 0.14); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.35); font-weight: 700; padding: 6px 12px; font-size: 0.82rem;">
                          <i class="bi bi-person-fill me-1"></i> <?= htmlspecialchars($sn['technician_name'] ?: 'Teknisi') ?>
                        </span>
                      <?php elseif ($isInstalled): ?>
                        <span class="badge" style="background: rgba(2, 132, 199, 0.12); color: var(--primary); border: 1px solid rgba(2, 132, 199, 0.3); font-weight: 700; padding: 6px 12px; font-size: 0.82rem;">
                          <i class="bi bi-check2-circle me-1"></i> Selesai
                        </span>
                      <?php elseif ($isBad): ?>
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                          <span class="badge" style="background: rgba(239, 68, 68, 0.12); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); font-weight: 700; padding: 6px 12px; font-size: 0.82rem;">
                            <i class="bi bi-x-octagon-fill me-1"></i> Unit Rusak / Bad
                          </span>
                          <?php if ($isAdmin): ?>
                            <button 
                              type="button" 
                              class="btn-secondary" 
                              style="padding: 4px 10px; font-size: 0.75rem; border-color: rgba(239, 68, 68, 0.4); color: #dc2626; cursor: pointer;"
                              onclick="openRmaModal(<?= $sn['id'] ?>, '<?= htmlspecialchars($sn['serial_number'], ENT_QUOTES) ?>', '<?= htmlspecialchars($sn['material_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($sn['installed_notes'] ?? '', ENT_QUOTES) ?>')"
                            >
                              <i class="bi bi-arrow-repeat me-1"></i> Kelola Retur / RMA
                            </button>
                          <?php endif; ?>
                        </div>
                        <?php if (!empty($sn['installed_notes'])): ?>
                          <div style="font-size: 0.74rem; color: #dc2626; margin-top: 4px; font-weight: 600;">
                            <i class="bi bi-info-circle me-1"></i> <?= htmlspecialchars($sn['installed_notes']) ?>
                          </div>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="badge" style="background: rgba(100, 116, 139, 0.12); color: #64748b; font-size: 0.82rem;">
                          <?= htmlspecialchars(ucfirst($sn['status'])) ?>
                        </span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    <!-- =========================================================
         TAB 2: STOK KABEL DROP CORE
         ========================================================= -->
    <?php elseif ($activeTab === 'kabel'): ?>

      <!-- Filter Bar for Cables -->
      <div style="background-color: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 18px 20px; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
        <form method="GET" action="stok.php" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
          <input type="hidden" name="tab" value="kabel">
          
          <div style="flex: 1; min-width: 240px; position: relative;">
            <input type="text" name="cable_search" class="form-control" placeholder="Cari ukuran kabel (150M, 100M, 75M, 50M)..." value="<?= htmlspecialchars($cableSearch) ?>" style="padding-left: 36px;">
            <i class="bi bi-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-dim);"></i>
          </div>

          <button type="submit" class="btn-primary" style="padding: 10px 18px;">
            <i class="bi bi-filter me-1"></i> Filter
          </button>
        </form>
      </div>

      <!-- Cables Table -->
      <div style="background-color: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm);">
        <div style="padding: 18px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #fafbfc;">
          <div style="font-weight: 800; font-size: 1rem; color: var(--text-main);">
            <i class="bi bi-bezier2 text-primary me-2"></i> Daftar Stok Roll Kabel Drop Core
          </div>
          <span style="font-size: 0.8rem; color: var(--text-dim);">Total: <?= $totalCablesRoll ?> Roll</span>
        </div>

        <div class="table-responsive mobile-cards-view">
          <table class="data-table">
            <thead>
              <tr>
                <th>Nama Material Drop Core</th>
                <th>Kategori</th>
                <th style="width: 260px;">Ketersediaan Stok Fisik</th>
                <th style="text-align: right;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cablesList as $c): 
                $isLow = ($c['stock_current'] <= $c['stock_min']);
              ?>
                <tr>
                  <td data-label="Nama Material">
                    <div style="font-weight: 700; color: var(--text-main); font-size: 0.92rem;">
                      <?= htmlspecialchars($c['name']) ?>
                    </div>
                    <small style="color: var(--text-dim);"><?= htmlspecialchars($c['brand'] ?: 'V-Sol / Netlink') ?> &bull; <?= htmlspecialchars($c['model_type'] ?: 'Pre-Connectorized') ?></small>
                  </td>
                  <td data-label="Kategori">
                    <span class="badge" style="background: rgba(2, 132, 199, 0.1); color: var(--primary); font-weight: 700;">
                      <i class="bi bi-bezier2 me-1"></i> Kabel <?= $c['cable_length'] ?>M
                    </span>
                  </td>
                  <td data-label="Ketersediaan Stok">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                      <span style="font-size: 1.05rem; font-weight: 800; font-family: var(--font-mono); color: <?= $isLow ? 'var(--danger)' : 'var(--text-main)' ?>;">
                        <?= $c['stock_current'] ?> <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted);"><?= $c['unit'] ?></span>
                      </span>
                      <?php if ($isLow): ?>
                        <span class="badge" style="background: rgba(239, 68, 68, 0.12); color: #ef4444; font-size: 0.72rem; font-weight: 700;">⚠️ Kritis (Min: <?= $c['stock_min'] ?>)</span>
                      <?php else: ?>
                        <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #10b981; font-size: 0.72rem; font-weight: 700;">✅ Aman (Min: <?= $c['stock_min'] ?>)</span>
                      <?php endif; ?>
                    </div>
                    <div style="background-color: #f1f5f9; height: 6px; border-radius: var(--radius-full); overflow: hidden;">
                      <div style="background: <?= $isLow ? '#ef4444' : 'var(--primary)' ?>; height: 100%; width: <?= min(100, round(($c['stock_current'] / max(1, $c['stock_min'] * 3)) * 100)) ?>%;"></div>
                    </div>
                  </td>
                  <td data-label="Aksi" style="text-align: right;">
                    <div style="display: inline-flex; gap: 6px;">
                      <button type="button" class="btn-icon-action" onclick="openRestockModal(<?= $c['id'] ?>)" title="Restock Kabel Ini" style="color: var(--success);">
                        <i class="bi bi-plus-circle"></i>
                      </button>
                      <button type="button" class="btn-icon-action" onclick="openModalBon(<?= $c['id'] ?>)" title="Input Bon untuk Kabel Ini" style="color: var(--primary);">
                        <i class="bi bi-receipt"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    <!-- =========================================================
         TAB 3: RIWAYAT & MUTASI STOK
         ========================================================= -->
    <?php elseif ($activeTab === 'mutasi'): ?>

      <div style="background-color: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm);">
        <div style="padding: 18px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #fafbfc;">
          <div style="font-weight: 800; font-size: 1rem; color: var(--text-main);">
            <i class="bi bi-journal-check text-primary me-2"></i> Log Kartu Mutasi Stok Masuk & Keluar
          </div>
          <span style="font-size: 0.8rem; color: var(--text-dim);">60 Transaksi Terakhir</span>
        </div>

        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Waktu</th>
                <th>Jenis Mutasi</th>
                <th>Nama Material / Kabel</th>
                <th>Kuantiti</th>
                <th>Perubahan Stok</th>
                <th>Petugas</th>
                <th>Keterangan</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($mutations)): ?>
                <tr>
                  <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                    Belum ada riwayat mutasi stok.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($mutations as $m): 
                  $isIn = (strpos($m['mutation_type'], 'in_') === 0);
                ?>
                  <tr>
                    <td style="font-size: 0.82rem; color: var(--text-muted); white-space: nowrap;">
                      <?= formatTanggalIndo($m['created_at']) ?>
                    </td>
                    <td>
                      <?php if ($isIn): ?>
                        <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #10b981; font-weight: 700;">
                          <i class="bi bi-arrow-down-left me-1"></i> Masuk (Restock)
                        </span>
                      <?php else: ?>
                        <span class="badge" style="background: rgba(239, 68, 68, 0.12); color: #ef4444; font-weight: 700;">
                          <i class="bi bi-arrow-up-right me-1"></i> Keluar (Bon)
                        </span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <strong style="color: var(--text-main); font-size: 0.88rem;"><?= htmlspecialchars($m['material_name']) ?></strong>
                      <div class="font-mono" style="font-size: 0.75rem; color: var(--text-dim);"><?= htmlspecialchars($m['material_code']) ?></div>
                    </td>
                    <td>
                      <span class="font-mono" style="font-weight: 800; color: <?= $isIn ? '#10b981' : '#ef4444' ?>;">
                        <?= $isIn ? '+' : '-' ?><?= $m['quantity'] ?> <?= $m['unit'] ?>
                      </span>
                    </td>
                    <td>
                      <span class="font-mono" style="font-size: 0.82rem; color: var(--text-muted);">
                        <?= $m['stock_before'] ?> &rarr; <strong style="color: var(--text-main);"><?= $m['stock_after'] ?></strong>
                      </span>
                    </td>
                    <td style="font-size: 0.84rem; color: var(--text-main);">
                      <?= htmlspecialchars($m['user_name']) ?>
                    </td>
                    <td style="font-size: 0.82rem; color: var(--text-muted);">
                      <?= htmlspecialchars($m['notes'] ?: '-') ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php endif; ?>

  </main>
</div>

<!-- =========================================================
     MODAL: TAMBAH SERIAL NUMBER ONT BARU
     ========================================================= -->
<div class="modal-backdrop" id="addOntModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <div class="modal-title">
        <i class="bi bi-plus-circle-fill text-primary"></i> Tambah Serial Number ONT Baru
      </div>
      <button type="button" class="modal-close-btn" onclick="closeAddOntModal()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <form method="POST" action="api/stok_action.php">
      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
      <input type="hidden" name="action" value="restock">
      <input type="hidden" name="reference_id" value="SN-ADD-<?= date('YmdHis') ?>">
      <input type="hidden" name="notes" value="Penerimaan Serial Number ONT baru ke Gudang">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label"><i class="bi bi-router text-primary me-1"></i> Tipe / Merek ONT *</label>
          <select name="material_id" id="modalOntMaterialId" class="form-select" required>
            <?php foreach ($serializedMaterials as $sm): ?>
              <option value="<?= $sm['id'] ?>">
                <?= htmlspecialchars($sm['name']) ?> (<?= htmlspecialchars($sm['brand'] ?: 'ZTE/Huawei') ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <label class="form-label" style="margin-bottom: 0;"><i class="bi bi-upc-scan text-primary me-1"></i> Serial Number (SN) *</label>
            <button 
              type="button" 
              class="btn-secondary" 
              style="padding: 4px 10px; font-size: 0.76rem; border-color: rgba(2, 132, 199, 0.4); color: var(--primary); cursor: pointer;"
              onclick="openBarcodeScanner((scannedSN) => {
                const ta = document.getElementById('inputNewSerials');
                if (ta) {
                  ta.value = ta.value ? ta.value + '\n' + scannedSN : scannedSN;
                  const qtyInput = document.getElementById('modalOntQty');
                  if (qtyInput) {
                    const lines = ta.value.trim().split(/\r\n|\r|\n/).filter(s => s.trim().length > 0);
                    qtyInput.value = lines.length;
                  }
                }
              })"
            >
              <i class="bi bi-camera-fill me-1 text-primary"></i> Scan Barcode Kamera
            </button>
          </div>
          <textarea name="new_serials" id="inputNewSerials" class="form-control font-mono" rows="4" placeholder="Masukkan 1 atau banyak SN (bisa pisahkan dengan baris baru atau koma)&#10;Contoh:&#10;ZTEGC892F110&#10;ZTEGC892F111&#10;HWTC8812905" required></textarea>
          <small style="color: var(--text-dim); font-size: 0.74rem;">Bisa ketik manual atau gunakan tombol <strong>Scan Barcode Kamera</strong> di atas.</small>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label"><i class="bi bi-calculator text-primary me-1"></i> Jumlah Unit Ditambahkan *</label>
          <input type="number" name="quantity" id="modalOntQty" class="form-control font-mono" value="1" min="1" required>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeAddOntModal()">Batal</button>
        <button type="submit" class="btn-primary">
          <i class="bi bi-check-lg me-1"></i> Simpan SN ke Stok Gudang
        </button>
      </div>
    </form>
  </div>
</div>

<!-- =========================================================
     MODAL: KELOLA UNIT BAD / RETUR VENDOR (RMA)
     ========================================================= -->
<div class="modal-backdrop" id="rmaModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <div class="modal-title">
        <i class="bi bi-arrow-repeat text-primary"></i> Kelola Unit Rusak / Retur Vendor (RMA)
      </div>
      <button type="button" class="modal-close-btn" onclick="closeRmaModal()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <form method="POST" action="api/stok_action.php">
      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
      <input type="hidden" name="action" value="update_bad_status">
      <input type="hidden" name="serial_id" id="rmaSerialId" value="">

      <div class="modal-body">
        <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: var(--radius-md); padding: 14px; margin-bottom: 18px;">
          <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Perangkat ONT Bermasalah:</div>
          <div style="font-weight: 800; font-size: 1rem; color: var(--text-main); margin-top: 2px;" id="rmaMatName">-</div>
          <div style="font-family: var(--font-mono); font-weight: 800; font-size: 0.92rem; color: #dc2626; margin-top: 2px;" id="rmaSerialNum">-</div>
          <div style="font-size: 0.76rem; color: #b91c1c; margin-top: 6px;" id="rmaCurrentNotes">-</div>
        </div>

        <div class="form-group">
          <label class="form-label font-sans" style="font-weight: 700;">Pilih Tindakan Logistik *</label>
          <div style="display: flex; flex-direction: column; gap: 10px;">
            <label style="display: flex; align-items: flex-start; gap: 10px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; cursor: pointer;">
              <input type="radio" name="bad_action" value="terima_gudang" checked style="margin-top: 3px;">
              <div>
                <strong style="font-size: 0.86rem; color: var(--text-main); display: block;">📦 Terima Fisik di Gudang</strong>
                <span style="font-size: 0.74rem; color: var(--text-muted);">Teknisi telah menyerahkan fisik modem rusak kembali ke loket gudang.</span>
              </div>
            </label>

            <label style="display: flex; align-items: flex-start; gap: 10px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; cursor: pointer;">
              <input type="radio" name="bad_action" value="retur_vendor" style="margin-top: 3px;">
              <div>
                <strong style="font-size: 0.86rem; color: var(--text-main); display: block;">🚚 Kirim Retur / Klaim Garansi Vendor</strong>
                <span style="font-size: 0.74rem; color: var(--text-muted);">Unit dikirim ke distributor/vendor ZTE/Huawei untuk proses klaim garansi.</span>
              </div>
            </label>

            <label style="display: flex; align-items: flex-start; gap: 10px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; cursor: pointer;">
              <input type="radio" name="bad_action" value="ganti_unit_selesai" style="margin-top: 3px;">
              <div>
                <strong style="font-size: 0.86rem; color: #047857; display: block;">✅ Selesai / Diganti Unit Baru (Masuk Stok Gudang)</strong>
                <span style="font-size: 0.74rem; color: var(--text-muted);">Vendor telah mengganti unit baru. Unit akan otomatis kembali ke status <strong>Tersedia di Gudang</strong> (+1 Stok).</span>
              </div>
            </label>
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Nomor Resi / No. RMA / Catatan Tambahan (Opsional)</label>
          <input type="text" name="rma_notes" class="form-control" placeholder="Contoh: No. Resi JNE 12345 / RMA-ZTE-009">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeRmaModal()">Batal</button>
        <button type="submit" class="btn-primary">
          <i class="bi bi-check-lg me-1"></i> Simpan Status
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddOntModal() {
  const modal = document.getElementById('addOntModal');
  modal.classList.add('show');
}

function closeAddOntModal() {
  const modal = document.getElementById('addOntModal');
  modal.classList.remove('show');
}

function openRmaModal(serialId, snNumber, matName, notes) {
  document.getElementById('rmaSerialId').value = serialId;
  document.getElementById('rmaSerialNum').textContent = 'SN: ' + snNumber;
  document.getElementById('rmaMatName').textContent = matName;
  document.getElementById('rmaCurrentNotes').textContent = notes ? 'Catatan kendala: ' + notes : 'Tidak ada catatan';
  
  const modal = document.getElementById('rmaModal');
  modal.classList.add('show');
}

function closeRmaModal() {
  const modal = document.getElementById('rmaModal');
  modal.classList.remove('show');
}

window.addEventListener('click', function(e) {
  const addModal = document.getElementById('addOntModal');
  const rmaModal = document.getElementById('rmaModal');
  if (e.target === addModal) closeAddOntModal();
  if (e.target === rmaModal) closeRmaModal();
});

window.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeAddOntModal();
    closeRmaModal();
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
