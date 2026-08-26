<?php
/**
 * Layout: Sidebar Navigation
 * PT Cipta Karya Teknologi (CKT Lampung)
 */

$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
$isAdmin = in_array($currentUser['role'] ?? '', ['admin_gudang', 'admin']);
$isTeknisi = (($currentUser['role'] ?? '') === 'teknisi');
?>
<aside class="main-sidebar" id="mainSidebar">
  <!-- Sidebar Header & Brand Logo -->
  <div class="sidebar-header">
    <a href="index.php" class="sidebar-brand-logo">
      <img src="assets/img/logo-ckt.svg" alt="CKT Lampung Logo">
    </a>
  </div>

  <!-- Sidebar Nav Menu -->
  <div class="sidebar-content">
    
    <!-- Sidebar Content for ADMIN GUDANG -->
    <?php if ($isAdmin): ?>
      <!-- Quick Actions (Admin Only) -->
      <div style="display: flex; flex-direction: column; gap: 8px;">
        <button type="button" class="btn-primary" id="btnOpenBonModal" style="width: 100%; padding: 10px; font-size: 0.82rem; justify-content: center; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35);">
          <i class="bi bi-person-check-fill me-1"></i> Input Bon Teknisi
        </button>
        <button type="button" class="btn-secondary" onclick="openRestockModal()" style="width: 100%; padding: 8px; font-size: 0.78rem; justify-content: center; background: rgba(255,255,255,0.05); color: #cbd5e1; border-color: #334155;">
          <i class="bi bi-plus-circle me-1 text-success"></i> Barang Masuk (Restock)
        </button>
      </div>

      <div>
        <div class="sidebar-group-title">Menu Utama</div>
        <ul class="sidebar-menu">
          <li>
            <a href="index.php" class="sidebar-nav-item <?= ($currentScript === 'index.php') ? 'active' : '' ?>">
              <i class="bi bi-grid-1x2-fill"></i>
              <span>Dashboard</span>
            </a>
          </li>
          <li>
            <a href="stok.php" class="sidebar-nav-item <?= ($currentScript === 'stok.php') ? 'active' : '' ?>">
              <i class="bi bi-boxes"></i>
              <span>Stok & Material</span>
              <?php if (($stockAlertCount ?? 0) > 0): ?>
                <span class="nav-badge" style="background: var(--danger); color: #fff;"><?= $stockAlertCount ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li>
            <a href="bon.php" class="sidebar-nav-item <?= ($currentScript === 'bon.php') ? 'active' : '' ?>">
              <i class="bi bi-file-earmark-text-fill"></i>
              <span>Surat Bon Material</span>
              <?php if (($pendingBonCount ?? 0) > 0): ?>
                <span class="nav-badge" style="background: var(--warning); color: #fff;"><?= $pendingBonCount ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li>
            <a href="riwayat.php" class="sidebar-nav-item <?= ($currentScript === 'riwayat.php') ? 'active' : '' ?>">
              <i class="bi bi-clock-history"></i>
              <span>Riwayat Pemasangan</span>
            </a>
          </li>
          <li>
            <a href="laporan.php" class="sidebar-nav-item <?= ($currentScript === 'laporan.php') ? 'active' : '' ?>">
              <i class="bi bi-bar-chart-line-fill"></i>
              <span>Laporan & Mutasi</span>
            </a>
          </li>
        </ul>
      </div>

      <div>
        <div class="sidebar-group-title">Kategori Material</div>
        <ul class="sidebar-menu">
          <li>
            <a href="stok.php?cat=CAT-ONT" class="sidebar-nav-item" style="font-size: 0.82rem;">
              <i class="bi bi-router" style="color: #a78bfa;"></i>
              <span>ONT & Modem Wi-Fi</span>
            </a>
          </li>
          <li>
            <a href="stok.php?cat=CAT-KBL" class="sidebar-nav-item" style="font-size: 0.82rem;">
              <i class="bi bi-bezier2" style="color: #38bdf8;"></i>
              <span>Kabel Drop Core FO</span>
            </a>
          </li>
        </ul>
      </div>

    <!-- Sidebar Content for TEKNISI -->
    <?php else: 
      $myActiveBonsCount = (int)$pdo->query("SELECT COUNT(*) FROM bon_requests WHERE user_id = " . (int)$currentUser['id'] . " AND status = 'approved'")->fetchColumn();
    ?>
      <div>
        <div class="sidebar-group-title">Menu Utama Teknisi</div>
        <ul class="sidebar-menu">
          <li>
            <a href="bon.php" class="sidebar-nav-item <?= ($currentScript === 'bon.php') ? 'active' : '' ?>">
              <i class="bi bi-card-checklist text-primary"></i>
              <span>Tugas Saya</span>
              <?php if ($myActiveBonsCount > 0): ?>
                <span class="nav-badge" style="background: var(--primary); color: #fff;"><?= $myActiveBonsCount ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li>
            <a href="riwayat.php" class="sidebar-nav-item <?= ($currentScript === 'riwayat.php') ? 'active' : '' ?>">
              <i class="bi bi-clock-history text-info"></i>
              <span>Riwayat Pemasangan</span>
            </a>
          </li>
        </ul>
      </div>

      <!-- Helper Card for Teknisi -->
      <div style="background: rgba(2, 132, 199, 0.08); border: 1px solid rgba(2, 132, 199, 0.2); border-radius: var(--radius-md); padding: 14px;">
        <div style="font-size: 0.78rem; font-weight: 700; color: #38bdf8; margin-bottom: 4px;">
          <i class="bi bi-info-circle me-1"></i> Alur Kerja Teknisi
        </div>
        <div style="font-size: 0.74rem; color: #94a3b8; line-height: 1.4;">
          1. Ambil material di loket gudang<br>
          2. Admin terbitkan / gabungkan bon<br>
          3. Pasang & lapor ONT (Terpasang / Bad / Change)<br>
          4. Cek histori di <strong>Riwayat Pemasangan</strong>
        </div>
      </div>
    <?php endif; ?>

    <!-- System Info Widget in Sidebar -->
    <div style="margin-top: auto; background: rgba(30, 41, 59, 0.5); padding: 12px; border-radius: var(--radius-md); border: 1px solid #1e293b;">
      <div style="font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 700; margin-bottom: 4px;">Status Sistem</div>
      <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.76rem; color: #cbd5e1;">
        <span><i class="bi bi-circle-fill text-success me-1" style="font-size: 0.55rem;"></i> Server</span>
        <span class="font-mono text-success">ONLINE</span>
      </div>
      <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.76rem; color: #cbd5e1; margin-top: 4px;">
        <span><i class="bi bi-database me-1"></i> Database</span>
        <span class="font-mono" style="color: #38bdf8;"><?= strtoupper($active_driver) ?></span>
      </div>
    </div>

  </div>

  <!-- Sidebar Footer: Current User & Logout -->
  <div class="sidebar-footer">
    <div class="sidebar-user-card">
      <div class="user-avatar"><?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?></div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name" title="<?= htmlspecialchars($currentUser['name'] ?? '') ?>"><?= htmlspecialchars($currentUser['name'] ?? 'Pengguna') ?></div>
        <div class="sidebar-user-role">
          <i class="bi <?= $isAdmin ? 'bi-shield-check' : 'bi-person-gear' ?>"></i>
          <span><?= $isAdmin ? 'Admin Gudang' : 'Teknisi Lapangan' ?></span>
        </div>
      </div>
      <a href="logout.php" title="Keluar" style="color: #ef4444; font-size: 1.1rem; padding: 4px;">
        <i class="bi bi-power"></i>
      </a>
    </div>
  </div>
</aside>
