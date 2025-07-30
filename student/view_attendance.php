<?php
session_start();

// 1. Only allow student access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';

$student_id = $_SESSION['user_id'];

// 2. Initialize filter variables safely
$subject_filter = $_GET['subject'] ?? '';
$month_filter = $_GET['month'] ?? '';
$status_filter = $_GET['status'] ?? '';

// 3. Build base SQL query with parameters
$sql = "
    SELECT s.subject_name, a.date, a.status
    FROM attendance a
    JOIN subjects s ON a.subject_id = s.subject_id
    WHERE a.student_id = ?
";

$params = [$student_id];
$types = "i";

if (!empty($subject_filter)) {
    $sql .= " AND s.subject_name = ?";
    $params[] = $subject_filter;
    $types .= "s";
}

if (!empty($month_filter)) {
    $month_filter_int = (int)$month_filter;
    if ($month_filter_int >= 1 && $month_filter_int <= 12) {
        $sql .= " AND MONTH(a.date) = ?";
        $params[] = $month_filter_int;
        $types .= "i";
    }
}

if (!empty($status_filter)) {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY s.subject_name, a.date DESC";

// 4. Prepare and execute the query
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// 5. Organize attendance data by subject and calculate totals
$attendance_data = [];
$total_present = 0;
$total_absent = 0;
$total_records = 0;

while ($row = $result->fetch_assoc()) {
    $subject = $row['subject_name'];
    $attendance_data[$subject][] = $row;

    $total_records++;
    if ($row['status'] === 'Present') {
        $total_present++;
    } elseif ($row['status'] === 'Absent') {
        $total_absent++;
    }
}

// 6. Calculate overall attendance percentage
$attendance_percentage = $total_records > 0 ? round(($total_present / $total_records) * 100, 2) : 0;

// 7. Fetch distinct subjects for filter dropdown
$subjects_sql = "
    SELECT DISTINCT s.subject_name 
    FROM attendance a
    JOIN subjects s ON a.subject_id = s.subject_id
    WHERE a.student_id = ?
    ORDER BY s.subject_name
";
$subjects_stmt = $conn->prepare($subjects_sql);
$subjects_stmt->bind_param("i", $student_id);
$subjects_stmt->execute();
$subjects_result = $subjects_stmt->get_result();

$subjects = [];
while ($subject_row = $subjects_result->fetch_assoc()) {
    $subjects[] = $subject_row['subject_name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>My Attendance</title>
    <link rel="stylesheet" href="../assets/css/style.css" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
    <style>
        .subject-group {
            margin-bottom: 30px;
        }
        .subject-group h3 {
            border-left: 5px solid #3498db;
            padding-left: 10px;
            color: #2c3e50;
            font-weight: 700;
            font-size: 1.25rem;
        }
        table.attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.attendance-table th,
        table.attendance-table td {
            padding: 8px;
            border: 1px solid #ccc;
            text-align: left;
            font-size: 0.95rem;
        }
        .present {
            color: #27ae60;
            font-weight: 600;
        }
        .absent {
            color: #c0392b;
            font-weight: 600;
        }
        .attendance-summary {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-bottom: 20px;
            gap: 15px;
        }
        .summary-card {
            flex: 1 1 200px;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .summary-card h3 {
            margin-top: 0;
            color: #2c3e50;
            font-size: 1rem;
            margin-bottom: 8px;
        }
        .summary-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #34495e;
        }
        .percentage {
            color: <?= $attendance_percentage >= 75 ? '#27ae60' : ($attendance_percentage >= 50 ? '#f39c12' : '#c0392b') ?>;
        }
        .filters {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .filter-group {
            display: inline-block;
            margin-right: 20px;
            min-width: 150px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #2c3e50;
        }
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .filter-group select:focus,
        .filter-group input:focus {
            border-color: #3498db;
            outline: none;
        }
        .filter-button,
        .reset-button {
            padding: 8px 20px;
            border-radius: 4px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            margin-top: 24px;
            font-size: 1rem;
        }
        .filter-button {
            background-color: #3498db;
            color: white;
            margin-right: 10px;
            transition: background-color 0.3s;
        }
        .filter-button:hover {
            background-color: #2980b9;
        }
        .reset-button {
            background-color: #95a5a6;
            color: white;
            transition: background-color 0.3s;
        }
        .reset-button:hover {
            background-color: #7f8c8d;
        }
        @media (max-width: 600px) {
            .filter-group {
                min-width: 100%;
                margin-bottom: 15px;
            }
            .attendance-summary {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="card">
            <h2>📅 My Attendance Overview</h2>
            
            <!-- Filters Section -->
            <div class="filters">
                <form method="get" action="">
                    <div class="filter-group">
                        <label for="subject">Subject</label>
                        <select id="subject" name="subject">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= htmlspecialchars($subject) ?>" <?= $subject_filter == $subject ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($subject) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="month">Month</label>
                        <select id="month" name="month">
                            <option value="">All Months</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= $month_filter == $i ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="Present" <?= $status_filter == 'Present' ? 'selected' : '' ?>>Present</option>
                            <option value="Absent" <?= $status_filter == 'Absent' ? 'selected' : '' ?>>Absent</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="filter-button">Apply Filters</button>
                        <a href="view_attendance.php" class="reset-button">Reset</a>
                    </div>
                </form>
            </div>
            
            <!-- Attendance Summary Cards -->
            <div class="attendance-summary">
                <div class="summary-card">
                    <h3>Total Classes</h3>
                    <div class="value"><?= (int)$total_records ?></div>
                </div>
                <div class="summary-card">
                    <h3>Present</h3>
                    <div class="value"><?= (int)$total_present ?></div>
                </div>
                <div class="summary-card">
                    <h3>Absent</h3>
                    <div class="value"><?= (int)$total_absent ?></div>
                </div>
                <div class="summary-card">
                    <h3>Attendance Percentage</h3>
                    <div class="value percentage"><?= $attendance_percentage ?>%</div>
                </div>
            </div>
            
            <!-- Subject-wise Attendance Details -->
            <?php if (!empty($attendance_data)): ?>
                <?php foreach ($attendance_data as $subject => $entries): 
                    $subject_present = 0;
                    $subject_absent = 0;
                    foreach ($entries as $entry) {
                        if ($entry['status'] === 'Present') {
                            $subject_present++;
                        } elseif ($entry['status'] === 'Absent') {
                            $subject_absent++;
                        }
                    }
                    $subject_total = count($entries);
                    $subject_percentage = $subject_total > 0 ? round(($subject_present / $subject_total) * 100, 2) : 0;
                ?>
                    <div class="subject-group">
                        <h3>
                            <?= htmlspecialchars($subject) ?> 
                            <span style="font-size: 14px; color: <?= $subject_percentage >= 75 ? '#27ae60' : ($subject_percentage >= 50 ? '#f39c12' : '#c0392b') ?>">
                                (<?= $subject_present ?>/<?= $subject_total ?> - <?= $subject_percentage ?>%)
                            </span>
                        </h3>
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entries as $entry): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($entry['date']) ?></td>
                                        <td class="<?= strtolower(htmlspecialchars($entry['status'])) ?>">
                                            <?= htmlspecialchars($entry['status']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No attendance records found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
