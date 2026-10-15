<?php
// =====================================================
// SIDEBAR CONFIGURATION
// =====================================================

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

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

$logoPath = '../images/beadle.png';

?>

<style>
/* =====================================================
   GLOBAL VARIABLES
===================================================== */

:root {
    --sidebar-width: 260px;
    --sidebar-bg: #172033;
    --sidebar-orange: #f97316;
    --sidebar-text: #cbd5e1;
    --header-height: 75px;
}

.sidebar,
.sidebar *,
.sidebar-overlay,
.sidebar-overlay *,
.topbar,
.topbar *,
.dashboard-content {
    box-sizing: border-box;
}

/* =====================================================
   MAIN CONTENT
===================================================== */

.main-content {
    margin-left: var(--sidebar-width);
    min-height: 100vh;
    min-width: 0;
    width: auto;
}

/* =====================================================
   TOP HEADER - STAYS VISIBLE ON SCROLL
===================================================== */

.topbar {
    position: sticky;
    top: 0;
    z-index: 1050;

    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;

    width: 100%;
    min-height: var(--header-height);
    padding: 12px 25px;

    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

/* HEADER LEFT */

.topbar-left {
    display: flex;
    align-items: center;
    gap: 15px;
    min-width: 0;
}

.header-title-wrapper {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 0;
}

.page-title {
    margin: 0;
    color: #1f2937;
    font-size: 22px;
    font-weight: 700;
    line-height: 1.3;
}

.breadcrumb {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;

    color: #6b7280;
    font-size: 12px;
}

/* HEADER RIGHT */

.topbar-right {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 20px;
    flex-shrink: 0;
}

/* =====================================================
   MOBILE MENU BUTTON
   Located inside the top header.
===================================================== */

.mobile-menu-btn {
    display: none;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;

    width: 44px;
    height: 44px;
    padding: 0;

    border: none;
    border-radius: 10px;

    background: var(--sidebar-orange);
    color: #ffffff;

    font-size: 20px;
    cursor: pointer;

    box-shadow: 0 3px 10px rgba(249, 115, 22, 0.2);

    -webkit-tap-highlight-color: transparent;
}

.mobile-menu-btn:hover {
    background: #ea580c;
}

.mobile-menu-btn:focus-visible {
    outline: 3px solid #fdba74;
    outline-offset: 3px;
}

/* =====================================================
   NOTIFICATION BUTTON
===================================================== */

.notification-btn {
    position: relative;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    width: 42px;
    height: 42px;

    border: 1px solid #e5e7eb;
    border-radius: 10px;

    background: #ffffff;
    color: #475569;

    font-size: 17px;
    cursor: pointer;
    flex-shrink: 0;
}

.notification-btn:hover {
    background: #f8fafc;
    color: #f97316;
}

.notif-badge {
    position: absolute;
    top: -5px;
    right: -5px;

    display: flex;
    align-items: center;
    justify-content: center;

    min-width: 19px;
    height: 19px;
    padding: 0 5px;

    border: 2px solid #ffffff;
    border-radius: 20px;

    background: #ef4444;
    color: #ffffff;

    font-size: 10px;
    font-weight: 700;
}

/* =====================================================
   USER PROFILE
===================================================== */

.user-profile {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
}

.user-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;

    width: 42px;
    height: 42px;

    border-radius: 50%;
    background: #fff7ed;
    color: #ea580c;

    font-size: 17px;
    font-weight: 700;
}

.user-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}

.user-name {
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;

    color: #1f2937;
    font-size: 13px;
    font-weight: 700;
}

.user-role {
    color: #6b7280;
    font-size: 11px;
}

/* =====================================================
   DASHBOARD CONTENT
===================================================== */

.dashboard-content {
    min-width: 0;
    padding: 25px;
}

/* =====================================================
   SIDEBAR
===================================================== */

.sidebar {
    position: fixed;
    top: 0;
    left: 0;

    width: var(--sidebar-width);
    height: 100vh;
    height: 100dvh;
    max-height: 100dvh;

    display: flex;
    flex-direction: column;

    background: linear-gradient(180deg, #111827, #172033);
    color: #e5e7eb;

    z-index: 1101;

    transform: translateX(0);
    transition: transform 0.3s ease;

    overflow-x: hidden;
    overflow-y: auto;

    overscroll-behavior: contain;
    scrollbar-width: thin;
    scrollbar-color: #374151 transparent;
}

/* =====================================================
   SIDEBAR LOGO HEADER
===================================================== */

.sidebar-header {
    flex-shrink: 0;
    width: 100%;
    padding: 20px 14px;

    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    text-align: center;
}

.sidebar-logo {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
}

.sidebar-logo img {
    display: block;

    width: 100%;
    max-width: 232px;
    height: 82px;
    padding: 8px;

    object-fit: contain;

    background: #ffffff;
    border-radius: 8px;
}

.sidebar-subtitle {
    margin-top: 12px;

    color: rgba(255, 255, 255, 0.5);
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* =====================================================
   SIDEBAR NAVIGATION
===================================================== */

.sidebar-menu {
    flex: 1 0 auto;
    width: 100%;
    padding: 20px 15px 16px;
}

.sidebar .menu-label {
    display: block;

    padding: 0 12px;
    margin-bottom: 10px;

    color: #9ca3af;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;

    width: 100%;
    min-height: 46px;
    padding: 12px 15px;
    margin-bottom: 5px;

    border-radius: 8px;

    color: var(--sidebar-text);
    text-decoration: none;

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

.sidebar-menu a:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
}

.sidebar-menu a.active {
    background: var(--sidebar-orange);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2);
}

/* =====================================================
   SIDEBAR FOOTER
===================================================== */

.sidebar-footer {
    position: sticky;
    bottom: 0;

    flex-shrink: 0;
    width: 100%;

    margin-top: auto;
    padding: 12px 15px;

    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: #172033;

    z-index: 2;
}

/* SETTINGS */

.sidebar .settings-btn {
    display: flex;
    align-items: center;
    gap: 12px;

    width: 100%;
    min-height: 42px;
    padding: 10px 15px;
    margin-bottom: 6px;

    border-radius: 8px;

    color: var(--sidebar-text);
    text-decoration: none;

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
    background: var(--sidebar-orange);
    color: #ffffff;
}

/* LOGOUT */

.sidebar .logout-btn {
    display: flex;
    align-items: center;
    gap: 12px;

    width: 100%;
    min-height: 42px;
    padding: 10px 15px;

    border-radius: 8px;

    background: rgba(239, 68, 68, 0.1);
    color: #fca5a5;
    text-decoration: none;

    font-size: 14px;
    font-weight: 500;

    transition: background 0.2s ease, color 0.2s ease;
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

/* =====================================================
   MOBILE OVERLAY
===================================================== */

.sidebar-overlay {
    display: none;
}

/* =====================================================
   MOBILE RESPONSIVENESS
===================================================== */

@media (max-width: 768px) {

    .main-content {
        margin-left: 0;
        width: 100%;
        min-width: 0;
    }

    .topbar {
        gap: 10px;
        padding: 12px 15px;
    }

    .topbar-left {
        gap: 10px;
    }

    .mobile-menu-btn {
        display: inline-flex;
    }

    .page-title {
        font-size: 18px;
    }

    .breadcrumb {
        gap: 6px;
        font-size: 11px;
    }

    .topbar-right {
        gap: 10px;
    }

    .user-info {
        display: none;
    }

    .dashboard-content {
        padding: 15px;
    }

    /* Hide sidebar until opened */

    .sidebar {
        width: 260px;
        max-width: 85vw;

        transform: translateX(-105%);
        transition: transform 0.3s ease;

        box-shadow: none;
    }

    .sidebar.open {
        transform: translateX(0);
        box-shadow: 8px 0 30px rgba(0, 0, 0, 0.25);
    }

    /* Dark background overlay */

    .sidebar-overlay.show {
        display: block;

        position: fixed;
        inset: 0;

        width: 100%;
        height: 100vh;
        height: 100dvh;

        background: rgba(15, 23, 42, 0.58);

        z-index: 1100;
        cursor: pointer;

        -webkit-tap-highlight-color: transparent;
    }

    /* Lock background scrolling while menu is open */

    body.sidebar-menu-open {
        overflow: hidden;
    }
}

/* =====================================================
   SMALL PHONES
===================================================== */

@media (max-width: 380px) {

    .topbar {
        padding: 10px;
    }

    .topbar-left {
        gap: 8px;
    }

    .page-title {
        font-size: 16px;
    }

    .breadcrumb {
        font-size: 10px;
        gap: 5px;
    }

    .mobile-menu-btn {
        width: 40px;
        height: 40px;
        font-size: 18px;
    }

    .notification-btn {
        width: 38px;
        height: 38px;
    }

    .topbar-right {
        gap: 7px;
    }

    .user-avatar {
        width: 36px;
        height: 36px;
    }

    .sidebar-header {
        padding: 16px 12px;
    }

    .sidebar-logo img {
        height: 72px;
    }

    .sidebar-menu {
        padding-left: 12px;
        padding-right: 12px;
    }

    .sidebar-footer {
        padding: 10px 12px;
    }
}

/* =====================================================
   REDUCED MOTION
===================================================== */

@media (prefers-reduced-motion: reduce) {
    .sidebar,
    .sidebar-menu a,
    .mobile-menu-btn {
        transition: none;
    }
}
</style>

<!-- =====================================================
     SIDEBAR OVERLAY
===================================================== -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
    aria-hidden="true"
></div>

<!-- =====================================================
     ADMIN SIDEBAR
===================================================== -->

<aside class="sidebar" id="sidebar">

    <!-- LOGO -->
    <div class="sidebar-header">

        <div class="sidebar-logo">
            <img
                src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>"
                alt="BeadleQR Logo"
                onerror="this.onerror=null;this.src='/Beader/images/beadle.png';"
            >
        </div>

        <div class="sidebar-subtitle">
            Admin Panel
        </div>

    </div>

    <!-- NAVIGATION -->
    <nav class="sidebar-menu" aria-label="Admin navigation">

        <div class="menu-label">
            Main Menu
        </div>

        <?php foreach ($mainMenu as $item): ?>

            <?php
            $isActive = $currentPage === $item['file'];
            ?>

            <a
                href="<?= htmlspecialchars($item['file'], ENT_QUOTES, 'UTF-8') ?>"
                class="<?= $isActive ? 'active' : '' ?>"
                <?= $isActive ? 'aria-current="page"' : '' ?>
            >
                <i
                    class="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"
                    aria-hidden="true"
                ></i>

                <span>
                    <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            </a>

        <?php endforeach; ?>

        <div class="menu-label" style="margin-top: 24px;">
            Management
        </div>

        <?php foreach ($managementMenu as $item): ?>

            <?php
            $isActive = $currentPage === $item['file'];
            ?>

            <a
                href="<?= htmlspecialchars($item['file'], ENT_QUOTES, 'UTF-8') ?>"
                class="<?= $isActive ? 'active' : '' ?>"
                <?= $isActive ? 'aria-current="page"' : '' ?>
            >
                <i
                    class="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"
                    aria-hidden="true"
                ></i>

                <span>
                    <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            </a>

        <?php endforeach; ?>

    </nav>

    <!-- SETTINGS AND LOGOUT -->
    <div class="sidebar-footer">

        <a
            href="settings.php"
            class="settings-btn <?= $currentPage === 'settings.php' ? 'active' : '' ?>"
            <?= $currentPage === 'settings.php' ? 'aria-current="page"' : '' ?>
        >
            <i class="fa-solid fa-gear" aria-hidden="true"></i>
            <span>Settings</span>
        </a>

        <a href="../logout.php" class="logout-btn">
            <i
                class="fa-solid fa-right-from-bracket"
                aria-hidden="true"
            ></i>
            <span>Logout</span>
        </a>

    </div>

</aside>

<script>
(function () {
    'use strict';

    const sidebar = document.getElementById('sidebar');
    const menuBtn = document.getElementById('mobileMenuBtn');
    const overlay = document.getElementById('sidebarOverlay');

    if (!sidebar || !menuBtn || !overlay) {
        return;
    }

    // Prevent duplicate initialization.
    if (menuBtn.dataset.sidebarInitialized === 'true') {
        return;
    }

    menuBtn.dataset.sidebarInitialized = 'true';

    const icon = menuBtn.querySelector('i');
    const mobileQuery = window.matchMedia('(max-width: 768px)');

    function isMobile() {
        return mobileQuery.matches;
    }

    function openSidebar() {
        if (!isMobile()) {
            return;
        }

        sidebar.classList.add('open');
        overlay.classList.add('show');

        document.body.classList.add('sidebar-menu-open');

        menuBtn.setAttribute('aria-expanded', 'true');
        menuBtn.setAttribute('aria-label', 'Close navigation menu');

        overlay.setAttribute('aria-hidden', 'false');

        if (icon) {
            icon.classList.remove('fa-bars');
            icon.classList.add('fa-xmark');
        }
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');

        document.body.classList.remove('sidebar-menu-open');

        menuBtn.setAttribute('aria-expanded', 'false');
        menuBtn.setAttribute('aria-label', 'Open navigation menu');

        overlay.setAttribute('aria-hidden', 'true');

        if (icon) {
            icon.classList.remove('fa-xmark');
            icon.classList.add('fa-bars');
        }
    }

    menuBtn.addEventListener('click', function () {
        if (sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    overlay.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (isMobile()) {
                closeSidebar();
            }
        });
    });

    window.addEventListener('resize', function () {
        if (!isMobile()) {
            closeSidebar();
        }
    });

    closeSidebar();
})();
</script>
