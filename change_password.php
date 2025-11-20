<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT role, username FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: index.php");
    exit;
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_pass = trim($_POST['new_password']);
    $confirm_pass = trim($_POST['confirm_password']);

    if (strlen($new_pass) < 6) {
        $error = "Password must be at least 6 characters!";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "Passwords do not match!";
    } else {
        $hashed = md5($new_pass);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, first_login = 0 WHERE id = ?");
        $stmt->execute([$hashed, $_SESSION['user_id']]);

        $success = "Password set successfully!";

        $dashboard = match ($user['role']) {
            'student' => 'dashboards/student.php',
            'warden'  => 'dashboards/warden.php',
            'office'  => 'dashboards/office.php',
            default   => 'index.php'
        };

        echo "<script>
            setTimeout(() => { window.location = '$dashboard'; }, 1800);
        </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>First Login - Set Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
            padding: 3rem 2rem;
            max-width: 440px;
            width: 100%;
            text-align: center;
        }
        .avatar-circle {
            width: 90px;
            height: 90px;
            background: #0d6efd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.8rem;
            box-shadow: 0 8px 25px rgba(13, 110, 253, 0.4);
        }
        .glass-input {
            background: rgba(255, 255, 255, 0.12) !important;
            border: 1px solid rgba(255, 255, 255, 0.25) !important;
            color: #fff !important;
            border-radius: 16px;
            padding: 0.9rem 1.2rem;
            font-size: 1rem;
        }
        .glass-input:focus {
            background: rgba(255, 255, 255, 0.18) !important;
            border-color: #0d6efd !important;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.3);
        }
        .glass-input::placeholder { color: #bbbbbb !important; }
        .btn-continue {
            background: #0d6efd;
            border: none;
            border-radius: 16px;
            padding: 0.8rem 1.5rem;
            font-weight: 600;
            font-size: 1.05rem;
            margin-top: 1.2rem;
            transition: all 0.3s;
        }
        .btn-continue:hover {
            background: #0b5ed7;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="glass-card text-white">
    <div class="avatar-circle">
        <i class="bi bi-person-fill text-white fs-1"></i>
    </div>


    <p class="text-white-75 mb-4">This is your first login.<br>Please set a secure password to continue.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger small py-2 rounded-3 mb-3"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success py-3 rounded-3">
            <i class="bi bi-check-circle-fill me-2"></i><?= $success ?>
            <br><small>Redirecting to your dashboard...</small>
        </div>
    <?php else: ?>
    <form method="POST">
        <div class="mb-3 text-start">
            <label class="form-label text-white fw-medium">New Password</label>
            <input type="password" name="new_password" class="form-control glass-input" 
                   placeholder="Enter password (min 6 chars)" required minlength="6">
        </div>

        <div class="mb-4 text-start">
            <label class="form-label text-white fw-medium">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control glass-input" 
                   placeholder="Re-type password" required minlength="6">
        </div>

        <button type="submit" class="btn btn-primary btn-continue w-100">
            Continue
        </button>
    </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>