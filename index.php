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

// Technicians Count
$totalTeknisi = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teknisi' AND status = 'active'")->fetchColumn();

// Low Stock Alert Materials
$lowStockItems = $pdo->query("
    SELECT m.*, c.name as category_name 
    FROM materials m 
    LEFT JOIN categories c ON m.category_id = c.id 
    WHERE m.stock_current <= m.stock_min 
    ORDER BY m.stock_current ASC
")->fetchAll();

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
          <?= $isTeknisi ? 'Lihat status material yang Anda bawa dan konfirmasi penyelesaian pemasangan di lokasi pelanggan.' : 'Monitoring stok real-time, ketersediaan kabel drop core, ONT, dan manajemen logistik lapangan.' ?>
        </div>
      </div>

      <?php if ($isAdmin): ?>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
          <button type="button" class="btn-primary" onclick="openModalBon()">
            <i class="bi bi-person-check-fill me-1"></i> Input Bon Teknisi
          </button>
          <button type="button" class="btn-secondary" onclick="openRestockModal()">
            <i class="bi bi-plus-circle me-1 text-success"></i> Restock Barang
          </button>
          <a href="pengguna.php" class="btn-secondary" style="text-decoration: none; display: inline-flex; align-items: center;">
            <i class="bi bi-people-fill me-1 text-primary"></i> Kelola Pengguna
          </a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Low Stock Alert Banner (If Any) -->
    <?php if (!empty($lowStockItems)): ?>
      <div style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.08) 0%, rgba(245, 158, 11, 0.08) 100%); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: var(--radius-lg); padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; backdrop-filter: blur(10px);">
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
        <a href="stok.php?tab=kabel" class="btn-secondary" style="font-size: 0.78rem; padding: 6px 14px; border-color: rgba(239, 68, 68, 0.3); color: var(--danger);">
          <i class="bi bi-arrow-right-circle me-1"></i> Periksa Stok
        </a>
      </div>
    <?php endif; ?>

    <!-- Stat KPI Cards Grid (3 Columns) -->
    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
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
        <div class="stat-value" style="color: var(--primary);">
          <?= $totalCablesRoll ?> <span style="font-size: 1rem; font-weight: 600; color: var(--text-muted);">Roll</span>
        </div>
        <div class="stat-desc">
          150M, 100M, 75M, 50M siap digunakan
        </div>
      </div>

      <!-- Technician Team Card -->
      <div class="stat-card">
        <div class="stat-card-top">
          <div class="stat-icon success">
            <i class="bi bi-people-fill"></i>
          </div>
          <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #10b981; font-weight: 700;">Tim Lapangan</span>
        </div>
        <div class="stat-label">Teknisi Lapangan</div>
        <div class="stat-value" style="color: #10b981;">
          <?= $totalTeknisi ?> <span style="font-size: 1rem; font-weight: 600; color: var(--text-muted);">Personel</span>
        </div>
        <div class="stat-desc">
          <span><strong><?= $bonActiveCount ?></strong> Surat Bon aktif di lapangan</span>
        </div>
      </div>
    </div>

    <!-- Cable 4 Varian Progress Card -->
    <div class="table-card" style="margin-bottom: 28px;">
      <div class="table-card-header">
        <div class="table-card-title">
          <i class="bi bi-bezier2 text-primary"></i> Ketersediaan Roll Kabel Drop Core
        </div>
        <a href="stok.php?tab=kabel" style="font-size: 0.8rem; font-weight: 700; color: var(--primary);">Lihat Detail Stok &rarr;</a>
      </div>
      <div style="padding: 22px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px;">
          <?php foreach ($cableStats as $c): 
            $percent = min(100, round(($c['stock_current'] / 150) * 100));
            $isLow = $c['stock_current'] <= $c['stock_min'];
          ?>
            <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); transition: transform 0.2s ease, box-shadow 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(15,64,104,0.08)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 1px 3px rgba(15,23,42,0.04)';">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <div style="font-weight: 700; font-size: 0.88rem; color: var(--text-main);">
                  Kabel Drop Core <?= $c['cable_length'] ?> Meter
                  <?php if ($isLow): ?>
                    <span class="badge" style="background: var(--danger-bg); color: var(--danger); font-size: 0.68rem; margin-left: 6px;">Stok Kritis</span>
                  <?php endif; ?>
                </div>
                <div style="font-family: var(--font-mono); font-weight: 800; font-size: 0.95rem; color: <?= $isLow ? 'var(--danger)' : 'var(--primary)' ?>;">
                  <?= $c['stock_current'] ?> Roll
                </div>
              </div>
              <div style="background: #cbd5e1; height: 8px; border-radius: var(--radius-full); overflow: hidden; margin-bottom: 8px;">
                <div style="background: <?= $isLow ? '#ef4444' : 'linear-gradient(90deg, #0F4068, #295A82)' ?>; height: 100%; width: <?= $percent ?>%; border-radius: var(--radius-full); transition: width 0.4s ease; box-shadow: 0 0 6px rgba(15, 64, 104, 0.3);"></div>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.74rem; color: var(--text-dim);">
                <span>Batas Min: <strong><?= $c['stock_min'] ?> Roll</strong></span>
                <span style="color: <?= $isLow ? 'var(--danger)' : 'var(--success)' ?>; font-weight: 700;">
                  <?= $isLow ? '⚠️ Perlu Restock' : '✅ Aman' ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Quick Access Navigation Hub -->
    <div class="table-card">
      <div class="table-card-header">
        <div class="table-card-title">
          <i class="bi bi-grid-fill text-primary"></i> Modul Operasional & Akses Cepat
        </div>
        <span style="font-size: 0.8rem; color: var(--text-dim);">Pintasan menu utama gudang</span>
      </div>
      <div style="padding: 22px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 18px;">
          <!-- Link 1: Stok ONT -->
          <a href="stok.php?tab=ont" style="display: flex; align-items: center; gap: 14px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px; text-decoration: none; transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.borderColor='var(--primary)'; this.style.boxShadow='0 6px 16px rgba(15,64,104,0.08)';" onmouseout="this.style.transform='none'; this.style.borderColor='var(--border-color)'; this.style.boxShadow='none';">
            <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(2, 132, 199, 0.1); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0;">
              <i class="bi bi-router"></i>
            </div>
            <div>
              <div style="font-weight: 800; font-size: 0.9rem; color: var(--text-main);">Stok ONT & Serial</div>
              <div style="font-size: 0.74rem; color: var(--text-muted); margin-top: 2px;">Cek SN ZTE/Huawei & status unit</div>
            </div>
          </a>

          <!-- Link 2: Surat Bon -->
          <a href="bon.php" style="display: flex; align-items: center; gap: 14px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px; text-decoration: none; transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.borderColor='var(--primary)'; this.style.boxShadow='0 6px 16px rgba(15,64,104,0.08)';" onmouseout="this.style.transform='none'; this.style.borderColor='var(--border-color)'; this.style.boxShadow='none';">
            <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(245, 158, 11, 0.12); color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0;">
              <i class="bi bi-receipt"></i>
            </div>
            <div>
              <div style="font-weight: 800; font-size: 0.9rem; color: var(--text-main);">Surat Bon Material</div>
              <div style="font-size: 0.74rem; color: var(--text-muted); margin-top: 2px;">Pengeluaran barang ke teknisi</div>
            </div>
          </a>

          <!-- Link 3: Riwayat Pemasangan -->
          <a href="riwayat.php" style="display: flex; align-items: center; gap: 14px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px; text-decoration: none; transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.borderColor='var(--primary)'; this.style.boxShadow='0 6px 16px rgba(15,64,104,0.08)';" onmouseout="this.style.transform='none'; this.style.borderColor='var(--border-color)'; this.style.boxShadow='none';">
            <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0;">
              <i class="bi bi-clock-history"></i>
            </div>
            <div>
              <div style="font-weight: 800; font-size: 0.9rem; color: var(--text-main);">Riwayat Pemasangan</div>
              <div style="font-size: 0.74rem; color: var(--text-muted); margin-top: 2px;">Laporan ONT & kabel terpasang</div>
            </div>
          </a>

          <!-- Link 4: Pengguna -->
          <a href="pengguna.php" style="display: flex; align-items: center; gap: 14px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px; text-decoration: none; transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.borderColor='var(--primary)'; this.style.boxShadow='0 6px 16px rgba(15,64,104,0.08)';" onmouseout="this.style.transform='none'; this.style.borderColor='var(--border-color)'; this.style.boxShadow='none';">
            <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(139, 92, 246, 0.12); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0;">
              <i class="bi bi-people"></i>
            </div>
            <div>
              <div style="font-weight: 800; font-size: 0.9rem; color: var(--text-main);">Kelola Pengguna</div>
              <div style="font-size: 0.74rem; color: var(--text-muted); margin-top: 2px;">Akun admin gudang & teknisi</div>
            </div>
          </a>
        </div>
      </div>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
