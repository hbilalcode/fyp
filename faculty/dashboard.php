<?php
session_start();

// 1. Access control: only faculty can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';

$user_id = $_SESSION['user_id'];

// 2. Fetch faculty details with user name
$sql = "SELECT f.*, u.first_name, u.last_name 
        FROM faculty f 
        JOIN users u ON f.faculty_id = u.user_id 
        WHERE f.faculty_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Faculty Dashboard | BS Academic System</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
</head>
<body>
<div class="dashboard">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="faculty-header">
            <h2>Welcome, <?= htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']) ?></h2>
            <p>Department: <?= htmlspecialchars($faculty['department'] ?? 'N/A') ?></p>
        </div>

        <div class="dashboard-cards">
            <div class="faculty-card">
                <h3>📤 Upload Assignment</h3>
                <a href="upload_assignment.php" class="btn">Upload Now</a>
            </div>

            <div class="faculty-card">
                <h3>🧪 Create Quiz</h3>
                <a href="quizzes.php" class="btn">Create Quiz</a>
            </div>

            <div class="faculty-card">
                <h3>📢 Post Announcement</h3>
                <a href="post_announcement.php" class="btn">Post Announcement</a>
            </div>

            <div class="faculty-card">
                <h3>📂 Upload Study Material</h3>
                <a href="upload_material.php" class="btn">Upload Material</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
