
<?php
// sidebar.php

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

// MAIN MENU
$mainMenu = [
    [
        'file' => 'dashboard.php',
        'icon' => 'fa-solid fa-chart-pie',
        'label' => 'Dashboard'
    ],
    [
        'file' => 'add_teacher.php',
        'icon' => 'fa-solid fa-user-tie',
        'label' => 'Teachers'
    ],
    [
        'file' => 'attendance.php',
        'icon' => 'fa-solid fa-clipboard-check',
        'label' => 'Attendance'
    ],
    [
        'file' => 'create_subject.php',
        'icon' => 'fa-solid fa-book',
        'label' => 'Subjects'
    ],
];

// MANAGEMENT MENU
$managementMenu = [
    [
        'file' => 'schedules.php',
        'icon' => 'fa-solid fa-calendar-days',
        'label' => 'Schedules'
    ],
    [
        'file' => 'students.php',
        'icon' => 'fa-solid fa-users',
        'label' => 'Students'
    ],
    [
        'file' => 'reports.php',
        'icon' => 'fa-solid fa-file-lines',
        'label' => 'Reports'
    ],
];

// LOGO PATH
$logoPath = '../images/beadle.png';
?>

<style>
/* =========================================================
   BEADLEQR ADMIN SIDEBAR
========================================================= */

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 260px;
    height: 100vh;
    height: 100dvh;
    background: linear-gradient(180deg, #111827, #172033);
    color: #e5e7eb;
    z-index: 1000;
    transition: transform 0.3s ease;
    overflow-y: auto;
    overflow-x: hidden;
    box-sizing: border-box;
    scrollbar-width: thin;
    scrollbar-color: #374151 transparent;
}

.sidebar *,
.sidebar *::before,
.sidebar *::after {
    box-sizing: border-box;
}

/* =========================================================
   LOGO HEADER
========================================================= */

.sidebar-header {
    width: 100%;
    padding: 20px 14px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    text-align: center;
}

.sidebar-logo {
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
}

.sidebar-logo img {
    width: 100%;
    max-width: 232px;
    height: 82px;
    padding: 8px;
    object-fit: contain;
    display: block;
    background: #ffffff;
    border-radius: 8px;
}

.sidebar-subtitle {
    color: rgba(255, 255, 255, 0.5);
    font-size: 11px;
    margin-top: 12px;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* =========================================================
   SIDEBAR MENU
========================================================= */

.sidebar-menu {
    padding: 20px 15px 150px;
}

.sidebar .menu-label {
    display: block;
    color: #9ca3af;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    padding: 0 12px;
    margin-bottom: 10px;
    font-weight: 700;
}

/* MENU LINKS */

.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    min-height: 46px;
    padding: 12px 15px;
    color: #cbd5e1;
    text-decoration: none;
    border-radius: 8px;
    margin-bottom: 5px;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.2s ease, color 0.2s ease;
}

.sidebar-menu a i {
    width: 22px;
    min-width: 22px;
    text-align: center;
    font-size: 16px;
}

/* HOVER */

.sidebar-menu a:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
}

/* ACTIVE MENU */

.sidebar-menu a.active {
    background: #f97316;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2);
}

/* =========================================================
   FIXED SETTINGS AND LOGOUT FOOTER
========================================================= */

.sidebar-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 260px;
    padding: 12px 15px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: #172033;
    z-index: 1001;
}

/* SETTINGS BUTTON */

.sidebar .settings-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    min-height: 42px;
    padding: 10px 15px;
    color: #cbd5e1;
    text-decoration: none;
    border-radius: 8px;
    margin-bottom: 6px;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.2s ease, color 0.2s ease;
}

.sidebar .settings-btn i {
    width: 22px;
    min-width: 22px;
    text-align: center;
    font-size: 16px;
}

.sidebar .settings-btn:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
}

.sidebar .settings-btn.active {
    background: #f97316;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2);
}

/* LOGOUT BUTTON */

.sidebar .logout-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    min-height: 42px;
    padding: 10px 15px;
    color: #fca5a5;
    text-decoration: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.2s ease, color 0.2s ease;
    background: rgba(239, 68, 68, 0.1);
}

.sidebar .logout-btn i {
    width: 22px;
    min-width: 22px;
    text-align: center;
    font-size: 16px;
}

.sidebar .logout-btn:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #ffffff;
}

/* =========================================================
   MAIN CONTENT
========================================================= */

.main-content {
    margin-left: 260px;
    min-height: 100vh;
    box-sizing: border-box;
}

/* =========================================================
   MOBILE RESPONSIVENESS
========================================================= */

@media (max-width: 768px) {
    .sidebar {
        width: 260px;
        max-width: 85vw;
        transform: translateX(-100%);
    }

    .sidebar.open {
        transform: translateX(0);
    }

    .sidebar-footer {
        width: 260px;
        max-width: 85vw;
    }

    .main-content {
        margin-left: 0;
        width: 100%;
    }
}

@media (max-width: 380px) {
    .sidebar-logo img {
        height: 72px;
    }

    .sidebar-header {
        padding: 16px 12px;
    }

    .sidebar-menu {
        padding-left: 12px;
        padding-right: 12px;
    }
}
</style>

<!-- =======================================================
     ADMIN SIDEBAR
======================================================= -->

<aside class="sidebar" id="sidebar">

    <!-- LOGO HEADER -->
    <div class="sidebar-header">

        <div class="sidebar-logo">
            <img
                src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>"
                alt="BeadleQR Logo"
                onerror="this.onerror=null; this.src='/Beader/images/beadle.png';"
            >
        </div>

        <div class="sidebar-subtitle">
            Admin Panel
        </div>

    </div>

    <!-- NAVIGATION MENU -->
    <nav class="sidebar-menu">

        <!-- MAIN MENU -->
        <div class="menu-label">
            Main Menu
        </div>

        <?php foreach ($mainMenu as $item): ?>

            <a
                href="<?= htmlspecialchars($item['file'], ENT_QUOTES, 'UTF-8') ?>"
                class="<?= $currentPage === $item['file'] ? 'active' : '' ?>"
                <?= $currentPage === $item['file'] ? 'aria-current="page"' : '' ?>
            >
                <i class="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>

                <span>
                    <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            </a>

        <?php endforeach; ?>

        <!-- MANAGEMENT MENU -->
        <div class="menu-label" style="margin-top: 24px;">
            Management
        </div>

        <?php foreach ($managementMenu as $item): ?>

            <a
                href="<?= htmlspecialchars($item['file'], ENT_QUOTES, 'UTF-8') ?>"
                class="<?= $currentPage === $item['file'] ? 'active' : '' ?>"
                <?= $currentPage === $item['file'] ? 'aria-current="page"' : '' ?>
            >
                <i class="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>

                <span>
                    <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            </a>

        <?php endforeach; ?>

    </nav>

    <!-- SETTINGS AND LOGOUT -->
    <div class="sidebar-footer">

        <!-- SETTINGS -->
        <a
            href="settings.php"
            class="settings-btn <?= $currentPage === 'settings.php' ? 'active' : '' ?>"
            <?= $currentPage === 'settings.php' ? 'aria-current="page"' : '' ?>
        >
            <i class="fa-solid fa-gear"></i>
            <span>Settings</span>
        </a>

        <!-- LOGOUT -->
        <a href="../logout.php" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>

    </div>

</aside>
