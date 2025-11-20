<?php
session_start();
include 'includes/db.php';

$token = $_GET['token'] ?? '';
$error = $success = '';

if (!$token) {
    $error = "Invalid or missing token!";
} else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_expiry > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "Link expired or invalid!";
    } elseif ($_POST['reset_password'] ?? false) {
        $pass = $_POST['password'];
        $confirm = $_POST['confirm'];

        if ($pass !== $confirm) {
            $error = "Passwords don't match!";
        } elseif (strlen($pass) < 6) {
            $error = "Password must be 6+ characters!";
        } else {
            $hashed = md5($pass);
            $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?")
                ->execute([$hashed, $user['id']]);
            $success = "Password updated! You can now login.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; display: flex; align-items: center; }
        .card { max-width: 420px; margin: auto; }
    </style>
</head>
<body>
<div class="container">
    <div class="card glass-card p-5 rounded-4 shadow-lg text-white">
        <h3 class="text-center mb-4">Reset Password</h3>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
            <a href="forgot_password.php" class="btn btn-outline-light">Try Again</a>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
            <a href="index.php" class="btn btn-primary w-100">Login Now</a>
        <?php else: ?>
            <form method="POST">
                <div class="mb-3">
                    <input type="password" name="password" class="form-control glass-input" placeholder="New Password" required minlength="6">
                </div>
                <div class="mb-3">
                    <input type="password" name="confirm" class="form-control glass-input" placeholder="Confirm Password" required>
                </div>
                <button type="submit" name="reset_password" class="btn btn-success w-100">Update Password</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>