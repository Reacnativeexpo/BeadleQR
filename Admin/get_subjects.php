<?php
require "../db.php";

$year_level_id = $_GET['year_level_id'];

/* Get subjects */
$stmt = $conn->prepare("
    SELECT id, subject_code, subject_name
    FROM subjects
    WHERE year_level_id = ?
");

$stmt->execute([$year_level_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Get assigned subjects (already used in schedules) */
$used = $conn->prepare("
    SELECT DISTINCT subject_id
    FROM schedules
    WHERE year_level_id = ?
");

$used->execute([$year_level_id]);
$usedSubjects = $used->fetchAll(PDO::FETCH_COLUMN);

/* Add flag: is_used */
foreach ($subjects as &$sub) {
    $sub['is_used'] = in_array($sub['id'], $usedSubjects);
}

echo json_encode($subjects);
?>