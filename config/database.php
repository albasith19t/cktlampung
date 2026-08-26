<?php
/**
 * CKT Lampung - Sistem Gudang & Bon Material
 * Database Configuration (MySQL XAMPP + Automatic Fallback & Auth Guard)
 * PT Cipta Karya Teknologi
 */

// ---------------------------------------------------------
// HTTP SECURITY HEADERS & SECURE SESSION SETUP
// ---------------------------------------------------------
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * CSRF Protection Helpers
 */
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ---------------------------------------------------------
// KONFIGURASI DATABASE MYSQL (Hosting / cPanel & XAMPP Support)
// ---------------------------------------------------------
$db_host     = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');    // Host MySQL
$db_port     = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');         // Port MySQL
$db_name     = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'cktlampung');   // Nama Database
$db_user     = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root');         // User MySQL
$db_pass     = (getenv('DB_PASS') !== false) ? getenv('DB_PASS') : ($_ENV['DB_PASS'] ?? ''); // Password MySQL

$pdo = null;
$active_driver = 'mysql';

// Coba koneksi ke MySQL terlebih dahulu
try {
    // 1. Koneksi ke server MySQL
    $pdoServer = new PDO("mysql:host={$db_host};port={$db_port};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 2
    ]);

    // Buat database cktlampung jika belum ada
    $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // 2. Koneksi ke database cktlampung
    $pdo = new PDO("mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $active_driver = 'mysql';
    initMySQLDatabase($pdo);

} catch (Exception $e) {
    // Fallback ke SQLite jika MySQL XAMPP sedang tidak aktif
    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0777, true);
    }
    $sqlitePath = $dataDir . '/cktlampung.sqlite';

    try {
        $pdo = new PDO("sqlite:" . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $pdo->exec("PRAGMA foreign_keys = ON;");
        $active_driver = 'sqlite';
        initSQLiteDatabase($pdo);
    } catch (PDOException $sqle) {
        die("Koneksi Database Gagal: " . $sqle->getMessage());
    }
}

// ---------------------------------------------------------
// AUTO-MIGRATE & SEEDER MYSQL
// ---------------------------------------------------------
function initMySQLDatabase($pdo) {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if (!$tableCheck) {
        $sqlFile = __DIR__ . '/../cktlampung.sql';
        if (file_exists($sqlFile)) {
            $sqlContent = file_get_contents($sqlFile);
            $pdo->exec($sqlContent);
        }
    } else {
        // Ensure username and password columns exist
        try {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `username` VARCHAR(50) UNIQUE AFTER `id`");
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `password` VARCHAR(255) AFTER `username`");
        } catch (Exception $e) {}

        // Ensure material_serials installation columns exist
        $serCols = [
            "ALTER TABLE `material_serials` ADD COLUMN `customer_name` VARCHAR(150) DEFAULT NULL",
            "ALTER TABLE `material_serials` ADD COLUMN `customer_id` VARCHAR(100) DEFAULT NULL",
            "ALTER TABLE `material_serials` ADD COLUMN `customer_address` TEXT DEFAULT NULL",
            "ALTER TABLE `material_serials` ADD COLUMN `cable_used` VARCHAR(100) DEFAULT NULL",
            "ALTER TABLE `material_serials` ADD COLUMN `installed_at` DATETIME DEFAULT NULL",
            "ALTER TABLE `material_serials` ADD COLUMN `installed_notes` TEXT DEFAULT NULL",
            "ALTER TABLE `material_serials` MODIFY COLUMN `status` VARCHAR(30) DEFAULT 'available'"
        ];
        foreach ($serCols as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Exception $e) {}
        }

        seedDefaultAccounts($pdo);
        consolidateActiveBonsForTechs($pdo);
    }
}

function consolidateActiveBonsForTechs($pdo) {
    try {
        $techs = $pdo->query("
            SELECT user_id, COUNT(*) as active_count 
            FROM bon_requests 
            WHERE status = 'approved' 
            GROUP BY user_id 
            HAVING active_count > 1
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($techs as $t) {
            $userId = (int)$t['user_id'];
            $activeBons = $pdo->query("
                SELECT id, bon_number 
                FROM bon_requests 
                WHERE user_id = {$userId} AND status = 'approved' 
                ORDER BY id ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            if (count($activeBons) <= 1) continue;

            $masterBonId = (int)$activeBons[0]['id'];

            for ($i = 1; $i < count($activeBons); $i++) {
                $otherBonId = (int)$activeBons[$i]['id'];
                $otherItems = $pdo->query("SELECT * FROM bon_items WHERE bon_id = {$otherBonId}")->fetchAll(PDO::FETCH_ASSOC);

                foreach ($otherItems as $oi) {
                    $matId = (int)$oi['material_id'];
                    $qty = (int)$oi['quantity_approved'];
                    $sns = trim($oi['serial_numbers'] ?? '');
                    $notes = trim($oi['notes'] ?? '');

                    $stMasterItem = $pdo->prepare("SELECT * FROM bon_items WHERE bon_id = ? AND material_id = ?");
                    $stMasterItem->execute([$masterBonId, $matId]);
                    $masterItem = $stMasterItem->fetch(PDO::FETCH_ASSOC);

                    if ($masterItem) {
                        $newQtyReq = (int)$masterItem['quantity_requested'] + (int)$oi['quantity_requested'];
                        $newQtyApp = (int)$masterItem['quantity_approved'] + $qty;
                        
                        $combinedSns = trim($masterItem['serial_numbers'] ?? '');
                        if (!empty($sns)) {
                            $combinedSns = !empty($combinedSns) ? ($combinedSns . ', ' . $sns) : $sns;
                        }

                        $combinedNotes = trim($masterItem['notes'] ?? '');
                        if (!empty($notes)) {
                            $combinedNotes = !empty($combinedNotes) ? ($combinedNotes . ' | ' . $notes) : $notes;
                        }

                        $pdo->prepare("
                            UPDATE bon_items 
                            SET quantity_requested = ?, quantity_approved = ?, serial_numbers = ?, notes = ? 
                            WHERE id = ?
                        ")->execute([$newQtyReq, $newQtyApp, $combinedSns, $combinedNotes, $masterItem['id']]);

                        $pdo->exec("DELETE FROM bon_items WHERE id = {$oi['id']}");
                    } else {
                        $pdo->exec("UPDATE bon_items SET bon_id = {$masterBonId} WHERE id = {$oi['id']}");
                    }
                }

                $pdo->exec("UPDATE material_serials SET bon_id = {$masterBonId} WHERE bon_id = {$otherBonId}");
                $pdo->exec("DELETE FROM bon_requests WHERE id = {$otherBonId}");
            }
        }
    } catch (Exception $e) {}
}

// ---------------------------------------------------------
// AUTO-MIGRATE & SEEDER SQLITE (Fallback)
// ---------------------------------------------------------
function initSQLiteDatabase($pdo) {
    $tableCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
    if (!$tableCheck) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) UNIQUE,
                password VARCHAR(255),
                nik VARCHAR(50) UNIQUE NOT NULL,
                name VARCHAR(100) NOT NULL,
                role VARCHAR(30) NOT NULL,
                department VARCHAR(50) DEFAULT 'Teknis & Jaringan',
                avatar VARCHAR(255),
                status VARCHAR(20) DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(50) NOT NULL,
                code VARCHAR(20) UNIQUE NOT NULL,
                description TEXT
            );
            CREATE TABLE IF NOT EXISTS materials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER,
                code VARCHAR(50) UNIQUE NOT NULL,
                name VARCHAR(150) NOT NULL,
                brand VARCHAR(100),
                model_type VARCHAR(100),
                cable_length INTEGER DEFAULT NULL,
                unit VARCHAR(20) NOT NULL DEFAULT 'Pcs',
                stock_current INTEGER NOT NULL DEFAULT 0,
                stock_min INTEGER NOT NULL DEFAULT 10,
                location_rack VARCHAR(50) DEFAULT 'Rak A-1',
                is_serialized INTEGER DEFAULT 0,
                specifications TEXT,
                image_url VARCHAR(255),
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS material_serials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                material_id INTEGER NOT NULL,
                serial_number VARCHAR(100) UNIQUE NOT NULL,
                mac_address VARCHAR(100),
                status VARCHAR(30) DEFAULT 'available',
                bon_id INTEGER DEFAULT NULL,
                received_date DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS bon_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                bon_number VARCHAR(50) UNIQUE NOT NULL,
                user_id INTEGER NOT NULL,
                approved_by INTEGER DEFAULT NULL,
                request_type VARCHAR(50) NOT NULL,
                customer_id VARCHAR(50),
                customer_name VARCHAR(150),
                customer_address TEXT,
                work_order_number VARCHAR(100),
                area_zone VARCHAR(100) DEFAULT 'Bandar Lampung',
                status VARCHAR(30) NOT NULL DEFAULT 'approved',
                notes TEXT,
                admin_notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                approved_at DATETIME DEFAULT NULL,
                completed_at DATETIME DEFAULT NULL
            );
            CREATE TABLE IF NOT EXISTS bon_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                bon_id INTEGER NOT NULL,
                material_id INTEGER NOT NULL,
                quantity_requested INTEGER NOT NULL,
                quantity_approved INTEGER DEFAULT 0,
                serial_numbers TEXT DEFAULT NULL,
                notes VARCHAR(255)
            );
            CREATE TABLE IF NOT EXISTS stock_mutations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                material_id INTEGER NOT NULL,
                mutation_type VARCHAR(30) NOT NULL,
                quantity INTEGER NOT NULL,
                stock_before INTEGER NOT NULL,
                stock_after INTEGER NOT NULL,
                reference_type VARCHAR(50),
                reference_id VARCHAR(50),
                user_id INTEGER NOT NULL,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Seed SQLite Initial Materials (ONT Besar, ONT Kecil, 4 Cables)
        $pdo->exec("
            INSERT OR IGNORE INTO categories (id, name, code, description) VALUES
            (1, 'ONT & Router Wi-Fi', 'CAT-ONT', 'Optical Network Terminal dan Router FTTH'),
            (2, 'Kabel Drop Core FO', 'CAT-KBL', 'Kabel Fiber Optic Drop Cable');

            INSERT OR IGNORE INTO materials (id, category_id, code, name, brand, model_type, cable_length, unit, stock_current, stock_min, is_serialized) VALUES
            (1, 1, 'MAT-ONT-BSR', 'ONT Besar (Dual Band Gigabit Wi-Fi)', 'ZTE / Huawei Dual Band', 'Dual Band 2.4/5GHz 4GE', NULL, 'Unit', 52, 15, 1),
            (2, 1, 'MAT-ONT-KCL', 'ONT Kecil (Single Band / Mini Wi-Fi)', 'Fiberhome / ZTE Single Band', 'Single Band 2.4GHz 1GE+3FE', NULL, 'Unit', 35, 10, 1),
            (4, 2, 'MAT-KBL-150', 'Kabel Drop Core 1 Core 3 Steel Wire (150 Meter)', 'V-Sol / Netlink', 'Pre-Connectorized SC/UPC', 150, 'Roll', 64, 20, 0),
            (5, 2, 'MAT-KBL-100', 'Kabel Drop Core 1 Core 3 Steel Wire (100 Meter)', 'V-Sol / Netlink', 'Pre-Connectorized SC/UPC', 100, 'Roll', 92, 25, 0),
            (6, 2, 'MAT-KBL-075', 'Kabel Drop Core 1 Core 3 Steel Wire (75 Meter)', 'V-Sol / Netlink', 'Pre-Connectorized SC/UPC', 75, 'Roll', 109, 30, 0),
            (7, 2, 'MAT-KBL-050', 'Kabel Drop Core 1 Core 3 Steel Wire (50 Meter)', 'V-Sol / Netlink', 'Pre-Connectorized SC/UPC', 50, 'Roll', 140, 35, 0);
        ");
    }
    seedDefaultAccounts($pdo);
}

// ---------------------------------------------------------
// SEED 1 ADMIN & 5 TEKNISI
// ---------------------------------------------------------
function seedDefaultAccounts($pdo) {
    // Password hash:
    // admin => admin123
    // teknisi (budi, rian, zaki, dimas, bayu) => 123456 (also support admin123)
    $hashAdmin = '$2y$10$whcawmpgeUj2MYXIbBMT2OIJdxGTFh6.XZuWxkUH9kZitRgzr0/2G'; // admin123
    $hashTeknisi = '$2y$10$SHu/56JpiDFAL9hHied1kulXjeqPqcHk7/T2EicEdgXhMBpYyKQTi'; // 123456

    $defaultUsers = [
        ['admin', $hashAdmin, 'CKT-ADM-001', 'Hendri Saputra', 'admin_gudang', 'Admin Gudang'],
        ['budi', $hashTeknisi, 'CKT-TEK-001', 'Budi Santoso', 'teknisi', 'Teknisi Lapangan'],
        ['rian', $hashTeknisi, 'CKT-TEK-002', 'Rian Pratama', 'teknisi', 'Teknisi Lapangan'],
        ['zaki', $hashTeknisi, 'CKT-TEK-003', 'Ahmad Zaki Fauzan', 'teknisi', 'Teknisi Lapangan'],
        ['dimas', $hashTeknisi, 'CKT-TEK-004', 'Dimas Aditya', 'teknisi', 'Teknisi Lapangan'],
        ['bayu', $hashTeknisi, 'CKT-TEK-005', 'Bayu Nugroho', 'teknisi', 'Teknisi Lapangan']
    ];

    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? OR nik = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO users (username, password, nik, name, role, department) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtUpdate = $pdo->prepare("UPDATE users SET username = ?, password = ?, name = ?, department = ? WHERE nik = ?");

    foreach ($defaultUsers as $u) {
        $stmtCheck->execute([$u[0], $u[2]]);
        $existing = $stmtCheck->fetch();
        if (!$existing) {
            $stmtInsert->execute($u);
        } else {
            $stmtUpdate->execute([$u[0], $u[1], $u[3], $u[5], $u[2]]);
        }
    }
}

// ---------------------------------------------------------
// AUTHENTICATION GUARD (Wajib Login untuk Mengakses Website)
// ---------------------------------------------------------
$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
$isPublicPage = in_array($currentScript, ['login.php']) || php_sapi_name() === 'cli';

if (!$isPublicPage) {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        $isApi = (strpos($_SERVER['PHP_SELF'] ?? '', '/api/') !== false);
        $redirectUrl = $isApi ? '../login.php' : 'login.php';
        header("Location: " . $redirectUrl);
        exit;
    }
}

function getCurrentUser($pdo) {
    $userId = $_SESSION['user_id'] ?? $_SESSION['current_user_id'] ?? 1;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        $user = $pdo->query("SELECT * FROM users LIMIT 1")->fetch();
    }
    return $user;
}

function getAllUsers($pdo) {
    return $pdo->query("SELECT * FROM users ORDER BY role ASC, name ASC")->fetchAll();
}

function getPendingBonCount($pdo) {
    return (int) $pdo->query("SELECT COUNT(*) FROM bon_requests WHERE status = 'pending'")->fetchColumn();
}

function getStockAlertCount($pdo) {
    return (int) $pdo->query("SELECT COUNT(*) FROM materials WHERE stock_current <= stock_min")->fetchColumn();
}

function formatTanggalIndo($datetime) {
    if (!$datetime) return '-';
    $time = strtotime($datetime);
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $tgl = date('d', $time);
    $bln = $bulan[(int)date('m', $time)];
    $thn = date('Y', $time);
    $jam = date('H:i', $time);
    return "$tgl $bln $thn, $jam WIB";
}

function formatTanggalSingkat($datetime) {
    if (!$datetime) return '-';
    return date('d/m/Y H:i', strtotime($datetime));
}

/**
 * Sinkronisasi Status Bon:
 * Jika masih ada ONT yang belum dilaporkan atau kabel yang belum terpakai di lapangan,
 * status Surat Bon otomatis 'approved' (Proses).
 * Jika 100% material telah selesai terpasang / digunakan, status otomatis 'completed' (Selesai).
 */
function syncBonCompletionStatus($pdo, $bonId) {
    $bonId = (int)$bonId;
    if ($bonId <= 0) return;

    $stmtBon = $pdo->prepare("SELECT id, status FROM bon_requests WHERE id = ?");
    $stmtBon->execute([$bonId]);
    $bon = $stmtBon->fetch();

    if (!$bon || $bon['status'] === 'cancelled') return;

    // 1. Ambil seluruh item material dalam surat bon ini
    $stmtItems = $pdo->prepare("
        SELECT bi.material_id, bi.quantity_approved, bi.quantity_requested, 
               m.is_serialized, m.name, m.cable_length 
        FROM bon_items bi 
        JOIN materials m ON bi.material_id = m.id 
        WHERE bi.bon_id = ?
    ");
    $stmtItems->execute([$bonId]);
    $items = $stmtItems->fetchAll();

    if (empty($items)) return;

    // 2. Ambil seluruh data serial number ONT yang terikat ke surat bon ini
    $stmtSerials = $pdo->prepare("SELECT * FROM material_serials WHERE bon_id = ?");
    $stmtSerials->execute([$bonId]);
    $serials = $stmtSerials->fetchAll();

    $hasUnfinishedMaterial = false;

    foreach ($items as $it) {
        $qtyTaken = (int)($it['quantity_approved'] > 0 ? $it['quantity_approved'] : $it['quantity_requested']);
        if ($qtyTaken <= 0) continue;

        $isSerialized = ($it['is_serialized'] == 1 || stripos($it['name'], 'ONT') !== false);
        $cableLength = (int)($it['cable_length'] ?? 0);

        if ($isSerialized) {
            $reportedCount = 0;
            foreach ($serials as $s) {
                if (in_array($s['status'], ['installed', 'bad', 'change'])) {
                    if ((int)$s['material_id'] === (int)$it['material_id']) {
                        $reportedCount++;
                    }
                }
            }
            if ($reportedCount < $qtyTaken) {
                $hasUnfinishedMaterial = true;
            }
        } elseif ($cableLength > 0) {
            $usedCableCount = 0;
            foreach ($serials as $s) {
                if ($s['status'] === 'installed' && !empty($s['cable_used'])) {
                    if (strpos($s['cable_used'], (string)$cableLength) !== false) {
                        $usedCableCount++;
                    }
                }
            }
            if ($usedCableCount < $qtyTaken) {
                $hasUnfinishedMaterial = true;
            }
        }
    }

    if ($hasUnfinishedMaterial) {
        $pdo->prepare("
            UPDATE bon_requests 
            SET status = 'approved',
                completed_at = NULL 
            WHERE id = ? AND status != 'cancelled'
        ")->execute([$bonId]);
    } else {
        $pdo->prepare("
            UPDATE bon_requests 
            SET status = 'completed',
                completed_at = COALESCE(completed_at, CURRENT_TIMESTAMP) 
            WHERE id = ? AND status != 'cancelled'
        ")->execute([$bonId]);
    }
}
