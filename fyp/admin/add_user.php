<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';

$success = $error = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect and sanitize form data
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $contact_number = $_POST['contact_number'];

    // Check if username or email already exists
    $check_sql = "SELECT * FROM users WHERE username = ? OR email = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $error = "Username or email already exists!";
    } else {
        // Insert new user
        $insert_sql = "INSERT INTO users (username, email, password_hash, role, first_name, last_name, contact_number)
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sssssss", $username, $email, $password, $role, $first_name, $last_name, $contact_number);

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;

            // If student, add to students table
            if ($role == 'student') {
                $enrollment_number = $_POST['enrollment_number'];
                $admission_date = $_POST['admission_date'];
                $current_semester = $_POST['current_semester'];
                $department = $_POST['student_department'] ?? null;  // Changed here

                $student_sql = "INSERT INTO students (student_id, enrollment_number, admission_date, current_semester, department, program)
                                VALUES (?, ?, ?, ?, ?, 'BS')";
                $stmt = $conn->prepare($student_sql);
                $stmt->bind_param("issis", $user_id, $enrollment_number, $admission_date, $current_semester, $department);

                if ($stmt->execute()) {
                    // Register student in current semester
                    $sem_stmt = $conn->prepare("SELECT semester_id FROM semesters WHERE semester_number = ?");
                    $sem_stmt->bind_param("i", $current_semester);
                    $sem_stmt->execute();
                    $sem_result = $sem_stmt->get_result()->fetch_assoc();
                    if ($sem_result) {
                        $semester_id = $sem_result['semester_id'];
                        $reg_stmt = $conn->prepare("INSERT INTO student_semester_registration (student_id, semester_id, status) VALUES (?, ?, 'registered')");
                        $reg_stmt->bind_param("ii", $user_id, $semester_id);
                        $reg_stmt->execute();
                    }
                } else {
                    $error = "❌ Error inserting student: " . $stmt->error;
                }
            }
            // If faculty, add to faculty table
            elseif ($role == 'faculty') {
                $employee_id = $_POST['employee_id'];
                $department = $_POST['faculty_department'] ?? null;  // Changed here
                $designation = $_POST['designation'];
                $specialization = $_POST['specialization'];

                $faculty_sql = "INSERT INTO faculty (faculty_id, employee_id, department, designation, specialization)
                                VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($faculty_sql);
                $stmt->bind_param("issss", $user_id, $employee_id, $department, $designation, $specialization);
                $stmt->execute();
            }

            if (!$error) {
                $success = "✅ User created successfully!";
                $_POST = array(); // Clear form
            }
        } else {
            $error = "❌ Error creating user: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add User | BS Academic System</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script>
        function showRoleFields() {
            document.querySelectorAll('.role-specific').forEach(section => section.style.display = 'none');
            const role = document.getElementById('role').value;
            if (role) document.getElementById(role + '-fields').style.display = 'block';
        }
        window.onload = showRoleFields;
    </script>
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
            <h2>Add New User</h2>
            <a href="manage_users.php" class="btn">Back to Users</a>
        </div>
        <div class="card">
            <div class="form-container">
                <?php if (isset($success)): ?>
                    <div class="alert success"><?php echo $success; ?></div>
                <?php elseif (isset($error)): ?>
                    <div class="alert error"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST" action="add_user.php">
                    <div class="form-section">
                        <h3>Basic Information</h3>
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" required onchange="showRoleFields()">
                                <option value="">-- Select Role --</option>
                                <option value="student" <?= ($_POST['role'] ?? '') == 'student' ? 'selected' : ''; ?>>Student</option>
                                <option value="faculty" <?= ($_POST['role'] ?? '') == 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                                <option value="admin" <?= ($_POST['role'] ?? '') == 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="form-group"><label for="username">Username</label><input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"></div>
                        <div class="form-group"><label for="email">Email</label><input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
                        <div class="form-group"><label for="password">Password</label><input type="password" name="password" required></div>
                        <div class="form-group"><label for="first_name">First Name</label><input type="text" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"></div>
                        <div class="form-group"><label for="last_name">Last Name</label><input type="text" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"></div>
                        <div class="form-group"><label for="contact_number">Contact Number</label><input type="text" name="contact_number" value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>"></div>
                    </div>

                    <div id="student-fields" class="role-specific form-section" style="<?= ($_POST['role'] ?? '') == 'student' ? 'display:block' : 'display:none' ?>">
                        <h3>Student Info</h3>
                        <div class="form-group"><label>Enrollment Number</label><input type="text" name="enrollment_number" value="<?= htmlspecialchars($_POST['enrollment_number'] ?? '') ?>"></div>
                        <div class="form-group"><label>Admission Date</label><input type="date" name="admission_date" value="<?= htmlspecialchars($_POST['admission_date'] ?? '') ?>"></div>
                        <div class="form-group"><label>Current Semester</label><input type="number" name="current_semester" min="1" max="8" value="<?= htmlspecialchars($_POST['current_semester'] ?? 1) ?>"></div>
                        <div class="form-group"><label>Department</label><input type="text" name="student_department" value="<?= htmlspecialchars($_POST['student_department'] ?? '') ?>"></div>
                    </div>

                    <div id="faculty-fields" class="role-specific form-section" style="<?= ($_POST['role'] ?? '') == 'faculty' ? 'display:block' : 'display:none' ?>">
                        <h3>Faculty Info</h3>
                        <div class="form-group"><label>Employee ID</label><input type="text" name="employee_id" value="<?= htmlspecialchars($_POST['employee_id'] ?? '') ?>"></div>
                        <div class="form-group"><label>Department</label><input type="text" name="faculty_department" value="<?= htmlspecialchars($_POST['faculty_department'] ?? '') ?>"></div>
                        <div class="form-group"><label>Designation</label><input type="text" name="designation" value="<?= htmlspecialchars($_POST['designation'] ?? '') ?>"></div>
                        <div class="form-group"><label>Specialization</label><input type="text" name="specialization" value="<?= htmlspecialchars($_POST['specialization'] ?? '') ?>"></div>
                    </div>
                    <button type="submit" class="btn">Create User</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
