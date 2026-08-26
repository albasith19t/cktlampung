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
}

header("Location: ../stok.php");
exit;
