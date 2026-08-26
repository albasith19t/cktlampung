<?php
/**
 * View: Manajemen Stok Barang, ONT & Kabel Drop Core
 * PT Cipta Karya Teknologi (CKT Lampung)
 */

$pageTitle = "Stok & Material Gudang";
$pageHeaderTitle = "Manajemen Stok Material & Pelacakan ONT";
$pageHeaderSubtitle = "Data real-time stok ONT Besar/Kecil dan kabel drop core 4 ukuran.";

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

// Active Tab
$activeTab = $_GET['tab'] ?? 'materials'; // 'materials', 'serials', 'mutations'

// Filter parameters for materials
$catFilter = $_GET['cat'] ?? '';
$stockStatusFilter = $_GET['filter'] ?? '';
$searchFilter = trim($_GET['search'] ?? '');
$highlightId = (int)($_GET['highlight'] ?? 0);

// Filter parameters for serials
$snSearch = trim($_GET['sn'] ?? '');
$serMaterialId = (int)($_GET['material_id'] ?? 0);
$serStatus = trim($_GET['status'] ?? '');

// If sn search query or material_id is present without explicit tab, switch to 'serials' tab
if (!empty($snSearch) || ($serMaterialId > 0 && !isset($_GET['tab']))) {
    $activeTab = 'serials';
}

// 1. Fetch Materials Query
$matSql = "
    SELECT m.*, c.name as category_name, c.code as category_code,
           (SELECT COUNT(*) FROM material_serials ms WHERE ms.material_id = m.id AND ms.status = 'available') as available_serials_count
    FROM materials m
    LEFT JOIN categories c ON m.category_id = c.id
    WHERE 1=1
";
$matParams = [];

if (!empty($catFilter)) {
    $matSql .= " AND c.code = ?";
    $matParams[] = $catFilter;
}

if ($stockStatusFilter === 'kritis') {
    $matSql .= " AND m.stock_current <= m.stock_min";
} elseif ($stockStatusFilter === 'aman') {
    $matSql .= " AND m.stock_current > m.stock_min";
}

if (!empty($searchFilter)) {
    $matSql .= " AND (m.name LIKE ? OR m.code LIKE ? OR m.model_type LIKE ? OR m.brand LIKE ?)";
    $like = '%' . $searchFilter . '%';
    $matParams[] = $like;
    $matParams[] = $like;
    $matParams[] = $like;
    $matParams[] = $like;
}

$matSql .= " ORDER BY m.category_id ASC, m.cable_length DESC, m.name ASC";
$stmtMat = $pdo->prepare($matSql);
$stmtMat->execute($matParams);
$materials = $stmtMat->fetchAll();

// 2. Fetch Categories for Tab Filters
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
$serializedMaterials = $pdo->query("SELECT id, code, name FROM materials WHERE is_serialized = 1 ORDER BY name ASC")->fetchAll();
$totalSerialsCount = (int)$pdo->query("SELECT COUNT(*) FROM material_serials")->fetchColumn();

// 3. Fetch Serial Numbers if activeTab == 'serials'
$serials = [];
if ($activeTab === 'serials') {
    $serSql = "
        SELECT ms.*, m.name as material_name, m.code as material_code, b.bon_number, u.name as technician_name
        FROM material_serials ms
        JOIN materials m ON ms.material_id = m.id
        LEFT JOIN bon_requests b ON ms.bon_id = b.id
        LEFT JOIN users u ON b.user_id = u.id
        WHERE 1=1
    ";
    $serParams = [];

    if ($serMaterialId > 0) {
        $serSql .= " AND ms.material_id = ?";
        $serParams[] = $serMaterialId;
    }

    if (!empty($serStatus)) {
        $serSql .= " AND ms.status = ?";
        $serParams[] = $serStatus;
    }

    if (!empty($snSearch)) {
        $serSql .= " AND (ms.serial_number LIKE ? OR ms.mac_address LIKE ? OR b.bon_number LIKE ? OR m.name LIKE ? OR m.code LIKE ? OR u.name LIKE ? OR ms.customer_name LIKE ?)";
        $like = '%' . $snSearch . '%';
        $serParams[] = $like;
        $serParams[] = $like;
        $serParams[] = $like;
        $serParams[] = $like;
        $serParams[] = $like;
        $serParams[] = $like;
        $serParams[] = $like;
    }

    $serSql .= " ORDER BY ms.received_date DESC LIMIT 150";
    $stmtSer = $pdo->prepare($serSql);
    $stmtSer->execute($serParams);
    $serials = $stmtSer->fetchAll();
}

// 4. Fetch Mutations if activeTab == 'mutations'
$mutations = [];
if ($activeTab === 'mutations') {
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
    <!-- Header -->
    <div class="page-header-row">
      <div>
        <div class="page-title-heading">Manajemen Stok & Logistik Gudang</div>
        <div class="page-title-subheading">Monitoring stok fisik, alokasi ONT ber-serial number, dan kartu riwayat mutasi barang.</div>
      </div>

      <?php if ($isAdmin): ?>
        <div style="display: flex; gap: 10px;">
          <button type="button" class="btn-primary" onclick="openRestockModal()">
            <i class="bi bi-box-arrow-in-down me-1"></i> Input Restock Barang Masuk
          </button>
        </div>
      <?php endif; ?>
    </div>

    <!-- Main Navigation Tabs -->
    <div style="display: flex; gap: 10px; border-bottom: 2px solid var(--border-color); margin-bottom: 24px;">
      <a href="stok.php?tab=materials" style="padding: 12px 18px; font-weight: 700; font-size: 0.88rem; border-bottom: 3px solid <?= ($activeTab === 'materials') ? 'var(--primary)' : 'transparent' ?>; color: <?= ($activeTab === 'materials') ? 'var(--primary)' : 'var(--text-muted)' ?>; text-decoration: none; margin-bottom: -2px;">
        <i class="bi bi-boxes me-1"></i> Data Material & Kabel (<?= count($materials) ?>)
      </a>
      <a href="stok.php?tab=serials" style="padding: 12px 18px; font-weight: 700; font-size: 0.88rem; border-bottom: 3px solid <?= ($activeTab === 'serials') ? 'var(--primary)' : 'transparent' ?>; color: <?= ($activeTab === 'serials') ? 'var(--primary)' : 'var(--text-muted)' ?>; text-decoration: none; margin-bottom: -2px;">
        <i class="bi bi-upc-scan me-1"></i> Serial Number ONT & Modem (<?= $totalSerialsCount ?>)
      </a>
      <a href="stok.php?tab=mutations" style="padding: 12px 18px; font-weight: 700; font-size: 0.88rem; border-bottom: 3px solid <?= ($activeTab === 'mutations') ? 'var(--primary)' : 'transparent' ?>; color: <?= ($activeTab === 'mutations') ? 'var(--primary)' : 'var(--text-muted)' ?>; text-decoration: none; margin-bottom: -2px;">
        <i class="bi bi-journal-check me-1"></i> Riwayat / Mutasi Stok
      </a>
    </div>

    <!-- TAB 1: MATERIALS & CABLES -->
    <?php if ($activeTab === 'materials'): ?>
      
      <!-- Filter Bar -->
      <div style="background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" action="stok.php" style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
          <input type="hidden" name="tab" value="materials">
          
          <div style="flex: 1; min-width: 220px;">
            <input type="text" name="search" class="form-control" placeholder="Cari nama material, tipe ONT, panjang kabel..." value="<?= htmlspecialchars($searchFilter) ?>">
          </div>

          <div style="width: 200px;">
            <select name="cat" class="form-select" onchange="this.form.submit()">
              <option value="">-- Semua Kategori --</option>
              <?php foreach ($categories as $cat): ?>
                <?php if ($cat['code'] === 'CAT-ACC') continue; ?>
                <option value="<?= $cat['code'] ?>" <?= ($catFilter === $cat['code']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div style="width: 180px;">
            <select name="filter" class="form-select" onchange="this.form.submit()">
              <option value="">-- Status Stok --</option>
              <option value="kritis" <?= ($stockStatusFilter === 'kritis') ? 'selected' : '' ?>>⚠️ Stok Kritis (Menipis)</option>
              <option value="aman" <?= ($stockStatusFilter === 'aman') ? 'selected' : '' ?>>✅ Stok Aman</option>
            </select>
          </div>

          <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 0.85rem;">
            <i class="bi bi-funnel-fill me-1"></i> Filter
          </button>
        </form>
      </div>

      <!-- Materials Table -->
      <div class="table-card">
        <div class="table-card-header">
          <div class="table-card-title">
            <i class="bi bi-box-seam text-primary"></i> Daftar Material Gudang
          </div>
          <span style="font-size: 0.8rem; color: var(--text-dim);">Menampilkan <?= count($materials) ?> barang</span>
        </div>

        <div class="table-responsive mobile-cards-view">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Nama Material / Kabel</th>
                <th>Kategori</th>
                <th style="width: 220px;">Ketersediaan Stok Fisik</th>
                <th>Serial Number</th>
                <th style="text-align: right;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($materials)): ?>
                <tr>
                  <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                    Tidak ada material yang sesuai kriteria filter.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($materials as $m): 
                  $isLow = ($m['stock_current'] <= $m['stock_min']);
                  $isHighlighted = ($m['id'] == $highlightId);
                ?>
                  <tr style="<?= $isHighlighted ? 'background-color: rgba(2, 132, 199, 0.08);' : '' ?>">
                    <td data-label="Kode">
                      <span class="font-mono" style="font-weight: 700; color: var(--primary);">
                        <?= htmlspecialchars($m['code']) ?>
                      </span>
                    </td>
                    <td data-label="Nama Material">
                      <div style="font-weight: 700; color: var(--text-main); font-size: 0.9rem;">
                        <?= htmlspecialchars($m['name']) ?>
                      </div>
                      <small style="color: var(--text-dim);"><?= htmlspecialchars($m['brand'] ?: '-') ?> &bull; <?= htmlspecialchars($m['model_type'] ?: '-') ?></small>
                    </td>
                    <td data-label="Kategori">
                      <?php if ($m['category_code'] === 'CAT-ONT'): ?>
                        <span class="badge badge-ont"><i class="bi bi-router me-1"></i> ONT & Router</span>
                      <?php elseif ($m['category_code'] === 'CAT-KBL'): ?>
                        <span class="badge badge-cable"><i class="bi bi-bezier2 me-1"></i> Kabel <?= $m['cable_length'] ?>M</span>
                      <?php endif; ?>
                    </td>
                    <td data-label="Stok Fisik">
                      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; width: 100%;">
                        <span style="font-size: 0.95rem; font-weight: 800; font-family: var(--font-mono); color: <?= $isLow ? 'var(--danger)' : 'var(--text-main)' ?>;">
                          <?= $m['stock_current'] ?> <span style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted);"><?= $m['unit'] ?></span>
                        </span>
                        <?php if ($isLow): ?>
                          <span class="badge" style="background: var(--danger-bg); color: var(--danger); font-size: 0.68rem;">Menipis (Min: <?= $m['stock_min'] ?>)</span>
                        <?php else: ?>
                          <span class="badge" style="background: var(--success-bg); color: var(--success); font-size: 0.68rem;">Aman (Min: <?= $m['stock_min'] ?>)</span>
                        <?php endif; ?>
                      </div>
                      <div style="background-color: #f1f5f9; height: 6px; border-radius: var(--radius-full); overflow: hidden; width: 100%;">
                        <div style="background: <?= $isLow ? 'var(--danger)' : 'var(--primary)' ?>; height: 100%; width: <?= min(100, round(($m['stock_current'] / max(1, $m['stock_min'] * 3)) * 100)) ?>%;"></div>
                      </div>
                    </td>
                    <td data-label="Serial Number">
                      <?php if ($m['is_serialized'] == 1): ?>
                        <a href="stok.php?tab=serials&material_id=<?= $m['id'] ?>" class="badge" style="background: rgba(139, 92, 246, 0.1); color: var(--ont-color); border: 1px solid rgba(139, 92, 246, 0.25); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding: 5px 10px;" title="Klik untuk lihat daftar serial number <?= htmlspecialchars($m['name']) ?>">
                          <i class="bi bi-upc me-1"></i> <strong><?= $m['available_serials_count'] ?></strong> SN Tersedia &rarr;
                        </a>
                      <?php else: ?>
                        <span style="color: var(--text-dim); font-size: 0.75rem;">-</span>
                      <?php endif; ?>
                    </td>
                    <td data-label="Aksi" style="text-align: right;">
                      <div class="btn-action-group" style="justify-content: flex-end; width: 100%;">
                        <?php if ($isAdmin): ?>
                          <button type="button" class="btn-icon-action" onclick="openRestockModal(<?= $m['id'] ?>)" title="Restock / Tambah Stok Masuk" style="color: var(--success);">
                            <i class="bi bi-plus-circle"></i>
                          </button>
                          <button type="button" class="btn-icon-action" onclick="openModalBon(<?= $m['id'] ?>)" title="Input Bon untuk Material Ini" style="color: var(--primary);">
                            <i class="bi bi-receipt"></i>
                          </button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    <!-- TAB 2: SERIAL NUMBERS (ONT) -->
    <?php elseif ($activeTab === 'serials'): ?>
      
      <!-- Filter Bar for Serials -->
      <div style="background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" action="stok.php" style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
          <input type="hidden" name="tab" value="serials">
          
          <div style="flex: 1; min-width: 240px;">
            <input type="text" name="sn" class="form-control font-mono" placeholder="Cari SN, MAC Address, No. Bon, Teknisi..." value="<?= htmlspecialchars($snSearch) ?>">
          </div>

          <div style="min-width: 200px;">
            <select name="material_id" class="form-control" onchange="this.form.submit()">
              <option value="">-- Semua Model ONT --</option>
              <?php foreach ($serializedMaterials as $sm): ?>
                <option value="<?= $sm['id'] ?>" <?= ($serMaterialId == $sm['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sm['name']) ?> (<?= htmlspecialchars($sm['code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div style="min-width: 170px;">
            <select name="status" class="form-control" onchange="this.form.submit()">
              <option value="">-- Semua Status --</option>
              <option value="available" <?= ($serStatus === 'available') ? 'selected' : '' ?>>Di Gudang (Tersedia)</option>
              <option value="allocated" <?= ($serStatus === 'allocated') ? 'selected' : '' ?>>Dibawa Teknisi</option>
              <option value="installed" <?= ($serStatus === 'installed') ? 'selected' : '' ?>>Terpasang</option>
              <option value="bad" <?= ($serStatus === 'bad') ? 'selected' : '' ?>>Bad</option>
              <option value="change" <?= ($serStatus === 'change') ? 'selected' : '' ?>>Change</option>
            </select>
          </div>

          <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 0.85rem;">
            <i class="bi bi-funnel-fill me-1"></i> Filter
          </button>
          <?php if (!empty($snSearch) || $serMaterialId > 0 || !empty($serStatus)): ?>
            <a href="stok.php?tab=serials" class="btn-secondary" style="padding: 8px 14px; font-size: 0.85rem;">Reset</a>
          <?php endif; ?>
        </form>
      </div>

      <div class="table-card">
        <div class="table-card-header">
          <div class="table-card-title">
            <i class="bi bi-upc-scan text-primary"></i> Data Serial Number ONT & Modem Wi-Fi
          </div>
          <span style="font-size: 0.8rem; color: var(--text-dim);">Menampilkan <?= count($serials) ?> serial number</span>
        </div>

        <div class="table-responsive mobile-cards-view">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Serial Number (SN)</th>
                <th>MAC Address</th>
                <th>Tipe / Model ONT</th>
                <th>Status</th>
                <th>Dialokasikan ke Bon</th>
                <th>Teknisi Pembawa</th>
                <th>Tanggal Masuk</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($serials)): ?>
                <tr>
                  <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                    Tidak ada serial number yang cocok.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($serials as $s): ?>
                  <tr>
                    <td data-label="Serial Number (SN)">
                      <span class="font-mono" style="font-weight: 800; color: var(--primary); font-size: 0.9rem;">
                        <?= htmlspecialchars($s['serial_number']) ?>
                      </span>
                    </td>
                    <td data-label="MAC Address">
                      <span class="font-mono" style="color: var(--text-muted);">
                        <?= htmlspecialchars($s['mac_address'] ?: '-') ?>
                      </span>
                    </td>
                    <td data-label="Model ONT" style="font-weight: 600; color: var(--text-main);">
                      <?= htmlspecialchars($s['material_name']) ?>
                    </td>
                    <td data-label="Status">
                      <?php if ($s['status'] === 'available'): ?>
                        <span class="status-pill status-approved"><i class="bi bi-check-circle"></i> Di Gudang</span>
                      <?php elseif ($s['status'] === 'allocated'): ?>
                        <span class="status-pill status-pending"><i class="bi bi-hourglass-split"></i> Dibawa Teknisi</span>
                      <?php elseif ($s['status'] === 'installed'): ?>
                        <span class="status-pill status-completed"><i class="bi bi-patch-check-fill"></i> Terpasang</span>
                      <?php elseif ($s['status'] === 'bad'): ?>
                        <span class="status-pill" style="background: rgba(239, 68, 68, 0.12); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); font-weight: 700;"><i class="bi bi-x-octagon-fill"></i> Bad</span>
                      <?php elseif ($s['status'] === 'change'): ?>
                        <span class="status-pill" style="background: rgba(245, 158, 11, 0.12); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); font-weight: 700;"><i class="bi bi-arrow-repeat"></i> Change</span>
                      <?php else: ?>
                        <span class="status-pill status-rejected"><i class="bi bi-question-circle"></i> <?= htmlspecialchars(ucfirst($s['status'])) ?></span>
                      <?php endif; ?>
                    </td>
                    <td data-label="Surat Bon">
                      <?php if ($s['bon_number']): ?>
                        <a href="bon.php?id=<?= $s['bon_id'] ?>" style="font-family: var(--font-mono); font-weight: 700; color: var(--primary);">
                          <?= htmlspecialchars($s['bon_number']) ?>
                        </a>
                      <?php else: ?>
                        <span style="color: var(--text-dim); font-size: 0.75rem;">-</span>
                      <?php endif; ?>
                    </td>
                    <td data-label="Teknisi">
                      <?= htmlspecialchars($s['technician_name'] ?: '-') ?>
                    </td>
                    <td data-label="Tgl Masuk" style="font-size: 0.78rem; color: var(--text-dim);">
                      <?= formatTanggalSingkat($s['received_date']) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    <!-- TAB 3: MUTATIONS AUDIT TRAIL -->
    <?php elseif ($activeTab === 'mutations'): ?>
      
      <div class="table-card">
        <div class="table-card-header">
          <div class="table-card-title">
            <i class="bi bi-journal-text text-primary"></i> Riwayat Keluar & Masuk Mutasi Barang
          </div>
          <span style="font-size: 0.8rem; color: var(--text-dim);">Audit Log Real-time</span>
        </div>

        <div class="table-responsive mobile-cards-view">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Waktu</th>
                <th>Material</th>
                <th>Jenis Mutasi</th>
                <th>Jumlah</th>
                <th>Stok Sebelum &rarr; Sesudah</th>
                <th>Referensi / No. Bon</th>
                <th>Petugas / User</th>
                <th>Keterangan</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($mutations)): ?>
                <tr>
                  <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                    Belum ada riwayat mutasi barang.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($mutations as $mut): ?>
                  <tr>
                    <td data-label="Waktu" style="font-size: 0.78rem; color: var(--text-dim); white-space: nowrap;">
                      <?= formatTanggalSingkat($mut['created_at']) ?>
                    </td>
                    <td data-label="Material" style="font-weight: 700; color: var(--text-main);">
                      <?= htmlspecialchars($mut['material_name']) ?>
                      <small style="display: block; font-family: var(--font-mono); color: var(--text-dim);"><?= htmlspecialchars($mut['material_code']) ?></small>
                    </td>
                    <td data-label="Mutasi">
                      <?php if ($mut['mutation_type'] === 'in_restock'): ?>
                        <span class="badge" style="background: var(--success-bg); color: var(--success); font-weight: 700;"><i class="bi bi-arrow-down-left"></i> Restock</span>
                      <?php elseif ($mut['mutation_type'] === 'out_bon'): ?>
                        <span class="badge" style="background: rgba(2, 132, 199, 0.1); color: var(--primary); font-weight: 700;"><i class="bi bi-arrow-up-right"></i> Bon</span>
                      <?php elseif ($mut['mutation_type'] === 'return'): ?>
                        <span class="badge" style="background: var(--warning-bg); color: var(--warning); font-weight: 700;"><i class="bi bi-arrow-counterclockwise"></i> Retur</span>
                      <?php else: ?>
                        <span class="badge" style="background: #f1f5f9; color: var(--text-muted); font-weight: 700;">Penyesuaian</span>
                      <?php endif; ?>
                    </td>
                    <td data-label="Jumlah" style="font-weight: 800; font-family: var(--font-mono); color: <?= ($mut['mutation_type'] === 'in_restock' || $mut['mutation_type'] === 'return') ? 'var(--success)' : 'var(--danger)' ?>;">
                      <?= ($mut['mutation_type'] === 'in_restock' || $mut['mutation_type'] === 'return') ? '+' : '-' ?><?= $mut['quantity'] ?> <?= $mut['unit'] ?>
                    </td>
                    <td data-label="Stok" style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-secondary);">
                      <?= $mut['stock_before'] ?> &rarr; <strong style="color: var(--primary);"><?= $mut['stock_after'] ?></strong>
                    </td>
                    <td data-label="Referensi">
                      <span class="font-mono" style="font-weight: 700; font-size: 0.8rem; color: var(--primary);">
                        <?= htmlspecialchars($mut['reference_id'] ?: '-') ?>
                      </span>
                    </td>
                    <td data-label="Petugas" style="font-weight: 600;">
                      <?= htmlspecialchars($mut['user_name']) ?>
                    </td>
                    <td data-label="Keterangan" style="font-size: 0.8rem; color: var(--text-muted);">
                      <?= htmlspecialchars($mut['notes'] ?: '-') ?>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
