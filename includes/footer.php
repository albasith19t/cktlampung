<?php
/**
 * Layout: Footer & Global Modals (Input Bon, Restock, Complete Task)
 * PT Cipta Karya Teknologi (CKT Lampung)
 */

// Fetch materials for Modals (Only ONT and Cables)
$modalMaterials = $pdo->query("
  SELECT m.id, m.code, m.name, m.unit, m.stock_current, m.is_serialized, m.cable_length, c.name as category_name 
  FROM materials m 
  LEFT JOIN categories c ON m.category_id = c.id 
  WHERE c.code IN ('CAT-ONT', 'CAT-KBL') OR m.category_id IN (1, 2)
  ORDER BY m.category_id ASC, m.cable_length DESC, m.name ASC
")->fetchAll();

// Fetch technicians
$modalTechnicians = $pdo->query("SELECT id, name, nik FROM users WHERE role = 'teknisi' ORDER BY name ASC")->fetchAll();
?>
</div> <!-- End .app-container -->

<!-- =========================================================================
     1. MODAL: INPUT BON TEKNISI BARU (Admin Gudang Langsung Input Pengeluaran)
     ========================================================================= -->
<div class="modal-backdrop" id="bonModalBackdrop">
  <div class="modal-dialog modal-lg">
    <div class="modal-header">
      <div class="modal-title">
        <i class="bi bi-person-check-fill text-primary"></i> Form Serah Terima & Pengeluaran Bon Material
      </div>
      <button type="button" class="modal-close-btn" id="btnCloseBonModal">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <form method="POST" action="api/bon_action.php" id="formCreateBon">
      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
      <input type="hidden" name="action" value="create">

      <div class="modal-body">
        <!-- Technician Selection -->
        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label"><i class="bi bi-person-fill text-primary me-1"></i> Teknisi Penerima Barang *</label>
          <select name="technician_id" class="form-select" required>
            <option value="">-- Pilih Teknisi Lapangan --</option>
            <?php foreach ($modalTechnicians as $tek): ?>
              <option value="<?= $tek['id'] ?>">
                <?= htmlspecialchars($tek['name']) ?> (<?= htmlspecialchars($tek['nik']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="font-size: 0.75rem; color: #38bdf8; background: rgba(2, 132, 199, 0.08); border: 1px solid rgba(2, 132, 199, 0.2); padding: 8px 12px; border-radius: 6px; margin-bottom: 18px; display: flex; align-items: center; gap: 6px;">
          <i class="bi bi-info-circle-fill"></i>
          <span><strong>Auto-Merge Bon:</strong> Jika teknisi sudah memiliki Surat Bon aktif, material yang diinput akan otomatis digabungkan ke bon aktif tersebut.</span>
        </div>

        <!-- Dynamic Material Item Rows -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
          <div style="font-weight: 800; font-size: 0.88rem; color: var(--text-main);">
            <i class="bi bi-box-seam text-primary me-1"></i> Material yang Diserahkan ke Teknisi:
          </div>
          <button type="button" class="btn-secondary" id="btnAddBonItem" style="padding: 5px 12px; font-size: 0.78rem;">
            <i class="bi bi-plus-lg me-1"></i> Tambah Baris Barang
          </button>
        </div>

        <div id="bonItemsContainer">
          <!-- Initial Row -->
          <div class="bon-item-row">
            <div style="display: grid; grid-template-columns: 2fr 1fr 1.5fr auto; gap: 10px; align-items: flex-start;">
              <div>
                <label class="form-label" style="font-size: 0.7rem;">Pilih Material / Kabel</label>
                <select name="material_id[]" class="form-select bon-material-select" onchange="handleMaterialChange(this)" required>
                  <option value="">-- Pilih Barang --</option>
                  <?php foreach ($modalMaterials as $mm): ?>
                    <option 
                      value="<?= $mm['id'] ?>" 
                      data-stock="<?= $mm['stock_current'] ?>" 
                      data-unit="<?= htmlspecialchars($mm['unit']) ?>"
                      data-serialized="<?= $mm['is_serialized'] ?>"
                    >
                      <?= htmlspecialchars($mm['name']) ?> (Stok: <?= $mm['stock_current'] ?> <?= $mm['unit'] ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="stock-avail-hint" style="font-size: 0.72rem; margin-top: 3px; color: var(--text-muted);"></div>
              </div>

              <div>
                <label class="form-label" style="font-size: 0.7rem;">Qty Ambil</label>
                <input type="number" name="quantity[]" class="form-control" value="1" min="1" required>
              </div>

              <div class="serial-col" style="opacity: 0.5;">
                <label class="form-label" style="font-size: 0.7rem;">Serial Number (ONT)</label>
                <input type="text" name="item_serial[]" class="form-control bon-serial-input font-mono" placeholder="Tidak butuh SN" style="font-size: 0.8rem;">
              </div>

              <div style="padding-top: 24px;">
                <button type="button" class="btn-icon-action" onclick="removeBonItemRow(this)" title="Hapus Baris" style="color: var(--danger);">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div class="form-group" style="margin-top: 16px; margin-bottom: 0;">
          <label class="form-label">Catatan Admin Gudang</label>
          <input type="text" name="admin_notes" class="form-control" placeholder="Contoh: Barang diambil teknisi di loket gudang jam 08:30 WIB">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-secondary" id="btnCancelBonModal">Batal</button>
        <button type="submit" class="btn-primary">
          <i class="bi bi-check2-circle me-1"></i> Terbitkan Bon & Potong Stok
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Template for Dynamic Bon Item Row -->
<template id="bonItemRowTemplate">
  <div class="bon-item-row">
    <div style="display: grid; grid-template-columns: 2fr 1fr 1.5fr auto; gap: 10px; align-items: flex-start;">
      <div>
        <label class="form-label" style="font-size: 0.7rem;">Pilih Material / Kabel</label>
        <select name="material_id[]" class="form-select bon-material-select" onchange="handleMaterialChange(this)" required>
          <option value="">-- Pilih Barang --</option>
          <?php foreach ($modalMaterials as $mm): ?>
            <option 
              value="<?= $mm['id'] ?>" 
              data-stock="<?= $mm['stock_current'] ?>" 
              data-unit="<?= htmlspecialchars($mm['unit']) ?>"
              data-serialized="<?= $mm['is_serialized'] ?>"
            >
              <?= htmlspecialchars($mm['name']) ?> (Stok: <?= $mm['stock_current'] ?> <?= $mm['unit'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <div class="stock-avail-hint" style="font-size: 0.72rem; margin-top: 3px; color: var(--text-muted);"></div>
      </div>

      <div>
        <label class="form-label" style="font-size: 0.7rem;">Qty Ambil</label>
        <input type="number" name="quantity[]" class="form-control" value="1" min="1" required>
      </div>

      <div class="serial-col" style="opacity: 0.5;">
        <label class="form-label" style="font-size: 0.7rem;">Serial Number (ONT)</label>
        <input type="text" name="item_serial[]" class="form-control bon-serial-input font-mono" placeholder="Tidak butuh SN" style="font-size: 0.8rem;">
      </div>

      <div style="padding-top: 24px;">
        <button type="button" class="btn-icon-action" onclick="removeBonItemRow(this)" title="Hapus Baris" style="color: var(--danger);">
          <i class="bi bi-trash"></i>
        </button>
      </div>
    </div>
  </div>
</template>


<!-- =========================================================================
     2. MODAL: RESTOCK / BARANG MASUK DARI SUPPLIER
     ========================================================================= -->
<div class="modal-backdrop" id="restockModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <div class="modal-title">
        <i class="bi bi-box-arrow-in-down text-success"></i> Form Penerimaan Stok Masuk (Restock)
      </div>
      <button type="button" class="modal-close-btn" onclick="closeRestockModal()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <form method="POST" action="api/stok_action.php">
      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
      <input type="hidden" name="action" value="restock">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Material / Kabel yang Diterima *</label>
          <select name="material_id" id="restockMaterialSelect" class="form-select" required>
            <option value="">-- Pilih Material --</option>
            <?php foreach ($modalMaterials as $mm): ?>
              <option value="<?= $mm['id'] ?>">
                <?= htmlspecialchars($mm['name']) ?> (Sisa saat ini: <?= $mm['stock_current'] ?> <?= $mm['unit'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label">Jumlah Kuantiti Masuk *</label>
            <input type="number" name="quantity" class="form-control" min="1" value="10" required>
          </div>
          <div class="form-group">
            <label class="form-label">No. PO / Surat Jalan Supplier</label>
            <input type="text" name="reference_id" class="form-control" value="PO-RESTOCK-<?= date('Ymd') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Serial Number Baru (Khusus ONT, pisahkan dengan koma/baris baru)</label>
          <textarea name="new_serials" class="form-control font-mono" rows="3" placeholder="Contoh: ZTEGC892F110, ZTEGC892F111"></textarea>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Catatan Tambahan</label>
          <input type="text" name="notes" class="form-control" placeholder="Penerimaan dari distributor resmi">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeRestockModal()">Batal</button>
        <button type="submit" class="btn-success">
          <i class="bi bi-plus-circle me-1"></i> Simpan Stok Masuk
        </button>
      </div>
    </form>
  </div>
</div>


<!-- =========================================================================
     3. MODAL: KONFIRMASI TUGAS SELESAI (Teknisi Input Alamat & Pelanggan)
     ========================================================================= -->
<div class="modal-backdrop" id="completeTaskModal">
  <div class="modal-dialog">
    <div class="modal-header" style="background: rgba(16, 185, 129, 0.1);">
      <div class="modal-title">
        <i class="bi bi-patch-check-fill text-success"></i> Konfirmasi Tugas Pemasangan Selesai
      </div>
      <button type="button" class="modal-close-btn" onclick="closeCompleteTaskModal()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <form method="POST" action="api/bon_action.php">
      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
      <input type="hidden" name="action" value="complete">
      <input type="hidden" name="bon_id" id="completeBonId" value="">

      <div class="modal-body">
        <div style="background-color: var(--bg-body); padding: 12px 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 18px;">
          <div style="font-size: 0.72rem; color: var(--text-dim); text-transform: uppercase; font-weight: 700;">Nomor Surat Bon:</div>
          <div style="font-size: 1.1rem; font-weight: 800; color: var(--primary); font-family: var(--font-mono);" id="completeBonNumber">
            BON-CKT-...
          </div>
          <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
            Pastikan seluruh material yang diambil telah selesai terpasang di rumah pelanggan.
          </div>
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label">Nama Pelanggan / Tempat Pasang *</label>
            <input type="text" name="customer_name" id="completeCustomerName" class="form-control" placeholder="Nama Pelanggan" required>
          </div>
          <div class="form-group">
            <label class="form-label">ID Pelanggan (Nomor Angka)</label>
            <input type="text" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')" name="customer_id" id="completeCustomerId" class="form-control font-mono" placeholder="Contoh: 100234">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Alamat Lengkap Pemasangan *</label>
          <textarea name="customer_address" id="completeCustomerAddress" class="form-control" rows="2" placeholder="Contoh: Jl. Teuku Umar No. 88, Kedaton, Bandar Lampung" required></textarea>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Catatan Hasil Lapangan</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Pemasangan lancar, redaman optik -18 dBm, Wi-Fi aktif."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeCompleteTaskModal()">Batal</button>
        <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
          <i class="bi bi-check-all me-1"></i> Nyatakan Selesai & Simpan
        </button>
      </div>
    </form>
  </div>
</div>


<!-- =========================================================================
     4. MODAL: KONFIRMASI PEMASANGAN INDIVIDUAL ONT SERIAL NUMBER
     ========================================================================= -->
<div class="modal-backdrop" id="installSNModal">
  <div class="modal-dialog">
    <div class="modal-header" style="background: rgba(2, 132, 199, 0.08);">
      <div class="modal-title">
        <i class="bi bi-router text-primary"></i> Form Laporan Hasil ONT Teknisi
      </div>
      <button type="button" class="modal-close-btn" onclick="closeInstallSNModal()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <form method="POST" action="api/bon_action.php" id="formReportSN">
      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
      <input type="hidden" name="action" value="install_sn">
      <input type="hidden" name="bon_id" id="installBonId" value="">
      <input type="hidden" name="material_id" id="installMaterialId" value="">
      <input type="hidden" name="orig_serial_number" id="installOrigSNInput" value="">
      <input type="hidden" name="serial_number" id="installSNInput" value="">

      <div class="modal-body">
        <!-- ONT & Bon Info Banner -->
        <div style="background-color: var(--bg-body); padding: 14px 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 18px;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;">
            <div>
              <div style="font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; font-weight: 700;">Serial Number ONT:</div>
              <div style="font-size: 1.2rem; font-weight: 800; color: var(--primary); font-family: var(--font-mono); margin-top: 2px;" id="installSNDisplay">
                ZTEGC...
              </div>
            </div>
            <div style="text-align: right;">
              <div style="font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; font-weight: 700;">No. Surat Bon:</div>
              <div style="font-size: 0.85rem; font-weight: 700; font-family: var(--font-mono); color: var(--text-main); margin-top: 2px;" id="installBonNumDisplay">
                BON-...
              </div>
            </div>
          </div>
          <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 6px; padding-top: 6px; border-top: 1px dashed var(--border-color);" id="installMatNameDisplay">
            ONT Wi-Fi
          </div>
        </div>

        <!-- 3 Pilihan Status / Hasil Lapangan -->
        <div class="form-group" style="margin-bottom: 18px;">
          <label class="form-label" style="font-weight: 700; display: block; margin-bottom: 8px;">
            <i class="bi bi-tag-fill text-primary me-1"></i> Pilih Status / Hasil Lapangan: *
          </label>
          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
            
            <!-- Option 1: Terpasang -->
            <label class="report-status-card" id="cardStatusInstalled" style="border: 2px solid #10b981; background: rgba(16, 185, 129, 0.08); border-radius: var(--radius-md); padding: 10px 8px; cursor: pointer; text-align: center; transition: all 0.2s ease;">
              <input type="radio" name="sn_status" id="radioStatusInstalled" value="installed" checked style="display: none;" onchange="updateReportStatusUI('installed')">
              <i class="bi bi-patch-check-fill" style="color: #10b981; font-size: 1.3rem; display: block; margin-bottom: 2px;"></i>
              <strong style="color: #10b981; font-size: 0.84rem; display: block;">Terpasang</strong>
              <small style="color: var(--text-muted); font-size: 0.68rem;">Sukses di rumah</small>
            </label>

            <!-- Option 2: Bad -->
            <label class="report-status-card" id="cardStatusBad" style="border: 1px solid var(--border-color); background: var(--bg-body); border-radius: var(--radius-md); padding: 10px 8px; cursor: pointer; text-align: center; transition: all 0.2s ease;">
              <input type="radio" name="sn_status" id="radioStatusBad" value="bad" style="display: none;" onchange="updateReportStatusUI('bad')">
              <i class="bi bi-x-octagon-fill" style="color: #ef4444; font-size: 1.3rem; display: block; margin-bottom: 2px;"></i>
              <strong style="color: #ef4444; font-size: 0.84rem; display: block;">Bad</strong>
              <small style="color: var(--text-muted); font-size: 0.68rem;">Rusak / kendala alat</small>
            </label>

            <!-- Option 3: Change -->
            <label class="report-status-card" id="cardStatusChange" style="border: 1px solid var(--border-color); background: var(--bg-body); border-radius: var(--radius-md); padding: 10px 8px; cursor: pointer; text-align: center; transition: all 0.2s ease;">
              <input type="radio" name="sn_status" id="radioStatusChange" value="change" style="display: none;" onchange="updateReportStatusUI('change')">
              <i class="bi bi-arrow-repeat" style="color: #f59e0b; font-size: 1.3rem; display: block; margin-bottom: 2px;"></i>
              <strong style="color: #f59e0b; font-size: 0.84rem; display: block;">Change</strong>
              <small style="color: var(--text-muted); font-size: 0.68rem;">Ganti unit / tipe</small>
            </label>

          </div>
        </div>

        <!-- Customer Form Grid -->
        <div class="form-grid-2" id="groupCustGrid">
          <div class="form-group">
            <label class="form-label" id="labelCustName"><i class="bi bi-person-fill text-primary me-1"></i> Nama Pelanggan / Pemilik Rumah *</label>
            <input type="text" name="customer_name" id="installCustomerName" class="form-control" placeholder="Contoh: Budi Santoso" required>
          </div>
          <div class="form-group">
            <label class="form-label">ID Pelanggan (Nomor Angka)</label>
            <input type="text" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')" name="customer_id" id="installCustomerId" class="form-control font-mono" placeholder="Contoh: 100234">
          </div>
        </div>

        <div class="form-group" id="groupCableUsed">
          <label class="form-label"><i class="bi bi-bezier2 text-primary me-1"></i> Kabel Drop Core yang Dipakai *</label>
          <select name="cable_used" id="installCableUsed" class="form-select" required>
            <option value="">-- Pilih Panjang Kabel yang Dipakai --</option>
            <option value="Kabel Drop Core 50 Meter">Kabel Drop Core (50 Meter)</option>
            <option value="Kabel Drop Core 75 Meter">Kabel Drop Core (75 Meter)</option>
            <option value="Kabel Drop Core 100 Meter">Kabel Drop Core (100 Meter)</option>
            <option value="Kabel Drop Core 150 Meter">Kabel Drop Core (150 Meter)</option>
            <option value="Tanpa Kabel Tambahan">Tanpa Kabel Tambahan (Kabel Eksisting / Lama)</option>
          </select>
        </div>

        <div class="form-group" id="groupCustAddress">
          <label class="form-label" id="labelCustAddress"><i class="bi bi-geo-alt-fill text-primary me-1"></i> Alamat Lengkap Pemasangan *</label>
          <textarea name="customer_address" id="installCustomerAddress" class="form-control" rows="2" placeholder="Contoh: Jl. Teuku Umar No. 88, Kedaton, Bandar Lampung" required></textarea>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" id="installNotesLabel">Catatan Hasil Lapangan (Opsional)</label>
          <textarea name="notes" id="installNotesInput" class="form-control" rows="2" placeholder="Contoh: Pemasangan lancar, redaman optik -18.2 dBm, Wi-Fi 2.4/5GHz aktif."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeInstallSNModal()">Batal</button>
        <button type="submit" id="btnSubmitReportSN" class="btn-primary" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
          <i class="bi bi-check-all me-1"></i> Simpan & Nyatakan Terpasang
        </button>
      </div>
    </form>
  </div>
</div>

<!-- SweetAlert2 Flash Notification Trigger -->
<?php if (isset($_SESSION['flash_message'])): 
  $fm = $_SESSION['flash_message'];
  unset($_SESSION['flash_message']);
?>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      Swal.fire({
        icon: '<?= $fm['type'] ?? 'info' ?>',
        title: '<?= addslashes($fm['title'] ?? '') ?>',
        text: '<?= addslashes($fm['text'] ?? '') ?>',
        confirmButtonColor: '#0284c7'
      });
    });
  </script>
<?php endif; ?>

<!-- Mobile Bottom Navigation Bar (< 992px) -->
<?php
$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
$isAdmin = in_array($currentUser['role'] ?? '', ['admin_gudang', 'admin']);
$isTeknisi = (($currentUser['role'] ?? '') === 'teknisi');
?>
<nav class="mobile-bottom-nav">
  <?php if ($isAdmin): ?>
    <a href="index.php" class="mobile-nav-item <?= ($currentScript === 'index.php') ? 'active' : '' ?>">
      <i class="bi bi-grid-1x2-fill"></i>
      <span>Beranda</span>
    </a>
    <a href="stok.php" class="mobile-nav-item <?= ($currentScript === 'stok.php') ? 'active' : '' ?>">
      <i class="bi bi-boxes"></i>
      <span>Stok</span>
      <?php if (($stockAlertCount ?? 0) > 0): ?>
        <span class="mobile-nav-badge"><?= $stockAlertCount ?></span>
      <?php endif; ?>
    </a>
    <a href="bon.php" class="mobile-nav-item <?= ($currentScript === 'bon.php') ? 'active' : '' ?>">
      <i class="bi bi-file-earmark-text-fill"></i>
      <span>Bon</span>
      <?php if (($pendingBonCount ?? 0) > 0): ?>
        <span class="mobile-nav-badge"><?= $pendingBonCount ?></span>
      <?php endif; ?>
    </a>
    <a href="laporan.php" class="mobile-nav-item <?= ($currentScript === 'laporan.php') ? 'active' : '' ?>">
      <i class="bi bi-bar-chart-line-fill"></i>
      <span>Laporan</span>
    </a>
    <a href="javascript:void(0)" onclick="document.getElementById('mainSidebar').classList.toggle('show-mobile')" class="mobile-nav-item">
      <i class="bi bi-list"></i>
      <span>Menu</span>
    </a>
  <?php else: 
    $myActiveCount = (int)$pdo->query("SELECT COUNT(*) FROM bon_requests WHERE user_id = " . (int)$currentUser['id'] . " AND status = 'approved'")->fetchColumn();
  ?>
    <a href="bon.php" class="mobile-nav-item active">
      <i class="bi bi-card-checklist"></i>
      <span>Tugas Saya</span>
      <?php if ($myActiveCount > 0): ?>
        <span class="mobile-nav-badge"><?= $myActiveCount ?></span>
      <?php endif; ?>
    </a>
    <a href="javascript:void(0)" onclick="location.reload()" class="mobile-nav-item">
      <i class="bi bi-arrow-clockwise"></i>
      <span>Segarkan</span>
    </a>
    <a href="logout.php" class="mobile-nav-item" style="color: #ef4444;">
      <i class="bi bi-power"></i>
      <span>Keluar</span>
    </a>
  <?php endif; ?>
</nav>

<!-- Global Modal Helper Functions -->
<script>
window.updateReportStatusUI = function(status) {
  const cInstalled = document.getElementById('cardStatusInstalled');
  const cBad = document.getElementById('cardStatusBad');
  const cChange = document.getElementById('cardStatusChange');
  const btnSubmit = document.getElementById('btnSubmitReportSN');
  const custNameInput = document.getElementById('installCustomerName');
  const custAddressInput = document.getElementById('installCustomerAddress');
  const labelCustName = document.getElementById('labelCustName');
  const labelCustAddress = document.getElementById('labelCustAddress');
  const cableUsedSelect = document.getElementById('installCableUsed');
  const notesLabel = document.getElementById('installNotesLabel');
  const notesInput = document.getElementById('installNotesInput');

  if (!cInstalled || !cBad || !cChange) return;

  // Reset border & bg
  [cInstalled, cBad, cChange].forEach(c => {
    c.style.border = '1px solid var(--border-color)';
    c.style.background = 'var(--bg-body)';
  });

  if (status === 'installed') {
    cInstalled.style.border = '2px solid #10b981';
    cInstalled.style.background = 'rgba(16, 185, 129, 0.08)';
    if (btnSubmit) {
      btnSubmit.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
      btnSubmit.innerHTML = '<i class="bi bi-check-all me-1"></i> Simpan & Nyatakan Terpasang';
    }
    if (custNameInput) custNameInput.required = true;
    if (labelCustName) labelCustName.innerHTML = '<i class="bi bi-person-fill text-primary me-1"></i> Nama Pelanggan / Pemilik Rumah *';
    if (custAddressInput) custAddressInput.required = true;
    if (labelCustAddress) labelCustAddress.innerHTML = '<i class="bi bi-geo-alt-fill text-primary me-1"></i> Alamat Lengkap Pemasangan *';
    if (cableUsedSelect) cableUsedSelect.required = true;
    if (notesLabel) notesLabel.innerHTML = 'Catatan Hasil Lapangan (Opsional)';
    if (notesInput) {
      notesInput.required = false;
      notesInput.placeholder = 'Contoh: Pemasangan lancar, redaman optik -18.2 dBm, Wi-Fi 2.4/5GHz aktif.';
    }
  } else if (status === 'bad') {
    cBad.style.border = '2px solid #ef4444';
    cBad.style.background = 'rgba(239, 68, 68, 0.08)';
    if (btnSubmit) {
      btnSubmit.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
      btnSubmit.innerHTML = '<i class="bi bi-x-octagon-fill me-1"></i> Simpan Status Bad';
    }
    if (custNameInput) custNameInput.required = false;
    if (labelCustName) labelCustName.innerHTML = '<i class="bi bi-person me-1"></i> Nama Pelanggan / Lokasi Pengecekan (Opsional)';
    if (custAddressInput) custAddressInput.required = false;
    if (labelCustAddress) labelCustAddress.innerHTML = '<i class="bi bi-geo-alt me-1"></i> Alamat / Titik Gangguan (Opsional)';
    if (cableUsedSelect) cableUsedSelect.required = false;
    if (notesLabel) notesLabel.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger me-1"></i> Alasan & Gejala Kerusakan Unit Bad *';
    if (notesInput) {
      notesInput.required = true;
      notesInput.placeholder = 'Wajib: Jelaskan kendala/kerusakan (Contoh: Lampu LOS kedap-kedip merah, Port LAN 1 mati, adaptor short/mati total).';
    }
  } else if (status === 'change') {
    cChange.style.border = '2px solid #f59e0b';
    cChange.style.background = 'rgba(245, 158, 11, 0.08)';
    if (btnSubmit) {
      btnSubmit.style.background = 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)';
      btnSubmit.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Simpan Status Change';
    }
    if (custNameInput) custNameInput.required = true;
    if (labelCustName) labelCustName.innerHTML = '<i class="bi bi-person-fill text-warning me-1"></i> Nama Pelanggan Penggantian Unit *';
    if (custAddressInput) custAddressInput.required = false;
    if (labelCustAddress) labelCustAddress.innerHTML = '<i class="bi bi-geo-alt me-1"></i> Alamat Pemasangan (Opsional)';
    if (cableUsedSelect) cableUsedSelect.required = false;
    if (notesLabel) notesLabel.innerHTML = '<i class="bi bi-arrow-repeat text-warning me-1"></i> Alasan Change / Pergantian Unit *';
    if (notesInput) {
      notesInput.required = true;
      notesInput.placeholder = 'Wajib: Jelaskan alasan pergantian (Contoh: Pelanggan upgrade ke ONT Dual Band, ganti ONT lama tipe single band).';
    }
  }
};

window.openInstallSNModal = function(bonId, bonNumber, serialNumber, matName, materialId) {
  const modal = document.getElementById('installSNModal');
  if (!modal) return;

  const bonIdInput = document.getElementById('installBonId');
  const matIdInput = document.getElementById('installMaterialId');
  const snInput = document.getElementById('installSNInput');
  const origSnInput = document.getElementById('installOrigSNInput');
  const snDisplay = document.getElementById('installSNDisplay');
  const bonNumDisplay = document.getElementById('installBonNumDisplay');
  const matDisplay = document.getElementById('installMatNameDisplay');
  const custNameInput = document.getElementById('installCustomerName');
  const cableSelect = document.getElementById('installCableUsed');

  if (bonIdInput) bonIdInput.value = bonId;
  if (matIdInput) matIdInput.value = materialId || 1;
  if (snInput) snInput.value = serialNumber;
  if (origSnInput) origSnInput.value = serialNumber;
  if (snDisplay) snDisplay.textContent = serialNumber;
  if (bonNumDisplay) bonNumDisplay.textContent = bonNumber || ('#' + bonId);
  if (matDisplay) matDisplay.textContent = matName || 'Perangkat ONT Wi-Fi';

  // Default to 'installed'
  const radioInstalled = document.getElementById('radioStatusInstalled');
  if (radioInstalled) {
    radioInstalled.checked = true;
    updateReportStatusUI('installed');
  }

  // Dynamically populate Cable options based ONLY on cables taken in this Bon
  if (cableSelect) {
    cableSelect.innerHTML = '';

    const cables = window.currentBonCables || [];
    if (cables.length > 0) {
      const defaultOpt = document.createElement('option');
      defaultOpt.value = '';
      defaultOpt.textContent = '-- Pilih Kabel dari Surat Bon Ini --';
      cableSelect.appendChild(defaultOpt);

      cables.forEach(c => {
        const opt = document.createElement('option');
        const cableLabel = c.mat_name + (c.cable_length ? ` (${c.cable_length} Meter)` : '');
        opt.value = cableLabel;
        opt.textContent = `${cableLabel} - Sisa: ${c.qty_remaining} Roll`;
        if (c.qty_remaining <= 0) {
          opt.textContent += ' (Sudah Habis Dipakai)';
          opt.disabled = true;
        }
        cableSelect.appendChild(opt);
      });
    }

    const noneOpt = document.createElement('option');
    noneOpt.value = 'Tanpa Kabel Tambahan (Kabel Eksisting / Lama)';
    noneOpt.textContent = 'Tanpa Kabel Tambahan (Kabel Eksisting / Lama)';
    if (cables.length === 0) {
      noneOpt.selected = true;
    }
    cableSelect.appendChild(noneOpt);
  }

  modal.classList.add('show');
  document.body.style.overflow = 'hidden';

  setTimeout(() => {
    if (custNameInput) custNameInput.focus();
  }, 100);
};

window.closeInstallSNModal = function() {
  const modal = document.getElementById('installSNModal');
  if (modal) {
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';
  }
};
</script>

<!-- Core JavaScript -->
<script src="assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>
