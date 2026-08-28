<?php
/**
 * API: 1-Click Database Backup (.sql)
 * PT Cipta Karya Teknologi (CKT Lampung)
 * Hanya dapat diakses oleh Admin / Admin Gudang
 */

require_once __DIR__ . '/../config/database.php';

$currentUser = getCurrentUser($pdo);
$isAdmin = in_array($currentUser['role'] ?? '', ['admin_gudang', 'admin']);

if (!$isAdmin) {
    http_response_code(403);
    die("Akses ditolak. Fitur backup database hanya untuk Admin Gudang.");
}

$dbType = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$timestamp = date('Ymd_His');
$filename = "cktlampung_backup_" . $timestamp . ".sql";

// Set Headers for Download
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = "";
$output .= "-- ========================================================\n";
$output .= "-- CKT LAMPUNG - SISTEM GUDANG & LOGISTIK FTTH\n";
$output .= "-- DATABASE BACKUP DUMP FILE\n";
$output .= "-- Tanggal Backup : " . date('d F Y, H:i:s') . "\n";
$output .= "-- Diekspor Oleh   : " . ($currentUser['name'] ?? 'Admin') . " (" . ($currentUser['role'] ?? '') . ")\n";
$output .= "-- Driver Database : " . strtoupper($dbType) . "\n";
$output .= "-- ========================================================\n\n";

if ($dbType === 'sqlite') {
    $output .= "PRAGMA foreign_keys = OFF;\n\n";
} else {
    $output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
}

// List of all tables in system
$tables = [
    'users',
    'categories',
    'materials',
    'material_serials',
    'bon_requests',
    'bon_items',
    'stock_mutations',
    'inventory_adjustments'
];

foreach ($tables as $table) {
    try {
        $check = $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
    } catch (Exception $e) {
        continue;
    }

    $output .= "-- --------------------------------------------------------\n";
    $output .= "-- Struktur Tabel: `{$table}`\n";
    $output .= "-- --------------------------------------------------------\n";

    if ($dbType === 'sqlite') {
        $schemaStmt = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?");
        $schemaStmt->execute([$table]);
        $createSql = $schemaStmt->fetchColumn();
        if ($createSql) {
            $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $output .= $createSql . ";\n\n";
        }
    } else {
        $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $createRow = $createStmt->fetch(PDO::FETCH_NUM);
        if ($createRow && isset($createRow[1])) {
            $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $output .= $createRow[1] . ";\n\n";
        }
    }

    // Dump Data
    $dataStmt = $pdo->query("SELECT * FROM `{$table}`");
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($rows)) {
        $output .= "-- Dumping data untuk tabel `{$table}` (" . count($rows) . " baris)\n";
        
        $columns = array_keys($rows[0]);
        $colList = implode('`, `', $columns);

        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $col) {
                $val = $row[$col];
                if ($val === null) {
                    $values[] = "NULL";
                } elseif (is_numeric($val) && !in_array($col, ['phone', 'nik', 'serial_number', 'mac_address', 'bon_number', 'work_order_number'])) {
                    $values[] = $val;
                } else {
                    $values[] = $pdo->quote($val);
                }
            }
            $valList = implode(', ', $values);
            $output .= "INSERT INTO `{$table}` (`{$colList}`) VALUES ({$valList});\n";
        }
        $output .= "\n";
    }
}

if ($dbType === 'sqlite') {
    $output .= "PRAGMA foreign_keys = ON;\n";
} else {
    $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";
}

$output .= "-- ========================================================\n";
$output .= "-- AKHIR DUMP DATABASE\n";
$output .= "-- ========================================================\n";

echo $output;
exit;
