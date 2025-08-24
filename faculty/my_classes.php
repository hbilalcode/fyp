<?php
session_start();

// 1. Access control: only faculty allowed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';

$faculty_id = $_SESSION['user_id'];

// 2. Fetch faculty class schedules with related info
$schedule_sql = "SELECT 
                    cs.schedule_id,
                    s.subject_name,
                    sem.semester_number,
                    cs.day_of_week,
                    cs.start_time,
                    cs.end_time,
                    cs.start_date,
                    cs.end_date,
                    cr.room_number
                FROM class_schedule cs
                JOIN subjects s ON cs.subject_id = s.subject_id
                JOIN semesters sem ON cs.semester_id = sem.semester_id
                JOIN classrooms cr ON cs.classroom_id = cr.classroom_id
                WHERE cs.faculty_id = ?
                ORDER BY sem.semester_number, cs.day_of_week, cs.start_time";

$stmt = $conn->prepare($schedule_sql);
if (!$stmt) {
    die("SQL Error: " . $conn->error);
}
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$schedules = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>My Classes | BS Academic System</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
</head>
<body>
<div class="dashboard">
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

    <div class="main-content">
        <div class="header">
            <h2>My Class Schedule</h2>
        </div>

        <div class="card">
            <?php if ($schedules->num_rows > 0): ?>
                <div class="classes-grid">
                    <?php while ($class = $schedules->fetch_assoc()): ?>
                        <div class="class-card">
                            <h3 class="class-title"><?= htmlspecialchars($class['subject_name']) ?></h3>
                            <div class="class-meta">
                                <p><strong>Semester:</strong> <?= htmlspecialchars($class['semester_number']) ?></p>
                                <p><strong>Day:</strong> <?= htmlspecialchars($class['day_of_week']) ?></p>
                                <p><strong>Time:</strong> <?= htmlspecialchars($class['start_time']) ?> - <?= htmlspecialchars($class['end_time']) ?></p>
                                <p><strong>Room:</strong> <?= htmlspecialchars($class['room_number']) ?></p>
                                <p><strong>Duration:</strong> From <?= htmlspecialchars($class['start_date']) ?> to <?= htmlspecialchars($class['end_date']) ?></p>
                            </div>
                            <div class="class-actions">
                                <a href="mark_attendance.php?schedule=<?= (int)$class['schedule_id'] ?>" class="action-btn btn-assign">Attendance</a>
                                <a href="upload_assignment.php?schedule=<?= (int)$class['schedule_id'] ?>" class="action-btn btn-edit">Assignments</a>
                                <a href="quizzes.php?schedule=<?= (int)$class['schedule_id'] ?>" class="action-btn btn-edit">Quizzes</a>
                                <a href="upload_material.php?schedule=<?= (int)$class['schedule_id'] ?>" class="action-btn btn-edit">Materials</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p>You are not assigned to any class schedules yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
