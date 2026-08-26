<?php
/**
 * API: User Action Handler (Create, Update, Reset Password, Delete)
 * PT Cipta Karya Teknologi (CKT Lampung)
 */

require_once __DIR__ . '/../config/database.php';

$currentUser = getCurrentUser($pdo);
$isAdmin = in_array($currentUser['role'] ?? '', ['admin_gudang', 'admin']);

if (!$isAdmin) {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'title' => 'Akses Ditolak',
        'text' => 'Hanya Admin Gudang yang berhak mengelola akun pengguna.'
    ];
    header("Location: ../bon.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'title' => 'Permintaan Tidak Valid (CSRF)',
            'text' => 'Token sesi formulir telah kedaluwarsa. Silakan refresh halaman dan coba kembali.'
        ];
        header("Location: ../pengguna.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // ---------------------------------------------------------
    // 1. TAMBAH PENGGUNA BARU (CREATE USER)
    // ---------------------------------------------------------
    if ($action === 'create') {
        try {
            $name = trim($_POST['name'] ?? '');
            $username = trim(strtolower($_POST['username'] ?? ''));
            $nik = trim($_POST['nik'] ?? '');
            $role = trim($_POST['role'] ?? 'teknisi');
            $department = trim($_POST['department'] ?? 'Teknis & Jaringan');
            $password = trim($_POST['password'] ?? '');
            $status = trim($_POST['status'] ?? 'active');

            if (empty($name) || empty($username) || empty($nik) || empty($password)) {
                throw new Exception("Semua kolom (Nama, Username, NIK, dan Password) wajib diisi.");
            }

            if (!in_array($role, ['admin_gudang', 'teknisi'])) {
                $role = 'teknisi';
            }

            // Cek duplikasi username atau NIK
            $stmtCheck = $pdo->prepare("SELECT id, username, nik FROM users WHERE username = ? OR nik = ? LIMIT 1");
            $stmtCheck->execute([$username, $nik]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                if ($existing['username'] === $username) {
                    throw new Exception("Username '{$username}' sudah digunakan oleh pengguna lain.");
                }
                if ($existing['nik'] === $nik) {
                    throw new Exception("NIK / ID Karyawan '{$nik}' sudah terdaftar dalam sistem.");
                }
            }

            // Hash password dengan Bcrypt
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $stmtInsert = $pdo->prepare("
                INSERT INTO users (name, username, nik, password, role, department, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmtInsert->execute([
                $name,
                $username,
                $nik,
                $hashedPassword,
                $role,
                $department,
                $status
            ]);

            $_SESSION['flash_message'] = [
                'type' => 'success',
                'title' => 'Pengguna Berhasil Ditambahkan!',
                'text' => "Akun {$role} atas nama '{$name}' (Username: {$username}) berhasil dibuat."
            ];
            header("Location: ../pengguna.php");
            exit;

        } catch (Exception $e) {
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'title' => 'Gagal Menambah Pengguna',
                'text' => $e->getMessage()
            ];
            header("Location: ../pengguna.php");
            exit;
        }
    }

    // ---------------------------------------------------------
    // 2. EDIT / UPDATE PENGGUNA
    // ---------------------------------------------------------
    if ($action === 'update') {
        try {
            $userId = (int)($_POST['user_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $username = trim(strtolower($_POST['username'] ?? ''));
            $nik = trim($_POST['nik'] ?? '');
            $role = trim($_POST['role'] ?? 'teknisi');
            $department = trim($_POST['department'] ?? 'Teknis & Jaringan');
            $password = trim($_POST['password'] ?? '');
            $status = trim($_POST['status'] ?? 'active');

            if ($userId <= 0 || empty($name) || empty($username) || empty($nik)) {
                throw new Exception("Data pengguna tidak valid atau ada kolom kosong.");
            }

            // Cek apakah user ada
            $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $user = $stmtUser->fetch();

            if (!$user) {
                throw new Exception("Pengguna tidak ditemukan.");
            }

            // Cek duplikasi username/NIK dengan user lain
            $stmtCheck = $pdo->prepare("SELECT id, username, nik FROM users WHERE (username = ? OR nik = ?) AND id != ? LIMIT 1");
            $stmtCheck->execute([$username, $nik, $userId]);
            $duplicate = $stmtCheck->fetch();

            if ($duplicate) {
                if ($duplicate['username'] === $username) {
                    throw new Exception("Username '{$username}' sudah digunakan oleh pengguna lain.");
                }
                if ($duplicate['nik'] === $nik) {
                    throw new Exception("NIK '{$nik}' sudah terdaftar untuk pengguna lain.");
                }
            }

            if (!empty($password)) {
                // Update dengan password baru
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmtUpdate = $pdo->prepare("
                    UPDATE users 
                    SET name = ?, username = ?, nik = ?, password = ?, role = ?, department = ?, status = ?
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$name, $username, $nik, $hashedPassword, $role, $department, $status, $userId]);
            } else {
                // Update tanpa mengganti password lama
                $stmtUpdate = $pdo->prepare("
                    UPDATE users 
                    SET name = ?, username = ?, nik = ?, role = ?, department = ?, status = ?
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$name, $username, $nik, $role, $department, $status, $userId]);
            }

            $_SESSION['flash_message'] = [
                'type' => 'success',
                'title' => 'Data Pengguna Diperbarui',
                'text' => "Perubahan data akun '{$name}' berhasil disimpan."
            ];
            header("Location: ../pengguna.php");
            exit;

        } catch (Exception $e) {
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'title' => 'Gagal Memperbarui Pengguna',
                'text' => $e->getMessage()
            ];
            header("Location: ../pengguna.php");
            exit;
        }
    }

    // ---------------------------------------------------------
    // 3. RESET PASSWORD PENGGUNA
    // ---------------------------------------------------------
    if ($action === 'reset_password') {
        try {
            $userId = (int)($_POST['user_id'] ?? 0);
            $newPassword = trim($_POST['new_password'] ?? '123456');

            if ($userId <= 0 || empty($newPassword)) {
                throw new Exception("ID Pengguna atau Password baru tidak valid.");
            }

            $stmtUser = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $userName = $stmtUser->fetchColumn();

            if (!$userName) {
                throw new Exception("Pengguna tidak ditemukan.");
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmtUp = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmtUp->execute([$hashedPassword, $userId]);

            $_SESSION['flash_message'] = [
                'type' => 'success',
                'title' => 'Password Berhasil Direset!',
                'text' => "Password akun '{$userName}' telah direset menjadi: {$newPassword}"
            ];
            header("Location: ../pengguna.php");
            exit;

        } catch (Exception $e) {
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'title' => 'Gagal Reset Password',
                'text' => $e->getMessage()
            ];
            header("Location: ../pengguna.php");
            exit;
        }
    }

    // ---------------------------------------------------------
    // 4. HAPUS PENGGUNA (DELETE USER)
    // ---------------------------------------------------------
    if ($action === 'delete') {
        try {
            $userId = (int)($_POST['user_id'] ?? 0);

            if ($userId <= 0) {
                throw new Exception("ID Pengguna tidak valid.");
            }

            if ($userId === (int)$currentUser['id']) {
                throw new Exception("Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.");
            }

            // Cek apakah user memiliki riwayat bon
            $stmtBonCheck = $pdo->prepare("SELECT COUNT(*) FROM bon_requests WHERE user_id = ?");
            $stmtBonCheck->execute([$userId]);
            $bonCount = (int)$stmtBonCheck->fetchColumn();

            if ($bonCount > 0) {
                // Jangan hard delete jika memiliki histori bon, set non-aktif saja
                $stmtDeactivate = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
                $stmtDeactivate->execute([$userId]);

                $_SESSION['flash_message'] = [
                    'type' => 'warning',
                    'title' => 'Akun Dinonaktifkan',
                    'text' => "Pengguna memiliki {$bonCount} riwayat Surat Bon. Status akun telah diubah menjadi Nonaktif (Data riwayat tetap aman tersimpan)."
                ];
            } else {
                // Hard delete jika belum pernah ada transaksi
                $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmtDel->execute([$userId]);

                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'title' => 'Pengguna Dihapus',
                    'text' => "Akun pengguna berhasil dihapus dari sistem."
                ];
            }

            header("Location: ../pengguna.php");
            exit;

        } catch (Exception $e) {
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'title' => 'Gagal Menghapus Pengguna',
                'text' => $e->getMessage()
            ];
            header("Location: ../pengguna.php");
            exit;
        }
    }
}

header("Location: ../pengguna.php");
exit;
