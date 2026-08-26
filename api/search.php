<?php
/**
 * API: Real-time Global Search Endpoint
 * PT Cipta Karya Teknologi (CKT Lampung)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['materials' => [], 'bons' => [], 'serials' => [], 'technicians' => []]);
    exit;
}

$like = '%' . $query . '%';

$currentUser = getCurrentUser($pdo);
$isAdmin = in_array($currentUser['role'] ?? '', ['admin_gudang', 'admin']);
$isTeknisi = (($currentUser['role'] ?? '') === 'teknisi');

try {
    $materials = [];
    $bons = [];
    $serials = [];

    if ($isAdmin) {
        // 1. Search Materials (Admin only)
        $stmtMat = $pdo->prepare("
            SELECT id, code, name, stock_current, unit, brand, cable_length, stock_min 
            FROM materials 
            WHERE name LIKE ? OR code LIKE ? OR specifications LIKE ?
            ORDER BY cable_length DESC, name ASC 
            LIMIT 6
        ");
        $stmtMat->execute([$like, $like, $like]);
        $materials = $stmtMat->fetchAll();

        // 2. Search Bon Requests (All)
        $stmtBon = $pdo->prepare("
            SELECT b.id, b.bon_number, b.customer_name, b.request_type, b.status, u.name as technician_name
            FROM bon_requests b
            JOIN users u ON b.user_id = u.id
            WHERE b.bon_number LIKE ? OR b.customer_name LIKE ? OR b.work_order_number LIKE ? OR u.name LIKE ?
            ORDER BY b.created_at DESC 
            LIMIT 5
        ");
        $stmtBon->execute([$like, $like, $like, $like]);
        $bons = $stmtBon->fetchAll();

        // 3. Search Serial Numbers (Admin only)
        $stmtSer = $pdo->prepare("
            SELECT ms.serial_number, ms.mac_address, ms.status, m.name as material_name
            FROM material_serials ms
            JOIN materials m ON ms.material_id = m.id
            WHERE ms.serial_number LIKE ? OR ms.mac_address LIKE ?
            LIMIT 5
        ");
        $stmtSer->execute([$like, $like]);
        $serials = $stmtSer->fetchAll();
    } else {
        // Teknisi: Hanya cari bon & tugas milik teknisi tersebut
        $stmtBon = $pdo->prepare("
            SELECT b.id, b.bon_number, b.customer_name, b.request_type, b.status, u.name as technician_name
            FROM bon_requests b
            JOIN users u ON b.user_id = u.id
            WHERE b.user_id = ? AND (b.bon_number LIKE ? OR b.customer_name LIKE ? OR b.work_order_number LIKE ? OR b.area_zone LIKE ?)
            ORDER BY b.created_at DESC 
            LIMIT 5
        ");
        $stmtBon->execute([$currentUser['id'], $like, $like, $like, $like]);
        $bons = $stmtBon->fetchAll();
    }

    echo json_encode([
        'materials' => $materials,
        'bons' => $bons,
        'serials' => $serials
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
