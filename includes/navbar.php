<?php
/**
 * Layout: Topbar & Global Search
 * PT Cipta Karya Teknologi (CKT Lampung)
 */

$isAdmin = in_array($currentUser['role'] ?? '', ['admin_gudang', 'admin']);
$isTeknisi = (($currentUser['role'] ?? '') === 'teknisi');
$myActiveBonCount = $isTeknisi ? (int)$pdo->query("SELECT COUNT(*) FROM bon_requests WHERE user_id = " . (int)$currentUser['id'] . " AND status = 'approved'")->fetchColumn() : 0;
?>
<header class="topbar">
  <div class="topbar-left">
    <button type="button" class="mobile-toggle-btn" id="mobileSidebarToggle" aria-label="Toggle Sidebar">
      <i class="bi bi-list"></i>
    </button>
    <div class="topbar-breadcrumb">
      <h1 class="topbar-title"><?= htmlspecialchars($pageTitle ?? ($isTeknisi ? 'Tugas & Bon Saya' : 'Dashboard Gudang')) ?></h1>
      <span class="topbar-subtitle">Citratel &bull; Wilayah Lampung</span>
    </div>
  </div>

  <!-- Global Live Search (Ctrl + K) -->
  <div class="global-search-wrapper">
    <div class="global-search-input-group">
      <i class="bi bi-search global-search-icon"></i>
      <input 
        type="text" 
        class="global-search-input" 
        id="globalSearchInput" 
        placeholder="<?= $isTeknisi ? 'Cari nomor bon atau pelanggan saya...' : 'Cari material, ONT, kabel, no bon, teknisi...' ?>" 
        autocomplete="off"
      >
      <button 
        type="button" 
        onclick="openBarcodeScanner((scanned) => {
          const inp = document.getElementById('globalSearchInput');
          if (inp) {
            inp.value = scanned;
            inp.dispatchEvent(new Event('input', { bubbles: true }));
          }
        })" 
        style="position: absolute; right: 60px; background: none; border: none; color: var(--primary); cursor: pointer; padding: 4px; font-size: 1rem;" 
        title="Scan Barcode Kamera untuk Cari Otomatis"
      >
        <i class="bi bi-camera-fill"></i>
      </button>
      <span class="search-shortcut-badge">Ctrl K</span>
    </div>
    <div class="search-results-dropdown" id="searchResultsDropdown"></div>
  </div>

  <!-- Topbar Actions & User Profile -->
  <div class="topbar-actions">
    <!-- Active Database Badge (Admin Only) -->
    <?php if ($isAdmin): ?>
      <span class="badge" style="background: <?= ($active_driver === 'mysql') ? 'rgba(16, 185, 129, 0.12)' : 'rgba(245, 158, 11, 0.12)' ?>; color: <?= ($active_driver === 'mysql') ? '#059669' : '#d97706' ?>; font-weight: 700; border: 1px solid <?= ($active_driver === 'mysql') ? 'rgba(16, 185, 129, 0.25)' : 'rgba(245, 158, 11, 0.25)' ?>;" title="Koneksi Database Aktif">
        <i class="bi bi-database-check me-1"></i> <?= strtoupper($active_driver) ?>
      </span>

      <!-- Stock Warning Badge (Admin Only) -->
      <a href="stok.php?filter=kritis" class="topbar-icon-btn" title="Peringatan Stok Menipis">
        <i class="bi bi-exclamation-triangle"></i>
        <?php if (($stockAlertCount ?? 0) > 0): ?>
          <span class="topbar-badge-count"><?= $stockAlertCount ?></span>
        <?php endif; ?>
      </a>

      <!-- Bon Notification -->
      <a href="bon.php" class="topbar-icon-btn" title="Surat Bon Material">
        <i class="bi bi-receipt"></i>
        <?php if (($pendingBonCount ?? 0) > 0): ?>
          <span class="topbar-badge-count" style="background-color: var(--warning);"><?= $pendingBonCount ?></span>
        <?php endif; ?>
      </a>
    <?php else: ?>
      <!-- Teknisi Task Notification -->
      <a href="bon.php" class="topbar-icon-btn" title="Tugas Lapangan Aktif">
        <i class="bi bi-card-checklist text-primary"></i>
        <?php if ($myActiveBonCount > 0): ?>
          <span class="topbar-badge-count" style="background-color: var(--primary);"><?= $myActiveBonCount ?></span>
        <?php endif; ?>
      </a>
    <?php endif; ?>

    <!-- User Profile Dropdown & Switcher -->
    <div class="topbar-user-dropdown">
      <button type="button" class="topbar-user-btn" id="userProfileTrigger">
        <div class="avatar"><?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?></div>
        <div style="text-align: left; line-height: 1.2;">
          <div class="name"><?= htmlspecialchars($currentUser['name'] ?? 'Pengguna') ?></div>
          <div style="font-size: 0.68rem; color: var(--text-muted); font-weight: 600;">
            <?= (($currentUser['role'] ?? '') === 'admin_gudang') ? 'Admin Gudang' : 'Teknisi' ?>
          </div>
        </div>
        <i class="bi bi-chevron-down" style="font-size: 0.75rem; color: var(--text-dim); margin-left: 4px;"></i>
      </button>

      <div class="user-dropdown-menu" id="userDropdownMenu">
        <div class="dropdown-user-header">
          <div style="font-weight: 800; font-size: 0.88rem; color: var(--text-main);"><?= htmlspecialchars($currentUser['name'] ?? '') ?></div>
          <div style="font-size: 0.75rem; color: var(--text-muted); font-family: var(--font-mono);"><?= htmlspecialchars($currentUser['nik'] ?? '') ?></div>
          <span class="dropdown-user-role-badge">
            <i class="bi bi-person-badge me-1"></i> <?= htmlspecialchars($currentUser['department'] ?? 'Gudang & Logistik') ?>
          </span>
        </div>

        <div class="dropdown-divider"></div>

        <?php if ($isAdmin): ?>
          <a href="pengguna.php" class="switch-user-item" style="color: var(--text-main);">
            <div style="display: flex; align-items: center; gap: 8px;">
              <i class="bi bi-people text-primary"></i>
              <span>Kelola Pengguna & Teknisi</span>
            </div>
          </a>
          <div class="dropdown-divider"></div>
        <?php endif; ?>

        <a href="logout.php" class="switch-user-item" style="color: var(--danger);">
          <div style="display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-box-arrow-right"></i>
            <span>Keluar (Logout)</span>
          </div>
        </a>
      </div>
    </div>
  </div>
</header>
