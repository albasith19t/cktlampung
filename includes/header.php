<?php
/**
 * Layout: Header & Meta
 * PT Cipta Karya Teknologi (CKT Lampung)
 */
require_once __DIR__ . '/../config/database.php';

$currentUser = getCurrentUser($pdo);
$pendingBonCount = getPendingBonCount($pdo);
$stockAlertCount = getStockAlertCount($pdo);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
  <title><?= $pageTitle ?? 'Sistem Gudang & Bon Material' ?> - CKT Lampung</title>
  
  <!-- Mobile & PWA Optimization -->
  <meta name="theme-color" content="#0284c7">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="CKT Gudang">
  <link rel="manifest" href="manifest.json">
  <link rel="apple-touch-icon" href="assets/img/logo-ckt.svg">

  <!-- Primary Meta Tags for SEO & System Branding -->
  <meta name="title" content="CKT Lampung - Sistem Gudang & Manajemen Material WiFi">
  <meta name="description" content="Sistem Informasi Manajemen Logistik & Pengeluaran Bon Material PT Cipta Karya Teknologi Lampung. Monitoring stok ONT dan Kabel Drop Core (150m, 100m, 75m, 50m).">
  
  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="assets/img/logo-ckt.svg">

  <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- Chart.js for Logistics Analytics -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <!-- Custom Core Stylesheet -->
  <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body>
<div class="app-container">
