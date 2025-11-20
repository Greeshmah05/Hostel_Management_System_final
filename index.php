<?php
session_start();
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'student': header("Location: dashboards/student.php"); break;
        case 'warden': header("Location: dashboards/warden.php"); break;
        case 'office': header("Location: dashboards/office.php"); break;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HMS | Login</title>

    <!-- Bootstrap 5 + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-bg d-flex align-items-center justify-content-center min-vh-100 p-3">

    <!-- CENTERED GLASS CARD -->
    <div class="glass-card p-4 p-md-5 rounded-4 shadow-lg" style="max-width: 400px; width: 100%;">
        
        <!-- Title -->
        <div class="text-center mb-4">
            <h3 class="fw-bold text-white mb-1">Hostel Management</h3>
            <p class="text-white-50 small">Login to continue</p>
        </div>

        <!-- Login Form -->
        <form action="login.php" method="POST">
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger small rounded-3 mb-3">Invalid username or password!</div>
            <?php endif; ?>

            <!-- Username -->
            <div class="mb-3">
                <label class="text-white small fw-medium">
                    <i class="bi bi-person me-1"></i> Username
                </label>
                <input type="text" name="username" class="form-control glass-input" placeholder="Enter username" required>
            </div>

            <!-- Password -->
            <div class="mb-4">
                <label class="text-white small fw-medium">
                    <i class="bi bi-lock me-1"></i> Password
                </label>
                <input type="password" name="password" class="form-control glass-input" placeholder="Enter password" required>
            </div>

            <!-- Login Button -->
            <button type="submit" class="btn btn-login w-100 rounded-pill fw-semibold">
                Login
            </button>
        </form>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>