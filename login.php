<?php
session_start();
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = md5($_POST['password']);  // Will upgrade to password_hash later

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // Check if first login
        if ($user['first_login'] == 1) {
            header("Location: change_password.php");
        } else {
            // Redirect based on role
            switch ($user['role']) {
                case 'student':
                    header("Location: dashboards/student.php");
                    break;
                case 'warden':
                    header("Location: dashboards/warden.php");
                    break;
                case 'office':
                    header("Location: dashboards/office.php");
                    break;
            }
        }
        exit;
    } else {
        header("Location: index.php?error=1");
        exit;
    }
}
?>