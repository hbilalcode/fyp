<?php
session_start();
include 'includes/db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get data from form
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    // Prepare SQL to prevent SQL injection
    $sql = "SELECT * FROM users WHERE username = ? AND role = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if user exists
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        // Verify password
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            // Redirect based on role
            if ($role == 'student') {
                header("Location: student/dashboard.php");
            } elseif ($role == 'faculty') {
                header("Location: faculty/dashboard.php");
            } elseif ($role == 'admin') {
                header("Location: admin/dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "User not found or role mismatch!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Login | BS Academic System</title>
  <link rel="stylesheet" href="assets/css/login.css" />
</head>
<body>
  <div class="container">
    <h2>Login to 🎓Academic System</h2>
    <?php if (!empty($error)) : ?>
      <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form action="login.php" method="POST" autocomplete="off" novalidate>
      <label for="username">Username</label>
      <input type="text" id="username" name="username" required autofocus placeholder="Enter your username" />

      <label for="password">Password</label>
      <input type="password" id="password" name="password" required placeholder="Enter your password" />

      <label for="role">Role</label>
      <select id="role" name="role" required>
        <option value="" disabled selected>Select your role</option>
        <option value="student">Student</option>
        <option value="faculty">Faculty</option>
        <option value="admin">Admin</option>
      </select>

      <button type="submit" class="btn">Login</button>
    </form>
    <a href="index.php" class="btn-back back" role="button" aria-label="Go back to homepage">⮌ Back</a>
    <a href="forgot_password.php" class = "forgot">Forgot Password?</a>
  </div>
</body>
</html>
