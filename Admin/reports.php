
<?php
session_start();
require "../db.php";
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatTime($time) {
    if (empty($time)) return '--';
    $timestamp = strtotime($time);
    if ($timestamp === false) return $time;
    return date('g:i A', $timestamp);
}

$day_names = [
    'Monday', 'Tuesday', 'Wednesday', 'Thursday',
    'Friday', 'Saturday', 'Sunday'
];

/* =========================================================
   REPORT RANGE
   ========================================================= */

$selected_date = $_GET['week'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

$selected_timestamp = strtotime($selected_date);

if ($selected_timestamp === false) {
    $selected_timestamp = strtotime(date('Y-m-d'));
}

$monday_timestamp = strtotime('monday this week', $selected_timestamp);
$monday_date = date('Y-m-d', $monday_timestamp);

$from_month = $_GET['from_month'] ?? '';
$to_month = $_GET['to_month'] ?? '';

if (!preg_match('/^\d{4}-\d{2}$/', $from_month)) {
    $from_month = '';
}

if (!preg_match('/^\d{4}-\d{2}$/', $to_month)) {
    $to_month = '';
}

$range_mode = ($from_month !== '' && $to_month !== '');

if ($range_mode) {
    $range_start_date = date('Y-m-01', strtotime($from_month . '-01'));
    $range_end_date = date('Y-m-t', strtotime($to_month . '-01'));

    if (strtotime($range_start_date) > strtotime($range_end_date)) {
        [$from_month, $to_month] = [$to_month, $from_month];

        $range_start_date = date('Y-m-01', strtotime($from_month . '-01'));
        $range_end_date = date('Y-m-t', strtotime($to_month . '-01'));
    }
} else {
    $range_start_date = $monday_date;
    $range_end_date = date('Y-m-d', strtotime('+6 days', $monday_timestamp));
}

/* =========================================================
   GENERATE WEEKS FOR SELECTED RANGE
   ========================================================= */

$first_week_timestamp = strtotime(
    'monday this week',
    strtotime($range_start_date)
);

$last_week_timestamp = strtotime(
    'monday this week',
    strtotime($range_end_date)
);

$report_weeks = [];

for (
    $week_ts = $first_week_timestamp;
    $week_ts <= $last_week_timestamp;
    $week_ts = strtotime('+7 days', $week_ts)
) {
    $week_dates = [];

    foreach ($day_names as $index => $day) {
        $week_dates[$day] = date(
            'Y-m-d',
            strtotime("+{$index} days", $week_ts)
        );
    }

    $report_weeks[] = [
        'monday' => $week_dates['Monday'],
        'sunday' => $week_dates['Sunday'],
        'dates' => $week_dates
    ];
}

/* =========================================================
   GET ALL TEACHER SCHEDULES
   ========================================================= */

$sql = "
    SELECT
        s.id AS schedule_id,
        s.teacher_id,
        s.subject_id,
        s.room,
        s.day,
        s.start_time,
        s.end_time,
        s.year_level_id,
        s.section_id,
        u.fullname AS teacher_name,
        u.employee_id,
        sub.subject_code,
        sub.subject_name,
        yl.year_name,
        sec.section_name
    FROM public.schedules s
    INNER JOIN public.users u ON u.id = s.teacher_id
    INNER JOIN public.subjects sub ON sub.id = s.subject_id
    LEFT JOIN public.year_levels yl ON yl.id = s.year_level_id
    LEFT JOIN public.sections sec ON sec.id = s.section_id
    WHERE u.role = 'teacher'
    ORDER BY
        u.fullname ASC,
        CASE LOWER(TRIM(s.day))
            WHEN 'monday' THEN 1
            WHEN 'tuesday' THEN 2
            WHEN 'wednesday' THEN 3
            WHEN 'thursday' THEN 4
            WHEN 'friday' THEN 5
            WHEN 'saturday' THEN 6
            WHEN 'sunday' THEN 7
            ELSE 8
        END,
        s.start_time ASC,
        sub.subject_name ASC
";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    die("Error loading schedules: " . e($ex->getMessage()));
}

/* =========================================================
   GET ATTENDANCE FOR SELECTED RANGE
   ========================================================= */

$attendance_sql = "
    SELECT
        ta.id,
        ta.teacher_id,
        ta.schedule_id,
        ta.attendance_date,
        ta.time_in,
        ta.time_out,
        ta.status,
        ta.remarks
    FROM public.teacher_attendance ta
    WHERE ta.attendance_date BETWEEN :range_start AND :range_end
    ORDER BY ta.attendance_date ASC
";

try {
    $attendance_stmt = $conn->prepare($attendance_sql);

    $attendance_stmt->execute([
        'range_start' => $range_start_date,
        'range_end' => $range_end_date
    ]);

    $attendance_rows = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    die("Error loading attendance: " . e($ex->getMessage()));
}

$attendance = [];

foreach ($attendance_rows as $row) {
    $key = $row['schedule_id'] . '_' . $row['attendance_date'];
    $attendance[$key] = $row;
}

/* =========================================================
   GROUP SCHEDULES BY TEACHER, SUBJECT AND CLASS
   ========================================================= */

$grouped_subjects = [];
$day_order = array_flip($day_names);

foreach ($schedules as $schedule) {
    $group_key = implode('|', [
        (string)$schedule['teacher_id'],
        (string)$schedule['subject_id'],
        (string)($schedule['year_level_id'] ?? ''),
        (string)($schedule['section_id'] ?? ''),
        (string)($schedule['room'] ?? '')
    ]);

    if (!isset($grouped_subjects[$group_key])) {
        $grouped_subjects[$group_key] = [
            'teacher_id' => $schedule['teacher_id'],
            'teacher_name' => $schedule['teacher_name'],
            'employee_id' => $schedule['employee_id'],
            'subject_id' => $schedule['subject_id'],
            'subject_name' => $schedule['subject_name'],
            'subject_code' => $schedule['subject_code'],
            'year_name' => $schedule['year_name'],
            'section_name' => $schedule['section_name'],
            'year_level_id' => $schedule['year_level_id'],
            'section_id' => $schedule['section_id'],
            'room' => $schedule['room'],
            'schedules_by_day' => []
        ];
    }

    $schedule_day = ucfirst(strtolower(trim($schedule['day'])));

    $grouped_subjects[$group_key]['schedules_by_day'][$schedule_day][] = $schedule;
}

$grouped_subjects = array_values($grouped_subjects);

foreach ($grouped_subjects as &$group) {
    $group['_day_index'] = 99;
    $group['_start_time'] = '23:59:59';

    foreach ($group['schedules_by_day'] as $day => $day_schedules) {
        $group['_day_index'] = min(
            $group['_day_index'],
            $day_order[$day] ?? 99
        );

        foreach ($day_schedules as $item) {
            if (
                !empty($item['start_time']) &&
                $item['start_time'] < $group['_start_time']
            ) {
                $group['_start_time'] = $item['start_time'];
            }
        }
    }
}
unset($group);

usort($grouped_subjects, function ($a, $b) {
    $teacher = strcasecmp(
        (string)$a['teacher_name'],
        (string)$b['teacher_name']
    );

    if ($teacher !== 0) return $teacher;

    if ($a['_day_index'] !== $b['_day_index']) {
        return $a['_day_index'] <=> $b['_day_index'];
    }

    $time = strcmp(
        (string)$a['_start_time'],
        (string)$b['_start_time']
    );

    if ($time !== 0) return $time;

    return strcasecmp(
        (string)$a['subject_name'],
        (string)$b['subject_name']
    );
});

$teacher_row_counts = [];

foreach ($grouped_subjects as $group) {
    $key = (string)$group['teacher_id'];

    $teacher_row_counts[$key] =
        ($teacher_row_counts[$key] ?? 0) + 1;
}

/* =========================================================
   RENDER WEEKLY REPORT TABLE
   ========================================================= */

function renderWeekTable(
    $week,
    $day_names,
    $grouped_subjects,
    $attendance,
    $teacher_row_counts
) {
    $week_dates = $week['dates'];
    $shown_teacher = [];
    ?>

    <div class="week-report">

        <div class="week-range">
            <i class="fa-solid fa-calendar-week"></i>

            <span>
                <strong><?= e(date('F d, Y', strtotime($week['monday']))) ?></strong>
                &nbsp;–&nbsp;
                <strong><?= e(date('F d, Y', strtotime($week['sunday']))) ?></strong>
            </span>
        </div>

        <div class="table-wrapper">
            <table class="report-table">

                <thead>
                    <tr>
                        <th>Teacher</th>
                        <th>Subject / Class</th>

                        <?php foreach ($day_names as $day): ?>
                            <th class="day-header">
                                <?= e($day) ?><br>
                                <small>
                                    <?= e(date('M d', strtotime($week_dates[$day]))) ?>
                                </small>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($grouped_subjects)): ?>

                    <tr>
                        <td colspan="9" style="text-align:center;padding:50px;">
                            <i class="fa-solid fa-calendar-xmark"
                               style="font-size:35px;color:#cbd5e1;"></i>

                            <div style="margin-top:10px;color:#64748b;">
                                No schedules found.
                            </div>
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($grouped_subjects as $group): ?>

                        <tr>
                            <?php
                            $teacher_key = (string)$group['teacher_id'];
                            $show_teacher = !isset($shown_teacher[$teacher_key]);
                            ?>

                            <?php if ($show_teacher): ?>

                                <td rowspan="<?= (int)$teacher_row_counts[$teacher_key] ?>">

                                    <div class="teacher-name">
                                        <?= e($group['teacher_name']) ?>
                                    </div>

                                    <?php if (!empty($group['employee_id'])): ?>
                                        <div class="employee-id">
                                            Employee ID: <?= e($group['employee_id']) ?>
                                        </div>
                                    <?php endif; ?>

                                </td>

                                <?php $shown_teacher[$teacher_key] = true; ?>

                            <?php endif; ?>

                            <td>
                                <div class="subject-name">
                                    <?= e($group['subject_name']) ?>
                                </div>

                                <?php if (!empty($group['subject_code'])): ?>
                                    <div class="subject-code">
                                        <?= e($group['subject_code']) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="class-info">
                                    <?php if (!empty($group['year_name'])): ?>
                                        <?= e($group['year_name']) ?>
                                    <?php endif; ?>

                                    <?php if (!empty($group['section_name'])): ?>
                                        - <?= e($group['section_name']) ?>
                                    <?php endif; ?>

                                    <?php if (!empty($group['room'])): ?>
                                        <br>Room: <?= e($group['room']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <?php foreach ($day_names as $day): ?>

                                <td>
                                    <?php
                                    $day_schedules =
                                        $group['schedules_by_day'][$day] ?? [];
                                    ?>

                                    <?php if (empty($day_schedules)): ?>

                                        <div class="empty-day">—</div>

                                    <?php else: ?>

                                        <?php foreach ($day_schedules as $schedule): ?>

                                            <?php
                                            $attendance_date = $week_dates[$day];

                                            $attendance_key =
                                                $schedule['schedule_id'] . '_' .
                                                $attendance_date;

                                            $att = $attendance[$attendance_key] ?? null;
                                            ?>

                                            <div class="attendance-box">

                                                <div class="schedule-time">
                                                    <i class="fa-regular fa-clock"></i>
                                                    <?= e(formatTime($schedule['start_time'])) ?>
                                                    -
                                                    <?= e(formatTime($schedule['end_time'])) ?>
                                                </div>

                                                <?php if ($att): ?>

                                                    <div class="actual-time">
                                                        <div class="time-row">
                                                            <span class="time-label">Time In</span>
                                                            <span class="time-value">
                                                                <?= e(formatTime($att['time_in'])) ?>
                                                            </span>
                                                        </div>

                                                        <div class="time-row">
                                                            <span class="time-label">Time Out</span>
                                                            <span class="time-value">
                                                                <?= e(formatTime($att['time_out'])) ?>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <?php
                                                    $status = strtolower(trim($att['status']));
                                                    $status_class = 'status-pending';

                                                    if ($status === 'present') {
                                                        $status_class = 'status-present';
                                                    } elseif ($status === 'late') {
                                                        $status_class = 'status-late';
                                                    } elseif ($status === 'absent') {
                                                        $status_class = 'status-absent';
                                                    } elseif ($status === 'excused') {
                                                        $status_class = 'status-excused';
                                                    }
                                                    ?>

                                                    <span class="status <?= e($status_class) ?>">
                                                        <?= e($att['status']) ?>
                                                    </span>

                                                    <?php if (!empty($att['remarks'])): ?>
                                                        <div class="remarks">
                                                            <strong>Remarks:</strong>
                                                            <?= e($att['remarks']) ?>
                                                        </div>
                                                    <?php endif; ?>

                                                <?php else: ?>

                                                    <div class="no-attendance">
                                                        No attendance record
                                                    </div>

                                                <?php endif; ?>

                                            </div>

                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                </td>

                            <?php endforeach; ?>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>

    <?php
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Teacher Attendance Report</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 30px;
    background: #f8fafc;
    font-family: 'Poppins', sans-serif;
    color: #1e293b;
}

.report-container {
    width: 100%;
    max-width: 1800px;
    margin: auto;
}

/* HEADER */
.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
}

.title-area h1 {
    margin: 0;
    font-size: 36px;
    font-weight: 700;
    color: #0f172a;
}

.title-area p {
    margin: 5px 0 0;
    font-size: 17px;
    color: #64748b;
}

/* WEEK SELECTOR */
.week-selector {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.week-selector label {
    font-size: 17px;
    font-weight: 600;
}

.week-selector input {
    height: 40px;
    padding: 0 10px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    background: #fff;
    font-family: inherit;
    font-size: 16px;
}

/* BUTTONS */
.btn {
    min-height: 40px;
    padding: 0 15px;
    border: 0;
    border-radius: 7px;
    background: #f97316;
    color: #fff;
    font-family: inherit;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s ease;
}

.btn:hover {
    background: #ea580c;
}

.btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

/* WEEK RANGE */
.week-range {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 18px;
    padding: 14px 17px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 17px;
}

.week-range i {
    color: #f97316;
}

/* TABLE */
.table-wrapper {
    width: 100%;
    overflow-x: auto;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(15, 23, 42, 0.05);
}

.report-table {
    width: 100%;
    min-width: 1450px;
    border-collapse: collapse;
}

.report-table th {
    padding: 13px 11px;
    background: #ec5c0d;
    color: #fff;
    font-size: 15px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.report-table th.day-header {
    text-align: center;
    min-width: 170px;
}

.report-table th:first-child {
    min-width: 180px;
}

.report-table th:nth-child(2) {
    min-width: 200px;
}

.report-table td {
    padding: 11px;
    border-right: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: top;
    font-size: 15px;
}

.report-table tr:last-child td {
    border-bottom: none;
}

/* TEACHER */
.teacher-name {
    color: #0f172a;
    font-size: 17px;
    font-weight: 700;
}

.employee-id {
    margin-top: 3px;
    color: #64748b;
    font-size: 14px;
}

/* SUBJECT */
.subject-name {
    color: #334155;
    font-size: 16px;
    font-weight: 600;
}

.subject-code {
    margin-top: 2px;
    color: #64748b;
    font-size: 14px;
}

.class-info {
    margin-top: 5px;
    color: #64748b;
    font-size: 14px;
    line-height: 1.5;
}

/* ATTENDANCE BOX */
.attendance-box {
    padding: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.schedule-time {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 7px;
    background: #fff7ed;
    color: #c2410c;
    border-radius: 5px;
    font-size: 14px;
    font-weight: 700;
}

.schedule-time i {
    font-size: 12px;
}

.actual-time {
    margin-top: 9px;
}

.time-row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 4px;
}

.time-label {
    color: #64748b;
    font-size: 14px;
}

.time-value {
    color: #0f172a;
    font-size: 14px;
    font-weight: 600;
}

/* STATUS */
.status {
    display: inline-block;
    margin-top: 5px;
    padding: 3px 7px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
}

.status-present {
    background: #dcfce7;
    color: #166534;
}

.status-late {
    background: #fef3c7;
    color: #92400e;
}

.status-absent {
    background: #fee2e2;
    color: #991b1b;
}

.status-excused {
    background: #dbeafe;
    color: #1e40af;
}

.status-pending {
    background: #e2e8f0;
    color: #475569;
}

/* REMARKS */
.remarks {
    margin-top: 7px;
    color: #64748b;
    font-size: 13px;
    line-height: 1.4;
}

/* EMPTY */
.no-attendance {
    padding: 7px 0;
    color: #94a3b8;
    text-align: center;
    font-size: 14px;
}

.empty-day {
    padding: 15px 0;
    color: #cbd5e1;
    text-align: center;
    font-size: 15px;
}

/* MONTH RANGE */
.range-selector {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 18px;
    padding: 15px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

.range-field {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.range-field label {
    font-size: 14px;
    font-weight: 600;
    color: #475569;
}

.range-field input {
    height: 40px;
    padding: 0 10px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    background: #fff;
    font-family: inherit;
    font-size: 15px;
}

.btn-range {
    background: #0f766e;
}

.btn-range:hover {
    background: #115e59;
}

/* FORMAT DROPDOWN */
.download-format {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 190px;
}

.download-format label {
    font-size: 14px;
    font-weight: 600;
    color: #475569;
}

.download-format select {
    width: 100%;
    height: 40px;
    padding: 0 12px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    background: #fff;
    color: #1e293b;
    font-family: inherit;
    font-size: 14px;
    cursor: pointer;
}

.btn-export {
    background: #15803d;
}

.btn-export:hover {
    background: #166534;
}

/* WEEKLY REPORTS */
.week-report {
    margin-bottom: 28px;
    break-after: page;
    page-break-after: always;
}

.week-report:last-child {
    break-after: auto;
    page-break-after: auto;
}

.range-label {
    margin: 0 0 12px;
    font-size: 15px;
    color: #64748b;
}

/* PDF LOADING OVERLAY */
.pdf-loading-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.72);
    backdrop-filter: blur(4px);
}

.pdf-loading-overlay.active {
    display: flex;
}

.pdf-loading-card {
    width: min(430px, calc(100vw - 40px));
    padding: 32px 28px;
    background: #fff;
    border-radius: 18px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
    font-family: Arial, sans-serif;
}

.pdf-spinner {
    width: 52px;
    height: 52px;
    margin: 0 auto 18px;
    border: 5px solid #e5e7eb;
    border-top-color: #f97316;
    border-radius: 50%;
    animation: pdfSpin .8s linear infinite;
}

@keyframes pdfSpin {
    to { transform: rotate(360deg); }
}

.pdf-loading-title {
    margin: 0 0 8px;
    font-size: 20px;
    font-weight: 700;
    color: #111827;
}

.pdf-loading-status {
    margin: 0 0 16px;
    font-size: 14px;
    color: #64748b;
}

.pdf-progress-track {
    width: 100%;
    height: 8px;
    overflow: hidden;
    background: #e5e7eb;
    border-radius: 999px;
}

.pdf-progress-bar {
    width: 0%;
    height: 100%;
    background: #f97316;
    border-radius: 999px;
    transition: width .2s ease;
}

.pdf-progress-percent {
    margin-top: 10px;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
}

/* MOBILE */
@media (max-width: 768px) {
    body {
        padding: 15px;
    }

    .report-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .week-selector {
        width: 100%;
    }

    .week-selector input {
        flex: 1;
        min-width: 0;
    }

    .download-format {
        width: 100%;
    }

    .btn-export {
        width: 100%;
    }
}

/* PRINT */
@media print {
    body {
        padding: 0;
        background: #fff;
    }

    .week-selector,
    .range-selector {
        display: none;
    }

    .week-range {
        border: none;
        padding-left: 0;
    }

    .table-wrapper {
        border: none;
        box-shadow: none;
        overflow: visible;
    }

    .report-table {
        min-width: 0;
    }

    .report-table th,
    .report-table td {
        padding: 5px;
    }

    .week-report {
        break-after: page;
        page-break-after: always;
    }

    .week-report:last-child {
        break-after: auto;
        page-break-after: auto;
    }
}
</style>
</head>

<body>

<div class="report-container" id="reportContainer">

    <div class="report-header">

<button type="button" class="btn" onclick="window.history.back()">
    <i class="fa-solid fa-arrow-left"></i> Back
</button>

        <div class="title-area">
            <h1>
                <i class="fa-solid fa-calendar-check"></i>
                Teacher Attendance Report
            </h1>
            <p>Weekly teacher schedule and attendance</p>
        </div>

        <form method="GET" class="week-selector">
            <label for="week">Select Week:</label>

            <input
                type="date"
                id="week"
                name="week"
                value="<?= e($monday_date) ?>"
            >

            <button type="submit" class="btn">
                <i class="fa-solid fa-filter"></i> View
            </button>
        </form>

    </div>

    <form method="GET" class="range-selector">

        <div class="range-field">
            <label for="from_month">From Month</label>
            <input
                type="month"
                id="from_month"
                name="from_month"
                value="<?= e($from_month ?: date('Y-m')) ?>"
                required
            >
        </div>

        <div class="range-field">
            <label for="to_month">To Month</label>
            <input
                type="month"
                id="to_month"
                name="to_month"
                value="<?= e($to_month ?: date('Y-m')) ?>"
                required
            >
        </div>

        <button type="submit" class="btn btn-range">
            <i class="fa-solid fa-calendar-days"></i>
            View Range
        </button>

        <!-- ONE DOWNLOAD FORMAT DROPDOWN -->
        <div class="download-format">

            <label for="downloadFormat">Choose Format</label>

            <select id="downloadFormat" name="downloadFormat">
                <option value="">Select format</option>
                <option value="pdf">PDF Format</option>
                <option value="excel">Excel Format (.xlsx)</option>
            </select>

            <button
                type="button"
                class="btn btn-export"
                id="downloadReportButton"
                onclick="downloadSelectedFormat()"
            >
                <i class="fa-solid fa-download"></i>
                Download Report
            </button>

        </div>

    </form>

    <?php if ($range_mode): ?>

        <div class="range-label">
            Showing attendance from
            <strong><?= e(date('F Y', strtotime($range_start_date))) ?></strong>
            to
            <strong><?= e(date('F Y', strtotime($range_end_date))) ?></strong>
            —
            <?= count($report_weeks) ?>
            weekly report<?= count($report_weeks) == 1 ? '' : 's' ?>.
        </div>

    <?php else: ?>

        <div class="week-range">
            <i class="fa-solid fa-calendar-week"></i>
            <span>
                <strong><?= e(date('F d, Y', strtotime($monday_date))) ?></strong>
                &nbsp;–&nbsp;
                <strong><?= e(date('F d, Y', strtotime($range_end_date))) ?></strong>
            </span>
        </div>

    <?php endif; ?>

    <?php foreach ($report_weeks as $week): ?>

        <?php
        renderWeekTable(
            $week,
            $day_names,
            $grouped_subjects,
            $attendance,
            $teacher_row_counts
        );
        ?>

    <?php endforeach; ?>

</div>

<!-- PDF LOADING OVERLAY -->
<div
    id="pdfLoadingOverlay"
    class="pdf-loading-overlay"
    aria-hidden="true"
>
    <div class="pdf-loading-card">

        <div class="pdf-spinner"></div>

        <h3 class="pdf-loading-title">Preparing your PDF</h3>

        <p id="pdfLoadingStatus" class="pdf-loading-status">
            Please wait while the report is being prepared...
        </p>

        <div class="pdf-progress-track">
            <div id="pdfProgressBar" class="pdf-progress-bar"></div>
        </div>

        <div id="pdfProgressPercent" class="pdf-progress-percent">0%</div>

    </div>
</div>

<script>
/* =========================================================
   CHOOSE DOWNLOAD FORMAT
   ========================================================= */

function downloadSelectedFormat() {
    const format = document.getElementById('downloadFormat').value;

    if (format === 'pdf') {
        downloadPDF();
    } else if (format === 'excel') {
        downloadExcel();
    } else {
        alert('Please choose a download format first.');
    }
}

/* =========================================================
   DOWNLOAD EXCEL
   ========================================================= */

function downloadExcel() {
    const report = document.getElementById('reportContainer');

    if (!report) {
        alert('Report content could not be found.');
        return;
    }

    if (typeof XLSX === 'undefined') {
        alert(
            'The Excel library did not load. ' +
            'Please check your internet connection and try again.'
        );
        return;
    }

    const weekReports = Array.from(
        report.querySelectorAll('.week-report')
    );

    if (!weekReports.length) {
        alert('There are no weekly reports to export.');
        return;
    }

    const button = document.getElementById('downloadReportButton');
    const formatSelect = document.getElementById('downloadFormat');
    const originalButtonHTML = button.innerHTML;

    try {
        button.disabled = true;
        formatSelect.disabled = true;
        button.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> Preparing Excel...';

        const workbook = XLSX.utils.book_new();

        weekReports.forEach(function(weekReport, index) {
            const table = weekReport.querySelector('.report-table');

            if (!table) return;

            const worksheet = XLSX.utils.table_to_sheet(table, {
                raw: false
            });

            worksheet['!cols'] = [
                { wch: 28 },
                { wch: 32 },
                { wch: 25 },
                { wch: 25 },
                { wch: 25 },
                { wch: 25 },
                { wch: 25 },
                { wch: 25 },
                { wch: 25 }
            ];

            const rangeText =
                weekReport.querySelector('.week-range')?.innerText ||
                ('Week ' + (index + 1));

            let sheetName = rangeText
                .replace(/[\[\]*?:/\\]/g, '')
                .trim()
                .substring(0, 31);

            if (!sheetName) {
                sheetName = 'Week ' + (index + 1);
            }

            let uniqueName = sheetName;
            let counter = 2;

            while (workbook.SheetNames.includes(uniqueName)) {
                const suffix = ' (' + counter + ')';
                uniqueName =
                    sheetName.substring(0, 31 - suffix.length) + suffix;
                counter++;
            }

            XLSX.utils.book_append_sheet(
                workbook,
                worksheet,
                uniqueName
            );
        });

        if (!workbook.SheetNames.length) {
            alert('No report tables were available for export.');
            return;
        }

        const startDate = <?= json_encode($range_start_date) ?>;
        const endDate = <?= json_encode($range_end_date) ?>;

        const filename =
            'Teacher_Attendance_Report_' +
            startDate + '_to_' + endDate + '.xlsx';

        XLSX.writeFile(workbook, filename);

    } catch (error) {
        console.error('Excel Export Error:', error);

        alert(
            'Unable to export the Excel file. Please try again.\n\n' +
            (error.message || error)
        );

    } finally {
        button.disabled = false;
        formatSelect.disabled = false;
        button.innerHTML = originalButtonHTML;
    }
}

/* =========================================================
   DOWNLOAD PDF
   ========================================================= */

async function downloadPDF() {
    const report = document.getElementById('reportContainer');

    if (!report) {
        alert('Report content could not be found.');
        return;
    }

    const button = document.getElementById('downloadReportButton');
    const formatSelect = document.getElementById('downloadFormat');
    const loadingOverlay = document.getElementById('pdfLoadingOverlay');
    const loadingStatus = document.getElementById('pdfLoadingStatus');
    const progressBar = document.getElementById('pdfProgressBar');
    const progressPercent = document.getElementById('pdfProgressPercent');
    const header = document.querySelector('.report-header');
    const rangeSelector = document.querySelector('.range-selector');
    const rangeLabel = document.querySelector('.range-label');
    const footer = document.querySelector('.report-footer');

    const weekReports = Array.from(
        report.querySelectorAll('.week-report')
    );

    if (!weekReports.length) {
        alert('There are no weekly reports to download.');
        return;
    }

    const originalButtonHTML = button.innerHTML;
    const originalHeaderDisplay = header ? header.style.display : '';
    const originalRangeDisplay = rangeSelector ? rangeSelector.style.display : '';
    const originalRangeLabelDisplay = rangeLabel ? rangeLabel.style.display : '';
    const originalFooterDisplay = footer ? footer.style.display : '';

    let captureArea = null;

    try {
        button.disabled = true;
        formatSelect.disabled = true;

        if (loadingOverlay) {
            loadingOverlay.classList.add('active');
            loadingOverlay.setAttribute('aria-hidden', 'false');
        }

        if (progressBar) progressBar.style.width = '0%';
        if (progressPercent) progressPercent.textContent = '0%';

        if (loadingStatus) {
            loadingStatus.textContent =
                'Preparing the report for PDF download...';
        }

        button.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> Preparing PDF...';

        if (header) header.style.display = 'none';
        if (rangeSelector) rangeSelector.style.display = 'none';
        if (rangeLabel) rangeLabel.style.display = 'none';
        if (footer) footer.style.display = 'none';

        if (document.fonts && document.fonts.ready) {
            await document.fonts.ready;
        }

        await new Promise(resolve =>
            requestAnimationFrame(() =>
                requestAnimationFrame(resolve)
            )
        );

        const { jsPDF } = window.jspdf || {};

        if (!jsPDF || typeof html2canvas === 'undefined') {
            throw new Error(
                'PDF libraries did not load. ' +
                'Please check your internet connection and try again.'
            );
        }

        const pdf = new jsPDF({
            orientation: 'landscape',
            unit: 'mm',
            format: 'a3',
            compress: true
        });

        const pageWidth = 420;
        const pageHeight = 297;
        const margin = 8;
        const usableWidth = pageWidth - (margin * 2);
        const usableHeight = pageHeight - (margin * 2);

        captureArea = document.createElement('div');
        captureArea.style.position = 'fixed';
        captureArea.style.left = '-100000px';
        captureArea.style.top = '0';
        captureArea.style.width = '1600px';
        captureArea.style.background = '#ffffff';
        captureArea.style.padding = '20px';
        captureArea.style.zIndex = '-99999';

        document.body.appendChild(captureArea);

        const reportStart =
            <?= json_encode(date('F Y', strtotime($range_start_date))) ?>;

        const reportEnd =
            <?= json_encode(date('F Y', strtotime($range_end_date))) ?>;

        for (let i = 0; i < weekReports.length; i++) {
            const progress = Math.round(
                (i / weekReports.length) * 100
            );

            if (progressBar) {
                progressBar.style.width = progress + '%';
            }

            if (progressPercent) {
                progressPercent.textContent = progress + '%';
            }

            if (loadingStatus) {
                loadingStatus.textContent =
                    'Generating weekly report ' +
                    (i + 1) + ' of ' + weekReports.length + '...';
            }

            button.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin"></i> PDF ' +
                (i + 1) + ' of ' + weekReports.length + '...';

            captureArea.innerHTML = '';

            const title = document.createElement('div');
            title.style.fontFamily = 'Arial, sans-serif';
            title.style.textAlign = 'center';
            title.style.marginBottom = '14px';

            title.innerHTML = `
                <div style="font-size:22px;font-weight:700;color:#111827;">
                    Teacher Attendance Report
                </div>
                <div style="margin-top:4px;font-size:12px;color:#64748b;">
                    Weekly Teacher Schedule and Attendance
                </div>
                <div style="margin-top:7px;font-size:12px;font-weight:600;color:#334155;">
                    ${reportStart} - ${reportEnd}
                </div>
            `;

            captureArea.appendChild(title);

            const clone = weekReports[i].cloneNode(true);
            clone.style.display = 'block';
            clone.style.width = '1560px';
            clone.style.maxWidth = 'none';
            clone.style.margin = '0';
            clone.style.padding = '0';
            clone.style.background = '#ffffff';
            clone.style.breakAfter = 'auto';
            clone.style.pageBreakAfter = 'auto';

            const clonedWrapper = clone.querySelector('.table-wrapper');

            if (clonedWrapper) {
                clonedWrapper.style.overflow = 'visible';
                clonedWrapper.style.boxShadow = 'none';
            }

            captureArea.appendChild(clone);

            await new Promise(resolve =>
                requestAnimationFrame(resolve)
            );

            const canvas = await html2canvas(captureArea, {
                scale: 1.15,
                useCORS: true,
                allowTaint: false,
                logging: false,
                backgroundColor: '#ffffff',
                windowWidth: 1600,
                scrollX: 0,
                scrollY: 0
            });

            if (!canvas.width || !canvas.height) {
                throw new Error('The report page could not be rendered.');
            }

            const imageData = canvas.toDataURL('image/jpeg', 0.94);
            const imageRatio = canvas.width / canvas.height;

            let imageWidth = usableWidth;
            let imageHeight = imageWidth / imageRatio;

            if (imageHeight > usableHeight) {
                imageHeight = usableHeight;
                imageWidth = imageHeight * imageRatio;
            }

            const x = (pageWidth - imageWidth) / 2;
            const y = (pageHeight - imageHeight) / 2;

            if (i > 0) {
                pdf.addPage('a3', 'landscape');
            }

            pdf.addImage(
                imageData,
                'JPEG',
                x,
                y,
                imageWidth,
                imageHeight,
                undefined,
                'FAST'
            );
        }

        if (captureArea) {
            captureArea.remove();
            captureArea = null;
        }

        const filename =
            'Teacher_Attendance_Report_' +
            <?= json_encode($range_start_date) ?> +
            '_to_' +
            <?= json_encode($range_end_date) ?> +
            '.pdf';

        if (progressBar) progressBar.style.width = '100%';
        if (progressPercent) progressPercent.textContent = '100%';

        if (loadingStatus) {
            loadingStatus.textContent = 'Finishing PDF download...';
        }

        await new Promise(resolve => setTimeout(resolve, 250));

        pdf.save(filename);

    } catch (error) {
        console.error('PDF Error:', error);

        alert(
            'Unable to generate the PDF. Please try again.\n\n' +
            (error.message || error)
        );

    } finally {
        if (captureArea) {
            captureArea.remove();
        }

        if (loadingOverlay) {
            loadingOverlay.classList.remove('active');
            loadingOverlay.setAttribute('aria-hidden', 'true');
        }

        if (header) header.style.display = originalHeaderDisplay;
        if (rangeSelector) rangeSelector.style.display = originalRangeDisplay;
        if (rangeLabel) rangeLabel.style.display = originalRangeLabelDisplay;
        if (footer) footer.style.display = originalFooterDisplay;

        button.disabled = false;
        formatSelect.disabled = false;
        button.innerHTML = originalButtonHTML;
    }
}
</script>

</body>
</html>
