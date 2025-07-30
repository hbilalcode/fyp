<?php
session_start();

// 1. Restrict access to admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';

$success = $error = null;

// 2. Handle assignment removal
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'remove') {
    $id = (int) $_GET['id'];
    $stmt = $conn->prepare("DELETE FROM semester_subjects WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success = "✅ Assignment removed successfully.";
        } else {
            $error = "❌ Failed to remove assignment.";
        }
    } else {
        $error = "❌ Failed to prepare statement for removal.";
    }
}

// 3. Handle new assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_subject'])) {
    // Validate and sanitize inputs
    $subject_id = (int) $_POST['subject_id'];
    $semester_id = (int) $_POST['semester_id'];
    $faculty_id = (int) $_POST['faculty_id'];

    // Optional: Check if this assignment already exists to avoid duplicates

    $stmt = $conn->prepare("INSERT INTO semester_subjects (semester_id, subject_id, faculty_id) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iii", $semester_id, $subject_id, $faculty_id);
        if ($stmt->execute()) {
            $success = "✅ Subject assigned to semester and faculty.";
        } else {
            $error = "❌ Failed to assign subject: " . $stmt->error;
        }
    } else {
        $error = "❌ Failed to prepare statement for assignment.";
    }
}

// 4. Fetch all subjects ordered by name
$subjects = $conn->query("SELECT * FROM subjects ORDER BY subject_name");

// 5. Fetch all semesters ordered by semester number
$semesters = $conn->query("SELECT * FROM semesters ORDER BY semester_number");

// 6. Fetch all faculty members with user names
$faculty = $conn->query("
    SELECT f.faculty_id, u.first_name, u.last_name 
    FROM faculty f
    JOIN users u ON f.faculty_id = u.user_id
    ORDER BY u.last_name, u.first_name
");

// 7. Fetch all current assignments with joined details
$assignments = $conn->query("
    SELECT ss.id, s.subject_name, s.subject_code, sem.semester_number, 
           u.first_name AS faculty_first, u.last_name AS faculty_last
    FROM semester_subjects ss
    JOIN subjects s ON ss.subject_id = s.subject_id
    JOIN semesters sem ON ss.semester_id = sem.semester_id
    JOIN users u ON ss.faculty_id = u.user_id
    ORDER BY sem.semester_number, s.subject_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Manage Courses | BS Academic System</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
</head>
<body>
<div class="dashboard">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h2>📚 Course Management</h2>
        </div>

        <div class="card">
            <?php if (!empty($success)): ?>
                <div class="alert success"><?= htmlspecialchars($success) ?></div>
            <?php elseif (!empty($error)): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="management-sections">
                <!-- ASSIGN SUBJECT -->
                <div class="management-section">
                    <h3>Assign Subject to Semester</h3>
                    <form method="POST" action="manage_courses.php" novalidate>
                        <div class="form-group">
                            <label for="subject_id">Subject</label>
                            <select id="subject_id" name="subject_id" required>
                                <option value="">-- Select Subject --</option>
                                <?php while ($subject = $subjects->fetch_assoc()): ?>
                                    <option value="<?= (int)$subject['subject_id'] ?>">
                                        <?= htmlspecialchars($subject['subject_code']) ?> - <?= htmlspecialchars($subject['subject_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="semester_id">Semester</label>
                            <select id="semester_id" name="semester_id" required>
                                <option value="">-- Select Semester --</option>
                                <?php while ($semester = $semesters->fetch_assoc()): ?>
                                    <option value="<?= (int)$semester['semester_id'] ?>">
                                        Semester <?= (int)$semester['semester_number'] ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="faculty_id">Faculty</label>
                            <select id="faculty_id" name="faculty_id" required>
                                <option value="">-- Select Faculty --</option>
                                <?php while ($fac = $faculty->fetch_assoc()): ?>
                                    <option value="<?= (int)$fac['faculty_id'] ?>">
                                        <?= htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" name="assign_subject" class="btn">Assign Subject</button>
                    </form>
                </div>

                <!-- CURRENT ASSIGNMENTS -->
                <div class="management-section">
                    <h3>Current Subject Assignments</h3>
                    <?php if ($assignments->num_rows > 0): ?>
                        <table class="assignments-table">
                            <thead>
                                <tr>
                                    <th>Semester</th>
                                    <th>Subject</th>
                                    <th>Faculty</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($assignment = $assignments->fetch_assoc()): ?>
                                    <tr>
                                        <td>Semester <?= (int)$assignment['semester_number'] ?></td>
                                        <td><?= htmlspecialchars($assignment['subject_name']) ?> (<?= htmlspecialchars($assignment['subject_code']) ?>)</td>
                                        <td><?= htmlspecialchars($assignment['faculty_first'] . ' ' . $assignment['faculty_last']) ?></td>
                                        <td>
                                            <a href="?action=remove&id=<?= (int)$assignment['id'] ?>"
                                               class="action-btn btn-delete"
                                               onclick="return confirm('Are you sure you want to remove this assignment?')">Remove</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No subject assignments found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
