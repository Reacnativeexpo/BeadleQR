<?php
session_start();
require "../db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$teachers = $conn->query("
    SELECT *
    FROM users
    WHERE role = 'teacher'
    ORDER BY fullname ASC
");
?>

<!DOCTYPE html>
<html>
<head>

    <title>Teachers Management</title>

    <meta charset="utf-8">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>

        body{
            background:#f4f6f9;
        }

        .card{
            border:none;
            border-radius:12px;
        }

        .card-header{
            border-radius:12px 12px 0 0 !important;
        }

    </style>

</head>

<body>

<div class="container mt-5">

    <?php if(isset($_SESSION['success'])): ?>

        <div class="alert alert-success">

            <?php
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>

        </div>

    <?php endif; ?>

    <div class="card shadow">

        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">

            <h4 class="mb-0">Teachers Management</h4>

            <a href="add_teacher.php" class="btn btn-light btn-sm">

                Add Teacher

            </a>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-hover">

                    <thead class="table-dark">

                        <tr>

                            <th>ID</th>
                            <th>Employee ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Username</th>
                            <th width="220">Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php while($teacher = $teachers->fetch(PDO::FETCH_ASSOC)): ?>

                        <tr>

                            <td><?php echo $teacher['id']; ?></td>

                            <td><?php echo htmlspecialchars($teacher['employee_id']); ?></td>

                            <td><?php echo htmlspecialchars($teacher['fullname']); ?></td>

                            <td><?php echo htmlspecialchars($teacher['email']); ?></td>

                            <td><?php echo htmlspecialchars($teacher['username']); ?></td>

                            <td>

                                <a
                                    href="assign_schedule.php?id=<?php echo $teacher['id']; ?>"
                                    class="btn btn-primary btn-sm">

                                    Assign Schedule

                                </a>

                                <a
                                    href="view_schedule.php?id=<?php echo $teacher['id']; ?>"
                                    class="btn btn-success btn-sm">

                                    View Schedule

                                </a>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>
</html>