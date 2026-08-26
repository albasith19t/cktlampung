<?php
/**
 * API: Bon Action Handler
 * Admin Gudang melayani dan menginput pengeluaran bon material teknisi secara langsung
 */

require_once __DIR__ . '/../config/database.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$currentUser = getCurrentUser($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'title' => 'Permintaan Tidak Valid (CSRF)',
            'text' => 'Token sesi formulir telah kedaluwarsa. Silakan refresh halaman bon dan coba kembali.'
        ];
        header("Location: ../bon.php");
        exit;
    }
    
    // ---------------------------------------------------------
    // 1. CREATE & ISSUE BON DIRECTLY BY ADMIN GUDANG
    // ---------------------------------------------------------
    if ($action === 'create' || $action === 'issue_direct') {
        try {
            $isAdmin = in_array($currentUser['role'] ?? '', ['admin_gudang', 'admin']);
            if (!$isAdmin) {
                throw new Exception("Hanya Admin Gudang yang berhak menerbitkan Surat Bon material.");
            }

            $pdo->beginTransaction();

            $technicianId = (int)($_POST['technician_id'] ?? 0);
            if ($technicianId <= 0) {
                throw new Exception("Pilih nama teknisi yang mengambil material.");
            }

            $requestType = trim($_POST['request_type'] ?? '') ?: 'Penarikan Material';
            $customerId = trim($_POST['customer_id'] ?? '');
            $customerName = trim($_POST['customer_name'] ?? '');
            $customerAddress = trim($_POST['customer_address'] ?? '');
            $workOrderNumber = trim($_POST['work_order_number'] ?? '');
            $areaZone = trim($_POST['area_zone'] ?? '') ?: 'Bandar Lampung';
            $adminNotes = trim($_POST['admin_notes'] ?? $_POST['notes'] ?? 'Material diserahkan ke teknisi di loket gudang');

            $materialIds = $_POST['material_id'] ?? [];
            $quantities = $_POST['quantity'] ?? [];
            $itemSerials = $_POST['item_serial'] ?? [];
            $itemNotes = $_POST['item_notes'] ?? [];

            if (empty($materialIds)) {
                throw new Exception("Paling sedikit harus memilih 1 material yang diambil oleh teknisi.");
            }

            // Cek apakah teknisi sudah memiliki Surat Bon Aktif (status = approved)
            $stmtActive = $pdo->prepare("
                SELECT * FROM bon_requests 
                WHERE user_id = ? AND status = 'approved' 
                ORDER BY id DESC LIMIT 1
            ");
            $stmtActive->execute([$technicianId]);
            $existingBon = $stmtActive->fetch();

            $isMerged = false;

            if ($existingBon) {
                $bonId = (int)$existingBon['id'];
                $bonNumber = $existingBon['bon_number'];
                $isMerged = true;

                // Update data pelanggan / work order jika sebelumnya kosong dan kini diisi
                if (!empty($customerName) && empty($existingBon['customer_name'])) {
                    $stmtUpBon = $pdo->prepare("
                        UPDATE bon_requests 
                        SET customer_name = ?,
                            customer_id = COALESCE(NULLIF(?, ''), customer_id),
                            customer_address = COALESCE(NULLIF(?, ''), customer_address),
                            work_order_number = COALESCE(NULLIF(?, ''), work_order_number)
                        WHERE id = ?
                    ");
                    $stmtUpBon->execute([$customerName, $customerId, $customerAddress, $workOrderNumber, $bonId]);
                }
            } else {
                // Generate unique Bon Number baru: BON-CKT-YYYYMM-XXXX
                $prefix = 'BON-CKT-' . date('Ym') . '-';
                $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM bon_requests WHERE bon_number LIKE ?");
                $stmtCount->execute([$prefix . '%']);
                $nextSeq = (int)$stmtCount->fetchColumn() + 1;
                $bonNumber = $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

                $stmtBon = $pdo->prepare("
                    INSERT INTO bon_requests 
                    (bon_number, user_id, approved_by, request_type, customer_id, customer_name, customer_address, work_order_number, area_zone, status, notes, admin_notes, created_at, approved_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                $stmtBon->execute([
                    $bonNumber,
                    $technicianId,
                    $currentUser['id'],
                    $requestType,
                    $customerId,
                    $customerName,
                    $customerAddress,
                    $workOrderNumber,
                    $areaZone,
                    $adminNotes,
                    $adminNotes
                ]);
                $bonId = (int)$pdo->lastInsertId();
            }

            // Prepare statements for items, stock deduction, mutations, and serial updates
            $stmtInsertItem = $pdo->prepare("
                INSERT INTO bon_items (bon_id, material_id, quantity_requested, quantity_approved, serial_numbers, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtUpdateItem = $pdo->prepare("
                UPDATE bon_items 
                SET quantity_requested = ?,
                    quantity_approved = ?,
                    serial_numbers = ?,
                    notes = ?
                WHERE id = ?
            ");
            $stmtCheckItem = $pdo->prepare("SELECT * FROM bon_items WHERE bon_id = ? AND material_id = ? LIMIT 1");
            $stmtUpdateStock = $pdo->prepare("UPDATE materials SET stock_current = stock_current - ? WHERE id = ?");
            $stmtInsertMutation = $pdo->prepare("
                INSERT INTO stock_mutations (material_id, mutation_type, quantity, stock_before, stock_after, reference_type, reference_id, user_id, notes, created_at)
                VALUES (?, 'out_bon', ?, ?, ?, 'bon_request', ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmtUpdateSerial = $pdo->prepare("UPDATE material_serials SET status = 'allocated', bon_id = ? WHERE serial_number = ?");

            for ($i = 0; $i < count($materialIds); $i++) {
                $matId = (int)$materialIds[$i];
                $qty = (int)$quantities[$i];
                $snAssigned = isset($itemSerials[$i]) ? trim($itemSerials[$i]) : null;
                $iNote = $itemNotes[$i] ?? '';

                if ($matId > 0 && $qty > 0) {
                    // Check stock
                    $stMat = $pdo->prepare("SELECT * FROM materials WHERE id = ?");
                    $stMat->execute([$matId]);
                    $mat = $stMat->fetch();

                    if (!$mat) {
                        throw new Exception("Material ID {$matId} tidak ditemukan.");
                    }

                    if ($qty > $mat['stock_current']) {
                        throw new Exception("Stok tidak mencukupi untuk '{$mat['name']}'. Sisa stok di rak: {$mat['stock_current']} {$mat['unit']}, diminta: {$qty}");
                    }

                    $stockBefore = (int)$mat['stock_current'];
                    $stockAfter = $stockBefore - $qty;

                    // 1. Cek apakah barang sudah ada di bon_items yang sama
                    $stmtCheckItem->execute([$bonId, $matId]);
                    $existingItem = $stmtCheckItem->fetch();

                    if ($existingItem) {
                        // Gabungkan kuantitas dan serial numbers
                        $newQtyReq = (int)$existingItem['quantity_requested'] + $qty;
                        $newQtyApp = (int)$existingItem['quantity_approved'] + $qty;

                        $combinedSns = $existingItem['serial_numbers'];
                        if (!empty($snAssigned)) {
                            $combinedSns = !empty($combinedSns) ? ($combinedSns . ', ' . $snAssigned) : $snAssigned;
                        }

                        $combinedNotes = $existingItem['notes'];
                        if (!empty($iNote)) {
                            $combinedNotes = !empty($combinedNotes) ? ($combinedNotes . ' | ' . $iNote) : $iNote;
                        }

                        $stmtUpdateItem->execute([$newQtyReq, $newQtyApp, $combinedSns, $combinedNotes, $existingItem['id']]);
                    } else {
                        // Insert row item baru
                        $stmtInsertItem->execute([
                            $bonId,
                            $matId,
                            $qty,
                            $qty,
                            $snAssigned,
                            $iNote
                        ]);
                    }

                    // 2. Potong stock_current di tabel materials
                    $stmtUpdateStock->execute([$qty, $matId]);

                    // 3. Catat Riwayat Mutasi Stok
                    $stmtInsertMutation->execute([
                        $matId,
                        $qty,
                        $stockBefore,
                        $stockAfter,
                        $bonNumber,
                        $technicianId,
                        $isMerged ? "Penambahan ke Bon {$bonNumber}" : "Pengeluaran Bon {$bonNumber}"
                    ]);

                    // 4. Jika ada Serial Number ONT yang dialokasikan, update statusnya
                    if (!empty($snAssigned)) {
                        $snList = preg_split('/[\r\n,]+/', $snAssigned);
                        foreach ($snList as $singleSn) {
                            $singleSn = trim($singleSn);
                            if (!empty($singleSn)) {
                                $stmtUpdateSerial->execute([$bonId, $singleSn]);
                            }
                        }
                    }
                }
            }

            $pdo->commit();

            if ($isMerged) {
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'title' => 'Material Berhasil Digabungkan!',
                    'text' => "Material berhasil ditambahkan ke Surat Bon Aktif ({$bonNumber}) milik teknisi."
                ];
            } else {
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'title' => 'Bon Material Berhasil Diterbitkan!',
                    'text' => "Surat Bon {$bonNumber} berhasil dicatat & stok rak gudang telah dipotong."
                ];
            }

            header("Location: ../bon.php?id=" . $bonId);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'title' => 'Gagal Menerbitkan Bon',
                'text' => $e->getMessage()
            ];
            header("Location: ../bon.php");
            exit;
        }
    }

    // ---------------------------------------------------------
    // 2. COMPLETE BON REQUEST (Konfirmasi Tugas Lapangan Selesai)
    // ---------------------------------------------------------
    if ($action === 'complete') {
        try {
            $bonId = (int)($_POST['bon_id'] ?? 0);
            if ($bonId <= 0) {
                throw new Exception("ID Bon tidak valid.");
            }

            $stCheck = $pdo->prepare("SELECT * FROM bon_requests WHERE id = ?");
            $stCheck->execute([$bonId]);
            $bon = $stCheck->fetch();

            if (!$bon) {
                throw new Exception("Data surat bon tidak ditemukan.");
            }

            $isAdmin = in_array($currentUser['role'] ?? '', ['admin_gudang', 'admin']);
            if (!$isAdmin && $bon['user_id'] != $currentUser['id']) {
                throw new Exception("Anda hanya dapat mengonfirmasi tugas selesai untuk bon milik Anda sendiri.");
            }

            $customerName = trim($_POST['customer_name'] ?? '');
            $customerAddress = trim($_POST['customer_address'] ?? '');
            $areaZone = trim($_POST['area_zone'] ?? '');
            if (empty($areaZone)) {
                $areaZone = $bon['area_zone'] ?: 'Bandar Lampung';
            }
            $customerId = preg_replace('/[^0-9]/', '', trim($_POST['customer_id'] ?? ($bon['customer_id'] ?? '')));
            $notes = trim($_POST['notes'] ?? '');
            if (empty($notes)) {
                $notes = 'Pemasangan material di lokasi pelanggan selesai dikerjakan oleh teknisi.';
            }

            if (empty($customerName)) {
                throw new Exception("Nama Pelanggan / Tempat Pasang wajib diisi.");
            }

            if (empty($customerAddress)) {
                throw new Exception("Alamat lengkap pemasangan wajib diisi.");
            }

            $stmt = $pdo->prepare("
                UPDATE bon_requests 
                SET status = 'completed', 
                    customer_name = ?, 
                    customer_address = ?, 
                    area_zone = ?, 
                    customer_id = ?, 
                    notes = ?, 
                    completed_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$customerName, $customerAddress, $areaZone, $customerId, $notes, $bonId]);

            $_SESSION['flash_message'] = [
                'type' => 'success',
                'title' => 'Tugas Lapangan Selesai!',
                'text' => "Surat Bon {$bon['bon_number']} telah berhasil dikonfirmasi selesai."
            ];
            header("Location: ../bon.php?id=" . $bonId);
            exit;

        } catch (Exception $e) {
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'title' => 'Gagal Mengonfirmasi Tugas',
                'text' => $e->getMessage()
            ];
            header("Location: ../bon.php" . ($bonId > 0 ? "?id=" . $bonId : ""));
            exit;
        }
    }

    // ---------------------------------------------------------
    // 3. INSTALL INDIVIDUAL SERIAL NUMBER ONT (Teknisi Pasang Per SN)
    // ---------------------------------------------------------
    if ($action === 'install_sn') {
        try {
            $bonId = (int)($_POST['bon_id'] ?? 0);
            $materialId = (int)($_POST['material_id'] ?? 0);
            $serialNumber = trim($_POST['serial_number'] ?? '');
            $origSerialNumber = trim($_POST['orig_serial_number'] ?? $serialNumber);
            $customerName = trim($_POST['customer_name'] ?? '');
            $customerId = preg_replace('/[^0-9]/', '', trim($_POST['customer_id'] ?? ''));
            $customerAddress = trim($_POST['customer_address'] ?? '');
            $cableUsed = trim($_POST['cable_used'] ?? 'Tanpa Kabel Tambahan');
            $notes = trim($_POST['notes'] ?? '');

            if ($bonId <= 0 || empty($serialNumber)) {
                throw new Exception("Data Surat Bon atau Serial Number tidak valid.");
            }

            $stCheck = $pdo->prepare("SELECT * FROM bon_requests WHERE id = ?");
            $stCheck->execute([$bonId]);
            $bon = $stCheck->fetch();

            if (!$bon) {
                throw new Exception("Surat Bon tidak ditemukan.");
            }

            $isAdmin = in_array($currentUser['role'] ?? '', ['admin_gudang', 'admin']);
            if (!$isAdmin && $bon['user_id'] != $currentUser['id']) {
                throw new Exception("Anda hanya dapat mencatat pemasangan untuk bon milik Anda sendiri.");
            }

            // Fallback material_id jika tidak terkirim dari form
            if ($materialId <= 0) {
                $stMatLook = $pdo->prepare("SELECT material_id FROM bon_items WHERE bon_id = ? AND (serial_numbers LIKE ? OR notes LIKE ?) LIMIT 1");
                $stMatLook->execute([$bonId, '%' . $origSerialNumber . '%', '%' . $origSerialNumber . '%']);
                $foundMatId = (int)$stMatLook->fetchColumn();
                if ($foundMatId > 0) {
                    $materialId = $foundMatId;
                } else {
                    if (stripos($origSerialNumber, 'BSR') !== false) {
                        $stBsr = $pdo->query("SELECT id FROM materials WHERE code = 'MAT-ONT-BSR' LIMIT 1");
                        $materialId = (int)$stBsr->fetchColumn() ?: 1;
                    } elseif (stripos($origSerialNumber, 'KCL') !== false) {
                        $stKcl = $pdo->query("SELECT id FROM materials WHERE code = 'MAT-ONT-KCL' LIMIT 1");
                        $materialId = (int)$stKcl->fetchColumn() ?: 2;
                    } else {
                        $stFirst = $pdo->prepare("SELECT material_id FROM bon_items WHERE bon_id = ? LIMIT 1");
                        $stFirst->execute([$bonId]);
                        $materialId = (int)$stFirst->fetchColumn() ?: 1;
                    }
                }
            }

            $snStatus = trim($_POST['sn_status'] ?? 'installed');
            if (!in_array($snStatus, ['installed', 'bad', 'change'])) {
                $snStatus = 'installed';
            }

            if ($snStatus === 'installed') {
                if (empty($customerName)) {
                    throw new Exception("Nama Pelanggan wajib diisi untuk status Terpasang.");
                }
                if (empty($customerAddress)) {
                    throw new Exception("Alamat lengkap pemasangan wajib diisi untuk status Terpasang.");
                }
            } elseif ($snStatus === 'bad') {
                if (empty($notes)) {
                    throw new Exception("Alasan / Gejala kerusakan unit Bad wajib diisi.");
                }
                if (empty($customerName)) {
                    $customerName = 'Unit Bad Lapangan';
                }
                if (empty($cableUsed)) {
                    $cableUsed = 'Tanpa Kabel Tambahan';
                }
            } elseif ($snStatus === 'change') {
                if (empty($notes)) {
                    throw new Exception("Alasan pergantian unit (Change) wajib diisi.");
                }
                if (empty($customerName)) {
                    throw new Exception("Nama Pelanggan wajib diisi untuk status Change.");
                }
                if (empty($cableUsed)) {
                    $cableUsed = 'Tanpa Kabel Tambahan';
                }
            }

            // Check if serial exists in material_serials
            $stmtSnCheck = $pdo->prepare("SELECT * FROM material_serials WHERE serial_number = ? OR (bon_id = ? AND serial_number = ?)");
            $stmtSnCheck->execute([$serialNumber, $bonId, $origSerialNumber]);
            $snRecord = $stmtSnCheck->fetch();

            if ($snRecord) {
                $stmtUpdateSN = $pdo->prepare("
                    UPDATE material_serials 
                    SET serial_number = ?,
                        status = ?,
                        bon_id = ?,
                        customer_name = ?,
                        customer_id = ?,
                        customer_address = ?,
                        cable_used = ?,
                        installed_at = CURRENT_TIMESTAMP,
                        installed_notes = ?
                    WHERE id = ?
                ");
                $stmtUpdateSN->execute([
                    $serialNumber,
                    $snStatus,
                    $bonId,
                    $customerName,
                    $customerId,
                    $customerAddress,
                    $cableUsed,
                    $notes,
                    $snRecord['id']
                ]);
            } else {
                // If serial wasn't in table yet, insert it directly with chosen status
                $stmtInsertSN = $pdo->prepare("
                    INSERT INTO material_serials 
                    (material_id, serial_number, status, bon_id, customer_name, customer_id, customer_address, cable_used, installed_at, installed_notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)
                ");
                $stmtInsertSN->execute([
                    $materialId,
                    $serialNumber,
                    $snStatus,
                    $bonId,
                    $customerName,
                    $customerId,
                    $customerAddress,
                    $cableUsed,
                    $notes
                ]);
            }

            // Update bon_requests customer info summary if customer name is provided
            if (!empty($customerName) && $customerName !== 'Unit Bad Lapangan') {
                $stmtUpdateBonCust = $pdo->prepare("
                    UPDATE bon_requests 
                    SET customer_name = ?,
                        customer_address = COALESCE(NULLIF(?, ''), customer_address),
                        customer_id = COALESCE(NULLIF(?, ''), customer_id)
                    WHERE id = ?
                ");
                $stmtUpdateBonCust->execute([$customerName, $customerAddress, $customerId, $bonId]);
            }

            // Sync Bon completion status across ALL materials (ONTs and Cables)
            syncBonCompletionStatus($pdo, $bonId);

            $statusText = ($snStatus === 'installed') ? 'Terpasang' : (($snStatus === 'bad') ? 'Bad' : 'Change');

            $_SESSION['flash_message'] = [
                'type' => 'success',
                'title' => "Laporan ONT Berhasil ({$statusText})!",
                'text' => "ONT SN: {$serialNumber} berhasil dicatat dengan status {$statusText}."
            ];

            header("Location: ../bon.php?id=" . $bonId);
            exit;

        } catch (Exception $e) {
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'title' => 'Gagal Mencatat Pemasangan',
                'text' => $e->getMessage()
            ];
            header("Location: ../bon.php" . ($bonId > 0 ? "?id=" . $bonId : ""));
            exit;
        }
    }

    // ---------------------------------------------------------
    // 4. CANCEL / VOID BON (Admin Gudang Only)
    // ---------------------------------------------------------
    if ($action === 'cancel') {
        try {
            $isAdmin = in_array($currentUser['role'] ?? '', ['admin_gudang', 'admin']);
            if (!$isAdmin) {
                throw new Exception("Hanya Admin Gudang yang berhak membatalkan Surat Bon.");
            }

            $pdo->beginTransaction();
            $bonId = (int)($_POST['bon_id'] ?? 0);
            $cancelReason = trim($_POST['admin_notes'] ?? 'Dibatalkan oleh Admin Gudang');

            // Fetch bon and items to restore stock
            $stmtBon = $pdo->prepare("SELECT * FROM bon_requests WHERE id = ?");
            $stmtBon->execute([$bonId]);
            $bon = $stmtBon->fetch();

            if ($bon && $bon['status'] !== 'cancelled') {
                $stmtItems = $pdo->prepare("SELECT * FROM bon_items WHERE bon_id = ?");
                $stmtItems->execute([$bonId]);
                $items = $stmtItems->fetchAll();

                $stmtRestore = $pdo->prepare("UPDATE materials SET stock_current = stock_current + ? WHERE id = ?");
                $stmtRestoreSN = $pdo->prepare("UPDATE material_serials SET status = 'available', bon_id = NULL WHERE bon_id = ?");
                $stmtMut = $pdo->prepare("
                    INSERT INTO stock_mutations (material_id, mutation_type, quantity, stock_before, stock_after, reference_type, reference_id, user_id, notes, created_at)
                    VALUES (?, 'return', ?, ?, ?, 'bon_cancel', ?, ?, ?, CURRENT_TIMESTAMP)
                ");

                foreach ($items as $it) {
                    $qty = $it['quantity_approved'] > 0 ? $it['quantity_approved'] : $it['quantity_requested'];
                    if ($qty > 0) {
                        $stMat = $pdo->prepare("SELECT stock_current FROM materials WHERE id = ?");
                        $stMat->execute([$it['material_id']]);
                        $currStock = (int)$stMat->fetchColumn();

                        $stmtRestore->execute([$qty, $it['material_id']]);
                        $stmtMut->execute([
                            $it['material_id'],
                            $qty,
                            $currStock,
                            $currStock + $qty,
                            $bon['bon_number'],
                            $currentUser['id'],
                            "Pembatalan Surat Bon {$bon['bon_number']}: {$cancelReason}"
                        ]);
                    }
                }

                $stmtRestoreSN->execute([$bonId]);

                $stmtUpdateBon = $pdo->prepare("UPDATE bon_requests SET status = 'cancelled', admin_notes = ? WHERE id = ?");
                $stmtUpdateBon->execute([$cancelReason, $bonId]);
            }

            $pdo->commit();
            $_SESSION['flash_message'] = [
                'type' => 'warning',
                'title' => 'Bon Dibatalkan',
                'text' => "Surat Bon {$bon['bon_number']} telah dibatalkan dan stok telah dikembalikan ke rak gudang."
            ];
            header("Location: ../bon.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'title' => 'Gagal Membatalkan Bon',
                'text' => $e->getMessage()
            ];
            header("Location: ../bon.php");
            exit;
        }
    }
}

header("Location: ../bon.php");
exit;
