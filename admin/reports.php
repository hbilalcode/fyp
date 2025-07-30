<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';

// Determine report type (faculty, students, subjects)
$report_type = $_GET['type'] ?? 'faculty';

// For students report, get semester filter from GET
$selected_semester_id = $_GET['semester_id'] ?? '';

// Fetch all semesters for the semester filter dropdown
$semesters = [];
$sem_result = $conn->query("SELECT semester_id, semester_number, academic_year FROM semesters ORDER BY academic_year DESC, semester_number DESC");
while ($row = $sem_result->fetch_assoc()) {
    $semesters[] = $row;
}

// Fetch data based on report type and semester filter if applicable
$data = [];
if ($report_type === 'faculty') {
    $sql = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.contact_number, f.employee_id, f.department, f.designation, f.specialization
            FROM users u JOIN faculty f ON u.user_id = f.faculty_id
            WHERE u.role = 'faculty' ORDER BY u.last_name";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
} elseif ($report_type === 'students') {
    if ($selected_semester_id) {
        // Fetch students registered in selected semester
        $sql = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.contact_number, s.enrollment_number, s.admission_date, s.program, s.current_semester, s.department
                FROM users u
                JOIN students s ON u.user_id = s.student_id
                JOIN student_semester_registration ssr ON s.student_id = ssr.student_id
                WHERE u.role = 'student' AND ssr.semester_id = ? AND ssr.status = 'registered'
                ORDER BY u.last_name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $selected_semester_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    } else {
        // No semester filter, fetch all students
        $sql = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.contact_number, s.enrollment_number, s.admission_date, s.program, s.current_semester, s.department
                FROM users u JOIN students s ON u.user_id = s.student_id
                WHERE u.role = 'student' ORDER BY u.last_name";
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
} elseif ($report_type === 'subjects') {
    $sql = "SELECT subject_id, subject_code, subject_name, credit_hours, department FROM subjects ORDER BY subject_name";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
} else {
    $report_type = 'faculty'; // fallback
}

// Helper function to escape output
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Reports | BS Academic System</title>
<link rel="stylesheet" href="../assets/css/dashboard.css" />
<style>
    .report-container { max-width: 100%; background: #fff; padding: 20px; border-radius: 8px; }
    .report-header { margin-bottom: 20px; }
    .report-filters { margin-bottom: 20px; }
    .report-filters select { padding: 8px 12px; font-size: 1rem; }
</style>
<!-- jsPDF and autoTable CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
<div class="dashboard">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="report-container">
            <h1 class="report-header">Reports</h1>

            <form method="GET" class="report-filters" id="reportForm">
                <label for="type">Select Report Type: </label>
                <select name="type" id="type" onchange="document.getElementById('reportForm').submit()">
                    <option value="faculty" <?= $report_type === 'faculty' ? 'selected' : '' ?>>Faculty Members</option>
                    <option value="students" <?= $report_type === 'students' ? 'selected' : '' ?>>Students</option>
                    <option value="subjects" <?= $report_type === 'subjects' ? 'selected' : '' ?>>Subjects</option>
                </select>

                <?php if ($report_type === 'students'): ?>
                    &nbsp;&nbsp;&nbsp;
                    <label for="semester_id">Filter by Semester:</label>
                    <select name="semester_id" id="semester_id" onchange="document.getElementById('reportForm').submit()">
                        <option value="">All Semesters</option>
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?= e($sem['semester_id']) ?>" <?= ($sem['semester_id'] == $selected_semester_id) ? 'selected' : '' ?>>
                                Semester <?= e($sem['semester_number']) ?> - <?= e($sem['academic_year']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </form>

            <?php if ($report_type === 'faculty'): ?>
                <?php if (count($data) > 0): ?>
                <table id="reportTable">
                    <thead>
                        <tr>
                            <th>ID</th><th>Name</th><th>Email</th><th>Contact</th><th>Employee ID</th><th>Department</th><th>Designation</th><th>Specialization</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?= e($row['user_id']) ?></td>
                            <td><?= e($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td><?= e($row['email']) ?></td>
                            <td><?= e($row['contact_number']) ?></td>
                            <td><?= e($row['employee_id']) ?></td>
                            <td><?= e($row['department']) ?></td>
                            <td><?= e($row['designation']) ?></td>
                            <td><?= e($row['specialization']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p>No faculty members found.</p>
                <?php endif; ?>

            <?php elseif ($report_type === 'students'): ?>
                <?php if (count($data) > 0): ?>
                <table id="reportTable">
                    <thead>
                        <tr>
                            <th>ID</th><th>Name</th><th>Email</th><th>Contact</th><th>Enrollment No</th><th>Admission Date</th><th>Program</th><th>Semester</th><th>Department</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?= e($row['user_id']) ?></td>
                            <td><?= e($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td><?= e($row['email']) ?></td>
                            <td><?= e($row['contact_number']) ?></td>
                            <td><?= e($row['enrollment_number']) ?></td>
                            <td><?= e($row['admission_date']) ?></td>
                            <td><?= e($row['program']) ?></td>
                            <td><?= e($row['current_semester']) ?></td>
                            <td><?= e($row['department']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p>No students found.</p>
                <?php endif; ?>

            <?php elseif ($report_type === 'subjects'): ?>
                <?php if (count($data) > 0): ?>
                <table id="reportTable">
                    <thead>
                        <tr>
                            <th>ID</th><th>Code</th><th>Name</th><th>Credit Hours</th><th>Department</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?= e($row['subject_id']) ?></td>
                            <td><?= e($row['subject_code']) ?></td>
                            <td><?= e($row['subject_name']) ?></td>
                            <td><?= e($row['credit_hours']) ?></td>
                            <td><?= e($row['department']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p>No subjects found.</p>
                <?php endif; ?>
            <?php endif; ?>

            <button class="btn" onclick="exportReportToPDF()">Download PDF</button>
        </div>
    </div>
</div>

<script>
    async function exportReportToPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'pt', 'a4');
        doc.setFontSize(18);
        doc.text("Report - <?= ucfirst($report_type) ?>", doc.internal.pageSize.getWidth() / 2, 40, { align: "center" });
        doc.setFontSize(10);
        doc.text(`Generated on: ${new Date().toLocaleString()}`, doc.internal.pageSize.getWidth() / 2, 60, { align: "center" });

        // Prepare columns and rows based on report type
        let columns = [];
        let rows = [];

        <?php if ($report_type === 'faculty'): ?>
            columns = ['ID', 'Name', 'Email', 'Contact', 'Employee ID', 'Department', 'Designation', 'Specialization'];
            rows = <?= json_encode(array_map(function($r) {
                return [
                    $r['user_id'],
                    $r['first_name'].' '.$r['last_name'],
                    $r['email'],
                    $r['contact_number'],
                    $r['employee_id'],
                    $r['department'],
                    $r['designation'],
                    $r['specialization']
                ];
            }, $data)); ?>;
        <?php elseif ($report_type === 'students'): ?>
            columns = ['ID', 'Name', 'Email', 'Contact', 'Enrollment No', 'Admission Date', 'Program', 'Semester', 'Department'];
            rows = <?= json_encode(array_map(function($r) {
                return [
                    $r['user_id'],
                    $r['first_name'].' '.$r['last_name'],
                    $r['email'],
                    $r['contact_number'],
                    $r['enrollment_number'],
                    $r['admission_date'],
                    $r['program'],
                    $r['current_semester'],
                    $r['department']
                ];
            }, $data)); ?>;
        <?php elseif ($report_type === 'subjects'): ?>
            columns = ['ID', 'Code', 'Name', 'Credit Hours', 'Department'];
            rows = <?= json_encode(array_map(function($r) {
                return [
                    $r['subject_id'],
                    $r['subject_code'],
                    $r['subject_name'],
                    $r['credit_hours'],
                    $r['department']
                ];
            }, $data)); ?>;
        <?php endif; ?>

        doc.autoTable({
            head: [columns],
            body: rows,
            startY: 80,
            styles: { fontSize: 8, cellPadding: 3 },
            headStyles: { fillColor: [0, 123, 255] },
            margin: { left: 20, right: 20 }
        });

        doc.save(`Report_<?= ucfirst($report_type) ?>.pdf`);
    }
</script>
</body>
</html>
