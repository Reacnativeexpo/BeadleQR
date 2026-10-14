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
$yearLevels = $conn->query("
    SELECT id, year_name FROM year_levels ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   LOAD SECTIONS
========================= */
$sections = $conn->query("
    SELECT id, section_name FROM sections ORDER BY section_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   LOAD STUDENTS (FOR MODAL)
========================= */
$students = $conn->query("
    SELECT id, fullname, student_no
    FROM users
    WHERE role = 'student'
    ORDER BY fullname ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   SAVE STUDENT
========================= */
if (isset($_POST['save'])) {

    $fullname      = trim($_POST['fullname']);
    $student_no    = trim($_POST['student_no']);
    $username      = trim($_POST['username']);
    $password      = trim($_POST['password']);
    $year_level_id = $_POST['year_level_id'];
    $section_id    = $_POST['section_id'];

    if (empty($fullname) || empty($student_no) || empty($username) || empty($password)) {
        $message = "<div class='alert alert-danger'>All fields are required.</div>";
    } else {

        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);

        if ($check->rowCount() > 0) {
            $message = "<div class='alert alert-danger'>Username already exists.</div>";
        } else {

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO users (
                    fullname, username, password, role,
                    student_no, year_level_id, section_id
                )
                VALUES (
                    :fullname, :username, :password, 'student',
                    :student_no, :year_level_id, :section_id
                )
            ");

            $stmt->execute([
                'fullname' => $fullname,
                'username' => $username,
                'password' => $hashedPassword,
                'student_no' => $student_no,
                'year_level_id' => $year_level_id,
                'section_id' => $section_id
            ]);

            $message = "<div class='alert alert-success'>Student created successfully.</div>";
        }
    }
}

/* =========================
   ASSIGN BEADER (MODAL)
========================= */
if (isset($_POST['assign_beader'])) {

    $schedule_id = $_POST['schedule_id'];
    $student_id  = $_POST['student_id'];

    $check = $conn->prepare("
        SELECT id FROM schedule_monitors WHERE schedule_id = ?
    ");
    $check->execute([$schedule_id]);

    if ($check->rowCount() > 0) {

        $update = $conn->prepare("
            UPDATE schedule_monitors
            SET student_id = ?
            WHERE schedule_id = ?
        ");
        $update->execute([$student_id, $schedule_id]);

    } else {

        $insert = $conn->prepare("
            INSERT INTO schedule_monitors (schedule_id, student_id)
            VALUES (?, ?)
        ");
        $insert->execute([$schedule_id, $student_id]);
    }

    $message = "<div class='alert alert-success'>Beader assigned successfully.</div>";
}

/* =========================
   LOAD SCHEDULES (FOR MODAL)
========================= */
$schedules = $conn->query("
    SELECT s.id, sub.subject_name, sec.section_name, s.day
    FROM schedules s
    JOIN subjects sub ON sub.id = s.subject_id
    JOIN sections sec ON sec.id = s.section_id
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Student + Beader</title>
    <meta charset="utf-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5">

    <?= $message ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Add Student</h3>

        <!-- BUTTON OPEN MODAL -->
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#beaderModal">
            Assign Beader
        </button>
    </div>

    <!-- ADD STUDENT FORM -->
    <form method="POST" class="card p-3">

        <input type="text" name="fullname" class="form-control mb-2" placeholder="Full Name" required>
        <input type="text" name="student_no" class="form-control mb-2" placeholder="Student No" required>
        <input type="text" name="username" class="form-control mb-2" placeholder="Username" required>
        <input type="password" name="password" class="form-control mb-2" placeholder="Password" required>

        <select name="year_level_id" class="form-control mb-2" required>
            <option value="">Year Level</option>
            <?php foreach ($yearLevels as $y): ?>
                <option value="<?= $y['id'] ?>"><?= $y['year_name'] ?></option>
            <?php endforeach; ?>
        </select>

        <select name="section_id" class="form-control mb-2" required>
            <option value="">Section</option>
            <?php foreach ($sections as $s): ?>
                <option value="<?= $s['id'] ?>"><?= $s['section_name'] ?></option>
            <?php endforeach; ?>
        </select>

        <button name="save" class="btn btn-success">
            Save Student
        </button>

    </form>

</div>

<!-- =========================
     BEADER MODAL
========================= -->
<div class="modal fade" id="beaderModal">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5>Assign Beader</h5>
            </div>

            <div class="modal-body">

                <form method="POST">

                    <select name="schedule_id" class="form-control mb-2" required>
                        <option value="">Select Schedule</option>
                        <?php foreach ($schedules as $sc): ?>
                            <option value="<?= $sc['id'] ?>">
                                <?= $sc['subject_name'] ?> - <?= $sc['section_name'] ?> (<?= $sc['day'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="student_id" class="form-control mb-2" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $st): ?>
                            <option value="<?= $st['id'] ?>">
                                <?= $st['fullname'] ?> (<?= $st['student_no'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button name="assign_beader" class="btn btn-primary w-100">
                        Assign as Beader
                    </button>

                </form>

            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>