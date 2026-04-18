<?php
// views/components/header.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/functions.php';
requireLogin();
$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';
$pageTitle = $pageTitle ?? 'SCADA Checklist System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - SCADA Checklist System</title>
    <link rel="stylesheet" href="/scada-checklist-system/assets/css/style.css">
    <link rel="stylesheet" href="/scada-checklist-system/assets/css/dashboard.css">
    <link rel="stylesheet" href="/scada-checklist-system/assets/css/responsive.css">
    <script src="/scada-checklist-system/assets/js/auto_refresh.js" defer></script>
</head>
<body data-page="<?php echo htmlspecialchars($role); ?>" data-user-id="<?php echo (int)$_SESSION['user_id']; ?>">
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<div class="app-wrapper">
