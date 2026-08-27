<?php
/**
 * View: Manajemen Pengguna & Teknisi (Admin Only)
 * PT Cipta Karya Teknologi (CKT Lampung)
 */

$pageTitle = "Manajemen Pengguna & Teknisi";
$pageHeaderTitle = "Manajemen Pengguna & Teknisi Lapangan";
$pageHeaderSubtitle = "Kelola akun pengguna, tambah teknisi baru, edit data karyawan, dan reset password login.";

require_once __DIR__ . '/config/database.php';

$currentUser = getCurrentUser($pdo);
$isAdmin = in_array($currentUser['role'] ?? '', ['admin_gudang', 'admin']);

if (!$isAdmin) {
    $_SESSION['flash_message'] = [
        'type' => 'warning',
        'title' => 'Akses Terbatas',
        'text' => 'Menu Manajemen Pengguna hanya dapat diakses oleh Admin Gudang.'
    ];
    header("Location: bon.php");
    exit;
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Filter Parameters
$roleFilter = $_GET['role'] ?? '';
$searchFilter = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

// Build Query
$sql = "
    SELECT u.*,
           (SELECT COUNT(*) FROM bon_requests b WHERE b.user_id = u.id) as total_bons_count,
           (SELECT COUNT(*) FROM bon_requests b WHERE b.user_id = u.id AND b.status = 'approved') as active_bons_count
    FROM users u
    WHERE 1=1
";
$params = [];

if (!empty($roleFilter)) {
    $sql .= " AND u.role = ?";
    $params[] = $roleFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND u.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchFilter)) {
    $sql .= " AND (u.name LIKE ? OR u.username LIKE ? OR u.nik LIKE ? OR u.department LIKE ?)";
    $like = '%' . $searchFilter . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}

$sql .= " ORDER BY u.role ASC, u.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$userList = $stmt->fetchAll();

// KPI Stats
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalTeknisi = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teknisi'")->fetchColumn();
$totalAdmin = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('admin_gudang', 'admin')")->fetchColumn();
$totalActive = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
?>

<div class="main-wrapper">
  <?php require_once __DIR__ . '/includes/navbar.php'; ?>

  <main class="content-body">
    <!-- Header Row -->
    <div class="page-header-row">
      <div>
        <div class="page-title-heading">Manajemen Akun Pengguna & Teknisi</div>
        <div class="page-title-subheading">Kelola hak akses Admin Gudang, daftarkan teknisi lapangan baru, dan atur akun karyawan.</div>
      </div>

      <div>
        <button type="button" class="btn-primary" onclick="openAddUserModal()">
          <i class="bi bi-person-plus-fill me-1"></i> Tambah Pengguna Baru
        </button>
      </div>
    </div>

    <!-- Stat KPI Cards Grid -->
    <div class="stat-grid" style="margin-bottom: 24px;">
      <div class="stat-card">
        <div class="stat-card-top">
          <div class="stat-icon primary">
            <i class="bi bi-people-fill"></i>
          </div>
          <span class="badge" style="background: rgba(2, 132, 199, 0.12); color: var(--primary); font-weight: 700;">Total Akun</span>
        </div>
        <div class="stat-label">Total Pengguna</div>
        <div class="stat-value text-primary"><?= $totalUsers ?> <span style="font-size: 1rem; font-weight: 600; color: var(--text-muted);">Akun</span></div>
        <div class="stat-desc">Terdaftar di sistem gudang CKT</div>
      </div>

      <div class="stat-card">
        <div class="stat-card-top">
          <div class="stat-icon success">
            <i class="bi bi-tools"></i>
          </div>
          <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #10b981; font-weight: 700;">Lapangan</span>
        </div>
        <div class="stat-label">Teknisi Lapangan</div>
        <div class="stat-value" style="color: #10b981;"><?= $totalTeknisi ?> <span style="font-size: 1rem; font-weight: 600; color: var(--text-muted);">Personel</span></div>
        <div class="stat-desc">Dapat mengambil bon & lapor ONT</div>
      </div>

      <div class="stat-card">
        <div class="stat-card-top">
          <div class="stat-icon ont">
            <i class="bi bi-shield-lock-fill"></i>
          </div>
          <span class="badge" style="background: rgba(139, 92, 246, 0.12); color: #8b5cf6; font-weight: 700;">Gudang</span>
        </div>
        <div class="stat-label">Admin Gudang</div>
        <div class="stat-value" style="color: #8b5cf6;"><?= $totalAdmin ?> <span style="font-size: 1rem; font-weight: 600; color: var(--text-muted);">Admin</span></div>
        <div class="stat-desc">Akses penuh stok & penerbitan bon</div>
      </div>

      <div class="stat-card">
        <div class="stat-card-top">
          <div class="stat-icon primary">
            <i class="bi bi-check-circle-fill"></i>
          </div>
          <span class="badge" style="background: rgba(2, 132, 199, 0.12); color: #0284c7; font-weight: 700;">Aktif</span>
        </div>
        <div class="stat-label">Status Akun Aktif</div>
        <div class="stat-value"><?= $totalActive ?> <span style="font-size: 1rem; font-weight: 600; color: var(--text-muted);">User</span></div>
        <div class="stat-desc">Dapat login ke sistem saat ini</div>
      </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div style="background-color: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 18px 20px; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
      <form method="GET" action="pengguna.php" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 240px; position: relative;">
          <input type="text" name="search" class="form-control" placeholder="Cari nama, username, NIK, divisi..." value="<?= htmlspecialchars($searchFilter) ?>" style="padding-left: 36px;">
          <i class="bi bi-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-dim);"></i>
        </div>

        <div style="min-width: 170px;">
          <select name="role" class="form-select" onchange="this.form.submit()">
            <option value="">Semua Peran (Role)</option>
            <option value="teknisi" <?= ($roleFilter === 'teknisi') ? 'selected' : '' ?>>Teknisi Lapangan</option>
            <option value="admin_gudang" <?= ($roleFilter === 'admin_gudang') ? 'selected' : '' ?>>Admin Gudang</option>
          </select>
        </div>

        <div style="min-width: 150px;">
          <select name="status" class="form-select" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            <option value="active" <?= ($statusFilter === 'active') ? 'selected' : '' ?>>Aktif</option>
            <option value="inactive" <?= ($statusFilter === 'inactive') ? 'selected' : '' ?>>Nonaktif</option>
          </select>
        </div>

        <button type="submit" class="btn-primary" style="padding: 10px 18px;">
          <i class="bi bi-filter me-1"></i> Filter
        </button>

        <?php if (!empty($searchFilter) || !empty($roleFilter) || !empty($statusFilter)): ?>
          <a href="pengguna.php" class="btn-secondary" style="padding: 10px 14px;" title="Reset Filter">
            <i class="bi bi-arrow-counterclockwise"></i> Reset
          </a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Users Table Card -->
    <div style="background-color: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm);">
      <div style="padding: 18px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #fafbfc;">
        <div style="font-weight: 800; font-size: 1rem; color: var(--text-main);">
          <i class="bi bi-people text-primary me-2"></i> Daftar Pengguna Sistem (<?= count($userList) ?>)
        </div>
      </div>

      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th style="width: 50px;">No</th>
              <th>Nama Pengguna & NIK</th>
              <th>Username</th>
              <th>Peran / Role</th>
              <th>Divisi / Departemen</th>
              <th>Status Akun</th>
              <th>Bon Aktif</th>
              <th style="text-align: right; width: 140px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($userList)): ?>
              <tr>
                <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                  <i class="bi bi-person-x" style="font-size: 2.5rem; display: block; margin-bottom: 8px; opacity: 0.4;"></i>
                  Tidak ada data pengguna yang sesuai dengan filter pencarian.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($userList as $idx => $u): 
                $isMe = ($u['id'] == $currentUser['id']);
                $isTek = ($u['role'] === 'teknisi');
              ?>
                <tr>
                  <td style="color: var(--text-dim); font-size: 0.82rem;"><?= $idx + 1 ?></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 12px;">
                      <div class="avatar" style="width: 38px; height: 38px; font-size: 0.95rem; background: <?= $isTek ? 'linear-gradient(135deg, #10b981 0%, #059669 100%)' : 'linear-gradient(135deg, #0284c7 0%, #0369a1 100%)' ?>;">
                        <?= strtoupper(substr($u['name'], 0, 1)) ?>
                      </div>
                      <div>
                        <div style="font-weight: 700; color: var(--text-main); font-size: 0.92rem;">
                          <?= htmlspecialchars($u['name']) ?>
                          <?php if ($isMe): ?>
                            <span class="badge" style="background: rgba(2, 132, 199, 0.15); color: var(--primary); font-size: 0.65rem; padding: 2px 6px; margin-left: 4px;">Akun Anda</span>
                          <?php endif; ?>
                        </div>
                        <small style="color: var(--text-muted); font-family: var(--font-mono); font-size: 0.76rem;">
                          NIK: <?= htmlspecialchars($u['nik']) ?>
                        </small>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="badge font-mono" style="background: rgba(0,0,0,0.04); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 600;">
                      @<?= htmlspecialchars($u['username'] ?: '-') ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($u['role'] === 'admin_gudang' || $u['role'] === 'admin'): ?>
                      <span class="badge" style="background: rgba(139, 92, 246, 0.12); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.25);">
                        <i class="bi bi-shield-check me-1"></i> Admin Gudang
                      </span>
                    <?php else: ?>
                      <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.25);">
                        <i class="bi bi-tools me-1"></i> Teknisi Lapangan
                      </span>
                    <?php endif; ?>
                  </td>
                  <td style="color: var(--text-muted); font-size: 0.85rem;">
                    <?= htmlspecialchars($u['department'] ?: 'Teknis & Jaringan') ?>
                  </td>
                  <td>
                    <?php if ($u['status'] === 'active'): ?>
                      <span class="status-pill status-approved" style="font-size: 0.72rem;">
                        <i class="bi bi-check-circle-fill"></i> Aktif
                      </span>
                    <?php else: ?>
                      <span class="status-pill status-cancelled" style="font-size: 0.72rem;">
                        <i class="bi bi-x-circle-fill"></i> Nonaktif
                      </span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($isTek): ?>
                      <?php if ($u['active_bons_count'] > 0): ?>
                        <span class="badge" style="background: rgba(245, 158, 11, 0.12); color: var(--warning); border: 1px solid rgba(245, 158, 11, 0.25);">
                          <?= $u['active_bons_count'] ?> Bon Dibawa
                        </span>
                      <?php else: ?>
                        <span style="color: var(--text-dim); font-size: 0.78rem;">Selesai (<?= $u['total_bons_count'] ?> total)</span>
                      <?php endif; ?>
                    <?php else: ?>
                      <span style="color: var(--text-dim); font-size: 0.78rem;">-</span>
                    <?php endif; ?>
                  </td>
                  <td style="text-align: right;">
                    <div style="display: inline-flex; gap: 6px; align-items: center;">
                      <!-- Edit Button -->
                      <button 
                        type="button" 
                        class="btn-icon-action" 
                        onclick='openEditUserModal(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' 
                        title="Edit Data Pengguna"
                        style="color: var(--primary);"
                      >
                        <i class="bi bi-pencil-square"></i>
                      </button>

                      <!-- Reset Password Button -->
                      <button 
                        type="button" 
                        class="btn-icon-action" 
                        onclick="openResetPasswordModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')" 
                        title="Reset Password"
                        style="color: var(--warning);"
                      >
                        <i class="bi bi-key-fill"></i>
                      </button>

                      <!-- Delete / Deactivate Button (Cannot delete self) -->
                      <?php if (!$isMe): ?>
                        <button 
                          type="button" 
                          class="btn-icon-action" 
                          onclick="confirmDeleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>', <?= (int)$u['total_bons_count'] ?>)" 
                          title="Hapus / Nonaktifkan Pengguna"
                          style="color: var(--danger);"
                        >
                          <i class="bi bi-trash3-fill"></i>
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
  </main>
</div>

<!-- =========================================================
     MODAL: TAMBAH / EDIT PENGGUNA
     ========================================================= -->
<div class="modal-backdrop" id="userModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <div class="modal-title" id="userModalTitle">
        <i class="bi bi-person-plus-fill text-primary"></i> Tambah Pengguna Baru
      </div>
      <button type="button" class="modal-close-btn" onclick="closeUserModal()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <form method="POST" action="api/user_action.php" id="formUser">
      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
      <input type="hidden" name="action" id="userAction" value="create">
      <input type="hidden" name="user_id" id="userIdInput" value="">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label"><i class="bi bi-person-fill text-primary me-1"></i> Nama Lengkap Karyawan / Teknisi *</label>
          <input type="text" name="name" id="userNameInput" class="form-control" placeholder="Contoh: Budi Santoso" required>
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label"><i class="bi bi-at text-primary me-1"></i> Username Login *</label>
            <input type="text" name="username" id="userUsernameInput" class="form-control font-mono" placeholder="Contoh: budi" required>
            <small style="color: var(--text-dim); font-size: 0.72rem;">Gunakan huruf kecil tanpa spasi.</small>
          </div>

          <div class="form-group">
            <label class="form-label"><i class="bi bi-card-heading text-primary me-1"></i> NIK / ID Karyawan *</label>
            <input type="text" name="nik" id="userNikInput" class="form-control font-mono" placeholder="Contoh: CKT-TEK-006" required>
          </div>
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label"><i class="bi bi-person-badge text-primary me-1"></i> Peran Akun (Role) *</label>
            <select name="role" id="userRoleSelect" class="form-select" required>
              <option value="teknisi">Teknisi Lapangan</option>
              <option value="admin_gudang">Admin Gudang</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label"><i class="bi bi-diagram-3 text-primary me-1"></i> Divisi / Departemen</label>
            <input type="text" name="department" id="userDeptInput" class="form-control" value="Teknis & Jaringan">
          </div>
        </div>

        <div class="form-group" id="passwordGroup">
          <label class="form-label" id="passwordLabel"><i class="bi bi-lock-fill text-primary me-1"></i> Password Login *</label>
          <input type="password" name="password" id="userPasswordInput" class="form-control" placeholder="Minimal 6 karakter" required>
          <small id="passwordHelp" style="color: var(--text-dim); font-size: 0.72rem; display: none;">Kosongkan jika tidak ingin mengubah password akun.</small>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label"><i class="bi bi-toggle-on text-primary me-1"></i> Status Akun</label>
          <select name="status" id="userStatusSelect" class="form-select">
            <option value="active">Aktif (Dapat Login ke Sistem)</option>
            <option value="inactive">Nonaktif (Akses Diblokir)</option>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeUserModal()">Batal</button>
        <button type="submit" class="btn-primary" id="btnSubmitUser">
          <i class="bi bi-check-lg me-1"></i> Simpan Pengguna
        </button>
      </div>
    </form>
  </div>
</div>

<!-- =========================================================
     MODAL: RESET PASSWORD PENGGUNA
     ========================================================= -->
<div class="modal-backdrop" id="resetModal">
  <div class="modal-dialog" style="max-width: 420px;">
    <div class="modal-header" style="background: rgba(245, 158, 11, 0.1);">
      <div class="modal-title">
        <i class="bi bi-key-fill text-warning"></i> Reset Password Pengguna
      </div>
      <button type="button" class="modal-close-btn" onclick="closeResetPasswordModal()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <form method="POST" action="api/user_action.php">
      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="resetUserId" value="">

      <div class="modal-body">
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px;">
          Anda akan mengatur ulang kata sandi untuk akun <strong id="resetUserName" style="color: var(--text-main);"></strong>.
        </p>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Password Baru</label>
          <input type="text" name="new_password" class="form-control font-mono" value="123456" required>
          <small style="color: var(--text-dim); font-size: 0.72rem;">Default: <code>123456</code> (Bisa diganti sesuai keinginan).</small>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeResetPasswordModal()">Batal</button>
        <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
          <i class="bi bi-key-fill me-1"></i> Reset Password Sekarang
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Form Hidden untuk Hapus Pengguna -->
<form id="formDeleteUser" method="POST" action="api/user_action.php" style="display: none;">
  <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="user_id" id="deleteUserId" value="">
</form>

<script>
function openAddUserModal() {
  document.getElementById('userAction').value = 'create';
  document.getElementById('userIdInput').value = '';
  document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-person-plus-fill text-primary"></i> Tambah Pengguna Baru';
  document.getElementById('btnSubmitUser').innerHTML = '<i class="bi bi-check-lg me-1"></i> Tambah Pengguna';
  
  document.getElementById('userNameInput').value = '';
  document.getElementById('userUsernameInput').value = '';
  document.getElementById('userNikInput').value = '';
  document.getElementById('userRoleSelect').value = 'teknisi';
  document.getElementById('userDeptInput').value = 'Teknis & Jaringan';
  document.getElementById('userPasswordInput').value = '';
  document.getElementById('userPasswordInput').required = true;
  document.getElementById('passwordHelp').style.display = 'none';
  document.getElementById('userStatusSelect').value = 'active';

  const modal = document.getElementById('userModal');
  modal.classList.add('show');
}

function openEditUserModal(user) {
  document.getElementById('userAction').value = 'update';
  document.getElementById('userIdInput').value = user.id;
  document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-pencil-square text-primary"></i> Edit Data Pengguna';
  document.getElementById('btnSubmitUser').innerHTML = '<i class="bi bi-check-lg me-1"></i> Simpan Perubahan';

  document.getElementById('userNameInput').value = user.name || '';
  document.getElementById('userUsernameInput').value = user.username || '';
  document.getElementById('userNikInput').value = user.nik || '';
  document.getElementById('userRoleSelect').value = user.role || 'teknisi';
  document.getElementById('userDeptInput').value = user.department || 'Teknis & Jaringan';
  document.getElementById('userPasswordInput').value = '';
  document.getElementById('userPasswordInput').required = false;
  document.getElementById('passwordHelp').style.display = 'block';
  document.getElementById('userStatusSelect').value = user.status || 'active';

  const modal = document.getElementById('userModal');
  modal.classList.add('show');
}

function closeUserModal() {
  const modal = document.getElementById('userModal');
  modal.classList.remove('show');
}

function openResetPasswordModal(userId, userName) {
  document.getElementById('resetUserId').value = userId;
  document.getElementById('resetUserName').innerText = userName;
  const modal = document.getElementById('resetModal');
  modal.classList.add('show');
}

function closeResetPasswordModal() {
  const modal = document.getElementById('resetModal');
  modal.classList.remove('show');
}

// Tutup modal jika klik di luar area dialog
window.addEventListener('click', function(e) {
  const userModal = document.getElementById('userModal');
  const resetModal = document.getElementById('resetModal');
  if (e.target === userModal) {
    closeUserModal();
  }
  if (e.target === resetModal) {
    closeResetPasswordModal();
  }
});

// Tutup modal jika tekan tombol ESC
window.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeUserModal();
    closeResetPasswordModal();
  }
});

function confirmDeleteUser(userId, userName, bonCount) {
  let warningText = `Apakah Anda yakin ingin menghapus akun '${userName}'?`;
  if (bonCount > 0) {
    warningText = `Pengguna '${userName}' memiliki ${bonCount} riwayat Surat Bon. Akun akan dinonaktifkan agar data riwayat gudang tetap terjaga.`;
  }

  Swal.fire({
    title: 'Konfirmasi Hapus / Nonaktifkan',
    text: warningText,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Ya, Lanjutkan!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      document.getElementById('deleteUserId').value = userId;
      document.getElementById('formDeleteUser').submit();
    }
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
