<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$error = '';
$success = '';

// Fetch user info
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: manage_users.php?error=User not found");
    exit();
}

// Fetch student info (to get current_semester)
$student = null;
if ($user['role'] === 'student') {
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
}

// Handle messages from redirect
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'unenrolled') {
        $success = "Student unenrolled from semester successfully.";
    } elseif ($_GET['msg'] === 'updated') {
        $success = "User updated successfully.";
    } elseif ($_GET['msg'] === 'promoted') {
        $success = "Student promoted to next semester successfully.";
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_semester'])) {
        $registration_id = (int)$_POST['registration_id'];
        $stmt = $conn->prepare("DELETE FROM student_semester_registration WHERE registration_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $registration_id, $user_id);
        if ($stmt->execute()) {
            header("Location: edit_user.php?id=$user_id&msg=unenrolled");
            exit();
        } else {
            $error = "Failed to unenroll student: " . $conn->error;
        }
    } elseif (isset($_POST['promote_semester'])) {
        // Promote student to next semester
        if (!$student) {
            $error = "Student record not found.";
        } else {
            $current_sem = (int)$student['current_semester'];
            if ($current_sem >= 8) {
                $error = "Student is already in the highest semester.";
            } else {
                $next_sem = $current_sem + 1;

                // Find semester_id for next semester in the same academic year or next academic year
                // Here we pick the semester with semester_number = $next_sem and latest academic_year
                $stmt = $conn->prepare("
                    SELECT semester_id FROM semesters 
                    WHERE semester_number = ? 
                    ORDER BY academic_year DESC LIMIT 1
                ");
                $stmt->bind_param("i", $next_sem);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows === 0) {
                    $error = "Next semester record not found in database.";
                } else {
                    $next_semester = $res->fetch_assoc();
                    $next_semester_id = $next_semester['semester_id'];

                    // Check if already registered in next semester
                    $stmt = $conn->prepare("SELECT registration_id FROM student_semester_registration WHERE student_id = ? AND semester_id = ? AND status = 'registered'");
                    $stmt->bind_param("ii", $user_id, $next_semester_id);
                    $stmt->execute();
                    $exists = $stmt->get_result()->num_rows > 0;

                    if ($exists) {
                        $error = "Student is already registered in the next semester.";
                    } else {
                        // Begin transaction
                        $conn->begin_transaction();
                        try {
                            // Update current_semester in students table
                            $stmt = $conn->prepare("UPDATE students SET current_semester = ? WHERE student_id = ?");
                            $stmt->bind_param("ii", $next_sem, $user_id);
                            $stmt->execute();

                            // Insert new registration record
                            $stmt = $conn->prepare("INSERT INTO student_semester_registration (student_id, semester_id, registration_date, status) VALUES (?, ?, NOW(), 'registered')");
                            $stmt->bind_param("ii", $user_id, $next_semester_id);
                            $stmt->execute();

                            $conn->commit();
                            header("Location: edit_user.php?id=$user_id&msg=promoted");
                            exit();
                        } catch (Exception $e) {
                            $conn->rollback();
                            $error = "Failed to promote student: " . $e->getMessage();
                        }
                    }
                }
            }
        }
    } else {
        // Update user info (no status dropdown)
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $contact_number = trim($_POST['contact_number']);
        $role = $_POST['role'];

        // Check for duplicate username/email excluding current user
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
        $stmt->bind_param("ssi", $username, $email, $user_id);
        $stmt->execute();
        $duplicates = $stmt->get_result();
        if ($duplicates->num_rows > 0) {
            $error = "Username or email already exists for another user.";
        } else {
            $update_sql = "UPDATE users SET username=?, email=?, first_name=?, last_name=?, contact_number=?, role=? WHERE user_id=?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ssssssi", $username, $email, $first_name, $last_name, $contact_number, $role, $user_id);
            if ($stmt->execute()) {
                $success = "User updated successfully!";
                // Refresh user info
                $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();

                // Refresh student info if role is student
                if ($user['role'] === 'student') {
                    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $student = $stmt->get_result()->fetch_assoc();
                }
            } else {
                $error = "Error updating user: " . $conn->error;
            }
        }
    }
}

// Fetch enrolled semesters fresh for students
$enrolled_semesters = [];
if ($user['role'] === 'student') {
    $stmt = $conn->prepare("
        SELECT ssr.registration_id, sem.semester_number, sem.academic_year 
        FROM student_semester_registration ssr
        JOIN semesters sem ON ssr.semester_id = sem.semester_id
        WHERE ssr.student_id = ? AND ssr.status = 'registered'
        ORDER BY sem.academic_year DESC, sem.semester_number DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $enrolled_semesters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Edit User | BS Academic System</title>
<link rel="stylesheet" href="../assets/css/dashboard.css" />
</head>
<body>
<div class="dashboard">
    <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>BS Academic System</h3>
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
            <h2>Edit User: <?= htmlspecialchars($user['username']) ?></h2>
            <a href="manage_users.php" class="btn">Back to Users</a>
        </div>

        <div class="card">
            <?php if ($success): ?>
                <div class="alert success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="edit_user.php?id=<?= $user_id ?>" method="POST" novalidate>
                <input type="hidden" name="update_user" value="1" />
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required value="<?= htmlspecialchars($user['username']) ?>" />
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>" />
                </div>

                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" required value="<?= htmlspecialchars($user['first_name']) ?>" />
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required value="<?= htmlspecialchars($user['last_name']) ?>" />
                </div>

                <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input type="text" id="contact_number" name="contact_number" value="<?= htmlspecialchars($user['contact_number']) ?>" />
                </div>

                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                        <option value="faculty" <?= $user['role'] === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>

                <button type="submit" class="btn">Update User</button>
            </form>

            <?php if ($user['role'] === 'student'): ?>
                <div class="semester-list">
                    <h3>Currently Enrolled Semesters</h3>
                    <?php if (count($enrolled_semesters) > 0): ?>
                        <?php foreach ($enrolled_semesters as $sem): ?>
                            <div class="semester-item">
                                <span>Semester <?= htmlspecialchars($sem['semester_number']) ?> - <?= htmlspecialchars($sem['academic_year']) ?></span>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="registration_id" value="<?= (int)$sem['registration_id'] ?>" />
                                    <button type="submit" name="remove_semester" class="btn-danger" title="Remove from this semester">Remove</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No semester registrations found.</p>
                    <?php endif; ?>

                    <!-- Promote button -->
                    <form method="POST" style="margin-top: 15px;">
                        <button type="submit" name="promote_semester" class="btn-promote" title="Promote to next semester">Promote to Next Semester</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
