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
            $newMacsRaw = trim($_POST['new_macs'] ?? '');

            if ($materialId <= 0) {
                throw new Exception("Pilih tipe material yang valid.");
            }

            // Fetch material
            $stMat = $pdo->prepare("SELECT * FROM materials WHERE id = ?");
            $stMat->execute([$materialId]);
            $mat = $stMat->fetch();

            if (!$mat) {
                throw new Exception("Material tidak ditemukan.");
            }

            // Process Serial Numbers & MAC Address if ONT
            $pairs = [];
            if ($mat['is_serialized'] == 1 && !empty($newSerialsRaw)) {
                $serialLines = array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', $newSerialsRaw)), function($l) { return $l !== ''; }));
                $macLines = array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', $newMacsRaw)), function($l) { return $l !== ''; }));

                if (!empty($macLines)) {
                    // Berpasangan baris per baris: kiri = SN, kanan = MAC
                    foreach ($serialLines as $idx => $sn) {
                        $mac = $macLines[$idx] ?? null;
                        $pairs[] = ['sn' => $sn, 'mac' => $mac];
                    }
                } else {
                    // Cek jika ada spasi/tab/koma di dalam satu baris (misal: "ZTEGDEAF144B D8855EE71889")
                    $hasMultiToken = false;
                    foreach ($serialLines as $l) {
                        if (preg_match('/[\s,;\t]+/', $l)) {
                            $hasMultiToken = true;
                            break;
                        }
                    }

                    if ($hasMultiToken) {
                        foreach ($serialLines as $l) {
                            $parts = preg_split('/[\s,;\t]+/', $l, 2);
                            $pairs[] = [
                                'sn' => $parts[0] ?? '',
                                'mac' => $parts[1] ?? null
                            ];
                        }
                    } elseif (count($serialLines) >= 2 && count($serialLines) % 2 === 0) {
                        // Cek jika format selang-seling (baris 1 = SN, baris 2 = MAC, dst)
                        $isAlternating = true;
                        for ($i = 1; $i < count($serialLines); $i += 2) {
                            $cleanMac = str_replace([':', '-', '.'], '', $serialLines[$i]);
                            if (!ctype_xdigit($cleanMac) || strlen($cleanMac) < 10) {
                                $isAlternating = false;
                                break;
                            }
                        }
                        if ($isAlternating) {
                            for ($i = 0; $i < count($serialLines); $i += 2) {
                                $pairs[] = [
                                    'sn' => $serialLines[$i],
                                    'mac' => $serialLines[$i + 1]
                                ];
                            }
                        } else {
                            foreach ($serialLines as $sn) {
                                $pairs[] = ['sn' => $sn, 'mac' => null];
                            }
                        }
                    } else {
                        foreach ($serialLines as $sn) {
                            $pairs[] = ['sn' => $sn, 'mac' => null];
                        }
                    }
                }

                // Sesuaikan kuantiti dengan jumlah pasti SN yang diinput
                $qty = count($pairs);
            } elseif ($mat['is_serialized'] == 1 && empty($newSerialsRaw)) {
                throw new Exception("Untuk material ONT, wajib memasukkan daftar Serial Number (SN) unit yang diterima agar stok gudang tetap sinkron.");
            }

            if ($qty <= 0) {
                throw new Exception("Masukkan jumlah kuantiti atau input serial number yang valid.");
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

            // 3. Insert Serial Numbers & MAC Address if ONT
            if (!empty($pairs)) {
                $ignoreClause = ($active_driver === 'mysql') ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
                $stmtSN = $pdo->prepare("{$ignoreClause} INTO material_serials (material_id, serial_number, mac_address, status, received_date) VALUES (?, ?, ?, 'available', CURRENT_TIMESTAMP)");

                foreach ($pairs as $p) {
                    $sn = strtoupper(trim($p['sn']));
                    $mac = !empty($p['mac']) ? strtoupper(trim($p['mac'])) : null;
                    if (!empty($sn)) {
                        $stmtSN->execute([$materialId, $sn, $mac]);
                    }
                }
            }

            // Sinkronisasi stok ONT dengan jumlah SN available riil
            syncSerializedMaterialStock($pdo);
            if ($mat['is_serialized'] == 1) {
                $stRecount = $pdo->prepare("SELECT stock_current FROM materials WHERE id = ?");
                $stRecount->execute([$materialId]);
                $stockAfter = (int)$stRecount->fetchColumn();
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
                $stmtUp = $pdo->prepare("UPDATE material_serials SET status = 'available', bon_id = NULL, customer_name = NULL, customer_id = NULL, customer_address = NULL, cable_used = NULL, installed_notes = 'Unit baru pengganti retur garansi', installed_at = NULL WHERE id = ?");
                $stmtUp->execute([$serialId]);

                // Tambahkan stok material kembali & sinkronisasi dengan jumlah SN available
                $stmtMat = $pdo->prepare("UPDATE materials SET stock_current = stock_current + 1 WHERE id = ?");
                $stmtMat->execute([$snData['mat_id']]);
                syncSerializedMaterialStock($pdo);

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

    // ---------------------------------------------------------
    // 3. EDIT SERIAL NUMBER, MAC ADDRESS & TIPE ONT (Koreksi Input)
    // ---------------------------------------------------------
    if ($action === 'edit_sn') {
        try {
            $serialId = (int)($_POST['serial_id'] ?? 0);
            $materialId = (int)($_POST['material_id'] ?? 0);
            $newSn = strtoupper(trim($_POST['serial_number'] ?? ''));
            $newMac = strtoupper(trim($_POST['mac_address'] ?? ''));
            if ($newMac === '') $newMac = null;

            if ($serialId <= 0 || $materialId <= 0 || empty($newSn)) {
                throw new Exception("Serial Number dan Tipe ONT wajib diisi.");
            }

            $stOld = $pdo->prepare("SELECT * FROM material_serials WHERE id = ?");
            $stOld->execute([$serialId]);
            $oldRow = $stOld->fetch();
            if (!$oldRow) {
                throw new Exception("Data Serial Number tidak ditemukan.");
            }

            // Cek duplikasi SN dengan baris lain
            $stDup = $pdo->prepare("SELECT id FROM material_serials WHERE serial_number = ? AND id != ? LIMIT 1");
            $stDup->execute([$newSn, $serialId]);
            if ($stDup->fetch()) {
                throw new Exception("Serial Number '{$newSn}' sudah terdaftar pada unit lain.");
            }

            $pdo->beginTransaction();

            $stmtUp = $pdo->prepare("UPDATE material_serials SET material_id = ?, serial_number = ?, mac_address = ? WHERE id = ?");
            $stmtUp->execute([$materialId, $newSn, $newMac, $serialId]);

            // Jika SN ini sedang terikat ke bon_items dan SN-nya berubah, sinkronkan teks SN di bon_items
            if (!empty($oldRow['bon_id']) && $oldRow['serial_number'] !== $newSn) {
                $stBi = $pdo->prepare("SELECT id, serial_numbers FROM bon_items WHERE bon_id = ?");
                $stBi->execute([$oldRow['bon_id']]);
                foreach ($stBi->fetchAll() as $bi) {
                    if (!empty($bi['serial_numbers']) && stripos($bi['serial_numbers'], $oldRow['serial_number']) !== false) {
                        $updatedSns = str_ireplace($oldRow['serial_number'], $newSn, $bi['serial_numbers']);
                        $pdo->prepare("UPDATE bon_items SET serial_numbers = ? WHERE id = ?")->execute([$updatedSns, $bi['id']]);
                    }
                }
            }

            syncSerializedMaterialStock($pdo);
            $pdo->commit();

            $_SESSION['flash_message'] = [
                'type' => 'success',
                'title' => 'Data ONT Berhasil Diperbarui!',
                'text' => "Serial Number '{$newSn}'" . ($newMac ? " (MAC: {$newMac})" : "") . " berhasil disimpan."
            ];
            header("Location: ../stok.php?tab=ont");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'title' => 'Gagal Mengubah Data ONT',
                'text' => $e->getMessage()
            ];
            header("Location: ../stok.php?tab=ont");
            exit;
        }
    }

    // ---------------------------------------------------------
    // 4. HAPUS SERIAL NUMBER ONT DARI STOK GUDANG (Jika Salah Input)
    // ---------------------------------------------------------
    if ($action === 'delete_sn') {
        try {
            $serialId = (int)($_POST['serial_id'] ?? 0);
            if ($serialId <= 0) {
                throw new Exception("ID Serial Number tidak valid.");
            }

            $stOld = $pdo->prepare("SELECT ms.*, m.name as mat_name, m.stock_current FROM material_serials ms JOIN materials m ON ms.material_id = m.id WHERE ms.id = ?");
            $stOld->execute([$serialId]);
            $oldRow = $stOld->fetch();
            if (!$oldRow) {
                throw new Exception("Data Serial Number tidak ditemukan.");
            }

            if ($oldRow['status'] !== 'available') {
                throw new Exception("Hanya unit ONT berstatus 'Stok Gudang' yang dapat dihapus langsung. Jika unit sedang dibawa teknisi, kembalikan ke gudang terlebih dahulu dari menu Surat Bon.");
            }

            $pdo->beginTransaction();

            $stockBefore = (int)$oldRow['stock_current'];
            $pdo->prepare("DELETE FROM material_serials WHERE id = ?")->execute([$serialId]);

            syncSerializedMaterialStock($pdo);

            $stRecount = $pdo->prepare("SELECT stock_current FROM materials WHERE id = ?");
            $stRecount->execute([$oldRow['material_id']]);
            $stockAfter = (int)$stRecount->fetchColumn();

            $pdo->prepare("
                INSERT INTO stock_mutations (material_id, mutation_type, quantity, stock_before, stock_after, reference_type, reference_id, user_id, notes, created_at)
                VALUES (?, 'adjustment', 1, ?, ?, 'delete_sn', ?, ?, ?, CURRENT_TIMESTAMP)
            ")->execute([
                $oldRow['material_id'],
                $stockBefore,
                $stockAfter,
                'DEL-SN-' . $serialId,
                $currentUser['id'],
                "Penghapusan/koreksi Serial Number ONT: {$oldRow['serial_number']}"
            ]);

            $pdo->commit();

            $_SESSION['flash_message'] = [
                'type' => 'success',
                'title' => 'Serial Number Berhasil Dihapus!',
                'text' => "SN: {$oldRow['serial_number']} ({$oldRow['mat_name']}) telah dihapus dari Stok Gudang."
            ];
            header("Location: ../stok.php?tab=ont");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'title' => 'Gagal Menghapus Serial Number',
                'text' => $e->getMessage()
            ];
            header("Location: ../stok.php?tab=ont");
            exit;
        }
    }
}

header("Location: ../stok.php");
exit;
