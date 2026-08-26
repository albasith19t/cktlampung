/**
 * CKT Lampung - Core JavaScript
 * PT Cipta Karya Teknologi
 * Real-time Search, Modals, Dynamic Bon Item Rows
 */

document.addEventListener('DOMContentLoaded', () => {
  initGlobalSearch();
  initModals();
  initDynamicBonRows();
  initUserDropdown();
  initMobileSidebar();
  initQuickBonButtons();
});

// =========================================================================
// 1. GLOBAL SEARCH (Navbar Atas Tengah - Ctrl+K)
// =========================================================================
function initGlobalSearch() {
  const searchInput = document.getElementById('globalSearchInput');
  const searchDropdown = document.getElementById('searchResultsDropdown');
  if (!searchInput || !searchDropdown) return;

  let debounceTimer = null;

  // Shortcut Ctrl + K to focus search
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      searchInput.focus();
      searchInput.select();
    }
  });

  searchInput.addEventListener('input', (e) => {
    const query = e.target.value.trim();
    clearTimeout(debounceTimer);

    if (query.length < 2) {
      searchDropdown.style.display = 'none';
      searchDropdown.innerHTML = '';
      return;
    }

    debounceTimer = setTimeout(() => {
      fetch(`api/search.php?q=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
          renderSearchResults(data, searchDropdown);
        })
        .catch(err => console.error('Search error:', err));
    }, 200);
  });

  // Close search dropdown on click outside
  document.addEventListener('click', (e) => {
    if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
      searchDropdown.style.display = 'none';
    }
  });
}

function renderSearchResults(data, container) {
  if (!data || (!data.materials?.length && !data.bons?.length && !data.serials?.length && !data.technicians?.length)) {
    container.innerHTML = `
      <div style="padding: 14px; text-align: center; color: var(--text-dim); font-size: 0.8rem;">
        <i class="bi bi-search" style="font-size: 1.2rem; display: block; margin-bottom: 4px;"></i>
        Tidak ditemukan material, nomor bon, atau teknisi yang cocok.
      </div>
    `;
    container.style.display = 'block';
    return;
  }

  let html = '';

  // Materials & Cable section
  if (data.materials?.length) {
    html += `<div class="search-result-group-title"><i class="bi bi-boxes me-1"></i> Material & Kabel</div>`;
    data.materials.forEach(m => {
      const stockBadge = m.stock_current <= m.stock_min 
        ? `<span class="badge" style="background: var(--danger-bg); color: var(--danger); font-size: 0.7rem;">Sisa: ${m.stock_current} ${m.unit} (Kritis)</span>`
        : `<span class="badge" style="background: var(--success-bg); color: var(--success); font-size: 0.7rem;">Sisa: ${m.stock_current} ${m.unit}</span>`;

      html += `
        <a href="stok.php?highlight=${m.id}" class="search-result-item">
          <div class="search-item-left">
            <i class="bi bi-box-seam" style="color: var(--primary); font-size: 1.1rem;"></i>
            <div>
              <div class="search-item-title">${escapeHtml(m.name)}</div>
              <div class="search-item-sub">${escapeHtml(m.code)} &bull; ${escapeHtml(m.brand || m.unit)}</div>
            </div>
          </div>
          <div>${stockBadge}</div>
        </a>
      `;
    });
  }

  // Bon Requests section
  if (data.bons?.length) {
    html += `<div class="search-result-group-title"><i class="bi bi-file-earmark-text me-1"></i> Surat Bon Material</div>`;
    data.bons.forEach(b => {
      html += `
        <a href="bon.php?id=${b.id}" class="search-result-item">
          <div class="search-item-left">
            <i class="bi bi-receipt" style="color: #c084fc; font-size: 1.1rem;"></i>
            <div>
              <div class="search-item-title">${escapeHtml(b.bon_number)} - ${escapeHtml(b.customer_name || 'Pelanggan')}</div>
              <div class="search-item-sub">Teknisi: ${escapeHtml(b.technician_name)}</div>
            </div>
          </div>
          <span class="badge" style="background: rgba(14, 165, 233, 0.15); color: #38bdf8; font-size: 0.7rem;">${escapeHtml(b.status)}</span>
        </a>
      `;
    });
  }

  // Serial Numbers section (ONT)
  if (data.serials?.length) {
    html += `<div class="search-result-group-title"><i class="bi bi-upc-scan me-1"></i> Serial Number ONT</div>`;
    data.serials.forEach(s => {
      html += `
        <a href="stok.php?sn=${encodeURIComponent(s.serial_number)}" class="search-result-item">
          <div class="search-item-left">
            <i class="bi bi-router" style="color: var(--ont-color); font-size: 1.1rem;"></i>
            <div>
              <div class="search-item-title font-mono" style="color: #38bdf8;">SN: ${escapeHtml(s.serial_number)}</div>
              <div class="search-item-sub">${escapeHtml(s.material_name)} &bull; Status: ${escapeHtml(s.status)}</div>
            </div>
          </div>
        </a>
      `;
    });
  }

  container.innerHTML = html;
  container.style.display = 'block';
}

// =========================================================================
// 2. MODAL CONTROLS (Input Bon & Restock)
// =========================================================================
function initModals() {
  const bonModal = document.getElementById('bonModalBackdrop');
  const restockModal = document.getElementById('restockModal');
  const completeModal = document.getElementById('completeTaskModal');
  const btnOpenBon = document.getElementById('btnOpenBonModal');
  const btnCloseBon = document.getElementById('btnCloseBonModal');
  const btnCancelBon = document.getElementById('btnCancelBonModal');

  if (btnOpenBon && bonModal) {
    btnOpenBon.addEventListener('click', () => openModalBon());
  }

  if (btnCloseBon && bonModal) {
    btnCloseBon.addEventListener('click', () => closeModalBon());
  }

  if (btnCancelBon && bonModal) {
    btnCancelBon.addEventListener('click', () => closeModalBon());
  }

  // Close modals when clicking outside dialog (backdrop)
  const installModal = document.getElementById('installSNModal');
  window.addEventListener('click', (e) => {
    if (e.target === bonModal) closeModalBon();
    if (e.target === restockModal) closeRestockModal();
    if (e.target === completeModal) closeCompleteTaskModal();
    if (e.target === installModal) closeInstallSNModal();
  });

  // ESC key closes all active modals
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (bonModal?.classList.contains('show')) closeModalBon();
      if (restockModal?.classList.contains('show')) closeRestockModal();
      if (completeModal?.classList.contains('show')) closeCompleteTaskModal();
      if (installModal?.classList.contains('show')) closeInstallSNModal();
    }
  });

  // Barcode / QR Scanner Protection:
  // Scanner guns type characters quickly and send an automatic 'Enter' key at the end.
  // Prevent Enter key in modal input fields from accidentally auto-submitting forms.
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      const activeEl = document.activeElement;
      if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'SELECT')) {
        if (activeEl.closest('.modal-backdrop') || activeEl.classList.contains('bon-serial-input')) {
          e.preventDefault(); // Stop form submission
          
          // If scanned in bon serial input, give visual green check feedback
          if (activeEl.classList.contains('bon-serial-input')) {
            activeEl.style.borderColor = '#10b981';
            activeEl.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.25)';
            setTimeout(() => {
              activeEl.style.borderColor = '';
              activeEl.style.boxShadow = '';
            }, 1000);
          }
        }
      }
    }
  });
}

function openModalBon(prefillMaterialId = null) {
  const bonModal = document.getElementById('bonModalBackdrop');
  if (!bonModal) return;
  bonModal.classList.add('show');
  document.body.style.overflow = 'hidden';

  if (prefillMaterialId) {
    const firstSelect = bonModal.querySelector('.bon-material-select');
    if (firstSelect) {
      firstSelect.value = prefillMaterialId;
      handleMaterialChange(firstSelect);
    }
  }
}

function closeModalBon() {
  const bonModal = document.getElementById('bonModalBackdrop');
  if (!bonModal) return;
  bonModal.classList.remove('show');
  document.body.style.overflow = 'auto';
}

function openRestockModal(materialId = '') {
  const modal = document.getElementById('restockModal');
  if (!modal) return;
  modal.classList.add('show');
  document.body.style.overflow = 'hidden';
  if (materialId) {
    const sel = document.getElementById('restockMaterialSelect');
    if (sel) sel.value = materialId;
  }
}

function closeRestockModal() {
  const modal = document.getElementById('restockModal');
  if (!modal) return;
  modal.classList.remove('show');
  document.body.style.overflow = 'auto';
}

// =========================================================================
// 3. DYNAMIC BON ITEM ROWS & ON-THE-FLY SERIAL NUMBER CHECK
// =========================================================================
function initDynamicBonRows() {
  const btnAddItem = document.getElementById('btnAddBonItem');
  const container = document.getElementById('bonItemsContainer');
  const template = document.getElementById('bonItemRowTemplate');

  if (btnAddItem && container && template) {
    btnAddItem.addEventListener('click', () => {
      const clone = template.content.cloneNode(true);
      container.appendChild(clone);

      // Auto-scroll the modal body down so the new row is in view
      const modalBody = container.closest('.modal-body');
      if (modalBody) {
        setTimeout(() => {
          modalBody.scrollTo({
            top: modalBody.scrollHeight,
            behavior: 'smooth'
          });
        }, 50);
      }
    });
  }
}

function removeBonItemRow(btn) {
  const row = btn.closest('.bon-item-row');
  const container = document.getElementById('bonItemsContainer');
  if (container && container.querySelectorAll('.bon-item-row').length > 1) {
    row.remove();
  } else {
    Swal.fire({
      icon: 'info',
      title: 'Minimal 1 Barang',
      text: 'Setiap pengajuan bon harus memiliki minimal satu material yang diambil.',
      background: '#111827',
      color: '#f8fafc',
      confirmButtonColor: '#0ea5e9'
    });
  }
}

function handleMaterialChange(selectElem) {
  const row = selectElem.closest('.bon-item-row');
  if (!row) return;

  const selectedOpt = selectElem.options[selectElem.selectedIndex];
  const isSerialized = selectedOpt.getAttribute('data-serialized') === '1';
  const stock = selectedOpt.getAttribute('data-stock');
  const unit = selectedOpt.getAttribute('data-unit') || 'Pcs';

  const serialCol = row.querySelector('.serial-col');
  const serialInput = row.querySelector('.bon-serial-input');
  const hintElem = row.querySelector('.stock-avail-hint');

  if (hintElem) {
    if (selectElem.value) {
      hintElem.innerHTML = `Sisa di rak: <strong style="color: #38bdf8;">${stock} ${unit}</strong>`;
    } else {
      hintElem.innerHTML = '';
    }
  }

  if (serialCol && serialInput) {
    if (isSerialized) {
      serialCol.style.opacity = '1';
      serialInput.placeholder = 'Isi SN ONT (Opsional)';
      serialInput.required = false;
    } else {
      serialCol.style.opacity = '0.5';
      serialInput.placeholder = 'Tidak butuh SN';
      serialInput.value = '';
    }
  }
}

// Quick Bon from Stock Cards
function initQuickBonButtons() {
  document.querySelectorAll('.btn-quick-bon').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const matId = btn.getAttribute('data-material-id');
      openModalBon(matId);
    });
  });
}

// =========================================================================
// 4. USER PROFILE DROPDOWN & MOBILE SIDEBAR
// =========================================================================
function initUserDropdown() {
  const trigger = document.getElementById('userProfileTrigger');
  const menu = document.getElementById('userDropdownMenu');
  if (!trigger || !menu) return;

  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
  });

  document.addEventListener('click', (e) => {
    if (!trigger.contains(e.target) && !menu.contains(e.target)) {
      menu.style.display = 'none';
    }
  });
}

function initMobileSidebar() {
  const toggleBtn = document.getElementById('mobileSidebarToggle');
  const sidebar = document.getElementById('mainSidebar');
  if (!toggleBtn || !sidebar) return;

  toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('show-mobile');
  });

  document.addEventListener('click', (e) => {
    if (sidebar.classList.contains('show-mobile') && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
      sidebar.classList.remove('show-mobile');
    }
  });
}

// Helper: Escape HTML
function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.innerText = str;
  return div.innerHTML;
}

/* =========================================================================
   COMPLETE TASK / TUGAS SELESAI MODAL (Teknisi Input Info Pelanggan)
   ========================================================================= */
function openCompleteTaskModal(bonId, bonNumber = '', customerName = '', customerAddress = '', areaZone = '', customerId = '') {
  const modal = document.getElementById('completeTaskModal');
  if (!modal) return;

  const bonIdInput = document.getElementById('completeBonId');
  const bonNumberEl = document.getElementById('completeBonNumber');
  const nameInput = document.getElementById('completeCustomerName');
  const addrInput = document.getElementById('completeCustomerAddress');
  const idInput = document.getElementById('completeCustomerId');

  if (bonIdInput) bonIdInput.value = bonId;
  if (bonNumberEl) bonNumberEl.textContent = bonNumber || `#${bonId}`;
  if (nameInput) nameInput.value = customerName || '';
  if (addrInput) addrInput.value = customerAddress || '';
  if (idInput) idInput.value = customerId || '';

  modal.classList.add('show');
  setTimeout(() => {
    if (nameInput) nameInput.focus();
  }, 100);
}

function closeCompleteTaskModal() {
  const modal = document.getElementById('completeTaskModal');
  if (modal) modal.classList.remove('show');
}

// Open modal to confirm individual ONT SN installation
function openInstallSNModal(bonId, bonNumber, serialNumber, matName = '', materialId = 1) {
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
  if (bonNumDisplay) bonNumDisplay.textContent = bonNumber || `#${bonId}`;
  if (matDisplay) matDisplay.textContent = matName || 'Perangkat ONT Wi-Fi';

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
}

function closeInstallSNModal() {
  const modal = document.getElementById('installSNModal');
  if (modal) {
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';
  }
}

// Event Delegation for .btn-complete-task
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.btn-complete-task');
  if (btn) {
    e.preventDefault();
    const bonId = btn.getAttribute('data-id');
    const bonNumber = btn.getAttribute('data-number');
    const custName = btn.getAttribute('data-name');
    const custAddr = btn.getAttribute('data-address');
    const zone = btn.getAttribute('data-zone');
    const custId = btn.getAttribute('data-customer-id');
    openCompleteTaskModal(bonId, bonNumber, custName, custAddr, zone, custId);
  }
});

