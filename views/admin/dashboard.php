<?php
// views/admin/dashboard.php - redirect to index
require_once __DIR__ . '/../../config/functions.php';
requireRole('admin');
header('Location: /scada-checklist-system/views/admin/index.php');
exit;
