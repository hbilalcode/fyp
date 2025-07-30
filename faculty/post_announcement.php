<?php
session_start();

// Only allow faculty access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';

$faculty_id = $_SESSION['user_id'];
$message = "";

// Handle delete request securely
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $announcement_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM announcements WHERE announcement_id = ? AND faculty_id = ?");
    $stmt->bind_param("ii", $announcement_id, $faculty_id);
    if ($stmt->execute()) {
        $message = "🗑️ Announcement deleted successfully.";
    } else {
        $message = "❌ Failed to delete announcement.";
    }
}

// Handle form submission securely
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $msg = trim($_POST['message']);
    $subject_id = !empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
    $semester_id = (int)$_POST['semester_id'];
    $is_important = isset($_POST['is_important']) ? 1 : 0;

    $sql = "INSERT INTO announcements (subject_id, faculty_id, title, message, semester_id, is_important)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iissii", $subject_id, $faculty_id, $title, $msg, $semester_id, $is_important);

    if ($stmt->execute()) {
        $message = "✅ Announcement posted successfully!";
    } else {
        $message = "❌ Failed to post announcement.";
    }
}

// Load subjects and semesters for dropdowns
$subjects = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name");
$semesters = $conn->query("SELECT semester_id, semester_number FROM semesters ORDER BY semester_number");

// Load faculty's announcements
$announcements_sql = "
    SELECT a.announcement_id, a.title, a.message, a.created_at, a.is_important, s.subject_name, sem.semester_number
    FROM announcements a
    LEFT JOIN subjects s ON a.subject_id = s.subject_id
    JOIN semesters sem ON a.semester_id = sem.semester_id
    WHERE a.faculty_id = ?
    ORDER BY a.created_at DESC
";
$announcement_stmt = $conn->prepare($announcements_sql);
$announcement_stmt->bind_param("i", $faculty_id);
$announcement_stmt->execute();
$announcements_result = $announcement_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Post Announcement | Faculty</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
    <script>
        function confirmDelete(id) {
            if (confirm("Are you sure you want to delete this announcement?")) {
                window.location.href = "post_announcement.php?delete=" + id;
            }
        }
    </script>
</head>
<body>
<div class="dashboard">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="card">
            <h2>📢 Post Announcement</h2>
            <?php if ($message): ?>
                <p><strong><?= htmlspecialchars($message) ?></strong></p>
            <?php endif; ?>

            <form method="post" novalidate>
                <div class="form-group">
                    <label for="title">Announcement Title</label>
                    <input type="text" id="title" name="title" required>
                </div>

                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="4" required></textarea>
                </div>

                <div class="form-group">
                    <label for="subject_id">Subject (optional)</label>
                    <select id="subject_id" name="subject_id">
                        <option value="">-- Optional Subject --</option>
                        <?php while ($row = $subjects->fetch_assoc()): ?>
                            <option value="<?= (int)$row['subject_id'] ?>"><?= htmlspecialchars($row['subject_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="semester_id">Target Semester</label>
                    <select id="semester_id" name="semester_id" required>
                        <option value="">-- Select Semester --</option>
                        <?php
                        $semesters->data_seek(0);
                        while ($row = $semesters->fetch_assoc()): ?>
                            <option value="<?= (int)$row['semester_id'] ?>">Semester <?= (int)$row['semester_number'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_important" value="1"> Mark as Important
                    </label>
                </div>

                <button type="submit" class="btn">Post Announcement</button>
            </form>
        </div>

        <div class="card announcement-list">
            <h3>📄 Your Posted Announcements</h3>
            <?php if ($announcements_result->num_rows > 0): ?>
                <ul style="list-style: none; padding: 0;">
                    <?php while ($row = $announcements_result->fetch_assoc()): ?>
                        <li class="announcement-item">
                            <strong><?= htmlspecialchars($row['title']) ?></strong>
                            <?php if ($row['is_important']): ?>
                                <span class="important">[IMPORTANT]</span>
                            <?php endif; ?>
                            <a href="javascript:void(0);" onclick="confirmDelete(<?= (int)$row['announcement_id'] ?>)" class="delete-btn">Delete</a><br>
                            <small>
                                Semester <?= (int)$row['semester_number'] ?>
                                <?= $row['subject_name'] ? " | Subject: " . htmlspecialchars($row['subject_name']) : "" ?>
                                | Posted: <?= date("M d, Y", strtotime($row['created_at'])) ?>
                            </small>
                            <p><?= nl2br(htmlspecialchars($row['message'])) ?></p>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p>No announcements posted yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
