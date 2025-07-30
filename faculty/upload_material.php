<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';
$faculty_id = $_SESSION['user_id'];
$message = "";

// Handle deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $material_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM study_materials WHERE material_id = ? AND uploaded_by = ?");
    $stmt->bind_param("ii", $material_id, $faculty_id);
    if ($stmt->execute()) {
        $message = "🗑️ Study material deleted successfully.";
    } else {
        $message = "❌ Failed to delete study material.";
    }
}

// Handle upload form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $subject_id = $_POST['subject_id'];
    $semester_id = $_POST['semester_id'];
    $file_type = $_POST['file_type'];
    $file_path = null;

    if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/materials/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        $filename = basename($_FILES['material_file']['name']);
        $targetPath = $uploadDir . time() . "_" . $filename;

        if (move_uploaded_file($_FILES['material_file']['tmp_name'], $targetPath)) {
            $file_path = basename($targetPath);
        } else {
            $message = "❌ Failed to upload file.";
        }
    }

    if ($file_path) {
        $sql = "INSERT INTO study_materials (subject_id, uploaded_by, title, description, file_path, semester_id, file_type)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisssis", $subject_id, $faculty_id, $title, $description, $file_path, $semester_id, $file_type);
        if ($stmt->execute()) {
            $message = "✅ Study material uploaded successfully!";
        } else {
            $message = "❌ Failed to save study material.";
        }
    }
}

// Fetch dropdown data
$subjects = $conn->query("SELECT subject_id, subject_name FROM subjects");
$semesters = $conn->query("SELECT semester_id, semester_number FROM semesters");

// Fetch uploaded materials
$sql = "SELECT m.material_id, m.title, m.description, m.file_path, m.file_type, m.upload_date, s.subject_name, sem.semester_number
        FROM study_materials m
        JOIN subjects s ON m.subject_id = s.subject_id
        JOIN semesters sem ON m.semester_id = sem.semester_id
        WHERE m.uploaded_by = ?
        ORDER BY m.upload_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$materials = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Study Material | Faculty</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script>
        function confirmDelete(id) {
            if (confirm("Are you sure you want to delete this material?")) {
                window.location.href = "upload_material.php?delete=" + id;
            }
        }
    </script>
</head>
<body>
<div class="dashboard">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="card">
            <h2>📂 Upload Study Material</h2>
            <?php if ($message) echo "<p><strong>$message</strong></p>"; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Material Title</label>
                    <input type="text" name="title" id="title" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" rows="3"></textarea>
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
                    <label for="file_type">File Type</label>
                    <input type="text" name="file_type" id="file_type" placeholder="e.g. PDF, PPT, DOCX" required>
                </div>

                <div class="form-group">
                    <label for="material_file">Upload File</label>
                    <input type="file" name="material_file" id="material_file" required>
                </div>

                <button type="submit" class="btn">Upload Material</button>
            </form>
        </div>

        <!-- Material List -->
        <div class="card material-list">
            <h3>📋 Your Uploaded Materials</h3>
            <?php if ($materials->num_rows > 0): ?>
                <ul style="list-style:none; padding-left:0;">
                    <?php while ($row = $materials->fetch_assoc()): ?>
                        <li class="material-item">
                            <strong><?= htmlspecialchars($row['title']) ?></strong>
                            <a href="javascript:void(0);" onclick="confirmDelete(<?= $row['material_id'] ?>)" class="delete-btn">Delete</a><br>
                            <small>
                                Semester <?= $row['semester_number'] ?> |
                                Subject: <?= htmlspecialchars($row['subject_name']) ?> |
                                Type: <?= htmlspecialchars($row['file_type']) ?> |
                                Uploaded: <?= date("M d, Y", strtotime($row['upload_date'])) ?>
                            </small>
                            <p><?= nl2br(htmlspecialchars($row['description'])) ?></p>
                            <a class = "btn btn-edit"href="../uploads/materials/<?= htmlspecialchars($row['file_path']) ?>" target="_blank">📥 Download</a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p>No study materials uploaded yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
