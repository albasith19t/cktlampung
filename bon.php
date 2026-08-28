<?php
/**
 * View: Daftar Surat Bon & Serah Terima Material
 * PT Cipta Karya Teknologi (CKT Lampung)
 */
$pageTitle = "Bon & Serah Terima Material";
$pageHeaderTitle = "Bon & Serah Terima Material Lapangan";
$pageHeaderSubtitle = "Pencatatan pengeluaran material oleh Admin Gudang kepada teknisi lapangan";

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Filter parameters
$statusFilter = $_GET['status'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$techFilter = (int)($_GET['tech_id'] ?? 0);
$searchFilter = trim($_GET['search'] ?? '');
$detailId = (int)($_GET['id'] ?? 0);

$isTeknisi = (($currentUser['role'] ?? '') === 'teknisi');
if ($isTeknisi) {
    $pageHeaderTitle = "Tugas & Bon Material Saya";
    $pageHeaderSubtitle = "Daftar material yang Anda ambil dari loket gudang. Nyatakan tugas selesai jika pemasangan di lapangan sudah tuntas.";
}

// Build Query
$sql = "
    SELECT b.*, u.name as technician_name, u.nik as technician_nik,
           ua.name as approver_name
    FROM bon_requests b
    JOIN users u ON b.user_id = u.id
    LEFT JOIN users ua ON b.approved_by = ua.id
    WHERE 1=1
";
$params = [];

if ($isTeknisi) {
    $sql .= " AND b.user_id = ?";
    $params[] = $currentUser['id'];
} elseif ($techFilter > 0) {
    $sql .= " AND b.user_id = ?";
    $params[] = $techFilter;
}

if ($statusFilter !== '') {
    $sql .= " AND b.status = ?";
    $params[] = $statusFilter;
}

if ($typeFilter !== '') {
    $sql .= " AND b.request_type = ?";
    $params[] = $typeFilter;
}

if ($searchFilter !== '') {
    $sql .= " AND (b.bon_number LIKE ? OR b.customer_name LIKE ? OR b.customer_id LIKE ? OR u.name LIKE ? OR b.work_order_number LIKE ?)";
    $like = '%' . $searchFilter . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY b.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bons = $stmt->fetchAll();

// Fetch Technicians for Filter
$allTechnicians = $pdo->query("SELECT id, name, nik FROM users WHERE role = 'teknisi' ORDER BY name ASC")->fetchAll();

// Helper to retrieve individual serial numbers for a material item in this bon
function getSerialsForItem($pdo, $bonId, $materialId, $serialNumbersText, $quantity = 1, $matCode = 'ONT') {
    $serials = [];
    $rawSns = [];
    if (!empty($serialNumbersText)) {
        $rawSns = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $serialNumbersText)));
    }

    if (!empty($rawSns)) {
        // Query specific serial numbers assigned to this specific row
        $placeholders = implode(',', array_fill(0, count($rawSns), '?'));
        $stmt = $pdo->prepare("
            SELECT * 
            FROM material_serials 
            WHERE serial_number IN ($placeholders)
            ORDER BY id ASC
        ");
        $stmt->execute(array_values($rawSns));
        $foundSerials = $stmt->fetchAll();

        foreach ($rawSns as $sn) {
            $matched = null;
            foreach ($foundSerials as $fs) {
                if ($fs['serial_number'] === $sn) {
                    $matched = $fs;
                    break;
                }
            }
            if ($matched) {
                $serials[] = $matched;
            } else {
                $serials[] = [
                    'id' => 0,
                    'material_id' => $materialId,
                    'serial_number' => $sn,
                    'status' => 'allocated',
                    'bon_id' => $bonId,
                    'customer_name' => null,
                    'customer_id' => null,
                    'customer_address' => null,
                    'installed_at' => null,
                    'installed_notes' => null
                ];
            }
        }
    } else {
        // No specific serial numbers in text for this row
        // Query material_serials for this bon and material
        $stmt = $pdo->prepare("
            SELECT * 
            FROM material_serials 
            WHERE (bon_id = ? AND material_id = ?)
            ORDER BY id ASC
        ");
        $stmt->execute([$bonId, $materialId]);
        $serials = $stmt->fetchAll();
    }

    // If unit count is less than quantity, generate unit entries so every unit can be installed individually
    $currentCount = count($serials);
    if ($currentCount < $quantity) {
        for ($i = $currentCount + 1; $i <= $quantity; $i++) {
            $unitCode = "UNIT-{$matCode}-BON{$bonId}-" . str_pad($i, 2, '0', STR_PAD_LEFT);
            $stPl = $pdo->prepare("SELECT * FROM material_serials WHERE bon_id = ? AND serial_number = ?");
            $stPl->execute([$bonId, $unitCode]);
            $foundPl = $stPl->fetch();
            if ($foundPl) {
                $serials[] = $foundPl;
            } else {
                $serials[] = [
                    'id' => 0,
                    'material_id' => $materialId,
                    'serial_number' => $unitCode,
                    'status' => 'allocated',
                    'bon_id' => $bonId,
                    'customer_name' => null,
                    'customer_id' => null,
                    'customer_address' => null,
                    'installed_at' => null,
                    'installed_notes' => null,
                    'is_placeholder' => true,
                    'unit_number' => $i
                ];
            }
        }
    }

    return $serials;
}

// If specific Bon ID is requested for detail view, OR if technician is logged in without specific ID, automatically open active bon
$selectedBon = null;
$selectedBonItems = [];

if ($isTeknisi && $detailId <= 0) {
    $stmtAct = $pdo->prepare("SELECT id FROM bon_requests WHERE user_id = ? AND status = 'approved' ORDER BY id DESC LIMIT 1");
    $stmtAct->execute([$currentUser['id']]);
    $actId = $stmtAct->fetchColumn();
    if ($actId) {
        $detailId = (int)$actId;
    }
}

if ($detailId > 0) {
    // Sinkronkan status bon berdasarkan apakah seluruh material telah terpasang/terpakai
    syncBonCompletionStatus($pdo, $detailId);

    $detailSql = "
        SELECT b.*, u.name as technician_name, u.nik as technician_nik, u.department,
               ua.name as approver_name
        FROM bon_requests b
        JOIN users u ON b.user_id = u.id
        LEFT JOIN users ua ON b.approved_by = ua.id
        WHERE b.id = ?
    ";
    $detailParams = [$detailId];
    if ($isTeknisi) {
        $detailSql .= " AND b.user_id = ?";
        $detailParams[] = $currentUser['id'];
    }

    $stmtDetail = $pdo->prepare($detailSql);
    $stmtDetail->execute($detailParams);
    $selectedBon = $stmtDetail->fetch();

    if ($selectedBon) {
        $stmtItems = $pdo->prepare("
            SELECT bi.*, m.name as mat_name, m.code as mat_code, m.unit, m.location_rack, m.cable_length, m.is_serialized
            FROM bon_items bi
            JOIN materials m ON bi.material_id = m.id
            WHERE bi.bon_id = ?
        ");
        $stmtItems->execute([$detailId]);
        $selectedBonItems = $stmtItems->fetchAll();

        // Get all installed/allocated serials for this bon to check cable usage
        $stAllSer = $pdo->prepare("SELECT * FROM material_serials WHERE bon_id = ?");
        $stAllSer->execute([$detailId]);
        $allBonSerials = $stAllSer->fetchAll();

        // Collect all cable items in this specific Bon with remaining quantities
        $bonCables = [];
        foreach ($selectedBonItems as $item) {
            if (!empty($item['cable_length']) || stripos($item['mat_name'], 'Kabel') !== false) {
                $qty = (int)($item['quantity_approved'] ?: $item['quantity_requested']);
                $cableLen = (int)($item['cable_length'] ?: 0);
                
                $usedCount = 0;
                if (!empty($allBonSerials)) {
                    foreach ($allBonSerials as $bs) {
                        if ($bs['status'] === 'installed' && !empty($bs['cable_used'])) {
                            if ($cableLen > 0 && strpos($bs['cable_used'], (string)$cableLen) !== false) {
                                $usedCount++;
                            } elseif ($cableLen === 0 && strpos($bs['cable_used'], $item['mat_name']) !== false) {
                                $usedCount++;
                            }
                        }
                    }
                }
                $remaining = max(0, $qty - $usedCount);

                $bonCables[] = [
                    'material_id' => $item['material_id'],
                    'mat_name' => $item['mat_name'],
                    'cable_length' => $cableLen,
                    'unit' => $item['unit'],
                    'qty_total' => $qty,
                    'qty_used' => $usedCount,
                    'qty_remaining' => $remaining
                ];
            }
        }

        // Process active items for view (if technician, finished ONTs and used cables disappear!)
        $activeBonItems = [];
        $activeOntCount = 0;
        $activeKabelCount = 0;

        foreach ($selectedBonItems as $item) {
            $qty = (int)($item['quantity_approved'] ?: $item['quantity_requested']);
            $isSerialized = ($item['is_serialized'] == 1 || !empty($item['serial_numbers']) || stripos($item['mat_name'], 'ONT') !== false);
            
            if ($isSerialized) {
                $allSerials = getSerialsForItem($pdo, $selectedBon['id'], $item['material_id'], $item['serial_numbers'], $qty, $item['mat_code']);
                $item['all_serials'] = $allSerials;
                
                if ($isTeknisi) {
                    $pendingSerials = array_values(array_filter($allSerials, fn($s) => !in_array($s['status'], ['installed', 'bad', 'change'])));
                    $item['display_serials'] = $pendingSerials;
                    $item['display_qty'] = count($pendingSerials);
                    if (!empty($pendingSerials)) {
                        $activeBonItems[] = $item;
                        $activeOntCount++;
                    }
                } else {
                    $item['display_serials'] = $allSerials;
                    $item['display_qty'] = $qty;
                    $activeBonItems[] = $item;
                    $activeOntCount++;
                }
            } else {
                // Kabel / Non-serial
                $cableLen = (int)($item['cable_length'] ?: 0);
                $usedCount = 0;
                if (!empty($allBonSerials)) {
                    foreach ($allBonSerials as $bs) {
                        if ($bs['status'] === 'installed' && !empty($bs['cable_used'])) {
                            if ($cableLen > 0 && strpos($bs['cable_used'], (string)$cableLen) !== false) {
                                $usedCount++;
                            } elseif ($cableLen === 0 && strpos($bs['cable_used'], $item['mat_name']) !== false) {
                                $usedCount++;
                            }
                        }
                    }
                }
                $remaining = max(0, $qty - $usedCount);
                $item['used_count'] = $usedCount;
                $item['remaining_qty'] = $remaining;
                $item['display_qty'] = $isTeknisi ? $remaining : $qty;

                if ($isTeknisi) {
                    if ($remaining > 0) {
                        $activeBonItems[] = $item;
                        $activeKabelCount++;
                    }
                } else {
                    $activeBonItems[] = $item;
                    $activeKabelCount++;
                }
            }
        }

        $isBonAllFinished = ($isTeknisi && empty($activeBonItems));
    }
}
?>

<?php if ($selectedBon): ?>
<script>
  window.currentBonCables = <?= json_encode($bonCables) ?>;
</script>
<?php endif; ?>

<div class="main-wrapper">
  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <main class="content-body">
    <!-- Header Controls -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
      <div>
        <div style="font-size: 1.35rem; font-weight: 800; color: var(--text-main);">
          <?= $isTeknisi ? 'Tugas Saya' : 'Daftar Surat Bon & Pengeluaran Barang' ?>
        </div>
        <div style="font-size: 0.82rem; color: var(--text-muted);">
          <?= $isTeknisi ? 'Daftar material bon yang Anda bawa. Silakan klik tombol SN untuk melaporkan material yang telah berhasil terpasang di lapangan.' : 'Admin Gudang menginput dan mengelola seluruh riwayat bon teknisi yang datang mengambil barang' ?>
        </div>
      </div>
      <?php if (in_array($currentUser['role'] ?? '', ['admin_gudang', 'admin'])): ?>
      <div style="display: flex; gap: 10px;">
        <button type="button" class="btn-primary" onclick="openModalBon()">
          <i class="bi bi-person-check-fill me-1"></i> Input Bon Teknisi Baru
        </button>
      </div>
      <?php endif; ?>
    </div>

    <!-- If Detail View is active -->
    <?php if ($selectedBon): ?>
      <div class="table-card" style="border: 1px solid var(--primary); margin-bottom: 30px;">
        <div class="table-card-header" style="background: rgba(2, 132, 199, 0.08);">
          <div class="table-card-title">
            <i class="bi bi-box-seam-fill text-primary"></i> <?= $isTeknisi ? 'Daftar Material Tugas Saya' : 'Detail Surat Bon' ?>: <span style="font-family: var(--font-mono); color: var(--primary);"><?= htmlspecialchars($selectedBon['bon_number']) ?></span>
          </div>
          <div style="display: flex; gap: 8px; align-items: center;">
            <?php 
              $hasSerializedItems = false;
              foreach ($selectedBonItems as $it) {
                  if ($it['is_serialized'] == 1 || !empty($it['serial_numbers'])) {
                      $hasSerializedItems = true;
                      break;
                  }
              }
            ?>
            <?php if (!$hasSerializedItems && $selectedBon['status'] === 'approved' && ($currentUser['role'] === 'admin_gudang' || $currentUser['role'] === 'admin' || $currentUser['id'] == $selectedBon['user_id'])): ?>
              <button 
                type="button" 
                class="btn-primary btn-complete-task" 
                style="padding: 6px 14px; font-size: 0.82rem; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; font-weight: 700; border-radius: 6px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);" 
                data-id="<?= $selectedBon['id'] ?>"
                data-number="<?= htmlspecialchars($selectedBon['bon_number'], ENT_QUOTES) ?>"
                data-name="<?= htmlspecialchars($selectedBon['customer_name'] ?? '', ENT_QUOTES) ?>"
                data-address="<?= htmlspecialchars($selectedBon['customer_address'] ?? '', ENT_QUOTES) ?>"
                data-zone="<?= htmlspecialchars($selectedBon['area_zone'] ?? '', ENT_QUOTES) ?>"
                data-customer-id="<?= htmlspecialchars($selectedBon['customer_id'] ?? '', ENT_QUOTES) ?>"
                onclick="window.openCompleteTaskModal(<?= $selectedBon['id'] ?>, '<?= htmlspecialchars($selectedBon['bon_number'], ENT_QUOTES) ?>', '<?= htmlspecialchars($selectedBon['customer_name'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($selectedBon['customer_address'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($selectedBon['area_zone'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($selectedBon['customer_id'] ?? '', ENT_QUOTES) ?>')"
              >
                <i class="bi bi-patch-check-fill me-1"></i> Nyatakan Tugas Selesai
              </button>
            <?php endif; ?>
            <?php if (!$isTeknisi): ?>
              <a href="bon.php" class="btn-secondary" style="padding: 6px 14px; font-size: 0.82rem;">
                <i class="bi bi-x-lg me-1"></i> Tutup Rincian
              </a>
            <?php endif; ?>
          </div>
        </div>

        <div style="padding: 24px;">
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; background: var(--neu-base); padding: 18px 22px; border-radius: var(--radius-md); box-shadow: var(--neu-inset-sm); border: 1px solid rgba(255, 255, 255, 0.8);">
            <div>
              <div style="font-size: 0.72rem; color: var(--text-dim); text-transform: uppercase;">Teknisi Penerima</div>
              <div style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($selectedBon['technician_name']) ?></div>
              <div style="font-size: 0.75rem; color: var(--text-muted); font-family: var(--font-mono);"><?= htmlspecialchars($selectedBon['technician_nik']) ?></div>
            </div>
            <div>
              <div style="font-size: 0.72rem; color: var(--text-dim); text-transform: uppercase;">Petugas Admin Gudang</div>
              <div style="font-weight: 700; color: var(--success);"><?= htmlspecialchars($selectedBon['approver_name'] ?: 'Admin Gudang') ?></div>
              <div style="font-size: 0.72rem; color: var(--text-dim); margin-top: 3px;"><?= formatTanggalIndo($selectedBon['created_at']) ?></div>
            </div>
          </div>

          <!-- Items Table in this Bon -->
          <?php if ($isBonAllFinished): ?>
            <div style="background: #ffffff; border: 1px solid rgba(16, 185, 129, 0.3); border-radius: var(--radius-lg); padding: 48px 24px; text-align: center; box-shadow: var(--shadow-sm); margin: 10px 0 20px;">
              <div style="width: 72px; height: 72px; border-radius: 50%; background: rgba(16, 185, 129, 0.12); color: #10b981; display: inline-flex; align-items: center; justify-content: center; font-size: 2.4rem; margin-bottom: 18px;">
                <i class="bi bi-check2-circle"></i>
              </div>
              <h2 style="font-weight: 800; font-size: 1.35rem; color: var(--text-main); margin-bottom: 8px;">Semua Tugas Selesai!</h2>
              <p style="color: var(--text-muted); font-size: 0.92rem; max-width: 540px; margin: 0 auto 24px; line-height: 1.6;">
                Seluruh unit ONT dan kabel pada surat bon <strong style="color: var(--primary); font-family: var(--font-mono);"><?= htmlspecialchars($selectedBon['bon_number']) ?></strong> telah berhasil dilaporkan terpasang. Seluruh data pekerjaan Anda sudah langsung tercatat rapi di menu <strong>Riwayat Pemasangan</strong>.
              </p>
              <a href="riwayat.php" class="btn-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px; padding: 12px 26px; font-weight: 700; border-radius: 8px; font-size: 0.95rem;">
                <i class="bi bi-clock-history"></i> Buka Riwayat Pemasangan Saya &rarr;
              </a>
            </div>
          <?php else: ?>

          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
            <!-- 2 Pilihan Kategori Material (Tab Filter) -->
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
              <button 
                type="button" 
                class="btn-mat-tab active" 
                id="tabBtn_all" 
                onclick="switchMaterialCategory('all')"
                style="padding: 7px 16px; border-radius: var(--radius-full); font-size: 0.82rem; font-weight: 700; border: 1px solid var(--primary); background: var(--primary); color: #ffffff; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 6px;"
              >
                <i class="bi bi-grid-fill"></i> Semua Material (<?= count($activeBonItems) ?>)
              </button>
              <button 
                type="button" 
                class="btn-mat-tab" 
                id="tabBtn_ont" 
                onclick="switchMaterialCategory('ont')"
                style="padding: 7px 16px; border-radius: var(--radius-full); font-size: 0.82rem; font-weight: 700; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 6px;"
              >
                <i class="bi bi-router text-primary"></i> ONT & Modem Wi-Fi (<?= $activeOntCount ?>)
              </button>
              <button 
                type="button" 
                class="btn-mat-tab" 
                id="tabBtn_kabel" 
                onclick="switchMaterialCategory('kabel')"
                style="padding: 7px 16px; border-radius: var(--radius-full); font-size: 0.82rem; font-weight: 700; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 6px;"
              >
                <i class="bi bi-bezier2 text-primary"></i> Kabel Drop Core (<?= $activeKabelCount ?>)
              </button>
            </div>

            <!-- Scanner Camera Action -->
            <button 
              type="button" 
              class="btn-primary" 
              style="padding: 7px 16px; border-radius: var(--radius-full); font-size: 0.82rem; font-weight: 700; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); border: none; box-shadow: 0 2px 8px rgba(2, 132, 199, 0.35); cursor: pointer; display: inline-flex; align-items: center; gap: 6px;"
              onclick="openBarcodeScanner((scannedSN) => {
                const allButtons = document.querySelectorAll('button[onclick*=\'openInstallSNModal\']');
                let found = false;
                allButtons.forEach(btn => {
                  if (btn.getAttribute('onclick').includes(scannedSN)) {
                    found = true;
                    btn.click();
                  }
                });
                if (!found) {
                  Swal.fire({
                    icon: 'warning',
                    title: 'SN Tidak Ditemukan di Bon',
                    text: 'Serial number \"' + scannedSN + '\" tidak terdapat pada daftar tugas bon Anda saat ini.',
                    confirmButtonColor: '#0F4068'
                  });
                }
              })"
              title="Arahkan kamera ke stiker barcode ONT modem"
            >
              <i class="bi bi-camera-fill"></i> Scan Barcode ONT
            </button>
          </div>

          <!-- Desktop Table View (Screens > 768px) -->
          <div class="bon-items-desktop-view table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%;">
            <table class="custom-table" style="margin-bottom: 18px; border: 1px solid var(--border-color); border-radius: var(--radius-md); width: 100%;">
              <thead>
                <tr>
                  <th>Kode</th>
                  <th>Nama Material / Perangkat</th>
                  <th>Panjang / Spek</th>
                  <th style="text-align: center;">Qty</th>
                  <th style="min-width: 320px;">Serial Number ONT & Status Pemasangan</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($activeBonItems as $item): 
                  $qty = (int)($item['display_qty'] ?? ($item['quantity_approved'] ?: $item['quantity_requested']));
                  $isSerialized = ($item['is_serialized'] == 1 || !empty($item['serial_numbers']) || stripos($item['mat_name'], 'ONT') !== false);
                  $itemCat = $isSerialized ? 'ont' : 'kabel';
                  $itemSerials = $item['display_serials'] ?? [];
                ?>
                  <tr class="bon-item-row-entry" data-item-category="<?= $itemCat ?>">
                    <td style="font-family: var(--font-mono); color: var(--text-dim);"><?= htmlspecialchars($item['mat_code']) ?></td>
                    <td style="font-weight: 700; color: var(--text-main);">
                      <?= htmlspecialchars($item['mat_name']) ?>
                      <?php if ($isSerialized && !empty($item['all_serials'])): 
                        $allS = $item['all_serials'];
                        $reportedCount = count(array_filter($allS, fn($s) => in_array($s['status'], ['installed', 'bad', 'change'])));
                        $installedCount = count(array_filter($allS, fn($s) => $s['status'] === 'installed'));
                        $badCount = count(array_filter($allS, fn($s) => $s['status'] === 'bad'));
                        $changeCount = count(array_filter($allS, fn($s) => $s['status'] === 'change'));
                      ?>
                        <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 600; margin-top: 3px;">
                          Progress: <strong style="color: <?= $reportedCount === count($allS) ? '#10b981' : '#d97706' ?>;"><?= $reportedCount ?>/<?= count($allS) ?> Unit Selesai</strong>
                          <span style="font-size: 0.7rem; color: var(--text-dim);">(<?= $installedCount ?> Terpasang<?= $badCount ? ", $badCount Bad" : "" ?><?= $changeCount ? ", $changeCount Change" : "" ?>)</span>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?= $item['cable_length'] ? '<span class="badge" style="background: rgba(2, 132, 199, 0.1); color: var(--primary); font-weight:700;">' . $item['cable_length'] . ' Meter</span>' : '-' ?>
                    </td>
                    <td style="text-align: center; font-weight: 800; font-family: var(--font-mono); color: var(--success);">
                      <?= $qty ?> <?= $item['unit'] ?>
                      <?php if ($isTeknisi): ?>
                        <span style="font-size: 0.7rem; color: var(--text-dim); display: block; font-weight: 600;">Sisa di Tas</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($isSerialized && !empty($itemSerials)): ?>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                          <?php foreach ($itemSerials as $sn): ?>
                            <?php if ($sn['status'] === 'installed'): ?>
                              <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 6px; padding: 8px 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 6px;">
                                  <span class="badge font-mono" style="background: #10b981; color: #fff; font-weight: 700; font-size: 0.75rem;">
                                    <i class="bi bi-patch-check-fill me-1"></i> SN: <?= htmlspecialchars($sn['serial_number']) ?>
                                  </span>
                                  <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #047857; font-size: 0.68rem; font-weight: 700;">
                                    <i class="bi bi-check-all"></i> Terpasang
                                  </span>
                                </div>
                                <div style="font-size: 0.76rem; color: var(--text-main); margin-top: 5px;">
                                  <i class="bi bi-person-check-fill text-success me-1"></i> <strong><?= htmlspecialchars($sn['customer_name'] ?: 'Pelanggan') ?></strong>
                                  <?php if (!empty($sn['customer_id'])): ?>
                                    <span style="color: var(--text-dim);">(<?= htmlspecialchars($sn['customer_id']) ?>)</span>
                                  <?php endif; ?>
                                </div>
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 1px;">
                                  <i class="bi bi-geo-alt-fill text-dim me-1"></i> <?= htmlspecialchars($sn['customer_address'] ?: '-') ?>
                                </div>
                                <?php if (!empty($sn['cable_used'])): ?>
                                  <div style="margin-top: 5px;">
                                    <span class="badge font-mono" style="background: rgba(2, 132, 199, 0.12); color: #0284c7; font-size: 0.72rem; font-weight: 700; border: 1px solid rgba(2, 132, 199, 0.25);">
                                      <i class="bi bi-bezier2 me-1"></i> <?= htmlspecialchars($sn['cable_used']) ?>
                                    </span>
                                  </div>
                                <?php endif; ?>
                                <?php if (!empty($sn['installed_notes'])): ?>
                                  <div style="font-size: 0.7rem; color: var(--text-dim); margin-top: 2px; font-style: italic;">
                                    <i class="bi bi-chat-text me-1"></i> <?= htmlspecialchars($sn['installed_notes']) ?>
                                  </div>
                                <?php endif; ?>
                              </div>
                            <?php elseif ($sn['status'] === 'bad'): ?>
                              <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 6px; padding: 8px 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 6px;">
                                  <span class="badge font-mono" style="background: #ef4444; color: #fff; font-weight: 700; font-size: 0.75rem;">
                                    <i class="bi bi-x-octagon-fill me-1"></i> SN: <?= htmlspecialchars($sn['serial_number']) ?>
                                  </span>
                                  <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #dc2626; font-size: 0.68rem; font-weight: 700;">
                                    <i class="bi bi-x-circle-fill"></i> Bad
                                  </span>
                                </div>
                                <?php if (!empty($sn['installed_notes'])): ?>
                                  <div style="font-size: 0.76rem; color: #dc2626; margin-top: 5px; font-weight: 600;">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Kendala: <?= htmlspecialchars($sn['installed_notes']) ?>
                                  </div>
                                <?php endif; ?>
                                <?php if (!empty($sn['customer_name']) && $sn['customer_name'] !== 'Unit Bad Lapangan'): ?>
                                  <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">
                                    Lokasi/Pelanggan: <?= htmlspecialchars($sn['customer_name']) ?>
                                  </div>
                                <?php endif; ?>
                              </div>
                            <?php elseif ($sn['status'] === 'change'): ?>
                              <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 6px; padding: 8px 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 6px;">
                                  <span class="badge font-mono" style="background: #f59e0b; color: #fff; font-weight: 700; font-size: 0.75rem;">
                                    <i class="bi bi-arrow-repeat me-1"></i> SN: <?= htmlspecialchars($sn['serial_number']) ?>
                                  </span>
                                  <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #d97706; font-size: 0.68rem; font-weight: 700;">
                                    <i class="bi bi-arrow-repeat"></i> Change
                                  </span>
                                </div>
                                <div style="font-size: 0.76rem; color: var(--text-main); margin-top: 5px;">
                                  <i class="bi bi-person-check-fill text-warning me-1"></i> <strong><?= htmlspecialchars($sn['customer_name'] ?: 'Pelanggan') ?></strong>
                                </div>
                                <?php if (!empty($sn['installed_notes'])): ?>
                                  <div style="font-size: 0.74rem; color: #d97706; margin-top: 2px; font-weight: 600;">
                                    <i class="bi bi-info-circle-fill me-1"></i> Alasan: <?= htmlspecialchars($sn['installed_notes']) ?>
                                  </div>
                                <?php endif; ?>
                              </div>
                            <?php else: ?>
                              <div style="background: rgba(2, 132, 199, 0.05); border: 1px dashed rgba(2, 132, 199, 0.35); border-radius: 6px; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <div style="cursor: pointer;" onclick="openInstallSNModal(<?= $selectedBon['id'] ?>, '<?= htmlspecialchars($selectedBon['bon_number'], ENT_QUOTES) ?>', '<?= htmlspecialchars($sn['serial_number'], ENT_QUOTES) ?>', '<?= htmlspecialchars($item['mat_name'], ENT_QUOTES) ?>', <?= (int)$item['material_id'] ?>)">
                                  <span class="badge font-mono" style="background: rgba(2, 132, 199, 0.15); color: var(--primary); font-size: 0.78rem; font-weight: 700; border: 1px solid rgba(2, 132, 199, 0.25);">
                                    <i class="bi bi-router me-1"></i> SN: <?= htmlspecialchars($sn['serial_number']) ?>
                                  </span>
                                  <div style="font-size: 0.7rem; color: #d97706; margin-top: 3px; font-weight: 600;">
                                    <i class="bi bi-hourglass-split"></i> Belum Dilaporkan (Di Tas)
                                  </div>
                                </div>
                                <button 
                                  type="button" 
                                  class="btn-primary" 
                                  style="padding: 6px 14px; font-size: 0.78rem; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); border: none; font-weight: 700; border-radius: 4px; box-shadow: 0 2px 6px rgba(2, 132, 199, 0.3); cursor: pointer;"
                                  onclick="openInstallSNModal(<?= $selectedBon['id'] ?>, '<?= htmlspecialchars($selectedBon['bon_number'], ENT_QUOTES) ?>', '<?= htmlspecialchars($sn['serial_number'], ENT_QUOTES) ?>', '<?= htmlspecialchars($item['mat_name'], ENT_QUOTES) ?>', <?= (int)$item['material_id'] ?>)"
                                >
                                  <i class="bi bi-pencil-square me-1"></i> Lapor Status ONT
                                </button>
                              </div>
                            <?php endif; ?>
                          <?php endforeach; ?>
                        </div>
                      <?php elseif ($item['cable_length']): ?>
                        <div style="font-size: 0.8rem; color: #047857; background: rgba(16, 185, 129, 0.08); padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(16, 185, 129, 0.25); font-weight: 600;">
                          <i class="bi bi-bezier2 me-1 text-primary"></i> Sisa Roll Kabel Tersedia di Tas (Siap Dipasang)
                        </div>
                      <?php elseif ($item['serial_numbers']): ?>
                        <span class="badge font-mono" style="background: rgba(2, 132, 199, 0.1); color: var(--primary); border: 1px solid rgba(2, 132, 199, 0.25); padding: 4px 8px;">
                          SN: <?= htmlspecialchars($item['serial_numbers']) ?>
                        </span>
                      <?php else: ?>
                        <span style="color: var(--text-dim); font-size: 0.75rem;">- (Material Non-SN)</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Mobile Card-Based View (Screens <= 768px) -->
          <div class="bon-items-mobile-view">
            <?php foreach ($activeBonItems as $item): 
              $qty = (int)($item['display_qty'] ?? ($item['quantity_approved'] ?: $item['quantity_requested']));
              $isSerialized = ($item['is_serialized'] == 1 || !empty($item['serial_numbers']) || stripos($item['mat_name'], 'ONT') !== false);
              $itemCat = $isSerialized ? 'ont' : 'kabel';
              $itemSerials = $item['display_serials'] ?? [];
            ?>
              <div class="bon-item-row-entry" data-item-category="<?= $itemCat ?>" style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 16px; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 8px;">
                  <div>
                    <span style="font-family: var(--font-mono); font-size: 0.72rem; color: var(--text-dim); font-weight: 600;"><?= htmlspecialchars($item['mat_code']) ?></span>
                    <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-main); line-height: 1.35; margin-top: 2px;">
                      <?= htmlspecialchars($item['mat_name']) ?>
                    </div>
                  </div>
                  <span class="badge font-mono" style="background: rgba(2, 132, 199, 0.12); color: var(--primary); font-size: 0.82rem; font-weight: 800; padding: 4px 10px; border-radius: 6px; white-space: nowrap;">
                    <?= $qty ?> <?= $item['unit'] ?>
                  </span>
                </div>

                <?php if ($item['cable_length']): ?>
                  <div style="font-size: 0.76rem; color: var(--text-muted); margin-bottom: 10px;">
                    <span class="badge" style="background: rgba(2, 132, 199, 0.1); color: var(--primary); font-weight:700;"><i class="bi bi-bezier2 me-1"></i> <?= $item['cable_length'] ?> Meter</span>
                  </div>
                <?php endif; ?>

                <!-- Unit SN Section on Mobile -->
                <?php if ($isSerialized && !empty($itemSerials)): ?>
                  <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php foreach ($itemSerials as $sn): ?>
                      <?php if ($sn['status'] === 'installed'): ?>
                        <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; padding: 12px;">
                          <div style="display: flex; justify-content: space-between; align-items: center; gap: 6px; margin-bottom: 6px;">
                            <span class="badge font-mono" style="background: #10b981; color: #fff; font-weight: 700; font-size: 0.78rem; padding: 4px 8px;">
                              <i class="bi bi-patch-check-fill me-1"></i> SN: <?= htmlspecialchars($sn['serial_number']) ?>
                            </span>
                            <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #047857; font-size: 0.7rem; font-weight: 700;">
                              <i class="bi bi-check-all"></i> Terpasang
                            </span>
                          </div>
                          <div style="font-size: 0.84rem; color: var(--text-main); font-weight: 700; margin-top: 4px;">
                            <i class="bi bi-person-check-fill text-success me-1"></i> <?= htmlspecialchars($sn['customer_name'] ?: 'Pelanggan') ?>
                          </div>
                        </div>
                      <?php elseif ($sn['status'] === 'bad'): ?>
                        <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 12px;">
                          <div style="display: flex; justify-content: space-between; align-items: center; gap: 6px; margin-bottom: 6px;">
                            <span class="badge font-mono" style="background: #ef4444; color: #fff; font-weight: 700; font-size: 0.78rem; padding: 4px 8px;">
                              <i class="bi bi-x-octagon-fill me-1"></i> SN: <?= htmlspecialchars($sn['serial_number']) ?>
                            </span>
                            <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #dc2626; font-size: 0.7rem; font-weight: 700;">
                              <i class="bi bi-x-circle-fill"></i> Bad
                            </span>
                          </div>
                        </div>
                      <?php else: ?>
                        <div style="background: rgba(2, 132, 199, 0.05); border: 1px dashed rgba(2, 132, 199, 0.35); border-radius: 8px; padding: 12px;">
                          <div style="display: flex; justify-content: space-between; align-items: center; gap: 6px; margin-bottom: 10px;">
                            <span class="badge font-mono" style="background: rgba(2, 132, 199, 0.15); color: var(--primary); font-size: 0.82rem; font-weight: 800; border: 1px solid rgba(2, 132, 199, 0.25); padding: 4px 8px;">
                              <i class="bi bi-router me-1"></i> SN: <?= htmlspecialchars($sn['serial_number']) ?>
                            </span>
                            <span class="badge" style="background: rgba(217, 119, 6, 0.15); color: #d97706; font-size: 0.72rem; font-weight: 700;">
                              <i class="bi bi-hourglass-split"></i> Di Tas
                            </span>
                          </div>
                          <button 
                            type="button" 
                            class="btn-primary" 
                            style="width: 100%; min-height: 46px; font-size: 0.88rem; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); border: none; font-weight: 800; border-radius: 8px; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35); display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer;"
                            onclick="openInstallSNModal(<?= $selectedBon['id'] ?>, '<?= htmlspecialchars($selectedBon['bon_number'], ENT_QUOTES) ?>', '<?= htmlspecialchars($sn['serial_number'], ENT_QUOTES) ?>', '<?= htmlspecialchars($item['mat_name'], ENT_QUOTES) ?>', <?= (int)$item['material_id'] ?>)"
                          >
                            <i class="bi bi-pencil-square" style="font-size: 1.1rem;"></i> Lapor Status ONT (Pasang / Bad / Change)
                          </button>
                        </div>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                <?php elseif ($item['cable_length']): ?>
                  <div style="font-size: 0.78rem; color: #047857; background: rgba(16, 185, 129, 0.08); padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(16, 185, 129, 0.25); font-weight: 600;">
                    <i class="bi bi-bezier2 me-1 text-primary"></i> Sisa Roll Kabel di Tas (Siap Dipasang)
                  </div>
                <?php else: ?>
                  <div style="font-size: 0.76rem; color: var(--text-dim);">
                    - (Material Non-SN)
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <?php endif; ?>

          <?php if (!empty($selectedBon['admin_notes'])): ?>
            <div style="background-color: var(--bg-input); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
              <div style="font-size: 0.72rem; font-weight: 700; color: var(--primary); text-transform: uppercase;">Catatan Admin Gudang:</div>
              <div style="font-size: 0.82rem; color: var(--text-main); margin-top: 4px;"><?= nl2br(htmlspecialchars($selectedBon['admin_notes'])) ?></div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <?php if ($isTeknisi): ?>
        <!-- Empty State for Teknisi with no active Bon -->
        <div class="table-card" style="text-align: center; padding: 60px 24px;">
          <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 16px;">
            <i class="bi bi-check2-all"></i>
          </div>
          <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-main);">Tidak Ada Material Aktif yang Sedang Dibawa</div>
          <div style="font-size: 0.85rem; color: var(--text-muted); max-width: 480px; margin: 8px auto 24px; line-height: 1.5;">
            Semua material sebelumnya telah dilaporkan selesai, atau Anda belum mengambil material baru dari loket gudang.
          </div>
          <div style="display: flex; justify-content: center; gap: 12px;">
            <a href="riwayat.php" class="btn-secondary" style="padding: 10px 20px; font-size: 0.85rem;">
              <i class="bi bi-clock-history me-1 text-primary"></i> Buka Riwayat Pemasangan
            </a>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Table of Bon Requests (Admin Only) -->
    <?php if (!$isTeknisi): ?>
      <!-- Filter Bar -->
      <div style="background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" action="bon.php" style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
          
          <div style="flex: 1; min-width: 220px;">
            <input type="text" name="search" class="form-control" placeholder="Cari No Bon, Pelanggan, Teknisi..." value="<?= htmlspecialchars($searchFilter) ?>">
          </div>

          <div style="width: 200px;">
            <select name="tech_id" class="form-select" onchange="this.form.submit()">
              <option value="">-- Semua Teknisi --</option>
              <?php foreach ($allTechnicians as $at): ?>
                <option value="<?= $at['id'] ?>" <?= $techFilter == $at['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($at['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div style="width: 180px;">
            <select name="status" class="form-select" onchange="this.form.submit()">
              <option value="">-- Semua Status --</option>
              <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Proses</option>
              <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Selesai</option>
              <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            </select>
          </div>

          <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 0.85rem;">
            <i class="bi bi-funnel-fill me-1"></i> Filter
          </button>
        </form>
      </div>

      <div class="table-card">
        <div class="table-card-header">
          <div class="table-card-title">
            <i class="bi bi-journal-text text-primary"></i> Data Riwayat Surat Bon Material
          </div>
          <span style="font-size: 0.8rem; color: var(--text-dim);">Menampilkan <?= count($bons) ?> data</span>
        </div>

        <div class="table-responsive mobile-cards-view">
          <table class="custom-table">
            <thead>
              <tr>
                <th>No. Surat Bon</th>
                <th>Teknisi Pengambil</th>
                <th>Material Diserahkan</th>
                <th>Status</th>
                <th>Tanggal</th>
                <th style="text-align: right;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($bons)): ?>
                <tr>
                  <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <i class="bi bi-inbox" style="font-size: 2rem; display: block; opacity: 0.5; margin-bottom: 8px;"></i>
                    Tidak ada data bon material yang sesuai kriteria pencarian.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($bons as $b): 
                  $stItems = $pdo->prepare("SELECT m.name, bi.quantity_approved, bi.quantity_requested, m.unit FROM bon_items bi JOIN materials m ON bi.material_id = m.id WHERE bi.bon_id = ?");
                  $stItems->execute([$b['id']]);
                  $bItems = $stItems->fetchAll();
                  $bSummary = [];
                  foreach ($bItems as $it) {
                      $qty = $it['quantity_approved'] > 0 ? $it['quantity_approved'] : $it['quantity_requested'];
                      $bSummary[] = $it['name'] . ' (' . $qty . ' ' . $it['unit'] . ')';
                  }
                  $bSummaryText = implode(', ', $bSummary);
                ?>
                  <tr>
                    <td data-label="No. Surat Bon">
                      <a href="bon.php?id=<?= $b['id'] ?>" style="font-family: var(--font-mono); font-weight: 700; color: var(--primary);">
                        <?= htmlspecialchars($b['bon_number']) ?>
                      </a>
                    </td>
                    <td data-label="Teknisi">
                      <div style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($b['technician_name']) ?></div>
                      <small style="color: var(--text-dim);"><?= htmlspecialchars($b['technician_nik']) ?></small>
                    </td>
                    <td data-label="Material Diserahkan">
                      <div style="max-width: 320px; font-size: 0.82rem; color: var(--text-muted); line-height: 1.4;" title="<?= htmlspecialchars($bSummaryText) ?>">
                        <?= htmlspecialchars($bSummaryText ?: 'Tidak ada barang') ?>
                      </div>
                    </td>
                    <td data-label="Status">
                      <?php if ($b['status'] === 'approved'): ?>
                        <span class="status-pill status-approved"><i class="bi bi-hourglass-split"></i> Proses</span>
                      <?php elseif ($b['status'] === 'completed'): ?>
                        <span class="status-pill status-completed"><i class="bi bi-patch-check-fill"></i> Selesai</span>
                      <?php elseif ($b['status'] === 'pending'): ?>
                        <span class="status-pill status-pending"><i class="bi bi-hourglass-split"></i> Pending</span>
                      <?php else: ?>
                        <span class="status-pill status-rejected"><i class="bi bi-x-circle"></i> Dibatalkan</span>
                      <?php endif; ?>
                    </td>
                    <td data-label="Waktu" style="color: var(--text-dim); font-size: 0.78rem;">
                      <?= formatTanggalSingkat($b['created_at']) ?>
                    </td>
                    <td data-label="Aksi" style="text-align: right;">
                      <div class="btn-action-group" style="justify-content: flex-end; align-items: center; gap: 6px; width: 100%;">
                        <a href="bon.php?id=<?= $b['id'] ?>" class="btn-icon-action" title="Lihat Rincian & Pasang Per-SN">
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
    <?php endif; ?>

  </main>
</div>

<script>
function switchMaterialCategory(cat) {
  // Update button active styles
  document.querySelectorAll('.btn-mat-tab').forEach(b => {
    b.classList.remove('active');
    b.style.background = 'var(--bg-card)';
    b.style.color = 'var(--text-main)';
    b.style.borderColor = 'var(--border-color)';
    b.style.boxShadow = 'none';
  });

  const activeBtn = document.getElementById('tabBtn_' + cat);
  if (activeBtn) {
    activeBtn.classList.add('active');
    activeBtn.style.background = 'var(--primary)';
    activeBtn.style.color = '#ffffff';
    activeBtn.style.borderColor = 'var(--primary)';
    activeBtn.style.boxShadow = '0 2px 8px rgba(2, 132, 199, 0.35)';
  }

  // Filter rows
  const rows = document.querySelectorAll('.bon-item-row-entry');
  let visibleCount = 0;

  rows.forEach(r => {
    const itemCat = r.getAttribute('data-item-category');
    if (cat === 'all' || itemCat === cat) {
      r.style.display = '';
      visibleCount++;
    } else {
      r.style.display = 'none';
    }
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
