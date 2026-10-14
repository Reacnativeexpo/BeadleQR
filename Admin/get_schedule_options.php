<?php

session_start();
require "../db.php";

header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| ADMIN ACCESS
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| GET PARAMETERS
|--------------------------------------------------------------------------
*/

$year_level_id = filter_input(
    INPUT_GET,
    'year_level_id',
    FILTER_VALIDATE_INT
);

$subject_id = filter_input(
    INPUT_GET,
    'subject_id',
    FILTER_VALIDATE_INT
);


if (!$year_level_id) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid year level.',
        'subjects' => [],
        'sections' => [],
        'occupiedSections' => []
    ]);

    exit();
}


try {

    /*
    |--------------------------------------------------------------------------
    | SUBJECTS
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            id,
            subject_code,
            subject_name
        FROM public.subjects
        WHERE year_level_id = :year_level_id
        ORDER BY subject_name ASC
    ");

    $stmt->execute([
        'year_level_id' => $year_level_id
    ]);

    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | SECTIONS
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            id,
            section_name
        FROM public.sections
        WHERE year_level_id = :year_level_id
        ORDER BY section_name ASC
    ");

    $stmt->execute([
        'year_level_id' => $year_level_id
    ]);

    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | OCCUPIED SECTIONS
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | Do NOT mark a section as occupied simply because the selected
    | subject already exists in that section.
    |
    | Example:
    |
    | P.E. - Section A - Monday
    |
    | should NOT prevent:
    |
    | P.E. - Section A - Tuesday
    |
    | Therefore, occupiedSections remains empty here.
    |
    | The actual schedule conflict should be checked when the form
    | is submitted in assign_schedule.php.
    |
    |--------------------------------------------------------------------------
    */

    $occupiedSections = [];


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        'success' => true,
        'subjects' => $subjects,
        'sections' => $sections,
        'occupiedSections' => $occupiedSections
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Database error.',
        'subjects' => [],
        'sections' => [],
        'occupiedSections' => []
    ]);
}