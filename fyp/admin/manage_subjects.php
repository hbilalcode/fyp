<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';

$success = $error = null;

// DELETE SUBJECT
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $id = (int) $_GET['id'];

    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM semester_subjects WHERE subject_id = ?");
    if ($check_stmt) {
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();

        if ($check_result['count'] > 0) {
            $error = "❌ Cannot delete subject — it is assigned to one or more semesters.";
        } else {
            $del_stmt = $conn->prepare("DELETE FROM subjects WHERE subject_id = ?");
            if ($del_stmt) {
                $del_stmt->bind_param("i", $id);
                if ($del_stmt->execute()) {
                    $success = "✅ Subject deleted successfully.";
                } else {
                    $error = "❌ Error deleting subject.";
                }
            }
        }
    }
}

// ADD OR UPDATE SUBJECT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_code = trim($_POST['subject_code']);
    $subject_name = trim($_POST['subject_name']);
    $credit_hours = (int) $_POST['credit_hours'];
    $department = trim($_POST['department']);
    $description = trim($_POST['description']);
    $edit_id = isset($_POST['edit_id']) ? (int) $_POST['edit_id'] : null;

    if ($edit_id) {
        $stmt = $conn->prepare("UPDATE subjects SET subject_code = ?, subject_name = ?, credit_hours = ?, department = ?, description = ? WHERE subject_id = ?");
        $stmt->bind_param("ssissi", $subject_code, $subject_name, $credit_hours, $department, $description, $edit_id);
        if ($stmt->execute()) {
            $success = "✅ Subject updated successfully.";
        } else {
            $error = "❌ Failed to update subject.";
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO subjects (subject_code, subject_name, credit_hours, department, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiss", $subject_code, $subject_name, $credit_hours, $department, $description);
        if ($stmt->execute()) {
            $success = "✅ New subject added successfully.";
        } else {
            $error = "❌ Failed to add subject.";
        }
    }
}

// FETCH ALL SUBJECTS
$subjects = $conn->query("SELECT * FROM subjects ORDER BY subject_name");

// FETCH SINGLE SUBJECT FOR EDITING
$edit_subject = null;
if (isset($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM subjects WHERE subject_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_subject = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Manage Subjects | BS Academic System</title>
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
            <h2>Manage Subjects</h2>
            <a href="manage_semesters.php" class="btn">Manage Semesters</a>
        </div>

        <div class="card">
            <?php if ($success): ?>
                <div class="alert success"><?= htmlspecialchars($success) ?></div>
            <?php elseif ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="subject-container">
                <!-- ADD / EDIT FORM -->
                <div class="subject-section">
                    <h3><?= $edit_subject ? 'Edit Subject' : 'Add New Subject' ?></h3>
                    <form method="POST" action="manage_subjects.php" novalidate>
                        <?php if ($edit_subject): ?>
                            <input type="hidden" name="edit_id" value="<?= (int)$edit_subject['subject_id'] ?>">
                        <?php endif; ?>
                        <div class="form-group">
                            <label for="subject_code">Subject Code</label>
                            <input type="text" id="subject_code" name="subject_code" required value="<?= htmlspecialchars($edit_subject['subject_code'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="subject_name">Subject Name</label>
                            <input type="text" id="subject_name" name="subject_name" required value="<?= htmlspecialchars($edit_subject['subject_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="credit_hours">Credit Hours</label>
                            <input type="number" id="credit_hours" name="credit_hours" min="1" required value="<?= htmlspecialchars($edit_subject['credit_hours'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="department">Department</label>
                            <input type="text" id="department" name="department" required value="<?= htmlspecialchars($edit_subject['department'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3"><?= htmlspecialchars($edit_subject['description'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn"><?= $edit_subject ? 'Update Subject' : 'Add Subject' ?></button>
                        <?php if ($edit_subject): ?>
                            <a href="manage_subjects.php" class="btn" style="background:#7f8c8d; margin-left:10px;">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- SUBJECTS LIST -->
                <div class="subject-section">
                    <h3>📚 All Subjects</h3>
                    <?php if ($subjects->num_rows > 0): ?>
                        <table class="subjects-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Credits</th>
                                    <th>Department</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($subject = $subjects->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($subject['subject_code']) ?></td>
                                        <td><?= htmlspecialchars($subject['subject_name']) ?></td>
                                        <td><?= (int)$subject['credit_hours'] ?></td>
                                        <td><?= htmlspecialchars($subject['department']) ?></td>
                                        <td>
                                            <a href="?edit=<?= (int)$subject['subject_id'] ?>" class="action-btn btn-edit">Edit</a>
                                            <a href="?action=delete&id=<?= (int)$subject['subject_id'] ?>" 
                                               class="action-btn btn-delete"
                                               onclick="return confirm('Are you sure you want to delete this subject?')">Delete</a>
                                            <a href="manage_courses.php?id=<?= (int)$subject['subject_id'] ?>" 
                                               class="action-btn btn-assign">Assign</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No subjects found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
