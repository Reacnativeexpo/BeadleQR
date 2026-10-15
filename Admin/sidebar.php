
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
/* =====================================================
   GLOBAL SIDEBAR VARIABLES AND RESET
===================================================== */

:root {
    --sidebar-width: 260px;
    --sidebar-bg: #172033;
    --sidebar-orange: #f97316;
    --sidebar-text: #cbd5e1;
}

.sidebar,
.sidebar *,
.sidebar-overlay,
.sidebar-overlay *,
.mobile-menu-btn {
    box-sizing: border-box;
}

/* =====================================================
   MOBILE MENU BUTTON
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

    z-index: 1000;

    transform: translateX(0);
    transition: transform 0.3s ease;

    overflow-x: hidden;
    overflow-y: auto;

    overscroll-behavior: contain;
    scrollbar-width: thin;
    scrollbar-color: #374151 transparent;
}

/* =====================================================
   LOGO HEADER
===================================================== */

.sidebar-header {
    flex-shrink: 0;
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

    color: #9ca3af;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;

    padding: 0 12px;
    margin-bottom: 10px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;

    width: 100%;
    min-height: 46px;
    padding: 12px 15px;
    margin-bottom: 5px;

    color: var(--sidebar-text);
    text-decoration: none;

    border-radius: 8px;

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
   SETTINGS AND LOGOUT FOOTER
   Footer stays inside the sidebar so it moves with it.
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

    color: var(--sidebar-text);
    text-decoration: none;

    border-radius: 8px;

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

    box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2);
}

/* LOGOUT */

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

    background: rgba(239, 68, 68, 0.1);

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
   MAIN CONTENT
===================================================== */

.main-content {
    margin-left: var(--sidebar-width);
    min-height: 100vh;

    box-sizing: border-box;
}

/* =====================================================
   MOBILE OVERLAY
===================================================== */

.sidebar-overlay {
    display: none;
}
    
/* MAIN CONTENT */
.main-content {
    min-height: 100vh;
    min-width: 0;
}

/* FIXED TOP HEADER */
.topbar {
    position: sticky;
    top: 0;
    z-index: 1000;

    width: 100%;
    min-height: 75px;
    box-sizing: border-box;

    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;

    padding: 12px 25px;

    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
}

/* LEFT HEADER SECTION */
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
}

.page-title {
    font-size: 22px;
    font-weight: 700;
    color: #1f2937;
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #6b7280;
}

/* RIGHT HEADER SECTION */
.topbar-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

/* KEEP CONTENT BELOW HEADER */
.dashboard-content {
    padding: 25px;
}

/* MOBILE RESPONSIVENESS */
@media (max-width: 768px) {
    .topbar {
        padding: 12px 15px;
        gap: 10px;
    }

    .topbar-left {
        gap: 10px;
    }

    .page-title {
        font-size: 18px;
    }

    .breadcrumb {
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
}


/* =====================================================
   MOBILE RESPONSIVENESS
===================================================== */

@media (max-width: 768px) {

    /* Sidebar starts hidden off-screen */
    .sidebar {
        width: 260px;
        max-width: 85vw;

        height: 100vh;
        height: 100dvh;

        transform: translateX(-105%);
        transition: transform 0.3s ease;

        z-index: 1101;

        box-shadow: none;
    }

    /* Show sidebar when opened */
    .sidebar.open {
        transform: translateX(0);
        box-shadow: 8px 0 30px rgba(0, 0, 0, 0.25);
    }

    /* Display the hamburger button */
    .mobile-menu-btn {
        display: inline-flex;
    }

    /* Dark overlay behind the sidebar */
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

    /* Main content occupies full width */
    .main-content {
        margin-left: 0 !important;
        width: 100%;
        min-width: 0;
    }

    /* Keep the sidebar footer within the sidebar */
    .sidebar-footer {
        position: sticky;
        bottom: 0;
        width: 100%;
        max-width: none;
    }

    /* Prevent the page from scrolling sideways */
    body.sidebar-menu-open {
        overflow: hidden;
    }
}

/* =====================================================
   SMALL PHONES
===================================================== */

@media (max-width: 380px) {
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

    .mobile-menu-btn {
        width: 42px;
        height: 42px;
    }
}

/* Respect reduced-motion preferences */
@media (prefers-reduced-motion: reduce) {
    .sidebar {
        transition: none;
    }
}
</style>

<!-- =====================================================
     MOBILE MENU BUTTON
     Automatically inserted before the sidebar.
===================================================== -->

<button
    type="button"
    class="mobile-menu-btn"
    id="mobileMenuBtn"
    aria-label="Open navigation menu"
    aria-controls="sidebar"
    aria-expanded="false"
>
    <i class="fa-solid fa-bars" aria-hidden="true"></i>
</button>

<!-- =====================================================
     DARK OVERLAY
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
    <nav class="sidebar-menu" aria-label="Admin navigation">

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
                <i
                    class="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"
                    aria-hidden="true"
                ></i>

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

        <!-- SETTINGS -->
        <a
            href="settings.php"
            class="settings-btn <?= $currentPage === 'settings.php' ? 'active' : '' ?>"
            <?= $currentPage === 'settings.php' ? 'aria-current="page"' : '' ?>
        >
            <i class="fa-solid fa-gear" aria-hidden="true"></i>
            <span>Settings</span>
        </a>

        <!-- LOGOUT -->
        <a href="../logout.php" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
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

    // Prevent duplicate event listeners if this script is included twice.
    if (menuBtn.dataset.sidebarInitialized === 'true') {
        return;
    }

    menuBtn.dataset.sidebarInitialized = 'true';

    const icon = menuBtn.querySelector('i');
    const mobileBreakpoint = 768;

    function isMobile() {
        return window.matchMedia(
            '(max-width: ' + mobileBreakpoint + 'px)'
        ).matches;
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

        if (icon) {
            icon.classList.remove('fa-xmark');
            icon.classList.add('fa-bars');
        }
    }

    function toggleSidebar() {
        if (sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    // Hamburger button opens and closes the sidebar.
    menuBtn.addEventListener('click', toggleSidebar);

    // Clicking outside the sidebar closes it.
    overlay.addEventListener('click', closeSidebar);

    // Escape key closes the sidebar.
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    // Close after selecting a menu link on mobile.
    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (isMobile()) {
                closeSidebar();
            }
        });
    });

    // Reset sidebar state when switching back to desktop width.
    window.addEventListener('resize', function () {
        if (!isMobile()) {
            closeSidebar();
        }
    });

    // Initialize in the correct state.
    closeSidebar();
})();
</script>
