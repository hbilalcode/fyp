<?php
session_start();

// Only allow admin users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';

$success = $error = null;

// Handle delete action
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $id = (int) $_GET['id'];

    // TODO: Add check to prevent deletion if semester is linked to registrations

    $stmt = $conn->prepare("DELETE FROM semesters WHERE semester_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success = "✅ Semester deleted successfully.";
        } else {
            $error = "❌ Could not delete semester.";
        }
    }
}

// Handle add or update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $semester_number = (int) $_POST['semester_number'];
    $academic_year = trim($_POST['academic_year']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $edit_id = isset($_POST['edit_id']) ? (int) $_POST['edit_id'] : null;

    if ($edit_id) {
        $stmt = $conn->prepare("UPDATE semesters SET semester_number = ?, academic_year = ?, start_date = ?, end_date = ? WHERE semester_id = ?");
        $stmt->bind_param("isssi", $semester_number, $academic_year, $start_date, $end_date, $edit_id);
        if ($stmt->execute()) {
            $success = "✅ Semester updated successfully.";
        } else {
            $error = "❌ Failed to update semester.";
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO semesters (semester_number, academic_year, start_date, end_date) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $semester_number, $academic_year, $start_date, $end_date);
        if ($stmt->execute()) {
            $success = "✅ New semester added successfully.";
        } else {
            $error = "❌ Failed to add semester.";
        }
    }
}

// Fetch all semesters for display
$semesters = $conn->query("SELECT * FROM semesters ORDER BY semester_number");

// Fetch semester to edit if requested
$edit_semester = null;
if (isset($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM semesters WHERE semester_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_semester = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Manage Semesters | BS Academic System</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
</head>
<body>
<div class="dashboard">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h2>Manage Semesters</h2>
        </div>

        <div class="card">
            <?php if (!empty($success)): ?>
                <div class="alert success"><?= htmlspecialchars($success) ?></div>
            <?php elseif (!empty($error)): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="form-container">
                <h3><?= $edit_semester ? 'Edit Semester' : 'Add New Semester' ?></h3>
                <form method="POST" action="manage_semesters.php" novalidate>
                    <?php if ($edit_semester): ?>
                        <input type="hidden" name="edit_id" value="<?= (int)$edit_semester['semester_id'] ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="semester_number">Semester Number</label>
                        <input type="number" id="semester_number" name="semester_number" min="1" max="8" required 
                               value="<?= htmlspecialchars($edit_semester['semester_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="academic_year">Academic Year</label>
                        <input type="text" id="academic_year" name="academic_year" placeholder="e.g. 2024-2025" required 
                               value="<?= htmlspecialchars($edit_semester['academic_year'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" required 
                               value="<?= htmlspecialchars($edit_semester['start_date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" required 
                               value="<?= htmlspecialchars($edit_semester['end_date'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn"><?= $edit_semester ? 'Update Semester' : 'Add Semester' ?></button>
                    <?php if ($edit_semester): ?>
                        <a href="manage_semesters.php" class="btn">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>

            <h3>📘 All Semesters</h3>
            <?php if ($semesters->num_rows > 0): ?>
                <table class="semesters-table">
                    <thead>
                        <tr>
                            <th>Semester</th>
                            <th>Academic Year</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($semester = $semesters->fetch_assoc()): ?>
                            <tr>
                                <td>Semester <?= (int)$semester['semester_number'] ?></td>
                                <td><?= htmlspecialchars($semester['academic_year']) ?></td>
                                <td><?= date('M d, Y', strtotime($semester['start_date'])) ?></td>
                                <td><?= date('M d, Y', strtotime($semester['end_date'])) ?></td>
                                <td>
                                    <a href="?edit=<?= (int)$semester['semester_id'] ?>" class="action-btn btn-edit">Edit</a>
                                    <a href="?action=delete&id=<?= (int)$semester['semester_id'] ?>" 
                                       class="action-btn btn-delete"
                                       onclick="return confirm('Are you sure you want to delete this semester?')">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No semesters found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
