<?php
session_start();
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COALESCE(u.email, s.email, w.email) AS user_email
        FROM users u
        LEFT JOIN students s ON u.id = s.user_id AND u.role = 'student'
        LEFT JOIN wardens w ON u.id = w.user_id AND u.role = 'warden'
        WHERE u.username = ?
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || empty($user['user_email'])) {
        $error = "No account found or email not set!";
    } else {
        $email = $user['user_email'];
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?");
        $stmt->execute([$token, $expiry, $user['id']]);

        $reset_link = "https://yourdomain.com/reset_password.php?token=" . $token;
        // Replace yourdomain.com with your actual domain or leave as relative:
        // $reset_link = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=$token";

        $subject = "Password Reset Request - HMS";
        $message = "Hi {$user['username']},\n\nYou requested a password reset.\n\nClick this link to reset your password (valid for 15 minutes):\n$reset_link\n\nIf you didn't request this, please ignore this email.\n\nThank you,\nHostel Management System";
        $headers = "From: no-reply@yourhostel.com";

        if (mail($email, $subject, $message, $headers)) {
            $success = "Reset link sent to your email!";
        } else {
            $error = "Failed to send email. Contact admin.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password - HMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .glass-card { backdrop-filter: blur(12px); background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); }
    </style>
</head>
<body>
<div class="container">
    <div class="glass-card p-5 rounded-4 shadow-lg" style="max-width: 420px;">
        <div class="text-center mb-4">
            <h2 class="text-white fw-bold">Forgot Password?</h2>
            <p class="text-white-75">Enter your username to get reset link</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger rounded-3"><?= $error ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="alert alert-success rounded-3"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <input type="text" name="username" class="form-control glass-input" 
                       placeholder="Enter Username" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-pill">
                Send Reset Link
            </button>
        </form>

        <div class="text-center mt-4">
            <a href="index.php" class="text-white-50 small">Back to Login</a>
        </div>
    </div>
</div>
</body>
</html>