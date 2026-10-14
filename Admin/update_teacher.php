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
// ONLY PROCESS POST REQUEST
// =========================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: add_teacher.php");
    exit();
}

// =========================================================
// CHECK UPDATE REQUEST
// =========================================================

if (!isset($_POST['update_teacher'])) {
    header("Location: add_teacher.php");
    exit();
}


// =========================================================
// GET FORM DATA
// =========================================================

$id = (int)($_POST['edit_id'] ?? 0);

$employee_id = trim($_POST['edit_employee'] ?? '');
$fullname = trim($_POST['edit_fullname'] ?? '');
$email = trim($_POST['edit_email'] ?? '');
$username = trim($_POST['edit_username'] ?? '');
$password = $_POST['edit_password'] ?? '';


// =========================================================
// VALIDATION
// =========================================================

if (
    $id <= 0 ||
    $employee_id === '' ||
    $fullname === '' ||
    $email === '' ||
    $username === ''
) {

    $_SESSION['toast_message'] =
        "Please fill in all required fields.";

    $_SESSION['toast_type'] = "error";

    header("Location: add_teacher.php");
    exit();
}


// =========================================================
// EMAIL VALIDATION
// =========================================================

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    $_SESSION['toast_message'] =
        "Please enter a valid email address.";

    $_SESSION['toast_type'] = "error";

    header("Location: add_teacher.php");
    exit();
}


// =========================================================
// PASSWORD VALIDATION
// =========================================================

if (
    $password !== '' &&
    strlen($password) < 6
) {

    $_SESSION['toast_message'] =
        "New password must be at least 6 characters.";

    $_SESSION['toast_type'] = "error";

    header("Location: add_teacher.php");
    exit();
}


try {

    // =====================================================
    // GET CURRENT TEACHER
    // =====================================================

    $stmt = $conn->prepare("
        SELECT
            id,
            employee_id,
            fullname,
            email,
            username,
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

        throw new Exception(
            "Teacher account was not found."
        );
    }


    // =====================================================
    // CHECK DUPLICATES
    // =====================================================

    $check = $conn->prepare("
        SELECT id
        FROM public.users
        WHERE id != :id
        AND (
            employee_id = :employee_id
            OR LOWER(email) = LOWER(:email)
            OR LOWER(username) = LOWER(:username)
        )
        LIMIT 1
    ");

    $check->execute([
        'id' => $id,
        'employee_id' => $employee_id,
        'email' => $email,
        'username' => $username
    ]);


    if ($check->fetch(PDO::FETCH_ASSOC)) {

        throw new Exception(
            "Employee ID, Email, or Username already exists."
        );
    }


    // =====================================================
    // KEEP CURRENT PROFILE IMAGE
    // =====================================================

    $profileImagePath =
        $teacher['profile_image'] ?? null;


    // =====================================================
    // CHECK NEW PROFILE IMAGE
    // =====================================================

    if (
        isset($_FILES['edit_profile_image']) &&
        $_FILES['edit_profile_image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        $file = $_FILES['edit_profile_image'];


        // =================================================
        // CHECK UPLOAD ERROR
        // =================================================

        if ($file['error'] !== UPLOAD_ERR_OK) {

            throw new Exception(
                "There was a problem uploading the profile picture."
            );
        }


        // =================================================
        // CHECK FILE SIZE
        // =================================================

        if ($file['size'] > 5 * 1024 * 1024) {

            throw new Exception(
                "Profile picture must not exceed 5 MB."
            );
        }


        // =================================================
        // VERIFY REAL IMAGE
        // =================================================

        $imageInfo =
            getimagesize($file['tmp_name']);


        if ($imageInfo === false) {

            throw new Exception(
                "The uploaded file is not a valid image."
            );
        }


        // =================================================
        // ALLOWED IMAGE TYPES
        // =================================================

        $allowedMimeTypes = [

            'image/jpeg' => 'jpg',

            'image/png' => 'png',

            'image/webp' => 'webp'

        ];


        $mimeType =
            $imageInfo['mime'];


        if (!isset($allowedMimeTypes[$mimeType])) {

            throw new Exception(
                "Only JPG, JPEG, PNG, and WEBP images are allowed."
            );
        }


        // =================================================
        // UPLOAD DIRECTORY
        // =================================================

        $uploadDir =
            __DIR__ . "/../uploads/teachers/";


        // Create folder if it does not exist

        if (!is_dir($uploadDir)) {

            if (!mkdir(
                $uploadDir,
                0755,
                true
            )) {

                throw new Exception(
                    "Unable to create teacher image folder."
                );
            }
        }


        // =================================================
        // CREATE UNIQUE FILE NAME
        // =================================================

        $extension =
            $allowedMimeTypes[$mimeType];


        $newFileName =
            'teacher_' .
            bin2hex(random_bytes(16)) .
            '.' .
            $extension;


        $destination =
            $uploadDir .
            $newFileName;


        // =================================================
        // MOVE UPLOADED IMAGE
        // =================================================

        if (!move_uploaded_file(
            $file['tmp_name'],
            $destination
        )) {

            throw new Exception(
                "Unable to save the new profile picture."
            );
        }


        // =================================================
        // DATABASE PATH
        // =================================================

        $profileImagePath =
            "uploads/teachers/" .
            $newFileName;


        // =================================================
        // DELETE OLD IMAGE
        // =================================================

        if (!empty($teacher['profile_image'])) {

            $oldImagePath =
                __DIR__ .
                "/../" .
                ltrim(
                    $teacher['profile_image'],
                    "/"
                );


            if (
                file_exists($oldImagePath) &&
                is_file($oldImagePath)
            ) {

                unlink($oldImagePath);
            }
        }
    }


    // =====================================================
    // UPDATE WITH NEW PASSWORD
    // =====================================================

    if ($password !== '') {

        $hashedPassword =
            password_hash(
                $password,
                PASSWORD_DEFAULT
            );


        $stmt = $conn->prepare("
            UPDATE public.users
            SET
                employee_id = :employee_id,
                fullname = :fullname,
                email = :email,
                username = :username,
                password = :password,
                profile_image = :profile_image
            WHERE id = :id
            AND role = 'teacher'
        ");


        $stmt->execute([

            'employee_id' =>
                $employee_id,

            'fullname' =>
                $fullname,

            'email' =>
                $email,

            'username' =>
                $username,

            'password' =>
                $hashedPassword,

            'profile_image' =>
                $profileImagePath,

            'id' =>
                $id
        ]);

    } else {

        // =================================================
        // UPDATE WITHOUT CHANGING PASSWORD
        // =================================================

        $stmt = $conn->prepare("
            UPDATE public.users
            SET
                employee_id = :employee_id,
                fullname = :fullname,
                email = :email,
                username = :username,
                profile_image = :profile_image
            WHERE id = :id
            AND role = 'teacher'
        ");


        $stmt->execute([

            'employee_id' =>
                $employee_id,

            'fullname' =>
                $fullname,

            'email' =>
                $email,

            'username' =>
                $username,

            'profile_image' =>
                $profileImagePath,

            'id' =>
                $id
        ]);
    }


    // =====================================================
    // SUCCESS
    // =====================================================

    $_SESSION['toast_message'] =
        "Teacher updated successfully!";

    $_SESSION['toast_type'] =
        "success";


} catch (PDOException $e) {

    $_SESSION['toast_message'] =
        "Error updating teacher. Please try again.";

    $_SESSION['toast_type'] =
        "error";

    // For debugging only:
    // $_SESSION['toast_message'] = $e->getMessage();

} catch (Exception $e) {

    $_SESSION['toast_message'] =
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