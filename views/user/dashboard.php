<?php
// views/user/dashboard.php - redirect to index
require_once __DIR__ . '/../../config/functions.php';
requireRole('user');
header('Location: /scada-checklist-system/views/user/index.php');
exit;
