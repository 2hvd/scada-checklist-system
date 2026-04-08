<?php
// views/components/sidebar.php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['role'] ?? 'user';
$username = $_SESSION['username'] ?? 'User';
$initial = strtoupper(substr($username, 0, 1));
?>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="brand-icon">⚙️</span>
        <div class="brand-text">
            SCADA Checklist
            <span class="brand-sub">Management System</span>
        </div>
    </div>

    <div class="sidebar-nav">
        <?php if ($role === 'admin'): ?>
        <div class="nav-section-title">Administration</div>
        <a class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'],'admin/index') !== false || strpos($_SERVER['REQUEST_URI'],'admin/dashboard') !== false) ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/admin/index.php">
            <span class="nav-icon">📊</span> Dashboard
            <span class="nav-badge" id="pendingBadge" style="display:none">0</span>
        </a>
        <a class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'],'swo_management') !== false ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/admin/swo_management.php">
            <span class="nav-icon">📋</span> SWO Management
        </a>
        <a class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'],'statistics') !== false ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/admin/statistics.php">
            <span class="nav-icon">📈</span> Statistics
        </a>

        <?php elseif ($role === 'support'): ?>
        <div class="nav-section-title">Support</div>
        <a class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'],'support/index') !== false ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/support/index.php">
            <span class="nav-icon">🏠</span> Dashboard
        </a>
        <a class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'],'create_swo') !== false ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/support/create_swo.php">
            <span class="nav-icon">➕</span> Create SWO
        </a>
        <a class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'],'my_swo') !== false ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/support/my_swo.php">
            <span class="nav-icon">📁</span> My SWOs
        </a>
        <a class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'],'submissions') !== false ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/support/submissions.php">
            <span class="nav-icon">📬</span> Submissions
            <span class="nav-badge" id="submissionsBadge" style="display:none">0</span>
        </a>

        <?php else: ?>
        <div class="nav-section-title">My Work</div>
        <a class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'],'user/index') !== false ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/user/index.php">
            <span class="nav-icon">🏠</span> Dashboard
        </a>
        <a class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'],'checklist') !== false ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/user/index.php">
            <span class="nav-icon">✅</span> My Checklists
        </a>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar"><?php echo htmlspecialchars($initial); ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($role); ?></div>
            </div>
        </div>
        <button class="btn-logout" id="logoutBtn">🚪 Logout</button>
    </div>
</nav>
