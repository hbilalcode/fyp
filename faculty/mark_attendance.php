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

$month = date('m');
$year = date('Y');
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// Fetch all subjects taught by this faculty
$subjects = $conn->query("SELECT DISTINCT s.subject_id, s.subject_name
    FROM subjects s
    JOIN semester_subjects ss ON s.subject_id = ss.subject_id
    WHERE ss.faculty_id = $faculty_id");

$students = [];
$semester_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subject_id'])) {
    $subject_id = intval($_POST['subject_id']);

    // Get semester_id for selected subject
    $semSql = "SELECT semester_id FROM semester_subjects WHERE subject_id = ? LIMIT 1";
    $stmt = $conn->prepare($semSql);
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $semResult = $stmt->get_result();
    $semester_id = $semResult->fetch_assoc()['semester_id'] ?? 0;

    // Get students registered in that semester
    $studentSql = "SELECT s.student_id, s.enrollment_number 
                   FROM students s
                   JOIN student_semester_registration ssr ON s.student_id = ssr.student_id
                   WHERE ssr.semester_id = ? AND ssr.status = 'registered'";
    $stmt = $conn->prepare($studentSql);
    $stmt->bind_param("i", $semester_id);
    $stmt->execute();
    $students = $stmt->get_result();
}

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance']) && isset($_POST['subject_id']) && isset($_POST['date'])) {
    $subject_id = intval($_POST['subject_id']);
    $date = $_POST['date'];

    if (isset($_POST['status']) && is_array($_POST['status'])) {
        foreach ($_POST['status'] as $student_id => $status) {
            $student_id = intval($student_id);
            $status = $conn->real_escape_string($status);

            $stmt = $conn->prepare("REPLACE INTO attendance (student_id, subject_id, schedule_id, date, status)
                                    VALUES (?, ?, 0, ?, ?)");
            $stmt->bind_param("iiss", $student_id, $subject_id, $date, $status);
            $stmt->execute();
        }
        $message = "✅ Attendance saved for " . htmlspecialchars($date);
    }
}

// --- Monthly Attendance Summary for Specific Subject ---

// Fetch subjects taught by faculty for the filter dropdown
$summary_subjects = [];
$subjRes = $conn->query("SELECT DISTINCT s.subject_id, s.subject_name, ss.semester_id
    FROM subjects s
    JOIN semester_subjects ss ON s.subject_id = ss.subject_id
    WHERE ss.faculty_id = $faculty_id
    ORDER BY s.subject_name");
while ($row = $subjRes->fetch_assoc()) {
    $summary_subjects[] = $row;
}

// Get filters from GET, default to first subject if none selected
$summary_subject_id = $_GET['summary_subject_id'] ?? ($summary_subjects[0]['subject_id'] ?? '');
$summary_month = $_GET['summary_month'] ?? date('m');
$summary_year = $_GET['summary_year'] ?? date('Y');

$low_attendance_threshold = 75;
$attendance_summary = [];
$summary_error = '';

if ($summary_subject_id) {
    // Find semester_id for selected subject
    $semester_id_for_summary = null;
    foreach ($summary_subjects as $subj) {
        if ($subj['subject_id'] == $summary_subject_id) {
            $semester_id_for_summary = $subj['semester_id'];
            break;
        }
    }

    if ($semester_id_for_summary === null) {
        $summary_error = "Selected subject not found or not assigned to you.";
    } else {
        // Fetch students registered in that semester
        $stmt = $conn->prepare("
            SELECT s.student_id, u.first_name, u.last_name, s.enrollment_number, s.department
            FROM students s
            JOIN users u ON s.student_id = u.user_id
            JOIN student_semester_registration ssr ON s.student_id = ssr.student_id
            WHERE ssr.semester_id = ? AND ssr.status = 'registered'
            ORDER BY u.last_name, u.first_name
        ");
        $stmt->bind_param("i", $semester_id_for_summary);
        $stmt->execute();
        $students_summary = $stmt->get_result();

        $days_in_month_summary = cal_days_in_month(CAL_GREGORIAN, $summary_month, $summary_year);

        while ($student = $students_summary->fetch_assoc()) {
            // Count present days for this student and subject in the selected month/year
            $attStmt = $conn->prepare("
                SELECT COUNT(*) AS present_days 
                FROM attendance 
                WHERE student_id = ? AND subject_id = ? AND status = 'Present'
                  AND MONTH(date) = ? AND YEAR(date) = ?
            ");
            $attStmt->bind_param("iiii", $student['student_id'], $summary_subject_id, $summary_month, $summary_year);
            $attStmt->execute();
            $attResult = $attStmt->get_result()->fetch_assoc();
            $present_days = $attResult['present_days'] ?? 0;

            $attendance_percentage = $days_in_month_summary > 0 ? round(($present_days / $days_in_month_summary) * 100, 2) : 0;

            $attendance_summary[] = [
                'enrollment_number' => $student['enrollment_number'],
                'name' => $student['first_name'] . ' ' . $student['last_name'],
                'department' => $student['department'],
                'attendance_percentage' => $attendance_percentage,
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Faculty | Mark Attendance & Monthly Report</title>
<link rel="stylesheet" href="../assets/css/style.css" />
<link rel="stylesheet" href="../assets/css/dashboard.css" />
<style>
    .sticky-col { position: sticky; left: 0; background: #fff; }
    .att-mark { width: 100%; padding: 3px; }
    .form-inline { display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
    .form-group { display: flex; flex-direction: column; }
    .alert-success {
        background-color: #d4edda;
        color: #155724;
        padding: 12px 20px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-weight: 600;
    }
    .low-attendance {
        background-color: #f8d7da;
        color: #721c24;
        font-weight: bold;
    }
    .btn { padding: 8px 16px; background-color: #007BFF; border: none; color: white; cursor: pointer; border-radius: 4px; }
    .btn:hover { background-color: #0056b3; }
    .filter-form { margin-top: 20px; margin-bottom: 20px; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
    .filter-form label { margin-bottom: 4px; }
    .filter-form select { padding: 6px 10px; font-size: 1rem; }
</style>
</head>
<body>
<div class="dashboard">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="card">
            <h2>📋 Mark Attendance</h2>
            <?php if ($message): ?>
                <div class="alert-success"><?= $message ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-inline">
                    <div class="form-group">
                        <label for="subject_id">Subject:</label>
                        <select name="subject_id" id="subject_id" required onchange="this.form.submit()">
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($subjects as $sub): ?>
                                <option value="<?= (int)$sub['subject_id'] ?>" <?= (isset($_POST['subject_id']) && $_POST['subject_id'] == $sub['subject_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sub['subject_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="date">Date:</label>
                        <input type="date" name="date" id="date" required max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['date'] ?? date('Y-m-d')) ?>">
                    </div>

                    <button type="submit" name="mark_attendance" class="btn">Save Attendance</button>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th class="sticky-col">Roll No</th>
                            <th class="sticky-col">Mark for Selected Date</th>
                            <?php for ($d = 1; $d <= $days_in_month; $d++): ?>
                                <th><?= $d ?></th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($students && $students->num_rows > 0): ?>
                            <?php while ($student = $students->fetch_assoc()): ?>
                                <tr>
                                    <td class="sticky-col"><?= htmlspecialchars($student['enrollment_number']) ?></td>
                                    <td class="sticky-col">
                                        <select name="status[<?= (int)$student['student_id'] ?>]" class="att-mark" required>
                                            <option value="Present">Present</option>
                                            <option value="Absent">Absent</option>
                                        </select>
                                    </td>
                                    <?php
                                    for ($d = 1; $d <= $days_in_month; $d++):
                                        $date_str = sprintf('%04d-%02d-%02d', $year, $month, $d);
                                        $status_sql = "SELECT status FROM attendance 
                                                       WHERE student_id = {$student['student_id']} 
                                                       AND subject_id = {$subject_id} 
                                                       AND date = '{$date_str}'";
                                        $result = $conn->query($status_sql);
                                        $status = $result->fetch_assoc()['status'] ?? '-';
                                        $color = $status === 'Present' ? 'green' : ($status === 'Absent' ? 'red' : 'gray');
                                        echo "<td style='color:$color'>" . htmlspecialchars(substr($status, 0, 1)) . "</td>";
                                    endfor;
                                    ?>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="<?= $days_in_month + 2 ?>">Please select a subject to load students.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>

        <!-- Monthly Attendance Summary Section -->
        <div class="card" style="margin-top: 40px;">
            <h3>📊 Monthly Attendance Summary</h3>
            <form method="GET" class="filter-form">
                <label for="summary_subject_id">Subject:</label>
                <select name="summary_subject_id" id="summary_subject_id" required onchange="this.form.submit()">
                    <?php foreach ($summary_subjects as $subj): ?>
                        <option value="<?= htmlspecialchars($subj['subject_id']) ?>" <?= ($subj['subject_id'] == $summary_subject_id) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($subj['subject_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="summary_month">Month:</label>
                <select name="summary_month" id="summary_month" onchange="this.form.submit()">
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $monthName = date('F', mktime(0,0,0,$m,1)); ?>
                        <option value="<?= $m ?>" <?= ($m == $summary_month) ? 'selected' : '' ?>><?= $monthName ?></option>
                    <?php endfor; ?>
                </select>

                <label for="summary_year">Year:</label>
                <select name="summary_year" id="summary_year" onchange="this.form.submit()">
                    <?php 
                    $currentYear = date('Y');
                    for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= ($y == $summary_year) ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </form>

            <?php if ($summary_error): ?>
                <p style="color:red;"><?= htmlspecialchars($summary_error) ?></p>
            <?php elseif ($summary_subject_id): ?>
                <?php if (count($attendance_summary) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Enrollment No</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Attendance (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_summary as $stu): ?>
                                <tr class="<?= ($stu['attendance_percentage'] < $low_attendance_threshold) ? 'low-attendance' : '' ?>">
                                    <td><?= htmlspecialchars($stu['enrollment_number']) ?></td>
                                    <td><?= htmlspecialchars($stu['name']) ?></td>
                                    <td><?= htmlspecialchars($stu['department']) ?></td>
                                    <td><?= $stu['attendance_percentage'] ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p><em>Rows highlighted indicate attendance below <?= $low_attendance_threshold ?>%</em></p>
                <?php else: ?>
                    <p>No attendance data found for the selected subject and month.</p>
                <?php endif; ?>
            <?php else: ?>
                <p>Please select a subject to view the attendance summary.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
