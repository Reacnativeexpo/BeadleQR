

<?php
/* =========================================================
   BEADLEQR - TEACHER SIDEBAR
   Compatible with Teacher/dashboard.php
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$teacherName = $_SESSION['fullname'] ?? 'Teacher';
$employeeId = '';
$profileImage = '';

if (isset($_SESSION['user_id']) && isset($conn) && $conn instanceof PDO) {
    try {
        $stmt = $conn->prepare("
            SELECT fullname, employee_id, profile_image
            FROM users
            WHERE id = ?
              AND role = 'teacher'
            LIMIT 1
        ");

        $stmt->execute([(int) $_SESSION['user_id']]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($teacher) {
            $teacherName = $teacher['fullname'] ?: 'Teacher';
            $employeeId = $teacher['employee_id'] ?? '';
            $profileImage = $teacher['profile_image'] ?? '';
        }
    } catch (PDOException $e) {
        error_log('Teacher sidebar error: ' . $e->getMessage());
    }
}

$initials = '';
$parts = preg_split('/\s+/', trim($teacherName));

if (!empty($parts[0])) {
    $initials .= strtoupper(substr($parts[0], 0, 1));
}

if (count($parts) > 1) {
    $initials .= strtoupper(substr(end($parts), 0, 1));
}

if ($initials === '') {
    $initials = 'T';
}

/* Logo path relative to pages in the Teacher folder */
$logoPath = '../images/beadle.png';

/* Profile image path */
$profileImageUrl = '';

if (!empty($profileImage)) {
    if (preg_match('/^https?:\/\//i', $profileImage)) {
        $profileImageUrl = $profileImage;
    } else {
        $profileImageUrl = '../' . ltrim($profileImage, '/');
    }
}
?>

<style>
/* =========================================================
   SIDEBAR - USE ORIGINAL DASHBOARD CLASS AND ID
========================================================= */

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 260px;
    height: 100vh;
    height: 100dvh;
    padding: 20px 16px;
    background: linear-gradient(180deg, #111827, #172033);
    color: #e5e7eb;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    overflow-x: hidden;
    box-sizing: border-box;
    z-index: 1000;
    transition: transform 0.3s ease;
    scrollbar-width: thin;
    scrollbar-color: #374151 transparent;
}

.sidebar *,
.sidebar *::before,
.sidebar *::after {
    box-sizing: border-box;
}

/* Logo */
.sidebar-brand {
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 24px;
}

.sidebar-brand img {
    width: 100%;
    max-width: 232px;
    height: 82px;
    padding: 8px;
    object-fit: contain;
    background: #fff;
    border-radius: 8px;
}

/* Teacher profile */
.teacher-sidebar-profile {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 0 6px 22px;
    margin-bottom: 20px;
    border-bottom: 1px solid rgba(255,255,255,.12);
}


.teacher-avatar-large {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    margin: 0 auto 8px;
}

.teacher-avatar-large img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}
.teacher-avatar-initials {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: linear-gradient(135deg, #f97316, #ea580c);
    color: #fff;
    font-size: 40px;
    font-weight: 800;
}

.teacher-online-dot {
    position: absolute;
    right: 7px;
    bottom: 7px;
    width: 14px;
    height: 14px;
    background: #22c55e;
    border: 2px solid #172033;
    border-radius: 50%;
}

.teacher-profile-info {
    width: 100%;
    min-width: 0;
}

.teacher-profile-name {
    color: #fff;
    font-size: 16px;
    line-height: 1.4;
    font-weight: 700;
    margin: 0;
    overflow-wrap: anywhere;
}

.teacher-profile-role {
    display: inline-block;
    color: #fdba74;
    font-size: 12px;
    font-weight: 600;
    margin-top: 5px;
    letter-spacing: .5px;
    text-transform: uppercase;
}

.teacher-profile-employee {
    color: #9ca3af;
    font-size: 11px;
    margin-top: 4px;
    overflow-wrap: anywhere;
}

/* Navigation */
.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: 100%;
}

.sidebar-label {
    color: #9ca3af;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    padding: 0 12px;
    margin: 0 0 8px;
}

.sidebar-link {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    min-height: 46px;
    padding: 12px 14px;
    color: #cbd5e1;
    text-decoration: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    transition: background .2s ease, color .2s ease;
}

.sidebar-link:hover {
    color: #fff;
    background: rgba(255,255,255,.08);
}

.sidebar-link.active {
    color: #fff;
    background: #f97316;
    box-shadow: 0 4px 12px rgba(249,115,22,.2);
}

.sidebar-link-icon {
    width: 20px;
    min-width: 20px;
    text-align: center;
    font-size: 17px;
}

.sidebar-link-text {
    flex: 1;
}

.sidebar-footer {
    margin-top: auto;
    padding-top: 20px;
}

.sidebar-footer .sidebar-link {
    color: #fca5a5;
}

.sidebar-footer .sidebar-link:hover {
    color: #fff;
    background: rgba(239,68,68,.15);
}

/* Must match dashboard.php selectors */
.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,.45);
    z-index: 999;
}

.sidebar-overlay.show {
    display: block;
}

/* Mobile */
@media (max-width: 992px) {
    .sidebar {
        transform: translateX(-100%);
    }

    .sidebar.show {
        transform: translateX(0);
    }
}

@media (max-width: 380px) {
    .sidebar {
        width: min(260px, 85vw);
    }

    .sidebar-brand img {
        height: 72px;
    }

    .teacher-avatar-large {
        width: 125px;
        height: 125px;
        flex-basis: 125px;
    }
}
</style>

<!-- The dashboard already provides its own mobile menu button. -->
<aside class="sidebar" id="sidebar">

    <div class="sidebar-brand">
        <img
            src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>"
            alt="BeadleQR Logo"
            onerror="this.style.display='none';"
        >
    </div>

    <!-- Circular teacher profile -->
    <div class="teacher-sidebar-profile">

        <div class="teacher-avatar-large">

            <?php if (!empty($profileImageUrl)): ?>
                <img
                    src="<?= htmlspecialchars($profileImageUrl, ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8') ?>"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                >

                <div class="teacher-avatar-initials" style="display:none;">
                    <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php else: ?>
                <div class="teacher-avatar-initials">
                    <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <span class="teacher-online-dot"></span>
        </div>

        <div class="teacher-profile-info">
            <h3 class="teacher-profile-name">
                <?= htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8') ?>
            </h3>

            <div class="teacher-profile-role">Teacher</div>

            <?php if ($employeeId !== ''): ?>
                <div class="teacher-profile-employee">
                    ID: <?= htmlspecialchars($employeeId, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">

        <div class="sidebar-label">Main Menu</div>

        <a
            href="dashboard.php"
            class="sidebar-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>"
        >
            <span class="sidebar-link-icon"><i class="fa-solid fa-house"></i></span>
            <span class="sidebar-link-text">Dashboard</span>
        </a>

        <a
            href="my_schedules.php"
            class="sidebar-link <?= $currentPage === 'my_schedules.php' ? 'active' : '' ?>"
        >
            <span class="sidebar-link-icon"><i class="fa-solid fa-calendar-days"></i></span>
            <span class="sidebar-link-text">My Schedules</span>
        </a>

        <a
            href="view_attendance.php"
            class="sidebar-link <?= $currentPage === 'view_attendance.php' ? 'active' : '' ?>"
        >
            <span class="sidebar-link-icon"><i class="fa-solid fa-clipboard-check"></i></span>
            <span class="sidebar-link-text">View Attendance</span>
        </a>

        <a
            href="settings.php"
            class="sidebar-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>"
        >
            <span class="sidebar-link-icon"><i class="fa-solid fa-gear"></i></span>
            <span class="sidebar-link-text">Settings</span>
        </a>

    </nav>

    <div class="sidebar-footer">
        <a href="../logout.php" class="sidebar-link">
            <span class="sidebar-link-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
            <span class="sidebar-link-text">Logout</span>
        </a>
    </div>

</aside>

<!-- Must match dashboard.php's existing JavaScript -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
