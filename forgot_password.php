<?php
session_start();
include 'includes/db.php';

$error = '';
$show_reset_form = false;
$username = '';
$role = '';
$user_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_user'])) {
        // Step 1: Verify user exists
        $username = trim($_POST['username'] ?? '');
        $role = $_POST['role'] ?? '';

        if (empty($username) || empty($role)) {
            $error = "Please enter username and select role.";
        } else {
            $sql = "SELECT user_id FROM users WHERE username = ? AND role = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $username, $role);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $user_id = $user['user_id'];
                $show_reset_form = true;
            } else {
                $error = "User not found with given username and role.";
            }
        }
    } elseif (isset($_POST['reset_password'])) {
        // Step 2: Reset password
        $user_id = (int)($_POST['user_id'] ?? 0);
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($user_id <= 0) {
            $error = "Invalid user.";
        } elseif (empty($password) || empty($confirm_password)) {
            $error = "Please enter and confirm your new password.";
            $show_reset_form = true;
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
            $show_reset_form = true;
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
            $show_reset_form = true;
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password_hash = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $password_hash, $user_id);
            if ($stmt->execute()) {
                $success = "Password updated successfully. You can now <a href='login.php'>login</a>.";
            } else {
                $error = "Failed to update password. Please try again.";
                $show_reset_form = true;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Forgot Password | BS Academic System</title>
<link rel="stylesheet" href="assets/css/login.css" />
</head>
<body>
<div class="container">
    <h2>Forgot Password</h2>

    <?php if (!empty($error)): ?>
        <div class="alert success"><?= htmlspecialchars($error) ?></div>
    <?php elseif (!empty($success)): ?>
        <div class="error-message"><?= $success ?></div>
    <?php endif; ?>

    <?php if (!$show_reset_form): ?>
    <!-- Step 1: Verify user -->
    <form method="POST" novalidate>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus placeholder="Enter your username" value="<?= htmlspecialchars($username) ?>" />

        <label for="role">Role</label>
        <select id="role" name="role" required>
            <option value="" disabled <?= $role === '' ? 'selected' : '' ?>>Select your role</option>
            <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Student</option>
            <option value="faculty" <?= $role === 'faculty' ? 'selected' : '' ?>>Faculty</option>
            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>

        <button type="submit" name="verify_user" class="btn">Verify User</button>
    </form>
    <?php else: ?>
    <!-- Step 2: Reset password -->
    <form method="POST" novalidate>
        <input type="hidden" name="user_id" value="<?= (int)$user_id ?>" />

        <label for="password">New Password</label>
        <input type="password" id="password" name="password" required placeholder="Enter new password" />

        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm new password" />

        <button type="submit" name="reset_password" class="btn">Reset Password</button>
    </form>
    <?php endif; ?>

    <p><a href="login.php">Back to Login</a></p>
</div>
</body>
</html>
