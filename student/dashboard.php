<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$user_id = $_SESSION['user_id'];

// Fetch student and semester registration info
$sql = "SELECT s.*, u.first_name,u.last_name, sem.semester_number, sem.semester_id
        FROM students s
        JOIN users u ON u.user_id = s.student_id
        JOIN student_semester_registration ssr ON ssr.student_id = s.student_id AND ssr.status = 'registered'
        JOIN semesters sem ON sem.semester_id = ssr.semester_id
        WHERE s.student_id = ?
        ORDER BY ssr.registration_date DESC
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

$current_semester = $student['semester_id'];
$semester_number = $student['semester_number'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Dashboard</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="dashboard">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h2>Welcome, <?php echo htmlspecialchars($student['first_name'].' '.$student['last_name']); ?></h2>
            <p>Current Semester: Semester <?php echo $semester_number ?? 'N/A'; ?></p>
        </div>

        <!-- 📢 Announcements -->
        <div class="card">
            <h3>📢 Announcements</h3>
            <?php
            $announcement_sql = "
                SELECT a.title, a.message, a.is_important, a.created_at, u.first_name AS faculty_first, u.last_name AS faculty_last, s.subject_name
                FROM announcements a
                LEFT JOIN faculty f ON a.faculty_id = f.faculty_id
                LEFT JOIN users u ON u.user_id = f.faculty_id
                LEFT JOIN subjects s ON a.subject_id = s.subject_id
                WHERE a.semester_id = ?
                ORDER BY a.created_at DESC
            ";
            $stmt = $conn->prepare($announcement_sql);
            $stmt->bind_param("i", $current_semester);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                echo "<ul style='list-style:none; padding-left:0'>";
                while ($row = $result->fetch_assoc()) {
                    $important = $row['is_important'] ? "<span style='color:red;'>[IMPORTANT]</span> " : "";
                    $faculty_name = htmlspecialchars($row['faculty_first'] . ' ' . $row['faculty_last']);
                    $subject_name = htmlspecialchars($row['subject_name'] ?? 'General');
                    echo "<li style='margin-bottom:1rem; border-bottom:1px solid #eee; padding-bottom:1rem'>";
                    echo "<strong>{$important}{$row['title']}</strong><br>";
                    echo "<small>Subject: {$subject_name} | By: {$faculty_name} | Posted on: " . date("M d, Y", strtotime($row['created_at'])) . "</small>";
                    echo "<p>" . nl2br(htmlspecialchars($row['message'])) . "</p>";
                    echo "</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>No announcements yet for your semester.</p>";
            }
            ?>
        </div>

        <!-- 📂 Study Materials -->
        <div class="card">
            <h3>📂 Study Materials</h3>
            <?php
            $materials_sql = "
                SELECT m.title, m.description, m.file_path, m.file_type, m.upload_date, s.subject_name
                FROM study_materials m
                JOIN subjects s ON m.subject_id = s.subject_id
                WHERE m.semester_id = ?
                ORDER BY m.upload_date DESC
            ";
            $stmt = $conn->prepare($materials_sql);
            $stmt->bind_param("i", $current_semester);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                echo "<ul style='list-style:none; padding-left:0'>";
                while ($row = $result->fetch_assoc()) {
                    $subject_name = htmlspecialchars($row['subject_name']);
                    echo "<li style='margin-bottom:1rem; border-bottom:1px solid #eee; padding-bottom:1rem'>";
                    echo "<strong>" . htmlspecialchars($row['title']) . "</strong> <small>({$row['file_type']})</small><br>";
                    echo "<small>Subject: {$subject_name} | Uploaded: " . date("M d, Y", strtotime($row['upload_date'])) . "</small>";
                    echo "<p>" . nl2br(htmlspecialchars($row['description'])) . "</p>";
                    echo "<a class= 'btn' href='../uploads/materials/" . htmlspecialchars($row['file_path']) . "' target='_blank'>📥 Download</a>";
                    echo "</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>No study materials uploaded yet.</p>";
            }
            ?>
        </div>

        <!-- 📚 Assignments -->
        <div class="card">
            <h3>📚 Your Assignments</h3>
            <?php
            $assignments_sql = "
                SELECT a.assignment_id, a.title, a.description, a.deadline, a.file_path, s.subject_name
                FROM assignments a
                JOIN subjects s ON a.subject_id = s.subject_id
                WHERE a.semester_id = ?
                ORDER BY a.deadline ASC
            ";
            $stmt = $conn->prepare($assignments_sql);
            $stmt->bind_param("i", $current_semester);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                echo "<ul style='list-style:none; padding-left:0'>";
                while ($row = $result->fetch_assoc()) {
                    $subject_name = htmlspecialchars($row['subject_name']);
                    echo "<li style='margin-bottom:1rem; border-bottom:1px solid #eee; padding-bottom:1rem'>";
                    echo "<strong>" . htmlspecialchars($row['title']) . "</strong><br>";
                    echo "<small>Subject: {$subject_name} | Deadline: " . date("M d, Y H:i", strtotime($row['deadline'])) . "</small><br>";
                    echo "<p>" . nl2br(htmlspecialchars($row['description'])) . "</p>";
                    if (!empty($row['file_path'])) {
                        echo "<a class= 'btn' href='../uploads/assignments/" . htmlspecialchars($row['file_path']) . "' target='_blank'>📥 Download Assignment</a><br>";
                    }
                    echo "</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>No assignments available right now.</p>";
            }
            ?>
        </div>

        <!-- 📝 Quizzes -->
        <div class="card">
            <h3>📝 Available Quizzes</h3>
            <?php
            $quizzes_sql = "
                SELECT q.quiz_id, q.title, q.description, q.total_marks, q.time_limit, q.created_at, s.subject_name
                FROM quizzes q
                JOIN subjects s ON q.subject_id = s.subject_id
                WHERE q.semester_id = ?
                ORDER BY q.created_at DESC
            ";
            $stmt = $conn->prepare($quizzes_sql);
            $stmt->bind_param("i", $current_semester);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                echo "<ul style='list-style:none; padding-left:0'>";
                while ($row = $result->fetch_assoc()) {
                    $subject_name = htmlspecialchars($row['subject_name']);
                    echo "<li style='margin-bottom:1rem; border-bottom:1px solid #eee; padding-bottom:1rem'>";
                    echo "<strong>" . htmlspecialchars($row['title']) . "</strong><br>";
                    echo "<small>Subject: {$subject_name} | Marks: {$row['total_marks']} | Time: {$row['time_limit']} min | Created: " . date("M d, Y", strtotime($row['created_at'])) . "</small>";
                    echo "<p>" . nl2br(htmlspecialchars($row['description'])) . "</p>";
                    echo "</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>No quizzes available right now.</p>";
            }
            ?>
        </div>

    </div>
</div>
</body>
</html>
