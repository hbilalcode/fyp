<?php
session_start();

// 1. Only allow admins to access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';

$success = $error = null;

// 2. Handle user actions (delete, toggle status)
if (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action'];
    $user_id = (int) $_GET['id'];

    // Prevent admin from deleting their own account
    if ($user_id === $_SESSION['user_id']) {
        $error = "❌ You cannot delete your own account.";
    } else {
        // Get the role of the user to delete or update
        $role_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
        $role_stmt->bind_param("i", $user_id);
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();
        $user_data = $role_result->fetch_assoc();
        $role = $user_data['role'] ?? null;

        if ($action === 'delete') {
            // Delete from role-specific table first (if applicable)
            if ($role === 'student') {
                $del_student = $conn->prepare("DELETE FROM students WHERE student_id = ?");
                $del_student->bind_param("i", $user_id);
                $del_student->execute();
            } elseif ($role === 'faculty') {
                $del_faculty = $conn->prepare("DELETE FROM faculty WHERE faculty_id = ?");
                $del_faculty->bind_param("i", $user_id);
                $del_faculty->execute();
            }

            // Delete from users table
            $del_user = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $del_user->bind_param("i", $user_id);
            if ($del_user->execute()) {
                header("Location: manage_users.php?msg=deleted");
                exit();
            } else {
                $error = "❌ Error deleting user: " . $del_user->error;
            }
        }
    }
}

// 3. Fetch all users to display
$users = $conn->query("SELECT * FROM users ORDER BY role, user_id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users | BS Academic System</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
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
            <h2>Manage Users</h2>
            <a href="add_user.php" class="btn">Add New User</a>
        </div>

        <div class="card">
            <!-- Show error or success messages -->
            <?php if ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php elseif (isset($_GET['msg'])): ?>
                <div class="alert success">
                    <?php 
                        if ($_GET['msg'] === 'deleted') echo "✅ User deleted successfully.";
                        elseif ($_GET['msg'] === 'status-toggled') echo "✅ User status updated.";
                    ?>
                </div>
            <?php endif; ?>

            <!-- Users table -->
            <table class="users-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?= $user['user_id']; ?></td>
                            <td><?= htmlspecialchars($user['username']); ?></td>
                            <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                            <td><?= htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge-<?= htmlspecialchars($user['role']); ?>">
                                    <?= ucfirst(htmlspecialchars($user['role'])); ?>
                                </span>
                            </td>
                            <td>
                                <a href="edit_user.php?id=<?= $user['user_id']; ?>" class = "action-btn btn-edit">Edit</a>

                                <?php if ($user['role'] !== 'admin'): ?>
                                    <a href="?action=delete&id=<?= $user['user_id']; ?>"
                                       class="action-btn btn-delete"
                                       onclick="return confirm('Are you sure you want to delete this user?')" class = "action-btn btn-delete">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
