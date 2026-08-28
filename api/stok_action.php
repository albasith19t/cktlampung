<?php
/**
 * API: Stok Action Handler (Restock / Barang Datang Supplier)
 * PT Cipta Karya Teknologi (CKT Lampung)
 */

require_once __DIR__ . '/../config/database.php';

$action = $_POST['action'] ?? '';
$currentUser = getCurrentUser($pdo);
$isAdmin = in_array($currentUser['role'] ?? '', ['admin_gudang', 'admin']);

if (!$isAdmin) {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'title' => 'Akses Ditolak',
        'text' => 'Hanya Admin Gudang yang berhak mengelola stok material.'
    ];
    header("Location: ../bon.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'title' => 'Permintaan Tidak Valid (CSRF)',
            'text' => 'Token sesi formulir telah kedaluwarsa. Silakan refresh halaman stok dan coba kembali.'
        ];
        header("Location: ../stok.php");
        exit;
    }
    
    // RESTOCK / STOK MASUK
    if ($action === 'restock') {
        try {
            $pdo->beginTransaction();

            $materialId = (int)($_POST['material_id'] ?? 0);
            $qty = (int)($_POST['quantity'] ?? 0);
            $referenceId = trim($_POST['reference_id'] ?? 'PO-RESTOCK-' . date('Ymd'));
            $notes = trim($_POST['notes'] ?? 'Penerimaan stok baru dari distributor/supplier');
            $newSerialsRaw = trim($_POST['new_serials'] ?? '');

            if ($materialId <= 0 || $qty <= 0) {
                throw new Exception("Pilih material dan masukkan jumlah kuantiti yang valid.");
            }

            // Fetch material
            $stMat = $pdo->prepare("SELECT * FROM materials WHERE id = ?");
            $stMat->execute([$materialId]);
            $mat = $stMat->fetch();

            if (!$mat) {
                throw new Exception("Material tidak ditemukan.");
            }

            $stockBefore = (int)$mat['stock_current'];
            $stockAfter = $stockBefore + $qty;

            // 1. Update Material Stock
            $stmtUp = $pdo->prepare("UPDATE materials SET stock_current = stock_current + ? WHERE id = ?");
            $stmtUp->execute([$qty, $materialId]);

            // 2. Insert Mutation Log
            $stmtMut = $pdo->prepare("
                INSERT INTO stock_mutations (material_id, mutation_type, quantity, stock_before, stock_after, reference_type, reference_id, user_id, notes, created_at)
                VALUES (?, 'in_restock', ?, ?, ?, 'supplier_po', ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmtMut->execute([
                $materialId,
                $qty,
                $stockBefore,
                $stockAfter,
                $referenceId,
                $currentUser['id'],
                $notes
            ]);

            // 3. Insert Serial Numbers if ONT
            if (!empty($newSerialsRaw) && $mat['is_serialized'] == 1) {
                $serials = preg_split('/[\r\n,]+/', $newSerialsRaw);
                $stmtSN = $pdo->prepare("INSERT OR IGNORE INTO material_serials (material_id, serial_number, status, received_date) VALUES (?, ?, 'available', CURRENT_TIMESTAMP)");
                // In MySQL we use INSERT IGNORE
                if ($active_driver === 'mysql') {
                    $stmtSN = $pdo->prepare("INSERT IGNORE INTO material_serials (material_id, serial_number, status, received_date) VALUES (?, ?, 'available', CURRENT_TIMESTAMP)");
                }
                foreach ($serials as $sn) {
                    $sn = trim($sn);
                    if (!empty($sn)) {
                        $stmtSN->execute([$materialId, $sn]);
                    }
                }
            }

            $pdo->commit();

            $_SESSION['flash_message'] = [
                'type' => 'success',
                'title' => 'Stok Berhasil Ditambahkan!',
                'text' => "Berhasil menambah {$qty} {$mat['unit']} untuk '{$mat['name']}'. Stok saat ini: {$stockAfter} {$mat['unit']}."
            ];

            header("Location: ../stok.php?highlight=" . $materialId);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'title' => 'Gagal Menambah Stok',
                'text' => $e->getMessage()
            ];
            header("Location: ../stok.php");
            exit;
        }
    }

    // UPDATE STATUS UNIT BAD / RETUR VENDOR (RMA)
    if ($action === 'update_bad_status') {
        try {
            $serialId = (int)($_POST['serial_id'] ?? 0);
            $badAction = trim($_POST['bad_action'] ?? '');
            $notes = trim($_POST['rma_notes'] ?? '');

            if ($serialId <= 0) {
                throw new Exception("Unit serial number tidak valid.");
            }

            $stSn = $pdo->prepare("SELECT ms.*, m.name as mat_name, m.id as mat_id FROM material_serials ms JOIN materials m ON ms.material_id = m.id WHERE ms.id = ?");
            $stSn->execute([$serialId]);
            $snData = $stSn->fetch();

            if (!$snData) {
                throw new Exception("Data serial number tidak ditemukan.");
            }

            if ($badAction === 'terima_gudang') {
                $newNote = "[Diterima Fisik di Gudang pada " . date('d/m/Y H:i') . "] " . $snData['installed_notes'];
                $stmtUp = $pdo->prepare("UPDATE material_serials SET installed_notes = ? WHERE id = ?");
                $stmtUp->execute([$newNote, $serialId]);

                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'title' => 'Unit Rusak Diterima di Gudang',
                    'text' => "SN: {$snData['serial_number']} telah dicatat resmi diterima kembali di loket gudang."
                ];
            } elseif ($badAction === 'retur_vendor') {
                $rmaInfo = !empty($notes) ? "No. RMA/Resi: {$notes}" : "Klaim Garansi Vendor";
                $newNote = "[Retur ke Vendor: {$rmaInfo} pada " . date('d/m/Y H:i') . "] " . $snData['installed_notes'];
                $stmtUp = $pdo->prepare("UPDATE material_serials SET installed_notes = ? WHERE id = ?");
                $stmtUp->execute([$newNote, $serialId]);

                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'title' => 'Unit Dikirim Retur ke Vendor',
                    'text' => "SN: {$snData['serial_number']} berhasil diperbarui statusnya sedang dalam proses retur/klaim garansi vendor."
                ];
            } elseif ($badAction === 'ganti_unit_selesai') {
                $pdo->beginTransaction();
                // Unit ganti baru / perbaikan selesai: kembalikan ke stok gudang
                $stmtUp = $pdo->prepare("UPDATE material_serials SET status = 'in_stock', bon_id = NULL, customer_name = NULL, customer_id = NULL, customer_address = NULL, cable_used = NULL, installed_notes = 'Unit baru pengganti retur garansi', installed_at = NULL WHERE id = ?");
                $stmtUp->execute([$serialId]);

                // Tambahkan stok material kembali
                $stmtMat = $pdo->prepare("UPDATE materials SET stock_current = stock_current + 1 WHERE id = ?");
                $stmtMat->execute([$snData['mat_id']]);

                // Mutation log
                $stmtMut = $pdo->prepare("
                    INSERT INTO stock_mutations (material_id, mutation_type, quantity, stock_before, stock_after, reference_type, reference_id, user_id, notes, created_at)
                    VALUES (?, 'in_restock', 1, 0, 0, 'rma_replacement', ?, ?, 'Penggantian unit baru dari retur vendor (SN: {$snData['serial_number']})', CURRENT_TIMESTAMP)
                ");
                $stmtMut->execute([$snData['mat_id'], 'RMA-OK-' . $serialId, $currentUser['id']]);

                $pdo->commit();

                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'title' => 'Unit Selesai & Kembali ke Stok Gudang!',
                    'text' => "SN: {$snData['serial_number']} telah pulih/diganti baru dan siap digunakan kembali untuk bon teknisi."
                ];
            }

            header("Location: ../stok.php?tab=ont");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'title' => 'Gagal Memproses Unit',
                'text' => $e->getMessage()
            ];
            header("Location: ../stok.php?tab=ont");
            exit;
        }
    }
}

header("Location: ../stok.php");
exit;
