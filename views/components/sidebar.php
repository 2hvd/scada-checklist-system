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
        <a class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'],'admin/index') !== false && strpos($_SERVER['REQUEST_URI'],'tab=') === false) || strpos($_SERVER['REQUEST_URI'],'tab=tab-statistics') !== false ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/admin/index.php?tab=tab-statistics">
            <span class="nav-icon">📊</span> Dashboard
        </a>
        <?php
            $uri = $_SERVER['REQUEST_URI'];
            $swoActive = (preg_match('/tab=tab-swo(&|$)/', $uri) || strpos($uri,'swo_management') !== false) ? 'active' : '';
        ?>
        <a class="nav-item <?= $swoActive ?>"
           href="/scada-checklist-system/views/admin/index.php?tab=tab-swo">
            <span class="nav-icon">📋</span> SWO Management
            <span class="nav-badge" id="pendingBadge" style="display:none">0</span>
        </a>
        <a class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'],'tab=tab-activity') !== false ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/admin/index.php?tab=tab-activity">
            <span class="nav-icon">🕐</span> Recent Activity
        </a>
        <a class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'],'tab=tab-checklist-items') !== false ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/admin/index.php?tab=tab-checklist-items">
            <span class="nav-icon">📝</span> Checklist Items
        </a>
        <a class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'],'tab=tab-swo-types') !== false ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/admin/index.php?tab=tab-swo-types">
            <span class="nav-icon">📌</span> SWO Types
        </a>
        <a class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'],'tab=tab-timeline') !== false ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/admin/index.php?tab=tab-timeline">
            <span class="nav-icon">📈</span> SWO Timeline
        </a>
        <a class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'],'statistics.php') !== false ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/admin/statistics.php">
            <span class="nav-icon">📊</span> Statistics
        </a>

        <?php elseif ($role === 'support'): ?>
        <?php
        $onSupportDash = strpos($_SERVER['REQUEST_URI'],'support/index') !== false;
        ?>
        <div class="nav-section-title">Support</div>
        <a class="nav-item <?php echo $onSupportDash ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/support/index.php">
            <span class="nav-icon">🏠</span> Dashboard
        </a>
        <a class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'],'create_swo') !== false ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/support/create_swo.php">
            <span class="nav-icon">➕</span> Create SWO
        </a>
        <a class="nav-item support-tab-nav" data-tab="tab-submissions"
           href="/scada-checklist-system/views/support/index.php#tab-submissions"
           <?php if($onSupportDash): ?>onclick="switchSupportTab('tab-submissions');return false;"<?php endif; ?>>
            <span class="nav-icon">📬</span> Pending Reviews
            <span class="nav-badge" id="submissionsBadge" style="display:none">0</span>
        </a>
        <a class="nav-item support-tab-nav" data-tab="tab-myswos"
           href="/scada-checklist-system/views/support/index.php#tab-myswos"
           <?php if($onSupportDash): ?>onclick="switchSupportTab('tab-myswos');return false;"<?php endif; ?>>
            <span class="nav-icon">📁</span> My SWOs
        </a>
        <a class="nav-item support-tab-nav" data-tab="tab-submitted"
           href="/scada-checklist-system/views/support/index.php#tab-submitted"
           <?php if($onSupportDash): ?>onclick="switchSupportTab('tab-submitted');return false;"<?php endif; ?>>
            <span class="nav-icon">📤</span> Submitted SWOs
        </a>

        <?php elseif ($role === 'control'): ?>
        <div class="nav-section-title">Control</div>
        <a class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'],'control/index') !== false ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/control/index.php">
            <span class="nav-icon">🏠</span> Dashboard
            <span class="nav-badge" id="controlPendingBadge" style="display:none">0</span>
        </a>

        <?php else: ?>
        <?php
        $uri = $_SERVER['REQUEST_URI'];
        $onMyChecklists = strpos($uri, 'my_checklists') !== false || strpos($uri, '/user/checklist') !== false;
        $onDashboard    = strpos($uri, 'user/index') !== false && !$onMyChecklists;
        ?>
        <div class="nav-section-title">My Work</div>
        <a class="nav-item <?php echo $onDashboard ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/user/index.php">
            <span class="nav-icon">🏠</span> Dashboard
        </a>
        <a class="nav-item <?php echo $onMyChecklists ? 'active' : ''; ?>"
           href="/scada-checklist-system/views/user/my_checklists.php">
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
