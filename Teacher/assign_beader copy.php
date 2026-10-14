<?php

session_start();
require "../db.php";

/* =========================
   ROLE CHECK
========================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'teacher') {
    header("Location: ../login.php");
    exit();
}

$message = "";

/* =========================
   LOAD YEAR LEVELS
========================= */
$stmt = $conn->query("
    SELECT id, year_name
    FROM year_levels
    ORDER BY id ASC
");
$yearLevels = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   LOAD SECTIONS
========================= */
$stmt = $conn->query("
    SELECT id, section_name
    FROM sections
    ORDER BY section_name ASC
");
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   SAVE STUDENT + AUTO ASSIGN BEADER
========================= */
if (isset($_POST['save'])) {

    $fullname      = trim($_POST['fullname']);
    $student_no    = trim($_POST['student_no']);
    $username      = trim($_POST['username']);
    $password      = trim($_POST['password']);
    $year_level_id = $_POST['year_level_id'];
    $section_id    = $_POST['section_id'];

    $schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;

    if (
        empty($fullname) ||
        empty($student_no) ||
        empty($username) ||
        empty($password)
    ) {
        $message = "<div class='alert alert-danger'>All fields are required.</div>";
    } else {

        try {

            /* CHECK USERNAME */
            $check = $conn->prepare("
                SELECT id FROM users WHERE username = ?
            ");
            $check->execute([$username]);

            if ($check->rowCount() > 0) {
                $message = "<div class='alert alert-danger'>Username already exists.</div>";
            } else {

                /* =========================
                   INSERT USER
                ========================= */
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                    INSERT INTO users (
                        fullname,
                        username,
                        password,
                        role,
                        student_no,
                        year_level_id,
                        section_id
                    )
                    VALUES (
                        :fullname,
                        :username,
                        :password,
                        'student',
                        :student_no,
                        :year_level_id,
                        :section_id
                    )
                ");

                $stmt->execute([
                    'fullname'      => $fullname,
                    'username'      => $username,
                    'password'      => $hashedPassword,
                    'student_no'    => $student_no,
                    'year_level_id' => $year_level_id,
                    'section_id'    => $section_id
                ]);

                $student_id = $conn->lastInsertId();

                /* =========================
                   AUTO ASSIGN AS BEADER
                   (NO is_active USED)
                ========================= */
                if ($schedule_id > 0) {

                    $checkMonitor = $conn->prepare("
                        SELECT id FROM schedule_monitors
                        WHERE schedule_id = ?
                    ");
                    $checkMonitor->execute([$schedule_id]);
                    $exists = $checkMonitor->fetch(PDO::FETCH_ASSOC);

                    if ($exists) {

                        $update = $conn->prepare("
                            UPDATE schedule_monitors
                            SET student_id = ?
                            WHERE schedule_id = ?
                        ");

                        $update->execute([$student_id, $schedule_id]);

                    } else {

                        $insert = $conn->prepare("
                            INSERT INTO schedule_monitors
                            (schedule_id, student_id)
                            VALUES (?, ?)
                        ");

                        $insert->execute([$schedule_id, $student_id]);
                    }
                }

                $message = "<div class='alert alert-success'>
                                Student created and assigned as beader successfully.
                            </div>";
            }

        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Student</title>
    <meta charset="utf-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5">

    <div class="card shadow">

        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Add Student</h4>
        </div>

        <div class="card-body">

            <?= $message ?>

            <form method="POST">

                <input type="hidden" name="schedule_id" value="<?= $_GET['schedule_id'] ?? '' ?>">

                <div class="mb-3">
                    <label>Full Name</label>
                    <input type="text" name="fullname" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Student Number</label>
                    <input type="text" name="student_no" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Year Level</label>
                    <select name="year_level_id" class="form-select" required>
                        <option value="">Select Year Level</option>
                        <?php foreach ($yearLevels as $year): ?>
                            <option value="<?= $year['id']; ?>">
                                <?= htmlspecialchars($year['year_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label>Section</label>
                    <select name="section_id" class="form-select" required>
                        <option value="">Select Section</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?= $section['id']; ?>">
                                <?= htmlspecialchars($section['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="save" class="btn btn-success">
                    Save Student & Assign as Beader
                </button>

                <a href="dashboard.php" class="btn btn-secondary">Back</a>

            </form>

        </div>

    </div>

</div>

</body>
</html>