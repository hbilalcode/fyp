<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';
$faculty_id = $_SESSION['user_id'];
$message = "";

// DELETE ASSIGNMENT
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $assignment_id = $_GET['delete'];
    
    // Get file path first
    $getFile = $conn->prepare("SELECT file_path FROM assignments WHERE assignment_id = ? AND faculty_id = ?");
    $getFile->bind_param("ii", $assignment_id, $faculty_id);
    $getFile->execute();
    $fileRes = $getFile->get_result();
    
    if ($fileRes->num_rows > 0) {
        $fileRow = $fileRes->fetch_assoc();
        $filePath = "../uploads/assignments/" . $fileRow['file_path'];
        
        // Delete from DB
        $del = $conn->prepare("DELETE FROM assignments WHERE assignment_id = ? AND faculty_id = ?");
        $del->bind_param("ii", $assignment_id, $faculty_id);
        if ($del->execute()) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $message = "✅ Assignment deleted successfully.";
        } else {
            $message = "❌ Failed to delete assignment.";
        }
    }
}

// UPLOAD ASSIGNMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $subject_id = $_POST['subject_id'];
    $semester_id = $_POST['semester_id'];
    $deadline = $_POST['deadline'];
    $total_marks = $_POST['total_marks'];

    $file_path = null;
    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/assignments/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = basename($_FILES['assignment_file']['name']);
        $targetPath = $uploadDir . time() . "_" . $fileName;
        if (move_uploaded_file($_FILES['assignment_file']['tmp_name'], $targetPath)) {
            $file_path = basename($targetPath);
        }
    }

    $sql = "INSERT INTO assignments (subject_id, faculty_id, title, description, deadline, total_marks, semester_id, file_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisssdis", $subject_id, $faculty_id, $title, $description, $deadline, $total_marks, $semester_id, $file_path);

    if ($stmt->execute()) {
        $message = "✅ Assignment uploaded successfully!";
    } else {
        $message = "❌ Failed to upload assignment.";
    }
}

// Fetch subjects and semesters
$subjects = $conn->query("SELECT s.subject_id, s.subject_name 
    FROM subjects s 
    JOIN semester_subjects ss ON s.subject_id = ss.subject_id
    WHERE ss.faculty_id = $faculty_id");

$semesters = $conn->query("SELECT semester_id, semester_number FROM semesters ORDER BY semester_number");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Assignment | Faculty</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
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
        <div class="card">
            <h2>📤 Upload Assignment</h2>
            <?php if ($message): ?>
                <p><strong><?= $message ?></strong></p>
            <?php endif; ?>

            <form action="" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Assignment Title</label>
                    <input type="text" name="title" id="title" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" rows="4"></textarea>
                </div>

                <div class="form-group">
                    <label for="subject_id">Subject</label>
                    <select name="subject_id" id="subject_id" required>
                        <option value="">-- Select Subject --</option>
                        <?php while ($row = $subjects->fetch_assoc()): ?>
                            <option value="<?= $row['subject_id'] ?>"><?= $row['subject_name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="semester_id">Semester</label>
                    <select name="semester_id" id="semester_id" required>
                        <option value="">-- Select Semester --</option>
                        <?php while ($row = $semesters->fetch_assoc()): ?>
                            <option value="<?= $row['semester_id'] ?>">Semester <?= $row['semester_number'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="deadline">Deadline</label>
                    <input type="datetime-local" name="deadline" id="deadline" required>
                </div>

                <div class="form-group">
                    <label for="total_marks">Total Marks</label>
                    <input type="number" step="0.01" name="total_marks" id="total_marks" required>
                </div>

                <div class="form-group">
                    <label for="assignment_file">Upload File</label>
                    <input type="file" name="assignment_file" id="assignment_file" required>
                </div>

                <button type="submit" class="btn">Upload Assignment</button>
            </form>
        </div>

        <!-- Display Uploaded Assignments -->
        <div class="card" style="margin-top: 2rem;">
            <h2>📚 Your Uploaded Assignments</h2>
            <?php
            $sql = "SELECT a.*, s.subject_name, sem.semester_number 
                    FROM assignments a
                    JOIN subjects s ON a.subject_id = s.subject_id
                    JOIN semesters sem ON a.semester_id = sem.semester_id
                    WHERE a.faculty_id = ?
                    ORDER BY a.deadline DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $faculty_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Subject</th>
                            <th>Semester</th>
                            <th>Deadline</th>
                            <th>Marks</th>
                            <th>File</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= htmlspecialchars($row['subject_name']) ?></td>
                            <td>Semester <?= $row['semester_number'] ?></td>
                            <td><?= date('M d, Y H:i', strtotime($row['deadline'])) ?></td>
                            <td><?= $row['total_marks'] ?></td>
                            <td>
                                <?php if ($row['file_path']): ?>
                                    <a class="btn btn-edit" href="../uploads/assignments/<?= $row['file_path'] ?>" target="_blank">📥 Download</a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="btn btn-delete" href="?delete=<?= $row['assignment_id'] ?>" onclick="return confirm('Are you sure you want to delete this assignment?')">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No assignments uploaded yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
