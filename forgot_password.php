<?php
session_start();
include 'includes/db.php';

// Clear session if requested
if (isset($_GET['clear'])) {
    unset($_SESSION['reset_user_id']);
    unset($_SESSION['reset_username']);
    unset($_SESSION['security_question']);
    exit;
}

$step = 1;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['verify_username'])) {
        // Step 1: Verify username exists
        $username = trim($_POST['username']);
        $stmt = $pdo->prepare("SELECT id, security_question, security_answer FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && $user['security_answer']) {
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_username'] = $username;
            $_SESSION['security_question'] = $user['security_question'];
            $step = 2;
        } else {
            $error = "Username not found or security question not set!";
        }
    } elseif (isset($_POST['verify_answer'])) {
        // Step 2: Verify security answer
        if (!isset($_SESSION['reset_user_id'])) {
            header("Location: forgot_password.php");
            exit;
        }

        $answer = trim(strtolower($_POST['security_answer']));
        $stmt = $pdo->prepare("SELECT security_answer FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['reset_user_id']]);
        $user = $stmt->fetch();

        if ($user && strtolower($user['security_answer']) === $answer) {
            $step = 3;
        } else {
            $error = "Incorrect answer to security question!";
            $step = 2;
        }
    } elseif (isset($_POST['reset_password'])) {
        // Step 3: Reset password
        if (!isset($_SESSION['reset_user_id'])) {
            header("Location: forgot_password.php");
            exit;
        }

        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters long!";
            $step = 3;
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match!";
            $step = 3;
        } else {
            $hashed_password = md5($new_password);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, first_login = 0 WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['reset_user_id']]);

            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_username']);
            unset($_SESSION['security_question']);

            $success = "Password reset successful! You can now login with your new password.";
            $step = 4;
        }
    }
}

// Preserve step from session
if (isset($_SESSION['reset_user_id']) && $step == 1) {
    $step = isset($_POST['verify_answer']) ? 2 : (isset($_POST['reset_password']) ? 3 : 2);
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-bg d-flex align-items-center justify-content-center min-vh-100 p-3">

    <div class="glass-card p-4 p-md-5 rounded-4 shadow-lg" style="max-width: 450px; width: 100%;">
        
        <div class="text-center mb-4">
            <i class="bi bi-key-fill text-white" style="font-size: 3rem;"></i>
            <h3 class="fw-bold text-white mb-1">Forgot Password</h3>
            <p class="text-white-50 small">Reset your password in 3 easy steps</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show small rounded-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show small rounded-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
            </div>
            <div class="text-center mt-3">
                <a href="index.php" class="btn btn-login w-100 rounded-pill fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                </a>
            </div>
        <?php elseif ($step == 1): ?>
            <!-- Step 1: Enter Username -->
            <form method="POST">
                <div class="mb-3">
                    <label class="text-white small fw-medium">
                        <i class="bi bi-person me-1"></i> Username
                    </label>
                    <input type="text" name="username" class="form-control glass-input" 
                           placeholder="Enter your username" required>
                </div>

                <button type="submit" name="verify_username" class="btn btn-login w-100 rounded-pill fw-semibold mb-3">
                    <i class="bi bi-arrow-right me-2"></i>Continue
                </button>

                <div class="text-center">
                    <a href="index.php" class="text-white-50 small text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i>Back to Login
                    </a>
                </div>
            </form>

        <?php elseif ($step == 2): ?>
            <!-- Step 2: Answer Security Question -->
            <form method="POST">
                <div class="mb-3">
                    <label class="text-white small fw-medium">
                        <i class="bi bi-question-circle me-1"></i> Security Question
                    </label>
                    <input type="text" class="form-control glass-input" 
                           value="<?= htmlspecialchars($_SESSION['security_question']) ?>" readonly>
                </div>

                <div class="mb-3">
                    <label class="text-white small fw-medium">
                        <i class="bi bi-chat-left-text me-1"></i> Your Answer
                    </label>
                    <input type="text" name="security_answer" class="form-control glass-input" 
                           placeholder="Enter your answer" required>
                </div>

                <button type="submit" name="verify_answer" class="btn btn-login w-100 rounded-pill fw-semibold mb-3">
                    <i class="bi bi-check-circle me-2"></i>Verify
                </button>

                <div class="text-center">
                    <a href="forgot_password.php" class="text-white-50 small text-decoration-none" onclick="clearSession()">
                        <i class="bi bi-arrow-left me-1"></i>Start Over
                    </a>
                </div>
            </form>

        <?php elseif ($step == 3): ?>
            <!-- Step 3: Reset Password -->
            <form method="POST">
                <div class="mb-3">
                    <label class="text-white small fw-medium">
                        <i class="bi bi-lock me-1"></i> New Password
                    </label>
                    <input type="password" name="new_password" class="form-control glass-input" 
                           placeholder=" " required minlength="6">
                </div>

                <div class="mb-3">
                    <label class="text-white small fw-medium">
                        <i class="bi bi-lock-fill me-1"></i> Confirm Password
                    </label>
                    <input type="password" name="confirm_password" class="form-control glass-input" 
                           placeholder=" " required minlength="6">
                </div>

                <button type="submit" name="reset_password" class="btn btn-login w-100 rounded-pill fw-semibold mb-3">
                    <i class="bi bi-shield-check me-2"></i>Reset Password
                </button>

                <div class="text-center">
                    <a href="forgot_password.php" class="text-white-50 small text-decoration-none" onclick="clearSession()">
                        <i class="bi bi-arrow-left me-1"></i>Start Over
                    </a>
                </div>
            </form>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function clearSession() {
            // Use fetch to clear session then reload
            fetch('forgot_password.php?clear=1')
                .then(() => window.location.href = 'forgot_password.php');
            return false;
        }
    </script>
</body>
</html>
