-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 26, 2026 at 02:38 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cktlampung`
--

-- --------------------------------------------------------

--
-- Table structure for table `bon_items`
--

CREATE TABLE `bon_items` (
  `id` int(11) NOT NULL,
  `bon_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity_requested` int(11) NOT NULL,
  `quantity_approved` int(11) NOT NULL DEFAULT 0,
  `serial_numbers` text DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bon_requests`
--

CREATE TABLE `bon_requests` (
  `id` int(11) NOT NULL,
  `bon_number` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Teknisi yang mengambil barang',
  `approved_by` int(11) DEFAULT NULL COMMENT 'Admin Gudang yang melayani',
  `request_type` varchar(50) NOT NULL,
  `customer_id` varchar(50) DEFAULT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `customer_address` text DEFAULT NULL,
  `work_order_number` varchar(100) DEFAULT NULL,
  `area_zone` varchar(100) DEFAULT 'Bandar Lampung',
  `status` enum('pending','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'approved',
  `notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `code`, `description`) VALUES
(1, 'ONT & Router Wi-Fi', 'CAT-ONT', 'Optical Network Terminal dan Router pelanggan FTTH (ONT Besar & ONT Kecil)'),
(2, 'Kabel Drop Core FO', 'CAT-KBL', 'Kabel Fiber Optic Drop Cable 4 ukuran panjang (150M, 100M, 75M, 50M)');

-- --------------------------------------------------------

--
-- Table structure for table `materials`
--

CREATE TABLE `materials` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model_type` varchar(100) DEFAULT NULL,
  `cable_length` int(11) DEFAULT NULL COMMENT 'Panjang kabel dalam meter (150, 100, 75, 50)',
  `unit` varchar(20) NOT NULL DEFAULT 'Pcs',
  `stock_current` int(11) NOT NULL DEFAULT 0,
  `stock_min` int(11) NOT NULL DEFAULT 10,
  `location_rack` varchar(50) DEFAULT 'Rak A-1',
  `is_serialized` tinyint(1) DEFAULT 0 COMMENT '1 jika butuh Serial Number (ONT/Modem)',
  `specifications` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `materials`
--

INSERT INTO `materials` (`id`, `category_id`, `code`, `name`, `brand`, `model_type`, `cable_length`, `unit`, `stock_current`, `stock_min`, `location_rack`, `is_serialized`, `specifications`, `image_url`, `updated_at`) VALUES
(1, 1, 'MAT-ONT-BSR', 'ONT Besar (Dual Band Gigabit Wi-Fi)', 'ZTE / Huawei Dual Band', 'Dual Band 2.4/5GHz 4GE', NULL, 'Unit', 42, 15, 'Rak A-01 (ONT Besar)', 1, 'Dual Band AC1200 (2.4GHz & 5GHz), 4 Port Gigabit LAN, 1 Port Telp/POTS, High Gain Antenna', NULL, '2026-08-23 23:33:42'),
(2, 1, 'MAT-ONT-KCL', 'ONT Kecil (Single Band / Mini Wi-Fi)', 'Fiberhome / ZTE Single Band', 'Single Band 2.4GHz 1GE+3FE', NULL, 'Unit', 34, 10, 'Rak A-02 (ONT Kecil)', 1, 'Single Band 2.4GHz 300Mbps, 1 Port GE + 3 Port FE, Desain ringkas hemat daya', NULL, '2026-08-21 09:22:28'),
(4, 2, 'MAT-KBL-150', 'Kabel Drop Core 1 Core 3 Steel Wire (150 Meter)', 'V-Sol / Netlink', 'Pre-Connectorized SC/UPC - SC/UPC', 150, 'Roll', 62, 20, 'Rak B-01 (Kabel 150M)', 0, 'Panjang 150M, 1C 3 Messenger Wire, LSZH Jacket Outdoor, Siap Pasang', NULL, '2026-08-23 23:05:36'),
(5, 2, 'MAT-KBL-100', 'Kabel Drop Core 1 Core 3 Steel Wire (100 Meter)', 'V-Sol / Netlink', 'Pre-Connectorized SC/UPC - SC/UPC', 100, 'Roll', 87, 25, 'Rak B-02 (Kabel 100M)', 0, 'Panjang 100M, 1C 3 Steel Wire Outdoor, Low Attenuation Loss', NULL, '2026-08-23 22:44:49'),
(6, 2, 'MAT-KBL-075', 'Kabel Drop Core 1 Core 3 Steel Wire (75 Meter)', 'V-Sol / Netlink', 'Pre-Connectorized SC/UPC - SC/UPC', 75, 'Roll', 107, 30, 'Rak B-03 (Kabel 75M)', 0, 'Panjang 75M, 1C 3 Steel Wire, Jarak Sambung Menengah', NULL, '2026-08-23 22:44:49'),
(7, 2, 'MAT-KBL-050', 'Kabel Drop Core 1 Core 3 Steel Wire (50 Meter)', 'V-Sol / Netlink', 'Pre-Connectorized SC/UPC - SC/UPC', 50, 'Roll', 140, 35, 'Rak B-04 (Kabel 50M)', 0, 'Panjang 50M, 1C 3 Steel Wire, Paling sering untuk pasang tiang depan rumah', NULL, '2026-08-20 00:33:53');

-- --------------------------------------------------------

--
-- Table structure for table `material_serials`
--

CREATE TABLE `material_serials` (
  `id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `mac_address` varchar(100) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'available',
  `bon_id` int(11) DEFAULT NULL,
  `received_date` datetime DEFAULT current_timestamp(),
  `customer_name` varchar(150) DEFAULT NULL,
  `customer_id` varchar(100) DEFAULT NULL,
  `customer_address` text DEFAULT NULL,
  `installed_at` datetime DEFAULT NULL,
  `installed_notes` text DEFAULT NULL,
  `cable_used` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `material_serials`
--

INSERT INTO `material_serials` (`id`, `material_id`, `serial_number`, `mac_address`, `status`, `bon_id`, `received_date`, `customer_name`, `customer_id`, `customer_address`, `installed_at`, `installed_notes`, `cable_used`) VALUES
(1, 1, 'ZTEGC892F101', '74:91:1A:BC:89:65', 'allocated', 1, '2026-08-18 13:59:26', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 1, 'ZTEGC892F102', '74:91:1A:BC:89:66', 'allocated', 5, '2026-08-18 13:59:26', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 1, 'ZTEGC892F103', '74:91:1A:BC:89:67', 'installed', 7, '2026-08-18 13:59:26', 'Achmad Galih Zatmico', '100001', 'JL. Mawar NO 27', '2026-08-20 00:26:41', '', 'Kabel Drop Core 1 Core 3 Steel Wire (50 Meter) (50 Meter)'),
(4, 1, 'ZTEGC892F104', '74:91:1A:BC:89:68', 'installed', 7, '2026-08-18 13:59:26', 'M.Denis Albasith', '100002', 'JL.Durian n0 27', '2026-08-20 00:27:26', '', 'Kabel Drop Core 1 Core 3 Steel Wire (75 Meter) (75 Meter)'),
(5, 1, 'ZTEGC892F105', '74:91:1A:BC:89:69', 'installed', 7, '2026-08-18 13:59:26', 'Romeo', '100003', 'KAPAL', '2026-08-20 00:27:57', '', 'Kabel Drop Core 1 Core 3 Steel Wire (100 Meter) (100 Meter)'),
(6, 2, '48575443A89B201', 'CC:D5:39:E1:2A:C9', 'allocated', 3, '2026-08-18 13:59:26', NULL, NULL, NULL, NULL, NULL, NULL),
(7, 2, '48575443A89B202', 'CC:D5:39:E1:2A:CA', 'installed', 8, '2026-08-18 13:59:26', 'Gilang', '100004', 'JL.Melati no 27', '2026-08-20 00:42:00', '', 'Kabel Drop Core 1 Core 3 Steel Wire (50 Meter) (50 Meter)'),
(15, 1, 'ZTEGDFHA657', NULL, 'allocated', 5, '2026-08-20 10:04:33', NULL, NULL, NULL, NULL, NULL, NULL),
(28, 1, 'ZTEGC892F0101', '74:91:1A:BC:0D:28', 'allocated', 19, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(29, 1, 'ZTEGC892F0102', '74:91:1A:BC:10:2F', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(30, 1, 'ZTEGC892F0103', '74:91:1A:BC:13:36', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(31, 1, 'ZTEGC892F0104', '74:91:1A:BC:16:3D', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(32, 1, 'ZTEGC892F0105', '74:91:1A:BC:19:44', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(33, 1, 'ZTEGC892F0106', '74:91:1A:BC:1C:4B', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(34, 1, 'ZTEGC892F0107', '74:91:1A:BC:1F:52', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(35, 1, 'ZTEGC892F0108', '74:91:1A:BC:22:59', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(36, 1, 'ZTEGC892F0109', '74:91:1A:BC:25:60', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(37, 1, 'ZTEGC892F0110', '74:91:1A:BC:28:67', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(38, 1, 'ZTEGC892F0111', '74:91:1A:BC:2B:6E', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(39, 1, 'ZTEGC892F0112', '74:91:1A:BC:2E:75', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(40, 1, 'ZTEGC892F0113', '74:91:1A:BC:31:7C', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(41, 1, 'ZTEGC892F0114', '74:91:1A:BC:34:83', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(42, 1, 'ZTEGC892F0115', '74:91:1A:BC:37:8A', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(43, 1, 'ZTEGC892F0116', '74:91:1A:BC:3A:91', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(44, 1, 'ZTEGC892F0117', '74:91:1A:BC:3D:98', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(45, 1, 'ZTEGC892F0118', '74:91:1A:BC:40:9F', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(46, 1, 'ZTEGC892F0119', '74:91:1A:BC:43:A6', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(47, 1, 'ZTEGC892F0120', '74:91:1A:BC:46:AD', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(48, 1, 'ZTEGC892F0121', '74:91:1A:BC:49:B4', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(49, 1, 'ZTEGC892F0122', '74:91:1A:BC:4C:BB', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(50, 1, 'ZTEGC892F0123', '74:91:1A:BC:4F:C2', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(51, 1, 'ZTEGC892F0124', '74:91:1A:BC:52:C9', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(52, 1, 'ZTEGC892F0125', '74:91:1A:BC:55:D0', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(53, 1, 'ZTEGC892F0126', '74:91:1A:BC:58:D7', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(54, 1, 'ZTEGC892F0127', '74:91:1A:BC:5B:DE', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(55, 1, 'ZTEGC892F0128', '74:91:1A:BC:5E:E5', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(56, 1, 'ZTEGC892F0129', '74:91:1A:BC:61:EC', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(57, 1, 'ZTEGC892F0130', '74:91:1A:BC:64:F3', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(58, 1, 'ZTEGC892F0131', '74:91:1A:BC:67:FA', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(59, 1, 'ZTEGC892F0132', '74:91:1A:BC:6A:01', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(60, 1, 'ZTEGC892F0133', '74:91:1A:BC:6D:08', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(61, 1, 'ZTEGC892F0134', '74:91:1A:BC:70:0F', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(62, 1, 'ZTEGC892F0135', '74:91:1A:BC:73:16', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(63, 1, 'ZTEGC892F0136', '74:91:1A:BC:76:1D', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(64, 1, 'ZTEGC892F0137', '74:91:1A:BC:79:24', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(65, 1, 'ZTEGC892F0138', '74:91:1A:BC:7C:2B', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(66, 1, 'ZTEGC892F0139', '74:91:1A:BC:7F:32', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(67, 1, 'ZTEGC892F0140', '74:91:1A:BC:82:39', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(68, 1, 'ZTEGC892F0141', '74:91:1A:BC:85:40', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(69, 1, 'ZTEGC892F0142', '74:91:1A:BC:88:47', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(70, 1, 'ZTEGC892F0143', '74:91:1A:BC:8B:4E', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(71, 1, 'ZTEGC892F0144', '74:91:1A:BC:8E:55', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(72, 1, 'ZTEGC892F0145', '74:91:1A:BC:91:5C', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(73, 1, 'ZTEGC892F0146', '74:91:1A:BC:94:63', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(74, 1, 'ZTEGC892F0147', '74:91:1A:BC:97:6A', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(75, 1, 'ZTEGC892F0148', '74:91:1A:BC:9A:71', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(76, 1, 'ZTEGC892F0149', '74:91:1A:BC:9D:78', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(77, 1, 'ZTEGC892F0150', '74:91:1A:BC:A0:7F', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(78, 1, 'ZTEGC892F0151', '74:91:1A:BC:A3:86', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(79, 1, 'ZTEGC892F0152', '74:91:1A:BC:A6:8D', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(80, 2, '48575443A89B001', 'F8:E7:1E:5D:19:42', 'allocated', 19, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(81, 2, '48575443A89B002', 'F8:E7:1E:5D:1E:4D', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(82, 2, '48575443A89B003', 'F8:E7:1E:5D:23:58', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(83, 2, '48575443A89B004', 'F8:E7:1E:5D:28:63', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(84, 2, '48575443A89B005', 'F8:E7:1E:5D:2D:6E', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(85, 2, '48575443A89B006', 'F8:E7:1E:5D:32:79', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(86, 2, '48575443A89B007', 'F8:E7:1E:5D:37:84', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(87, 2, '48575443A89B008', 'F8:E7:1E:5D:3C:8F', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(88, 2, '48575443A89B009', 'F8:E7:1E:5D:41:9A', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(89, 2, '48575443A89B010', 'F8:E7:1E:5D:46:A5', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(90, 2, '48575443A89B011', 'F8:E7:1E:5D:4B:B0', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(91, 2, '48575443A89B012', 'F8:E7:1E:5D:50:BB', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(92, 2, '48575443A89B013', 'F8:E7:1E:5D:55:C6', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(93, 2, '48575443A89B014', 'F8:E7:1E:5D:5A:D1', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(94, 2, '48575443A89B015', 'F8:E7:1E:5D:5F:DC', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(95, 2, '48575443A89B016', 'F8:E7:1E:5D:64:E7', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(96, 2, '48575443A89B017', 'F8:E7:1E:5D:69:F2', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(97, 2, '48575443A89B018', 'F8:E7:1E:5D:6E:FD', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(98, 2, '48575443A89B019', 'F8:E7:1E:5D:73:08', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(99, 2, '48575443A89B020', 'F8:E7:1E:5D:78:13', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(100, 2, '48575443A89B021', 'F8:E7:1E:5D:7D:1E', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(101, 2, '48575443A89B022', 'F8:E7:1E:5D:82:29', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(102, 2, '48575443A89B023', 'F8:E7:1E:5D:87:34', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(103, 2, '48575443A89B024', 'F8:E7:1E:5D:8C:3F', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(104, 2, '48575443A89B025', 'F8:E7:1E:5D:91:4A', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(105, 2, '48575443A89B026', 'F8:E7:1E:5D:96:55', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(106, 2, '48575443A89B027', 'F8:E7:1E:5D:9B:60', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(107, 2, '48575443A89B028', 'F8:E7:1E:5D:A0:6B', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(108, 2, '48575443A89B029', 'F8:E7:1E:5D:A5:76', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(109, 2, '48575443A89B030', 'F8:E7:1E:5D:AA:81', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(110, 2, '48575443A89B031', 'F8:E7:1E:5D:AF:8C', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(111, 2, '48575443A89B032', 'F8:E7:1E:5D:B4:97', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(112, 2, '48575443A89B033', 'F8:E7:1E:5D:B9:A2', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(113, 2, '48575443A89B034', 'F8:E7:1E:5D:BE:AD', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(114, 2, '48575443A89B035', 'F8:E7:1E:5D:C3:B8', 'available', NULL, '2026-08-20 12:47:11', NULL, NULL, NULL, NULL, NULL, NULL),
(133, 1, 'UNIT-MAT-ONT-BSR-BON28-01', NULL, 'installed', 28, '2026-08-21 11:21:33', 'Dimas', '100001', 'Jalan durian no 27', '2026-08-21 11:21:33', '', 'Kabel Drop Core 1 Core 3 Steel Wire (100 Meter) (100 Meter)'),
(134, 1, 'UNIT-MAT-ONT-BSR-BON28-02', NULL, 'installed', 28, '2026-08-21 11:21:52', 'Jsns', '100002', 'Jqnsns', '2026-08-21 11:21:52', '', 'Kabel Drop Core 1 Core 3 Steel Wire (100 Meter) (100 Meter)'),
(143, 1, 'UNIT-MAT-ONT-BSR-BON33-01', NULL, 'installed', 33, '2026-08-23 22:21:36', 'sfasf', '12313', '13124', '2026-08-23 22:21:36', '', 'Kabel Drop Core 1 Core 3 Steel Wire (100 Meter) (100 Meter)'),
(149, 1, 'UNIT-MAT-ONT-BSR-BON36-01', NULL, 'installed', 36, '2026-08-23 22:45:00', 'asdaaad', '1223', '414321', '2026-08-23 22:45:00', '', 'Kabel Drop Core 1 Core 3 Steel Wire (100 Meter) (100 Meter)'),
(160, 1, 'UNIT-MAT-ONT-BSR-BON36-02', NULL, 'installed', 36, '2026-08-23 23:05:07', 'wrhgaengtj', '3246', 'eutrtyug', '2026-08-23 23:05:07', '', 'Kabel Drop Core 1 Core 3 Steel Wire (75 Meter) (75 Meter)'),
(164, 1, 'UNIT-MAT-ONT-BSR-BON41-01', NULL, 'installed', 41, '2026-08-23 23:35:49', 'sdfsg', '1478', 'ergmms', '2026-08-23 23:35:49', '', 'Kabel Drop Core 1 Core 3 Steel Wire (150 Meter) (150 Meter)'),
(168, 1, 'UNIT-MAT-ONT-BSR-BON41-02', NULL, 'bad', 41, '2026-08-23 23:44:28', 'erterrt', '54759', 'etertert', '2026-08-23 23:44:28', '325346', 'Tanpa Kabel Tambahan (Kabel Eksisting / Lama)');

-- --------------------------------------------------------

--
-- Table structure for table `stock_mutations`
--

CREATE TABLE `stock_mutations` (
  `id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `mutation_type` enum('in_restock','out_bon','adjustment','return') NOT NULL,
  `quantity` int(11) NOT NULL,
  `stock_before` int(11) NOT NULL,
  `stock_after` int(11) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` varchar(50) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_mutations`
--

INSERT INTO `stock_mutations` (`id`, `material_id`, `mutation_type`, `quantity`, `stock_before`, `stock_after`, `reference_type`, `reference_id`, `user_id`, `notes`, `created_at`) VALUES
(1, 1, 'in_restock', 50, 0, 50, 'supplier_po', 'PO-ONT-2026-01', 1, 'Penerimaan batch baru ONT Besar 50 unit', '2026-08-10 10:00:00'),
(2, 2, 'in_restock', 40, 0, 40, 'supplier_po', 'PO-ONT-2026-01', 1, 'Penerimaan batch baru ONT Kecil 40 unit', '2026-08-10 10:00:00'),
(3, 4, 'in_restock', 70, 0, 70, 'supplier_po', 'PO-KBL-2026-08', 1, 'Restock Kabel Drop Core 150M 70 Roll', '2026-08-10 11:00:00'),
(4, 5, 'in_restock', 100, 0, 100, 'supplier_po', 'PO-KBL-2026-08', 1, 'Restock Kabel Drop Core 100M 100 Roll', '2026-08-10 11:00:00'),
(5, 6, 'in_restock', 120, 0, 120, 'supplier_po', 'PO-KBL-2026-08', 1, 'Restock Kabel Drop Core 75M 120 Roll', '2026-08-10 11:00:00'),
(6, 7, 'in_restock', 150, 0, 150, 'supplier_po', 'PO-KBL-2026-08', 1, 'Restock Kabel Drop Core 50M 150 Roll', '2026-08-10 11:00:00'),
(7, 1, 'out_bon', 1, 52, 51, 'bon_request', 'BON-CKT-202608-0004', 2, 'Pengeluaran Bon BON-CKT-202608-0004', '2026-08-18 14:20:30'),
(8, 1, 'out_bon', 1, 51, 50, 'bon_request', 'BON-CKT-202608-0005', 5, 'Pengeluaran Bon BON-CKT-202608-0005', '2026-08-19 23:29:50'),
(9, 1, 'out_bon', 3, 50, 47, 'bon_request', 'BON-CKT-202608-0006', 4, 'Pengeluaran Bon BON-CKT-202608-0006', '2026-08-20 00:05:10'),
(10, 1, 'out_bon', 1, 47, 46, 'bon_request', 'BON-CKT-202608-0007', 3, 'Pengeluaran Bon BON-CKT-202608-0007', '2026-08-20 00:13:07'),
(11, 1, 'out_bon', 1, 46, 45, 'bon_request', 'BON-CKT-202608-0007', 3, 'Pengeluaran Bon BON-CKT-202608-0007', '2026-08-20 00:13:07'),
(12, 1, 'out_bon', 1, 45, 44, 'bon_request', 'BON-CKT-202608-0007', 3, 'Pengeluaran Bon BON-CKT-202608-0007', '2026-08-20 00:13:07'),
(13, 7, 'out_bon', 1, 142, 141, 'bon_request', 'BON-CKT-202608-0007', 3, 'Pengeluaran Bon BON-CKT-202608-0007', '2026-08-20 00:13:07'),
(14, 6, 'out_bon', 1, 110, 109, 'bon_request', 'BON-CKT-202608-0007', 3, 'Pengeluaran Bon BON-CKT-202608-0007', '2026-08-20 00:13:07'),
(15, 5, 'out_bon', 1, 93, 92, 'bon_request', 'BON-CKT-202608-0007', 3, 'Pengeluaran Bon BON-CKT-202608-0007', '2026-08-20 00:13:07'),
(16, 2, 'out_bon', 1, 36, 35, 'bon_request', 'BON-CKT-202608-0008', 3, 'Pengeluaran Bon BON-CKT-202608-0008', '2026-08-20 00:33:53'),
(17, 7, 'out_bon', 1, 141, 140, 'bon_request', 'BON-CKT-202608-0008', 3, 'Pengeluaran Bon BON-CKT-202608-0008', '2026-08-20 00:33:53'),
(18, 1, 'out_bon', 1, 44, 43, 'bon_request', 'BON-CKT-202608-0009', 5, 'Pengeluaran Bon BON-CKT-202608-0009', '2026-08-20 10:02:46'),
(19, 4, 'out_bon', 1, 65, 64, 'bon_request', 'BON-CKT-202608-0009', 5, 'Pengeluaran Bon BON-CKT-202608-0009', '2026-08-20 10:02:46'),
(20, 1, 'in_restock', 10, 43, 53, 'supplier_po', 'PO-RESTOCK-20260820', 1, '', '2026-08-20 10:04:33'),
(21, 1, 'out_bon', 1, 53, 52, 'bon_request', 'BON-CKT-202608-0010', 5, 'Pengeluaran Bon BON-CKT-202608-0010', '2026-08-20 10:05:42'),
(22, 1, 'out_bon', 1, 52, 51, 'bon_request', 'BON-CKT-202608-0006', 4, 'Penambahan ke Bon BON-CKT-202608-0006', '2026-08-20 12:57:13'),
(23, 1, 'out_bon', 1, 51, 50, 'bon_request', 'BON-CKT-202608-0009', 3, 'Pengeluaran Bon BON-CKT-202608-0009', '2026-08-20 12:58:08'),
(24, 1, 'out_bon', 1, 50, 49, 'bon_request', 'BON-CKT-202608-0001', 5, 'Pengeluaran Bon BON-CKT-202608-0001', '2026-08-21 09:22:28'),
(25, 2, 'out_bon', 1, 35, 34, 'bon_request', 'BON-CKT-202608-0001', 5, 'Pengeluaran Bon BON-CKT-202608-0001', '2026-08-21 09:22:28'),
(26, 5, 'out_bon', 1, 92, 91, 'bon_request', 'BON-CKT-202608-0001', 5, 'Pengeluaran Bon BON-CKT-202608-0001', '2026-08-21 09:22:28'),
(27, 6, 'out_bon', 1, 109, 108, 'bon_request', 'BON-CKT-202608-0001', 5, 'Pengeluaran Bon BON-CKT-202608-0001', '2026-08-21 09:22:28'),
(28, 1, 'out_bon', 1, 49, 48, 'bon_request', 'BON-CKT-202608-0001', 5, 'Pengeluaran Bon BON-CKT-202608-0001', '2026-08-21 11:20:46'),
(29, 1, 'out_bon', 1, 48, 47, 'bon_request', 'BON-CKT-202608-0001', 5, 'Pengeluaran Bon BON-CKT-202608-0001', '2026-08-21 11:20:46'),
(30, 5, 'out_bon', 2, 91, 89, 'bon_request', 'BON-CKT-202608-0001', 5, 'Pengeluaran Bon BON-CKT-202608-0001', '2026-08-21 11:20:46'),
(31, 4, 'out_bon', 1, 64, 63, 'bon_request', 'BON-CKT-202608-0001', 5, 'Pengeluaran Bon BON-CKT-202608-0001', '2026-08-21 11:20:46'),
(32, 1, 'out_bon', 1, 47, 46, 'bon_request', 'BON-CKT-202608-0002', 6, 'Pengeluaran Bon BON-CKT-202608-0002', '2026-08-23 22:21:26'),
(33, 5, 'out_bon', 1, 89, 88, 'bon_request', 'BON-CKT-202608-0002', 6, 'Pengeluaran Bon BON-CKT-202608-0002', '2026-08-23 22:21:26'),
(34, 1, 'out_bon', 1, 46, 45, 'bon_request', 'BON-CKT-202608-0001', 4, 'Pengeluaran Bon BON-CKT-202608-0001', '2026-08-23 22:44:49'),
(35, 5, 'out_bon', 1, 88, 87, 'bon_request', 'BON-CKT-202608-0001', 4, 'Pengeluaran Bon BON-CKT-202608-0001', '2026-08-23 22:44:49'),
(36, 6, 'out_bon', 1, 108, 107, 'bon_request', 'BON-CKT-202608-0001', 4, 'Pengeluaran Bon BON-CKT-202608-0001', '2026-08-23 22:44:49'),
(37, 1, 'out_bon', 1, 45, 44, 'bon_request', 'BON-CKT-202608-0001', 4, 'Penambahan ke Bon BON-CKT-202608-0001', '2026-08-23 23:04:21'),
(38, 1, 'out_bon', 1, 44, 43, 'bon_request', 'BON-CKT-202608-0002', 4, 'Pengeluaran Bon BON-CKT-202608-0002', '2026-08-23 23:05:36'),
(39, 4, 'out_bon', 1, 63, 62, 'bon_request', 'BON-CKT-202608-0002', 4, 'Pengeluaran Bon BON-CKT-202608-0002', '2026-08-23 23:05:36'),
(40, 1, 'out_bon', 1, 43, 42, 'bon_request', 'BON-CKT-202608-0002', 4, 'Penambahan ke Bon BON-CKT-202608-0002', '2026-08-23 23:33:42');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nik` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` enum('admin_gudang','teknisi','manager') NOT NULL DEFAULT 'teknisi',
  `department` varchar(100) DEFAULT 'Teknis & Jaringan',
  `avatar` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nik`, `name`, `role`, `department`, `avatar`, `status`, `created_at`) VALUES
(1, 'admin', '$2y$10$whcawmpgeUj2MYXIbBMT2OIJdxGTFh6.XZuWxkUH9kZitRgzr0/2G', 'CKT-ADM-001', 'Hendri Saputra', 'admin_gudang', 'Admin Gudang', 'assets/img/avatars/admin.png', 'active', '2026-08-18 13:59:26'),
(2, 'budi', '$2y$10$SHu/56JpiDFAL9hHied1kulXjeqPqcHk7/T2EicEdgXhMBpYyKQTi', 'CKT-TEK-001', 'Budi Santoso', 'teknisi', 'Teknisi Lapangan', 'assets/img/avatars/tek1.png', 'active', '2026-08-18 13:59:26'),
(3, 'rian', '$2y$10$SHu/56JpiDFAL9hHied1kulXjeqPqcHk7/T2EicEdgXhMBpYyKQTi', 'CKT-TEK-002', 'Rian Pratama', 'teknisi', 'Teknisi Lapangan', 'assets/img/avatars/tek2.png', 'active', '2026-08-18 13:59:26'),
(4, 'zaki', '$2y$10$SHu/56JpiDFAL9hHied1kulXjeqPqcHk7/T2EicEdgXhMBpYyKQTi', 'CKT-TEK-003', 'Ahmad Zaki Fauzan', 'teknisi', 'Teknisi Lapangan', 'assets/img/avatars/tek3.png', 'active', '2026-08-18 13:59:26'),
(5, 'dimas', '$2y$10$SHu/56JpiDFAL9hHied1kulXjeqPqcHk7/T2EicEdgXhMBpYyKQTi', 'CKT-TEK-004', 'Dimas Aditya', 'teknisi', 'Teknisi Lapangan', 'assets/img/avatars/tek4.png', 'active', '2026-08-18 13:59:26'),
(6, 'bayu', '$2y$10$SHu/56JpiDFAL9hHied1kulXjeqPqcHk7/T2EicEdgXhMBpYyKQTi', 'CKT-TEK-005', 'Bayu Nugroho', 'teknisi', 'Teknisi Lapangan', 'assets/img/avatars/tek5.png', 'active', '2026-08-18 13:59:26'),
(7, 'juwadi', '$2y$10$cp6Foznr/w6EGrXbritUTeZ.gOYl59GM.qCEGw/gMcOO60w5uUi32', 'CKT-TEK-006', 'Juwadi', 'teknisi', 'Teknisi Lapangan', NULL, 'active', '2026-08-21 11:44:10'),
(8, 'riski', '123456', 'CKT-TEK-007', 'Riski', 'teknisi', 'Teknis Lapangan', NULL, 'active', '2026-08-21 11:46:23'),
(9, 'thoib', '123456', 'CKT-TEK-008', 'M Thoib', 'teknisi', 'Teknis Lapangan', NULL, 'active', '2026-08-21 11:46:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bon_items`
--
ALTER TABLE `bon_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bi_bon` (`bon_id`),
  ADD KEY `fk_bi_mat` (`material_id`);

--
-- Indexes for table `bon_requests`
--
ALTER TABLE `bon_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_bon_num` (`bon_number`),
  ADD KEY `fk_bon_user` (`user_id`),
  ADD KEY `fk_bon_admin` (`approved_by`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_cat_code` (`code`);

--
-- Indexes for table `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_mat_code` (`code`),
  ADD KEY `fk_mat_cat` (`category_id`);

--
-- Indexes for table `material_serials`
--
ALTER TABLE `material_serials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_serial_num` (`serial_number`),
  ADD KEY `fk_ser_mat` (`material_id`);

--
-- Indexes for table `stock_mutations`
--
ALTER TABLE `stock_mutations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_mut_mat` (`material_id`),
  ADD KEY `fk_mut_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_username` (`username`),
  ADD UNIQUE KEY `uk_nik` (`nik`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bon_items`
--
ALTER TABLE `bon_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `bon_requests`
--
ALTER TABLE `bon_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `material_serials`
--
ALTER TABLE `material_serials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=175;

--
-- AUTO_INCREMENT for table `stock_mutations`
--
ALTER TABLE `stock_mutations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bon_items`
--
ALTER TABLE `bon_items`
  ADD CONSTRAINT `fk_bi_bon` FOREIGN KEY (`bon_id`) REFERENCES `bon_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bi_mat` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bon_requests`
--
ALTER TABLE `bon_requests`
  ADD CONSTRAINT `fk_bon_admin` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bon_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `materials`
--
ALTER TABLE `materials`
  ADD CONSTRAINT `fk_mat_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `material_serials`
--
ALTER TABLE `material_serials`
  ADD CONSTRAINT `fk_ser_mat` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_mutations`
--
ALTER TABLE `stock_mutations`
  ADD CONSTRAINT `fk_mut_mat` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mut_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
