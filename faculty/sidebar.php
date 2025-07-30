<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | BS Academic System</title>
    <link rel="stylesheet" href="../assets/css/sidebar.css">
</head>
<body>
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>BS Academic System</h3>
                <p>Faculty Dashboard</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                <li><a href="my_classes.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'my_classes.php' ? 'active' : ''; ?>">My Classes</a></li>
                <li><a href="mark_attendance.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'mark_attendance.php' ? 'active' : ''; ?>">Manage Attendance</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
</html>