<?php
session_start();

// 1. Access control: only faculty allowed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';

$faculty_id = $_SESSION['user_id'];
$message = "";

// 2. Handle quiz deletion securely
if (
    isset($_GET['action'], $_GET['id']) &&
    $_GET['action'] === 'delete' &&
    is_numeric($_GET['id'])
) {
    $quiz_id = (int) $_GET['id'];

    // Verify quiz belongs to this faculty
    $check = $conn->prepare("SELECT quiz_id FROM quizzes WHERE quiz_id = ? AND faculty_id = ?");
    $check->bind_param("ii", $quiz_id, $faculty_id);
    $check->execute();
    $result = $check->get_result();

    if ($result && $result->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM quizzes WHERE quiz_id = ?");
        $stmt->bind_param("i", $quiz_id);
        if ($stmt->execute()) {
            $message = "✅ Quiz deleted.";
        } else {
            $message = "❌ Failed to delete quiz.";
        }
    } else {
        $message = "❌ Unauthorized or invalid quiz.";
    }
}

// 3. Handle quiz creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $subject_id = (int) $_POST['subject_id'];
    $semester_id = (int) $_POST['semester_id'];
    $total_marks = (int) $_POST['total_marks'];
    $time_limit = (float) $_POST['time_limit'];

    $sql = "INSERT INTO quizzes (subject_id, faculty_id, title, description, total_marks, time_limit, semester_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iissdii", $subject_id, $faculty_id, $title, $description, $total_marks, $time_limit, $semester_id);

    if ($stmt->execute()) {
        $message = "✅ Quiz created successfully!";
    } else {
        $message = "❌ Failed to create quiz.";
    }
}

// 4. Fetch subjects and semesters for dropdowns
$subjects = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name");
$semesters = $conn->query("SELECT semester_id, semester_number FROM semesters ORDER BY semester_number");

// 5. Fetch quizzes created by this faculty
$quiz_sql = "
    SELECT q.quiz_id, q.title, q.description, q.total_marks, q.time_limit, q.created_at,
           s.subject_name, sem.semester_number
    FROM quizzes q
    JOIN subjects s ON q.subject_id = s.subject_id
    JOIN semesters sem ON q.semester_id = sem.semester_id
    WHERE q.faculty_id = ?
    ORDER BY q.created_at DESC
";
$stmt = $conn->prepare($quiz_sql);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$quizzes_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Create & Manage Quiz | BS Academic System</title>
    <link rel="stylesheet" href="../assets/css/style.css" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
    <style>
        .btn-sm {
            font-size: 0.85rem;
            padding: 5px 10px;
            margin-right: 5px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
        }
        .delete-btn {
            background-color: #e74c3c;
            color: white;
        }
        .delete-btn:hover {
            background-color: #c0392b;
        }
        .form-group {
            margin-bottom: 18px;
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-weight: 600;
            margin-bottom: 6px;
            color: #2c3e50;
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            padding: 10px 12px;
            font-size: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            transition: border-color 0.3s;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #3498db;
            outline: none;
        }
        .btn {
            background-color: #3498db;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            display: inline-block;
            margin-top: 10px;
            text-align: center;
            text-decoration: none;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        ul.quiz-list {
            list-style: none;
            padding-left: 0;
        }
        ul.quiz-list li {
            margin-bottom: 1rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
        }
        ul.quiz-list li small {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<div class="dashboard">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="card">
            <h2>🧪 Create New Quiz</h2>
            <?php if ($message): ?>
                <p><strong><?= htmlspecialchars($message) ?></strong></p>
            <?php endif; ?>
            <form method="post" novalidate>
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="subject_id">Subject</label>
                    <select id="subject_id" name="subject_id" required>
                        <option value="">-- Select Subject --</option>
                        <?php while ($row = $subjects->fetch_assoc()): ?>
                            <option value="<?= (int)$row['subject_id'] ?>"><?= htmlspecialchars($row['subject_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="semester_id">Semester</label>
                    <select id="semester_id" name="semester_id" required>
                        <option value="">-- Select Semester --</option>
                        <?php $semesters->data_seek(0); while ($row = $semesters->fetch_assoc()): ?>
                            <option value="<?= (int)$row['semester_id'] ?>">Semester <?= (int)$row['semester_number'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="total_marks">Total Marks</label>
                    <input type="number" id="total_marks" name="total_marks" required min="0">
                </div>
                <div class="form-group">
                    <label for="time_limit">Time Limit (mins)</label>
                    <input type="number" id="time_limit" name="time_limit" required min="0" step="0.01">
                </div>
                <button type="submit" class="btn">Create Quiz</button>
            </form>
        </div>

        <div class="card">
            <h3>📋 Your Quizzes</h3>
            <?php if ($quizzes_result->num_rows > 0): ?>
                <ul class="quiz-list">
                    <?php while ($row = $quizzes_result->fetch_assoc()): ?>
                        <li>
                            <strong><?= htmlspecialchars($row['title']) ?></strong><br>
                            <small>
                                Semester <?= (int)$row['semester_number'] ?> | Subject: <?= htmlspecialchars($row['subject_name']) ?> |
                                Marks: <?= (int)$row['total_marks'] ?> | Time: <?= htmlspecialchars($row['time_limit']) ?> min |
                                Created: <?= date("M d, Y", strtotime($row['created_at'])) ?>
                            </small>
                            <p><?= nl2br(htmlspecialchars($row['description'])) ?></p>
                            <a href="?action=delete&id=<?= (int)$row['quiz_id'] ?>" class="btn-sm delete-btn"
                               onclick="return confirm('Are you sure you want to delete this quiz?');">Delete</a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p>No quizzes created yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
