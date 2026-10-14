<?php
session_start();
require "../db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: teachers.php");
    exit();
}

$teacher_id = $_GET['id'];

/* GET TEACHER */
$stmt = $conn->prepare("
    SELECT *
    FROM users
    WHERE id = ?
    AND role = 'teacher'
");
$stmt->execute([$teacher_id]);

$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    header("Location: teachers.php");
    exit();
}

$message = "";

/* SAVE SCHEDULE */
if (isset($_POST['save_schedule'])) {

    $subject_id = $_POST['subject_id'];
    $year_level_id = $_POST['year_level_id'];
    $section_id = $_POST['section_id'];
    $room = $_POST['room'];
    $day = $_POST['day'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    // 🔥 1. Validate subject belongs to year level
    $checkSubject = $conn->prepare("
        SELECT id 
        FROM subjects 
        WHERE id = :subject_id 
        AND year_level_id = :year_level_id
    ");

    $checkSubject->execute([
        'subject_id' => $subject_id,
        'year_level_id' => $year_level_id
    ]);

    if ($checkSubject->rowCount() == 0) {
        $_SESSION['error'] = "Invalid subject for selected year level!";
        header("Location: assign_schedule.php?id=" . $teacher_id);
        exit();
    }

    // 🔥 2. SECTION CONFLICT CHECK (IMPORTANT)
    $conflict = $conn->prepare("
        SELECT id 
        FROM schedules
        WHERE section_id = :section_id
        AND day = :day
        AND (
            (start_time < :end_time AND end_time > :start_time)
        )
    ");

    $conflict->execute([
        'section_id' => $section_id,
        'day' => $day,
        'start_time' => $start_time,
        'end_time' => $end_time
    ]);

    if ($conflict->rowCount() > 0) {
        $_SESSION['error'] = "❌ Section is already OCCUPIED at this time!";
        header("Location: assign_schedule.php?id=" . $teacher_id);
        exit();
    }

    // 🔥 3. INSERT SCHEDULE
    $save = $conn->prepare("
        INSERT INTO schedules
        (
            teacher_id,
            subject_id,
            year_level_id,
            section_id,
            room,
            day,
            start_time,
            end_time
        )
        VALUES
        (
            :teacher_id,
            :subject_id,
            :year_level_id,
            :section_id,
            :room,
            :day,
            :start_time,
            :end_time
        )
    ");

    $save->execute([
        'teacher_id' => $teacher_id,
        'subject_id' => $subject_id,
        'year_level_id' => $year_level_id,
        'section_id' => $section_id,
        'room' => $room,
        'day' => $day,
        'start_time' => $start_time,
        'end_time' => $end_time
    ]);

    $_SESSION['success'] = "Schedule assigned successfully.";
    header("Location: teachers.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Assign Schedule</title>
    <meta charset="utf-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5">

    <div class="card shadow">

        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Assign Schedule</h4>
        </div>

        <div class="card-body">

            <!-- SUCCESS / ERROR -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div class="alert alert-info">
                <strong>Teacher:</strong>
                <?php echo htmlspecialchars($teacher['fullname']); ?>
            </div>

            <form method="POST">

                <!-- YEAR LEVEL -->
                <div class="mb-3">
                    <label>Year Level</label>
                    <select name="year_level_id" id="yearLevel" class="form-select" required>
                        <option value="">Select Year Level</option>

                        <?php
                        $years = $conn->query("SELECT * FROM year_levels ORDER BY id ASC");
                        while ($y = $years->fetch(PDO::FETCH_ASSOC)):
                        ?>
                            <option value="<?php echo $y['id']; ?>">
                                <?php echo $y['year_name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- SUBJECT -->
                <div class="mb-3">
                    <label>Subject</label>
                    <select name="subject_id" id="subject" class="form-select" required>
                        <option value="">Select Year Level First</option>
                    </select>
                </div>

                <!-- SECTION (STATIC) -->
                <div class="mb-3">
                    <label>Section</label>
                    <select name="section_id" class="form-select" required>
                        <option value="">Select Section</option>

                        <?php
                        $sections = $conn->query("SELECT * FROM sections ORDER BY section_name ASC");
                        while ($sec = $sections->fetch(PDO::FETCH_ASSOC)):
                        ?>
                            <option value="<?php echo $sec['id']; ?>">
                                <?php echo $sec['section_name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- ROOM -->
                <div class="mb-3">
                    <label>Room</label>
                    <input type="text" name="room" class="form-control" required>
                </div>

                <!-- DAY -->
                <div class="mb-3">
                    <label>Day</label>
                    <select name="day" class="form-select" required>
                        <option>Monday</option>
                        <option>Tuesday</option>
                        <option>Wednesday</option>
                        <option>Thursday</option>
                        <option>Friday</option>
                        <option>Saturday</option>
                    </select>
                </div>

                <!-- TIME -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Start Time</label>
                        <input type="time" name="start_time" class="form-control" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>End Time</label>
                        <input type="time" name="end_time" class="form-control" required>
                    </div>
                </div>

                <button type="submit" name="save_schedule" class="btn btn-success">
                    Save Schedule
                </button>

                <a href="add_teacher.php" class="btn btn-secondary">
                    Back
                </a>

            </form>

        </div>
    </div>
</div>

<!-- JS: Year Level → Subject -->
<script>
document.getElementById("yearLevel").addEventListener("change", function () {

    let yearLevelId = this.value;

    fetch("get_subjects.php?year_level_id=" + yearLevelId)
        .then(res => res.json())
        .then(data => {

            let subject = document.getElementById("subject");
            subject.innerHTML = "<option value=''>Select Subject</option>";

            data.forEach(s => {
                subject.innerHTML += `
                    <option value="${s.id}">
                        ${s.subject_code} - ${s.subject_name}
                    </option>
                `;
            });

        });

});
</script>

</body>
</html>