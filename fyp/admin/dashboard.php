<?php
session_start();

// 1. Only allow logged-in admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';

// 2. Get admin's name
$sql = "SELECT first_name, last_name FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

// 3. Fetch system stats
$stats = [];

// Total Students
$students_sql = "SELECT COUNT(*) as total FROM students";
$stats['students'] = $conn->query($students_sql)->fetch_assoc()['total'];

// Total Faculty
$faculty_sql = "SELECT COUNT(*) as total FROM faculty";
$stats['faculty'] = $conn->query($faculty_sql)->fetch_assoc()['total'];

// Active Semesters
$semesters_sql = "SELECT COUNT(*) as total FROM semesters";
$stats['active_semesters'] = $conn->query($semesters_sql)->fetch_assoc()['total'];

// Students Per Semester
$semester_student_sql = "
    SELECT sem.semester_number, COUNT(reg.student_id) as total
    FROM semesters sem
    JOIN student_semester_registration reg ON sem.semester_id = reg.semester_id
    WHERE reg.status = 'registered'
    GROUP BY sem.semester_number
    ORDER BY sem.semester_number ASC
";
$semester_students = $conn->query($semester_student_sql);

// Recently Registered Users (7 Days)
$recent_users_sql = "
    SELECT u.user_id, u.username, u.role, u.registration_date, 
           COALESCE(s.enrollment_number, f.employee_id, 'ADMIN') as identifier
    FROM users u
    LEFT JOIN students s ON u.user_id = s.student_id AND u.role = 'student'
    LEFT JOIN faculty f ON u.user_id = f.faculty_id AND u.role = 'faculty'
    WHERE u.registration_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY u.registration_date DESC
    LIMIT 5
";
$recent_users = $conn->query($recent_users_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | Academic System</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="dashboard">
    <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Academic System</h3>
                <p>Admin Dashboard</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                <li><a href="manage_users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>">Manage Users</a></li>
                <li><a href="manage_subjects.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_subjects.php' ? 'active' : ''; ?>">Manage Subjects</a></li>
                <li><a href="manage_semesters.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_semesters.php' ? 'active' : ''; ?>">Manage Semesters</a></li>
                <li><a href="manage_courses.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_courses.php' ? 'active' : ''; ?>">Manage Courses</a></li>
                <li><a href="timetable_management.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'timetable_management.php' ? 'active' : ''; ?>">Manage Timetable</a></li>
                <li><a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">Reports</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>

    <div class="main-content">
        <div class="header">
            <h2>Admin Dashboard</h2>
            <p>Welcome back <?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></p>
        </div>

        <!-- OVERVIEW STATS -->
        <div class="card">
            <h3>📊 System Overview</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['students'] ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['faculty'] ?></div>
                    <div class="stat-label">Total Faculty</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['active_semesters'] ?></div>
                    <div class="stat-label">Active Semesters</div>
                </div>
            </div>
        </div>

        <!-- STUDENTS PER SEMESTER -->
        <div class="card">
            <h3>👨‍🎓 Students by Semester</h3>
            <ul class = "no-bullets">
                <?php while ($row = $semester_students->fetch_assoc()): ?>
                    <li>Semester <?= htmlspecialchars($row['semester_number']) ?> — <?= htmlspecialchars($row['total']) ?> students</li>
                <?php endwhile; ?>
            </ul>
        </div>

        <!-- RECENT USERS -->
        <div class="card recent-users">
            <h3>🕓 Recently Registered Users (Last 7 Days)</h3>
            <?php if ($recent_users->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Identifier</th>
                            <th>Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $recent_users->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['user_id']) ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td>
                                    <span class="role-badge badge-<?= htmlspecialchars($user['role']) ?>">
                                        <?= ucfirst(htmlspecialchars($user['role'])) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($user['identifier']) ?></td>
                                <td><?= date('M d, Y', strtotime($user['registration_date'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No new users registered recently.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
