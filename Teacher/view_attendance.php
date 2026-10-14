<?php
session_start();
require "../db.php";

/*
|--------------------------------------------------------------------------
| TEACHER ATTENDANCE
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$teacher_id = (int) $_SESSION['user_id'];

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$date = $_GET['date'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

/*
|--------------------------------------------------------------------------
| ATTENDANCE QUERY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        ta.id,
        ta.attendance_date,
        ta.time_in,
        ta.time_out,
        ta.status,
        ta.scan_source,
        ta.remarks,

        s.id AS schedule_id,
        s.start_time,
        s.end_time,
        s.room,
        s.day,

        sub.subject_code,
        sub.subject_name,

        y.year_name,

        sec.section_name,

        teacher.fullname AS teacher_name,

        beader.fullname AS beader_name,
        beader.student_no AS beader_student_no

    FROM teacher_attendance ta

    INNER JOIN schedules s
        ON s.id = ta.schedule_id

    INNER JOIN subjects sub
        ON sub.id = s.subject_id

    INNER JOIN year_levels y
        ON y.id = s.year_level_id

    INNER JOIN sections sec
        ON sec.id = s.section_id

    INNER JOIN users teacher
        ON teacher.id = ta.teacher_id

    INNER JOIN users beader
        ON beader.id = ta.beader_id

    WHERE ta.teacher_id = :teacher_id
";

$params = [
    ':teacher_id' => $teacher_id
];

/*
|--------------------------------------------------------------------------
| DATE FILTER
|--------------------------------------------------------------------------
*/

if ($date !== '') {

    $sql .= "
        AND ta.attendance_date = :attendance_date
    ";

    $params[':attendance_date'] = $date;
}

/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if ($status !== '') {

    $sql .= "
        AND ta.status = :status
    ";

    $params[':status'] = $status;
}

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            LOWER(sub.subject_code) LIKE LOWER(:search)
            OR LOWER(sub.subject_name) LIKE LOWER(:search)
            OR LOWER(sec.section_name) LIKE LOWER(:search)
            OR LOWER(y.year_name) LIKE LOWER(:search)
            OR LOWER(beader.fullname) LIKE LOWER(:search)
            OR LOWER(beader.student_no) LIKE LOWER(:search)
        )
    ";

    $params[':search'] = '%' . $search . '%';
}

/*
|--------------------------------------------------------------------------
| ORDER
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        ta.attendance_date DESC,
        s.start_time ASC,
        ta.time_in ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);

$attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

$total = count($attendanceRecords);

$present = 0;
$late = 0;
$absent = 0;
$pending = 0;

foreach ($attendanceRecords as $record) {

    switch ($record['status']) {

        case 'Present':
            $present++;
            break;

        case 'Late':
            $late++;
            break;

        case 'Absent':
            $absent++;
            break;

        case 'Pending':
            $pending++;
            break;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Attendance - Teacher</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        :root {

            --primary: #f97316;
            --primary-dark: #ea580c;
            --primary-light: #fb923c;

            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;

            --card-bg: #ffffff;
            --body-bg: #f1f5f9;

            --text-dark: #1e293b;
            --text-muted: #64748b;

            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;

        }

        * {

            margin: 0;
            padding: 0;
            box-sizing: border-box;

            font-family: 'Poppins', sans-serif;

        }

        body {

            background: var(--body-bg);
            min-height: 100vh;

        }



        /* MAIN */

        .main-content {

            margin-left: 260px;

            min-height: 100vh;

        }

        .topbar {

            background: white;

            height: 70px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            padding: 0 35px;

            box-shadow:
                0 2px 10px rgba(0,0,0,0.04);

            position: sticky;

            top: 0;

            z-index: 100;

        }

        .topbar-title {

            font-size: 20px;

            font-weight: 700;

            color: var(--text-dark);

        }

        .topbar-user {

            display: flex;

            align-items: center;

            gap: 10px;

            color: var(--text-muted);

            font-size: 13px;

        }

        .user-icon {

            width: 38px;
            height: 38px;

            border-radius: 50%;

            background: #fff7ed;

            color: var(--primary);

            display: flex;

            align-items: center;
            justify-content: center;

        }

        .content-area {

            padding: 30px 35px;

        }


        /* PAGE HEADER */

        .page-header {

            margin-bottom: 25px;

        }

        .page-header h1 {

            font-size: 26px;

            color: var(--text-dark);

            font-weight: 700;

        }

        .page-header p {

            color: var(--text-muted);

            font-size: 13px;

            margin-top: 4px;

        }


        /* STAT CARDS */

        .stats {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;

            margin-bottom: 25px;

        }

        .stat-card {

            background: white;

            border-radius: 15px;

            padding: 20px;

            display: flex;

            align-items: center;

            gap: 15px;

            box-shadow:
                0 3px 15px rgba(0,0,0,0.04);

        }

        .stat-icon {

            width: 50px;
            height: 50px;

            border-radius: 12px;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 20px;

        }

        .stat-total {

            background: #fff7ed;
            color: var(--primary);

        }

        .stat-present {

            background: #ecfdf5;
            color: var(--success);

        }

        .stat-late {

            background: #fffbeb;
            color: var(--warning);

        }

        .stat-absent {

            background: #fef2f2;
            color: var(--danger);

        }

        .stat-info small {

            display: block;

            color: var(--text-muted);

            font-size: 12px;

        }

        .stat-info strong {

            display: block;

            color: var(--text-dark);

            font-size: 22px;

            margin-top: 2px;

        }


        /* FILTER */

        .filter-card {

            background: white;

            padding: 20px;

            border-radius: 15px;

            margin-bottom: 20px;

            box-shadow:
                0 3px 15px rgba(0,0,0,0.04);

        }

        .filter-form {

            display: grid;

            grid-template-columns:
                180px
                180px
                1fr
                auto;

            gap: 12px;

            align-items: end;

        }

        .form-group label {

            display: block;

            font-size: 12px;

            font-weight: 600;

            color: var(--text-dark);

            margin-bottom: 6px;

        }

        .form-control {

            width: 100%;

            height: 42px;

            border: 1px solid #e2e8f0;

            border-radius: 9px;

            padding: 0 12px;

            outline: none;

            font-size: 13px;

            color: var(--text-dark);

        }

        .form-control:focus {

            border-color: var(--primary);

        }

        .btn {

            height: 42px;

            border: none;

            border-radius: 9px;

            padding: 0 18px;

            cursor: pointer;

            font-size: 13px;

            font-weight: 600;

        }

        .btn-primary {

            background: var(--primary);

            color: white;

        }

        .btn-primary:hover {

            background: var(--primary-dark);

        }


        /* TABLE */

        .table-card {

            background: white;

            border-radius: 15px;

            overflow: hidden;

            box-shadow:
                0 3px 15px rgba(0,0,0,0.04);

        }

        .table-header {

            padding: 20px 22px;

            border-bottom: 1px solid #e2e8f0;

            display: flex;

            justify-content: space-between;

            align-items: center;

        }

        .table-header h3 {

            color: var(--text-dark);

            font-size: 17px;

        }

        .table-wrapper {

            overflow-x: auto;

        }

        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 950px;

        }

        th {

            background: #f8fafc;

            color: var(--text-muted);

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: .5px;

            font-weight: 600;

            padding: 14px 16px;

            text-align: left;

        }

        td {

            padding: 15px 16px;

            border-top: 1px solid #f1f5f9;

            color: var(--text-dark);

            font-size: 13px;

        }

        tbody tr:hover {

            background: #fffaf5;

        }

        .subject-code {

            display: inline-block;

            background: #fff7ed;

            color: var(--primary-dark);

            padding: 4px 8px;

            border-radius: 6px;

            font-size: 10px;

            font-weight: 700;

            margin-bottom: 3px;

        }

        .subject-name {

            font-weight: 600;

        }

        .muted {

            color: var(--text-muted);

            font-size: 11px;

        }

        .time {

            font-weight: 600;

        }

        .empty-time {

            color: #94a3b8;

        }

        .status-badge {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 700;

        }

        .status-present {

            background: #dcfce7;

            color: #15803d;

        }

        .status-late {

            background: #fef3c7;

            color: #b45309;

        }

        .status-absent {

            background: #fee2e2;

            color: #b91c1c;

        }

        .status-pending {

            background: #e0f2fe;

            color: #0369a1;

        }

        .no-data {

            padding: 60px 20px;

            text-align: center;

            color: var(--text-muted);

        }

        .no-data i {

            font-size: 40px;

            margin-bottom: 12px;

            color: #cbd5e1;

        }


        /* MOBILE */

        .mobile-menu {

            display: none;

            border: none;

            background: transparent;

            font-size: 22px;

            color: var(--text-dark);

            cursor: pointer;

        }

        @media(max-width:992px) {

            .sidebar {

                transform: translateX(-100%);

            }

            .sidebar.show {

                transform: translateX(0);

            }

            .main-content {

                margin-left: 0;

            }

            .mobile-menu {

                display: block;

            }

            .stats {

                grid-template-columns:
                    repeat(2,1fr);

            }

            .filter-form {

                grid-template-columns:
                    1fr 1fr;

            }

        }

        @media(max-width:600px) {

            .content-area {

                padding: 20px 15px;

            }

            .topbar {

                padding: 0 15px;

            }

            .stats {

                grid-template-columns: 1fr;

            }

            .filter-form {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>

<body>


<?php include "../asset/teacher_sidebar.php"; ?>


<!-- MAIN -->

<div class="main-content">


    <!-- TOPBAR -->

    <div class="topbar">

        <div style="display:flex;align-items:center;gap:15px;">

            <button
                class="mobile-menu"
                onclick="toggleSidebar()"
            >

                <i class="fa-solid fa-bars"></i>

            </button>

            <div class="topbar-title">

                My Attendance

            </div>

        </div>


        <div class="topbar-user">

            <div class="user-icon">

                <i class="fa-solid fa-user"></i>

            </div>

            <?php echo e($_SESSION['fullname'] ?? 'Teacher'); ?>

        </div>

    </div>


    <!-- CONTENT -->

    <div class="content-area">


        <div class="page-header">

            <h1>

                Attendance Records

            </h1>

            <p>

                View your attendance records from Beader QR scans.

            </p>

        </div>


        <!-- STATISTICS -->

        <div class="stats">

            <div class="stat-card">

                <div class="stat-icon stat-total">

                    <i class="fa-solid fa-clipboard-list"></i>

                </div>

                <div class="stat-info">

                    <small>Total Records</small>

                    <strong><?php echo $total; ?></strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon stat-present">

                    <i class="fa-solid fa-circle-check"></i>

                </div>

                <div class="stat-info">

                    <small>Present</small>

                    <strong><?php echo $present; ?></strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon stat-late">

                    <i class="fa-solid fa-clock"></i>

                </div>

                <div class="stat-info">

                    <small>Late</small>

                    <strong><?php echo $late; ?></strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon stat-absent">

                    <i class="fa-solid fa-circle-xmark"></i>

                </div>

                <div class="stat-info">

                    <small>Absent</small>

                    <strong><?php echo $absent; ?></strong>

                </div>

            </div>

        </div>


        <!-- FILTER -->

        <div class="filter-card">

            <form
                method="GET"
                class="filter-form"
            >

                <div class="form-group">

                    <label>Date</label>

                    <input
                        type="date"
                        name="date"
                        class="form-control"
                        value="<?php echo e($date); ?>"
                    >

                </div>


                <div class="form-group">

                    <label>Status</label>

                    <select
                        name="status"
                        class="form-control"
                    >

                        <option value="">All Status</option>

                        <option
                            value="Present"
                            <?php echo $status === 'Present' ? 'selected' : ''; ?>
                        >
                            Present
                        </option>

                        <option
                            value="Late"
                            <?php echo $status === 'Late' ? 'selected' : ''; ?>
                        >
                            Late
                        </option>

                        <option
                            value="Absent"
                            <?php echo $status === 'Absent' ? 'selected' : ''; ?>
                        >
                            Absent
                        </option>

                        <option
                            value="Pending"
                            <?php echo $status === 'Pending' ? 'selected' : ''; ?>
                        >
                            Pending
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>Search</label>

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Subject, section, Beader..."
                        value="<?php echo e($search); ?>"
                    >

                </div>


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <i class="fa-solid fa-filter"></i>

                    Filter

                </button>

            </form>

        </div>


        <!-- TABLE -->

        <div class="table-card">

            <div class="table-header">

                <h3>

                    <i class="fa-solid fa-list-check"></i>

                    Attendance History

                </h3>

                <span class="muted">

                    <?php echo $total; ?> record(s)

                </span>

            </div>


            <?php if (count($attendanceRecords) > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>Subject</th>

                                <th>Class</th>

                                <th>Room</th>

                                <th>Date</th>

                                <th>Time In</th>

                                <th>Time Out</th>

                                <th>Duration</th>

                                <th>Status</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($attendanceRecords as $record): ?>

                            <tr>

                                <!-- SUBJECT -->

                                <td>

                                    <?php if (!empty($record['subject_code'])): ?>

                                        <span class="subject-code">

                                            <?php
                                            echo e(
                                                $record['subject_code']
                                            );
                                            ?>

                                        </span>

                                    <?php endif; ?>

                                    <div class="subject-name">

                                        <?php
                                        echo e(
                                            $record['subject_name']
                                        );
                                        ?>

                                    </div>

                                </td>


                                <!-- CLASS -->

                                <td>

                                    <strong>

                                        <?php
                                        echo e(
                                            $record['year_name']
                                        );
                                        ?>

                                    </strong>

                                    <div class="muted">

                                        <?php
                                        echo e(
                                            $record['section_name']
                                        );
                                        ?>

                                    </div>

                                </td>


                                <!-- ROOM -->

                                <td>

                                    <?php
                                    echo e(
                                        $record['room'] ?: 'N/A'
                                    );
                                    ?>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?php
                                    echo e(
                                        date(
                                            'M d, Y',
                                            strtotime(
                                                $record['attendance_date']
                                            )
                                        )
                                    );
                                    ?>

                                </td>


                                <!-- TIME IN -->

                                <td class="time">

                                    <?php if (!empty($record['time_in'])): ?>

                                        <?php
                                        echo e(
                                            date(
                                                'h:i A',
                                                strtotime(
                                                    $record['time_in']
                                                )
                                            )
                                        );
                                        ?>

                                    <?php else: ?>

                                        <span class="empty-time">
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- TIME OUT -->

                                <td class="time">

                                    <?php if (!empty($record['time_out'])): ?>

                                        <?php
                                        echo e(
                                            date(
                                                'h:i A',
                                                strtotime(
                                                    $record['time_out']
                                                )
                                            )
                                        );
                                        ?>

                                    <?php else: ?>

                                        <span class="empty-time">
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- DURATION -->

                                <td>

                                    <?php

                                    if (
                                        !empty($record['time_in']) &&
                                        !empty($record['time_out'])
                                    ) {

                                        $timeIn =
                                            strtotime(
                                                $record['time_in']
                                            );

                                        $timeOut =
                                            strtotime(
                                                $record['time_out']
                                            );

                                        $seconds =
                                            max(
                                                0,
                                                $timeOut - $timeIn
                                            );

                                        $hours =
                                            floor(
                                                $seconds / 3600
                                            );

                                        $minutes =
                                            floor(
                                                ($seconds % 3600) / 60
                                            );

                                        if ($hours > 0) {

                                            echo $hours . 'h ' .
                                                 $minutes . 'm';

                                        } else {

                                            echo $minutes . 'm';

                                        }

                                    } else {

                                        echo '—';

                                    }

                                    ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php

                                    $statusClass =
                                        match ($record['status']) {

                                            'Present'
                                                => 'status-present',

                                            'Late'
                                                => 'status-late',

                                            'Absent'
                                                => 'status-absent',

                                            default
                                                => 'status-pending'

                                        };

                                    ?>

                                    <span
                                        class="status-badge <?php echo $statusClass; ?>"
                                    >

                                        <?php if ($record['status'] === 'Present'): ?>

                                            <i class="fa-solid fa-circle-check"></i>

                                        <?php elseif ($record['status'] === 'Late'): ?>

                                            <i class="fa-solid fa-clock"></i>

                                        <?php elseif ($record['status'] === 'Absent'): ?>

                                            <i class="fa-solid fa-circle-xmark"></i>

                                        <?php else: ?>

                                            <i class="fa-solid fa-hourglass-half"></i>

                                        <?php endif; ?>

                                        <?php
                                        echo e(
                                            $record['status']
                                        );
                                        ?>

                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="no-data">

                    <i class="fa-solid fa-clipboard-question"></i>

                    <h3>No Attendance Records</h3>

                    <p>

                        No attendance records were found for the selected filters.

                    </p>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>


<script>

function toggleSidebar() {

    document
        .getElementById('sidebar')
        .classList
        .toggle('show');

}

</script>

</body>
</html>