<?php
require "../db.php";

if (!isset($_GET['teacher_id'])) {
    exit();
}

$teacher_id = (int)$_GET['teacher_id'];

$teacher = $conn->prepare("
    SELECT fullname
    FROM users
    WHERE id = ?
");

$teacher->execute([$teacher_id]);
$t = $teacher->fetch(PDO::FETCH_ASSOC);

if (!$t) {
    exit("Teacher not found.");
}

echo "
<h5 class='mb-3'>
    Teacher: " . htmlspecialchars($t['fullname']) . "
</h5>
";

$schedules = $conn->prepare("
    SELECT
        s.*,
        sub.subject_code,
        sub.subject_name,
        yl.year_name,
        sec.section_name
    FROM schedules s

    LEFT JOIN subjects sub
        ON s.subject_id = sub.id

    LEFT JOIN year_levels yl
        ON s.year_level_id = yl.id

    LEFT JOIN sections sec
        ON s.section_id = sec.id

    WHERE s.teacher_id = ?

    ORDER BY
        CASE s.day
            WHEN 'Monday' THEN 1
            WHEN 'Tuesday' THEN 2
            WHEN 'Wednesday' THEN 3
            WHEN 'Thursday' THEN 4
            WHEN 'Friday' THEN 5
            WHEN 'Saturday' THEN 6
            ELSE 7
        END,
        s.start_time
");

$schedules->execute([$teacher_id]);

if ($schedules->rowCount() == 0) {

    echo "
    <div class='alert alert-warning'>
        No schedule assigned yet.
    </div>
    ";

    exit();
}
?>

<div class="table-responsive">

    <table class="table table-bordered table-hover">

        <thead class="table-dark">

            <tr>
                <th>Subject</th>
                <th>Year Level</th>
                <th>Section</th>
                <th>Room</th>
                <th>Day</th>
                <th>Start Time</th>
                <th>End Time</th>
            </tr>

        </thead>

        <tbody>

        <?php while($row = $schedules->fetch(PDO::FETCH_ASSOC)): ?>

            <tr>

                <td>
                    <?= htmlspecialchars($row['subject_code']) ?>
                    -
                    <?= htmlspecialchars($row['subject_name']) ?>
                </td>

                <td>
                    <?= htmlspecialchars($row['year_name']) ?>
                </td>

                <td>
                    <?= htmlspecialchars($row['section_name']) ?>
                </td>

                <td>
                    <?= htmlspecialchars($row['room']) ?>
                </td>

                <td>
                    <?= htmlspecialchars($row['day']) ?>
                </td>

                <td>
                    <?= date('h:i A', strtotime($row['start_time'])) ?>
                </td>

                <td>
                    <?= date('h:i A', strtotime($row['end_time'])) ?>
                </td>

            </tr>

        <?php endwhile; ?>

        </tbody>

    </table>

</div>