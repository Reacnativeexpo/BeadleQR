<?php

session_start();
require "../db.php";


// =========================================================
// ADMIN ACCESS CHECK
// =========================================================

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}


// =========================================================
// CHECK TEACHER ID
// =========================================================

if (!isset($_GET['id'])) {
    $_SESSION['toast_message'] = "Teacher ID is missing.";
    $_SESSION['toast_type'] = "error";

    header("Location: add_teacher.php");
    exit();
}


$id = (int)$_GET['id'];


try {

    // =====================================================
    // GET TEACHER
    // =====================================================

    $stmt = $conn->prepare("
        SELECT
            id,
            fullname,
            profile_image
        FROM public.users
        WHERE id = :id
        AND role = 'teacher'
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $id
    ]);

    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$teacher) {

        $_SESSION['toast_message'] =
            "Teacher not found.";

        $_SESSION['toast_type'] =
            "error";

        header("Location: add_teacher.php");
        exit();
    }


    // =====================================================
    // START TRANSACTION
    // =====================================================

    $conn->beginTransaction();


    // =====================================================
    // 1. GET ALL SCHEDULE IDS OF THIS TEACHER
    // =====================================================

    $scheduleStmt = $conn->prepare("
        SELECT id
        FROM public.schedules
        WHERE teacher_id = :teacher_id
    ");

    $scheduleStmt->execute([
        'teacher_id' => $id
    ]);

    $scheduleIds = $scheduleStmt->fetchAll(PDO::FETCH_COLUMN);


    // =====================================================
    // 2. DELETE TEACHER ATTENDANCE
    // =====================================================
    // teacher_attendance references:
    //
    // teacher_id
    // schedule_id
    // session_id
    // beader_id
    //
    // It must be deleted before sessions/schedules.


    $deleteTeacherAttendance = $conn->prepare("
        DELETE FROM public.teacher_attendance
        WHERE teacher_id = :teacher_id
    ");

    $deleteTeacherAttendance->execute([
        'teacher_id' => $id
    ]);


    // =====================================================
    // 3. DELETE ATTENDANCE SESSIONS
    // =====================================================
    // attendance_sessions references schedules.
    //
    // Delete sessions belonging to the teacher's schedules.


    if (!empty($scheduleIds)) {

        $placeholders = [];

        foreach ($scheduleIds as $index => $scheduleId) {
            $placeholders[] = ":schedule_" . $index;
        }

        $sql = "
            DELETE FROM public.attendance_sessions
            WHERE schedule_id IN (
                " . implode(',', $placeholders) . "
            )
        ";

        $deleteSessions = $conn->prepare($sql);

        foreach ($scheduleIds as $index => $scheduleId) {
            $deleteSessions->bindValue(
                ":schedule_" . $index,
                $scheduleId,
                PDO::PARAM_INT
            );
        }

        $deleteSessions->execute();
    }


    // =====================================================
    // 4. DELETE BEADER ASSIGNMENTS
    // =====================================================
    // A teacher may have been assigned as a Beadle.
    //
    // beader_assignments.beader_id references users.id.


    $deleteBeaderAssignments = $conn->prepare("
        DELETE FROM public.beader_assignments
        WHERE beader_id = :teacher_id
    ");

    $deleteBeaderAssignments->execute([
        'teacher_id' => $id
    ]);


    // =====================================================
    // 5. DELETE SCHEDULES
    // =====================================================


    $deleteSchedules = $conn->prepare("
        DELETE FROM public.schedules
        WHERE teacher_id = :teacher_id
    ");

    $deleteSchedules->execute([
        'teacher_id' => $id
    ]);


    // =====================================================
    // 6. DELETE TEACHER
    // =====================================================


    $deleteTeacher = $conn->prepare("
        DELETE FROM public.users
        WHERE id = :id
        AND role = 'teacher'
    ");

    $deleteTeacher->execute([
        'id' => $id
    ]);


    // =====================================================
    // CHECK DELETE
    // =====================================================

    if ($deleteTeacher->rowCount() === 0) {

        $conn->rollBack();

        $_SESSION['toast_message'] =
            "Teacher could not be deleted.";

        $_SESSION['toast_type'] =
            "error";

        header("Location: add_teacher.php");
        exit();
    }


    // =====================================================
    // COMMIT DATABASE CHANGES
    // =====================================================

    $conn->commit();


    // =====================================================
    // DELETE PROFILE IMAGE
    // =====================================================

    if (!empty($teacher['profile_image'])) {

        $imagePath =
            __DIR__ .
            "/../" .
            ltrim(
                $teacher['profile_image'],
                "/"
            );


        if (
            file_exists($imagePath) &&
            is_file($imagePath)
        ) {

            unlink($imagePath);
        }
    }


    // =====================================================
    // SUCCESS MESSAGE
    // =====================================================

    $_SESSION['toast_message'] =
        "Teacher deleted successfully!";

    $_SESSION['toast_type'] =
        "success";


} catch (PDOException $e) {

    // =====================================================
    // ROLLBACK
    // =====================================================

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }


    $_SESSION['toast_message'] =
        "Cannot delete teacher because related records still exist.";

    $_SESSION['toast_type'] =
        "error";


    // For debugging, temporarily use:
    // $_SESSION['toast_message'] = $e->getMessage();


} catch (Exception $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }


    $_SESSION['toast_message'] =
        "Error deleting teacher: " .
        $e->getMessage();

    $_SESSION['toast_type'] =
        "error";
}


// =========================================================
// RETURN TO TEACHER PAGE
// =========================================================

header("Location: add_teacher.php");
exit();

?>