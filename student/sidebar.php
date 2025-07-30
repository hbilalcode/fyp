<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Academic System</title>
    <link rel="stylesheet" href="../assets/css/sidebar.css">
</head>
<body>
<div class="sidebar">
    <div class="sidebar-header">
        <h3>BS Academic System</h3>
        <p>Student Dashboard</p>
    </div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
        <li><a href="view_attendance.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'view_attendance.php' ? 'active' : ''; ?>">Attendance</a></li>
        <li><a href="timetable.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'timetable.php' ? 'active' : ''; ?>">Timetable</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</div>
</html>