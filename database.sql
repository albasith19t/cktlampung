-- =========================================================================
-- DATABASE MYSQL: cktlampung
-- PT CIPTA KARYA TEKNOLOGI (CKT LAMPUNG)
-- Sistem Manajemen Gudang & Bon Pengeluaran Material Lapangan
-- File ini bisa di-import langsung di phpMyAdmin (http://localhost/phpmyadmin)
-- =========================================================================

CREATE DATABASE IF NOT EXISTS `cktlampung` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cktlampung`;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------
-- 1. TABEL: users (1 Admin Gudang + 5 Teknisi Lapangan)
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `nik` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin_gudang', 'teknisi', 'manager') NOT NULL DEFAULT 'teknisi',
  `department` VARCHAR(100) DEFAULT 'Teknis & Jaringan',
  `avatar` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_nik` (`nik`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Data: 1 Admin Gudang + 5 Teknisi (Password: 'admin123' untuk admin, '123456' untuk teknisi)
INSERT INTO `users` (`id`, `username`, `password`, `nik`, `name`, `role`, `department`, `avatar`) VALUES
(1, 'admin', '$2y$10$wKqK.Lp5Z3p3t2n5B.o0xO6B6rM8D6yH7cW9dE8fG1hJ2kL3mN4oP', 'CKT-ADM-001', 'Hendri Saputra', 'admin_gudang', 'Admin Gudang', 'assets/img/avatars/admin.png'),
(2, 'budi', '$2y$10$wKqK.Lp5Z3p3t2n5B.o0xO6B6rM8D6yH7cW9dE8fG1hJ2kL3mN4oP', 'CKT-TEK-001', 'Budi Santoso', 'teknisi', 'Teknisi Lapangan', 'assets/img/avatars/tek1.png'),
(3, 'rian', '$2y$10$wKqK.Lp5Z3p3t2n5B.o0xO6B6rM8D6yH7cW9dE8fG1hJ2kL3mN4oP', 'CKT-TEK-002', 'Rian Pratama', 'teknisi', 'Teknisi Lapangan', 'assets/img/avatars/tek2.png'),
(4, 'zaki', '$2y$10$wKqK.Lp5Z3p3t2n5B.o0xO6B6rM8D6yH7cW9dE8fG1hJ2kL3mN4oP', 'CKT-TEK-003', 'Ahmad Zaki Fauzan', 'teknisi', 'Teknisi Lapangan', 'assets/img/avatars/tek3.png'),
(5, 'dimas', '$2y$10$wKqK.Lp5Z3p3t2n5B.o0xO6B6rM8D6yH7cW9dE8fG1hJ2kL3mN4oP', 'CKT-TEK-004', 'Dimas Aditya', 'teknisi', 'Teknisi Lapangan', 'assets/img/avatars/tek4.png'),
(6, 'bayu', '$2y$10$wKqK.Lp5Z3p3t2n5B.o0xO6B6rM8D6yH7cW9dE8fG1hJ2kL3mN4oP', 'CKT-TEK-005', 'Bayu Nugroho', 'teknisi', 'Teknisi Lapangan', 'assets/img/avatars/tek5.png');

-- ---------------------------------------------------------
-- 2. TABEL: categories (Kategori Material Gudang)
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `code` VARCHAR(20) NOT NULL,
  `description` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cat_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`, `name`, `code`, `description`) VALUES
(1, 'ONT & Router Wi-Fi', 'CAT-ONT', 'Optical Network Terminal dan Router pelanggan FTTH (ONT Besar & ONT Kecil)'),
(2, 'Kabel Drop Core FO', 'CAT-KBL', 'Kabel Fiber Optic Drop Cable 4 ukuran panjang (150M, 100M, 75M, 50M)');

-- ---------------------------------------------------------
-- 3. TABEL: materials (Data Barang: ONT Besar, ONT Kecil & Kabel 150, 100, 75, 50)
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `materials`;
CREATE TABLE `materials` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_id` INT(11) DEFAULT NULL,
  `code` VARCHAR(50) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `brand` VARCHAR(100) DEFAULT NULL,
  `model_type` VARCHAR(100) DEFAULT NULL,
  `cable_length` INT(11) DEFAULT NULL COMMENT 'Panjang kabel dalam meter (150, 100, 75, 50)',
  `unit` VARCHAR(20) NOT NULL DEFAULT 'Pcs',
  `stock_current` INT(11) NOT NULL DEFAULT 0,
  `stock_min` INT(11) NOT NULL DEFAULT 10,
  `is_serialized` TINYINT(1) DEFAULT 0 COMMENT '1 jika butuh Serial Number (ONT/Modem)',
  `specifications` TEXT DEFAULT NULL,
  `image_url` VARCHAR(255) DEFAULT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mat_code` (`code`),
  KEY `fk_mat_cat` (`category_id`),
  CONSTRAINT `fk_mat_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `materials` (`id`, `category_id`, `code`, `name`, `brand`, `model_type`, `cable_length`, `unit`, `stock_current`, `stock_min`, `is_serialized`, `specifications`) VALUES
-- ONT Group: 2 Versi (ONT Besar & ONT Kecil)
(1, 1, 'MAT-ONT-BSR', 'ONT Besar (Dual Band Gigabit Wi-Fi)', 'ZTE / Huawei Dual Band', 'Dual Band 2.4/5GHz 4GE', NULL, 'Unit', 52, 15, 1, 'Dual Band AC1200 (2.4GHz & 5GHz), 4 Port Gigabit LAN, 1 Port Telp/POTS, High Gain Antenna'),
(2, 1, 'MAT-ONT-KCL', 'ONT Kecil (Single Band / Mini Wi-Fi)', 'Fiberhome / ZTE Single Band', 'Single Band 2.4GHz 1GE+3FE', NULL, 'Unit', 35, 10, 1, 'Single Band 2.4GHz 300Mbps, 1 Port GE + 3 Port FE, Desain ringkas hemat daya'),

-- Cable Group (4 Varian Panjang: 150m, 100m, 75m, 50m)
(4, 2, 'MAT-KBL-150', 'Kabel Drop Core 1 Core 3 Steel Wire (150 Meter)', 'V-Sol / Netlink', 'Pre-Connectorized SC/UPC - SC/UPC', 150, 'Roll', 64, 20, 0, 'Panjang 150M, 1C 3 Messenger Wire, LSZH Jacket Outdoor, Siap Pasang'),
(5, 2, 'MAT-KBL-100', 'Kabel Drop Core 1 Core 3 Steel Wire (100 Meter)', 'V-Sol / Netlink', 'Pre-Connectorized SC/UPC - SC/UPC', 100, 'Roll', 92, 25, 0, 'Panjang 100M, 1C 3 Steel Wire Outdoor, Low Attenuation Loss'),
(6, 2, 'MAT-KBL-075', 'Kabel Drop Core 1 Core 3 Steel Wire (75 Meter)', 'V-Sol / Netlink', 'Pre-Connectorized SC/UPC - SC/UPC', 75, 'Roll', 109, 30, 0, 'Panjang 75M, 1C 3 Steel Wire, Jarak Sambung Menengah'),
(7, 2, 'MAT-KBL-050', 'Kabel Drop Core 1 Core 3 Steel Wire (50 Meter)', 'V-Sol / Netlink', 'Pre-Connectorized SC/UPC - SC/UPC', 50, 'Roll', 140, 35, 0, 'Panjang 50M, 1C 3 Steel Wire, Paling sering untuk pasang tiang depan rumah');

-- ---------------------------------------------------------
-- 4. TABEL: material_serials (Pelacakan Serial Number ONT)
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `material_serials`;
CREATE TABLE `material_serials` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `material_id` INT(11) NOT NULL,
  `serial_number` VARCHAR(100) NOT NULL,
  `mac_address` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('available', 'allocated', 'installed', 'bad', 'change') DEFAULT 'available',
  `bon_id` INT(11) DEFAULT NULL,
  `received_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_serial_num` (`serial_number`),
  KEY `fk_ser_mat` (`material_id`),
  CONSTRAINT `fk_ser_mat` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `material_serials` (`material_id`, `serial_number`, `mac_address`, `status`) VALUES
(1, 'ZTEGC892F101', '74:91:1A:BC:89:65', 'allocated'),
(1, 'ZTEGC892F102', '74:91:1A:BC:89:66', 'available'),
(1, 'ZTEGC892F103', '74:91:1A:BC:89:67', 'available'),
(1, 'ZTEGC892F104', '74:91:1A:BC:89:68', 'available'),
(1, 'ZTEGC892F105', '74:91:1A:BC:89:69', 'available'),
(2, '48575443A89B201', 'CC:D5:39:E1:2A:C9', 'allocated'),
(2, '48575443A89B202', 'CC:D5:39:E1:2A:CA', 'available'),
(2, '48575443A89B203', 'CC:D5:39:E1:2A:CB', 'available');

-- ---------------------------------------------------------
-- 5. TABEL: bon_requests (Surat Jalan & Pengeluaran Bon Material)
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `bon_requests`;
CREATE TABLE `bon_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `bon_number` VARCHAR(50) NOT NULL,
  `user_id` INT(11) NOT NULL COMMENT 'Teknisi yang mengambil barang',
  `approved_by` INT(11) DEFAULT NULL COMMENT 'Admin Gudang yang melayani',
  `request_type` VARCHAR(50) NOT NULL,
  `customer_id` VARCHAR(50) DEFAULT NULL,
  `customer_name` VARCHAR(150) DEFAULT NULL,
  `customer_address` TEXT DEFAULT NULL,
  `work_order_number` VARCHAR(100) DEFAULT NULL,
  `area_zone` VARCHAR(100) DEFAULT 'Bandar Lampung',
  `status` ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') NOT NULL DEFAULT 'approved',
  `notes` TEXT DEFAULT NULL,
  `admin_notes` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `approved_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bon_num` (`bon_number`),
  KEY `fk_bon_user` (`user_id`),
  KEY `fk_bon_admin` (`approved_by`),
  CONSTRAINT `fk_bon_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bon_admin` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `bon_requests` (`id`, `bon_number`, `user_id`, `approved_by`, `request_type`, `customer_id`, `customer_name`, `customer_address`, `work_order_number`, `area_zone`, `status`, `notes`, `admin_notes`, `created_at`, `approved_at`, `completed_at`) VALUES
(1, 'BON-CKT-202608-0001', 2, 1, 'Pasang Baru (PSB)', 'CKT-PLG-1029', 'Hendra Gunawan', 'Jl. Raden Intan No. 45, Way Halim, Bandar Lampung', 'WO-PSB-8801', 'Way Halim', 'completed', 'Pasang baru Paket WiFi 50Mbps Home Gaming', 'Material diserahkan lengkap di loket gudang', '2026-08-14 08:30:00', '2026-08-14 08:35:00', '2026-08-14 11:30:00'),
(2, 'BON-CKT-202608-0002', 3, 1, 'Perbaikan / Maintenance', 'CKT-PLG-0844', 'Siti Rahmawati', 'Jl. Yos Sudarso Gg. Cendana No. 12, Teluk Betung', 'WO-MTN-3412', 'Teluk Betung', 'completed', 'Kabel drop core putus, pergantian 75M', 'Diserahkan kabel 75m + 2 Fastconn', '2026-08-14 13:15:00', '2026-08-14 13:20:00', '2026-08-14 15:45:00'),
(3, 'BON-CKT-202608-0003', 4, 1, 'Pasang Baru (PSB)', 'CKT-PLG-1030', 'Dr. Agus Setiawan, Sp.A', 'Perumahan Bukit Kencana Blok C-08, Sukabumi', 'WO-PSB-8805', 'Sukarame', 'approved', 'Pasang baru Paket Bisnis 100Mbps (Kabel 150M)', 'Diserahkan langsung ke Teknisi Zaki', '2026-08-15 08:10:00', '2026-08-15 08:15:00', NULL);

-- ---------------------------------------------------------
-- 6. TABEL: bon_items (Rincian Barang dalam Bon)
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `bon_items`;
CREATE TABLE `bon_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `bon_id` INT(11) NOT NULL,
  `material_id` INT(11) NOT NULL,
  `quantity_requested` INT(11) NOT NULL,
  `quantity_approved` INT(11) NOT NULL DEFAULT 0,
  `serial_numbers` TEXT DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_bi_bon` (`bon_id`),
  KEY `fk_bi_mat` (`material_id`),
  CONSTRAINT `fk_bi_bon` FOREIGN KEY (`bon_id`) REFERENCES `bon_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bi_mat` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `bon_items` (`bon_id`, `material_id`, `quantity_requested`, `quantity_approved`, `serial_numbers`, `notes`) VALUES
(1, 1, 1, 1, 'ZTEGC892F101', 'ONT Besar Dual Band'),
(1, 5, 1, 1, NULL, 'Kabel 100M'),
(1, 8, 2, 2, NULL, 'Fast Connector'),
(2, 6, 1, 1, NULL, 'Kabel 75M'),
(2, 8, 2, 2, NULL, 'Fast Connector SC/UPC'),
(3, 1, 1, 1, '48575443A89B201', 'ONT Besar High Power'),
(3, 4, 1, 1, NULL, 'Kabel 150M'),
(3, 8, 2, 2, NULL, 'Fast Connector');

-- ---------------------------------------------------------
-- 7. TABEL: stock_mutations (Riwayat / Audit Trail Mutasi Stok)
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `stock_mutations`;
CREATE TABLE `stock_mutations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `material_id` INT(11) NOT NULL,
  `mutation_type` ENUM('in_restock', 'out_bon', 'adjustment', 'return') NOT NULL,
  `quantity` INT(11) NOT NULL,
  `stock_before` INT(11) NOT NULL,
  `stock_after` INT(11) NOT NULL,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `reference_id` VARCHAR(50) DEFAULT NULL,
  `user_id` INT(11) NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_mut_mat` (`material_id`),
  KEY `fk_mut_user` (`user_id`),
  CONSTRAINT `fk_mut_mat` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mut_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stock_mutations` (`material_id`, `mutation_type`, `quantity`, `stock_before`, `stock_after`, `reference_type`, `reference_id`, `user_id`, `notes`, `created_at`) VALUES
(1, 'in_restock', 50, 0, 50, 'supplier_po', 'PO-ONT-2026-01', 1, 'Penerimaan batch baru ONT Besar 50 unit', '2026-08-10 10:00:00'),
(2, 'in_restock', 40, 0, 40, 'supplier_po', 'PO-ONT-2026-01', 1, 'Penerimaan batch baru ONT Kecil 40 unit', '2026-08-10 10:00:00'),
(4, 'in_restock', 70, 0, 70, 'supplier_po', 'PO-KBL-2026-08', 1, 'Restock Kabel Drop Core 150M 70 Roll', '2026-08-10 11:00:00'),
(5, 'in_restock', 100, 0, 100, 'supplier_po', 'PO-KBL-2026-08', 1, 'Restock Kabel Drop Core 100M 100 Roll', '2026-08-10 11:00:00'),
(6, 'in_restock', 120, 0, 120, 'supplier_po', 'PO-KBL-2026-08', 1, 'Restock Kabel Drop Core 75M 120 Roll', '2026-08-10 11:00:00'),
(7, 'in_restock', 150, 0, 150, 'supplier_po', 'PO-KBL-2026-08', 1, 'Restock Kabel Drop Core 50M 150 Roll', '2026-08-10 11:00:00');

SET FOREIGN_KEY_CHECKS = 1;
