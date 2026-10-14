<?php
session_start();

if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>

    <!-- GOOGLE FONT -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- FONT AWESOME -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
            font-family:'Poppins', sans-serif;
        }

        body{
            background:#f4f7fb;
        }

        /* TOPBAR */
        .topbar{
            width:100%;
            height:70px;
            background:#1e293b;
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:0 40px;
            box-shadow:0 2px 10px rgba(0,0,0,0.1);
        }

        .logo{
            color:white;
            font-size:22px;
            font-weight:600;
        }

        .menu{
            display:flex;
            gap:20px;
            align-items:center;
        }

        .menu a{
            text-decoration:none;
            color:white;
            font-size:15px;
            padding:10px 18px;
            border-radius:8px;
            transition:0.3s;
        }

        .menu a:hover{
            background:#334155;
        }

        .logout{
            background:#dc2626;
        }

        .logout:hover{
            background:#b91c1c !important;
        }

        /* CONTENT */
        .container{
            padding:40px;
        }

        .welcome-box{
            background:white;
            padding:30px;
            border-radius:15px;
            box-shadow:0 4px 15px rgba(0,0,0,0.05);
        }

        .welcome-box h1{
            color:#1e293b;
            margin-bottom:10px;
        }

        .welcome-box p{
            color:#64748b;
            font-size:15px;
        }

        /* DASHBOARD CARDS */
        .cards{
            margin-top:30px;
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(250px,1fr));
            gap:20px;
        }

        .card{
            background:white;
            padding:25px;
            border-radius:15px;
            box-shadow:0 4px 15px rgba(0,0,0,0.05);
            transition:0.3s;
        }

        .card:hover{
            transform:translateY(-5px);
        }

        .card i{
            font-size:35px;
            color:#2563eb;
            margin-bottom:15px;
        }

        .card h3{
            margin-bottom:10px;
            color:#1e293b;
        }

        .card p{
            color:#64748b;
            font-size:14px;
            margin-bottom:20px;
        }

        .card a{
            text-decoration:none;
            background:#2563eb;
            color:white;
            padding:10px 18px;
            border-radius:8px;
            display:inline-block;
            font-size:14px;
        }

        .card a:hover{
            background:#1d4ed8;
        }
    </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">

    <div class="logo">
        Smart Attendance System
    </div>

    <div class="menu">
        <a href="dashboard.php">
            <i class="fa fa-home"></i> Dashboard
        </a>

        <a href="add_teacher.php">
            <i class="fa fa-calendar"></i> Schedules
        </a>

        <a href="attendance.php">
            <i class="fa fa-clipboard-check"></i> Attendance
        </a>
        <a href="create_subject.php">
    <i class="fa fa-book"></i> Subjects
</a>

        <a href="../logout.php" class="logout">
            <i class="fa fa-sign-out-alt"></i> Logout
        </a>
    </div>

</div>

<!-- CONTENT -->
<div class="container">

    <div class="welcome-box">
        <h1>Welcome Admin</h1>
        <p>
            Hello, <?php echo $_SESSION['fullname']; ?> 👋
        </p>
    </div>

    <!-- DASHBOARD CARDS -->
    <div class="cards">

        <div class="card">
            <i class="fa fa-user-tie"></i>

            <h3>Teacher Schedule</h3>

            <p>
                Manage teacher schedules, subjects, and sections.
            </p>

            <a href="add_teacher.php">
                Open
            </a>
        </div>

        <div class="card">
            <i class="fa fa-calendar-check"></i>

            <h3>Attendance</h3>

            <p>
                Monitor Teacher attendance and check records.
            </p>

            <a href="attendance.php">
                Open
            </a>
        </div>
        <div class="card">
    <i class="fa fa-book-open"></i>

    <h3>Create Subject</h3>

    <p>
        Add and manage school subjects for teachers and schedules.
    </p>

    <a href="create_subject.php">
        Open
    </a>
</div>

    </div>

</div>

</body>
</html>