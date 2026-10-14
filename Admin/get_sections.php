<?php
require "../db.php";
header("Content-Type: application/json");

$year_level_id = $_GET['year_level_id'] ?? 0;

if (!$year_level_id) {
    echo json_encode([]);
    exit();
}

/*
    1. GET ALL SECTIONS (NO JOIN, NO FILTER)
*/
$sections = $conn->prepare("
    SELECT id, section_name
    FROM sections
    WHERE year_level_id = ?
    ORDER BY section_name ASC
");
$sections->execute([$year_level_id]);
$sections = $sections->fetchAll(PDO::FETCH_ASSOC);

/*
    2. GET USED SECTIONS ONLY
*/
$used = $conn->prepare("
    SELECT DISTINCT section_id
    FROM schedules
    WHERE year_level_id = ?
");
$used->execute([$year_level_id]);
$used = $used->fetchAll(PDO::FETCH_COLUMN);

/*
    3. MERGE DATA IN PHP
*/
$result = [];

foreach ($sections as $s) {

    $result[] = [
        "id" => $s["id"],
        "section_name" => $s["section_name"],
        "is_used" => in_array($s["id"], $used) ? 1 : 0
    ];
}

echo json_encode($result);