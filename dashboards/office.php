<?php
session_start();
// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'office') {
    header("Location: ../index.php");
    exit;
}
include '../includes/db.php';

// === CHANGE PASSWORD ===
if (isset($_POST['change_password'])) {
    $current = md5($_POST['current_password']);
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user['password'] !== $current) {
        $error = "Current password is incorrect!";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match!";
    } elseif (strlen($new) < 6) {
        $error = "Password must be 6+ characters!";
    } else {
        $hashed = md5($new);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $_SESSION['user_id']]);
        $success = "Password updated!";
    }
}

// === ADD STUDENT ===
if (isset($_POST['add_student'])) {
    $name = trim($_POST['name']);
    $register_no = trim($_POST['register_no']);
    $block = $_POST['block'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $dob = $_POST['dob'];
    $address = $_POST['address'];
    $year = $_POST['year'];
    $branch = $_POST['branch'];

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address!";
    } else {
        $username = $register_no;
        $password = md5($register_no . '123');

        try {
            $pdo->beginTransaction();

        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->rowCount() > 0) throw new Exception("Register No already used!");

        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, first_login) VALUES (?, ?, 'student', 1)");
        $stmt->execute([$username, $password]);
        $user_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO students (user_id, name, register_no, block, phone, email, dob, address, year, branch, semester_mess_balance, semester_rent) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 25000.00, 50000.00)");
        $stmt->execute([$user_id, $name, $register_no, $block, $phone, $email, $dob, $address, $year, $branch]);

            $pdo->commit();
            $success = "Student added! Login: <code>$username</code> | Pass: <code>$register_no"."123</code>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// === ADD WARDEN ===
if (isset($_POST['add_warden'])) {
    $name = trim($_POST['warden_name']);
    $username = trim($_POST['warden_username']);
    $block = $_POST['block'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $dob = $_POST['dob'];
    $address = $_POST['address'];

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address!";
    } else {
        $auto_password = $username . '123';
        $hashed_password = md5($auto_password);

        try {
            $pdo->beginTransaction();

        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->rowCount() > 0) throw new Exception("Username '$username' already taken!");

        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, first_login) VALUES (?, ?, 'warden', 1)");
        $stmt->execute([$username, $hashed_password]);
        $user_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO wardens (user_id, name, block, phone, email, dob, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $name, $block, $phone, $email, $dob, $address]);

            $pdo->commit();
            $warden_success = "Warden added! Login: <code>$username</code> | Pass: <code>$auto_password</code>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// === UPLOAD NOTICE ===
if (isset($_POST['upload_notice'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $posted_by = $_SESSION['user_id'];
    $target_audience = $_POST['target_audience'] ?? 'both';
    
    // Check if target_audience column exists, if not add it
    try {
        $stmt = $pdo->prepare("INSERT INTO notices (title, content, posted_by, target_audience) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $content, $posted_by, $target_audience]);
    } catch (PDOException $e) {
        // Column doesn't exist, add it
        $pdo->exec("ALTER TABLE notices ADD COLUMN target_audience VARCHAR(20) DEFAULT 'both'");
        $stmt = $pdo->prepare("INSERT INTO notices (title, content, posted_by, target_audience) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $content, $posted_by, $target_audience]);
    }
    $notice_success = "Notice uploaded!";
}

// === DELETE NOTICE (ONLY OWN) ===
if (isset($_POST['delete_notice'])) {
    $nid = $_POST['notice_id'];
    $stmt = $pdo->prepare("DELETE FROM notices WHERE id = ? AND posted_by = ?");
    $stmt->execute([$nid, $_SESSION['user_id']]);
    $success = "Notice deleted!";
}

// === UPLOAD MESS BILL ===
if (isset($_POST['upload_bill'])) {
    $student_id = $_POST['student_id'];
    $month = $_POST['month'];
    $amount = (float)$_POST['amount'];
    $uploaded_by = $_SESSION['user_id'];
    $check = $pdo->prepare("SELECT id FROM mess_bills WHERE student_id = ? AND month = ?");
    $check->execute([$student_id, $month]);
    if ($check->rowCount() > 0) {
        $error = "Bill for $month already exists!";
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO mess_bills (student_id, month, amount, uploaded_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$student_id, $month, $amount, $uploaded_by]);
            $stmt = $pdo->prepare("UPDATE students SET semester_mess_balance = semester_mess_balance - ? WHERE id = ? AND semester_mess_balance >= ?");
            $stmt->execute([$amount, $student_id, $amount]);
            $pdo->commit();
            $bill_success = "Mess bill ₹$amount uploaded for $month! Balance deducted.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Insufficient balance or error: " . $e->getMessage();
        }
    }
}
// === UPDATE WARDEN (FULL - ALL DETAILS) ===
if (isset($_POST['update_warden'])) {
    $user_id    = (int)$_POST['edit_warden_id'];
    $name       = trim($_POST['edit_name']);
    $username   = trim($_POST['edit_username']);
    $block      = $_POST['edit_block'];
    $phone      = $_POST['edit_phone'];
    $email      = $_POST['edit_email'];
    $dob        = $_POST['edit_dob'];
    $address    = $_POST['edit_address'];

    if (empty($name) || empty($username) || empty($block) || empty($phone) || empty($email) || empty($dob) || empty($address)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address!";
    } else {
        try {
            $pdo->beginTransaction();

            // Check username uniqueness
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->execute([$username, $user_id]);
            if ($check->rowCount() > 0) {
                throw new Exception("Username '$username' is already taken.");
            }

            // Update username in users table
            $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->execute([$username, $user_id]);

            // Update ALL warden details
            $stmt = $pdo->prepare("UPDATE wardens SET name = ?, block = ?, phone = ?, email = ?, dob = ?, address = ? WHERE user_id = ?");
            $stmt->execute([$name, $block, $phone, $email, $dob, $address, $user_id]);

            $pdo->commit();
            $success = "Warden <strong>" . htmlspecialchars($name) . "</strong> updated successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Update failed: " . $e->getMessage();
        }
    }
}

// === DELETE WARDEN ===
if (isset($_POST['delete_warden'])) {
    $user_id = $_POST['delete_warden_id'];
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM wardens WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'warden'");
        $stmt->execute([$user_id]);
        $pdo->commit();
        $success = "Warden deleted!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Delete failed: " . $e->getMessage();
    }
}

// === UPDATE STUDENT ===
if (isset($_POST['update_student'])) {
    $student_id = (int)$_POST['edit_student_id'];
    $name       = trim($_POST['edit_name']);
    $phone      = $_POST['edit_phone'];
    $email      = $_POST['edit_email'];
    $dob        = $_POST['edit_dob'];
    $address    = $_POST['edit_address'];
    $year       = $_POST['edit_year'];
    $branch     = $_POST['edit_branch'];
    $block      = $_POST['edit_block'];

    if (empty($name) || empty($phone) || empty($email) || empty($dob) || empty($address) || empty($year) || empty($branch) || empty($block)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address!";
    } else {
        try {
            $pdo->beginTransaction();

            // Update students table
            $stmt = $pdo->prepare("UPDATE students SET name = ?, phone = ?, email = ?, dob = ?, address = ?, year = ?, branch = ?, block = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $email, $dob, $address, $year, $branch, $block, $student_id]);

            $pdo->commit();
            $success = "Student <strong>" . htmlspecialchars($name) . "</strong> updated successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Update failed: " . $e->getMessage();
        }
    }
}

// === MARK FEE PAID ===
if (isset($_POST['mark_fee_paid'])) {
    $student_id = (int)$_POST['fee_student_id'];
    try {
        $check = $pdo->prepare("SELECT name, register_no FROM students WHERE id = ? AND fee_status = 'not_paid'");
        $check->execute([$student_id]);
        $student = $check->fetch();
        if ($student) {
            $stmt = $pdo->prepare("UPDATE students SET fee_status = 'paid', fee_paid_by = ?, fee_paid_at = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $student_id]);
            $fee_success = "Fee PAID: <strong>" . htmlspecialchars($student['name']) . " (" . $student['register_no'] . ")</strong>";
        } else {
            $error = "Already paid or not found.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// === DELETE STUDENT – FINAL FIX (Register No + Room Freed) ===
if (isset($_POST['delete_student'])) {
    $student_id = $_POST['delete_student_id'];
    
    try {
        $pdo->beginTransaction();

        // 1. Get user_id + room BEFORE deleting anything
        $stmt = $pdo->prepare("SELECT user_id, room_no, block FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();

        if (!$student) {
            throw new Exception("Student not found");
        }

        $user_id = $student['user_id'];
        $room_no = $student['room_no'];
        $block   = $student['block'];

        // 2. Delete all related records
        $pdo->prepare("DELETE FROM attendance WHERE student_id = ?")->execute([$student_id]);
        $pdo->prepare("DELETE FROM mess_bills WHERE student_id = ?")->execute([$student_id]);
        $pdo->prepare("DELETE FROM leave_requests WHERE student_id = ?")->execute([$student_id]);
        $pdo->prepare("DELETE FROM visitor_requests WHERE student_id = ?")->execute([$student_id]);
        $pdo->prepare("DELETE FROM cleaning_requests WHERE student_id = ?")->execute([$student_id]);
        $pdo->prepare("DELETE FROM complaints WHERE student_id = ?")->execute([$student_id]);

        // 3. Delete student record
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$student_id]);

        // 4. Delete user → THIS FREES THE REGISTER NUMBER!
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);

        // 5. Free the room
        if ($room_no) {
            $pdo->prepare("UPDATE rooms SET occupied = occupied - 1 
                           WHERE block = ? AND room_no = ? AND occupied > 0")
                ->execute([$block, $room_no]);
        }

        $pdo->commit();
        $success = "Student deleted!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Delete failed: " . $e->getMessage();
    }
}

// === RESET ALL MESS BALANCES + OPTIONAL CLEAR HISTORY ===
if (isset($_POST['reset_mess_bill'])) {
    try {
        $pdo->beginTransaction();

        $default_balance = 25000.00;

        // 1. Reset balance
        $stmt = $pdo->prepare("UPDATE students SET semester_mess_balance = ?");
        $stmt->execute([$default_balance]);
        $affected = $stmt->rowCount();

        // 2. DELETE ALL OLD BILLS (Uncomment the line below if you want FULL wipe)
        $pdo->exec("DELETE FROM mess_bills");   // ← Remove // to delete all history

        $pdo->commit();

        $success = "Mess reset complete for <strong>$affected students</strong>! 
                    Balance = ₹25,000. " . 
                    ($pdo->query("SELECT COUNT(*) FROM mess_bills")->fetchColumn() == 0 
                        ? "All old bills cleared." 
                        : "Old bills preserved.");

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Reset failed: " . $e->getMessage();
    }
}

// === EXPORT CSV ===
// === FETCH DATA ===
$students = $pdo->query("SELECT s.*, u.username FROM students s JOIN users u ON s.user_id = u.id ORDER BY s.name")->fetchAll();
$wardens = $pdo->query("SELECT w.*, u.username FROM wardens w JOIN users u ON w.user_id = u.id")->fetchAll();
$notices = $pdo->query("SELECT n.*, u.username, u.role FROM notices n JOIN users u ON n.posted_by = u.id ORDER BY n.posted_at DESC")->fetchAll();


// === SEARCH & REPORTS ===
$sql = "SELECT s.*, u.username,
           COALESCE(SUM(mb.amount), 0) as total_mess,
           s.semester_mess_balance,
           COALESCE(ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / NULLIF(COUNT(a.date), 0)) * 100, 2), 0) as att_percent,
           COUNT(a.date) as total_att_days
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN mess_bills mb ON s.id = mb.student_id
    LEFT JOIN attendance a ON s.id = a.student_id
    WHERE 1=1";
$params = [];
if (!empty($_GET['name'])) { $sql .= " AND s.name LIKE ?"; $params[] = "%" . $_GET['name'] . "%"; }
if (!empty($_GET['reg_no'])) { $sql .= " AND s.register_no LIKE ?"; $params[] = "%" . $_GET['reg_no'] . "%"; }
if (!empty($_GET['room'])) { $sql .= " AND s.room_no = ?"; $params[] = $_GET['room']; }
if (!empty($_GET['fee_status'])) { $sql .= " AND s.fee_status = ?"; $params[] = $_GET['fee_status']; }
if (!empty($_GET['block'])) { $sql .= " AND s.block = ?"; $params[] = $_GET['block']; }
$sql .= " GROUP BY s.id ORDER BY s.name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$search_results = $stmt->fetchAll();

if (isset($_POST['export_csv'])) {
    $filename = "hms_report_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Reg No', 'Block', 'Room', 'Fee', 'Mess Spent', 'Mess Balance', 'Att %', 'Att Days']);
    foreach ($search_results as $r) {
        fputcsv($output, [
            $r['name'],
            $r['register_no'],
            $r['block'],
            $r['room_no'] ?? '—',
            ucfirst(str_replace('_', ' ', $r['fee_status'])),
            number_format($r['total_mess'], 2),
            number_format($r['semester_mess_balance'], 2),
            $r['att_percent'] . '%',
            $r['total_att_days']
        ]);
    }
    exit;
}

// === GET CURRENT SECTION ===
$active_section = $_GET['section'] ?? 'dashboard';

// === HELPER: Build URL with section preserved ===
function url_with_section($section = null, $extra = []) {
    $params = $extra;
    if ($section !== null) $params['section'] = $section;
    elseif (isset($_GET['section'])) $params['section'] = $_GET['section'];
    return $params ? '?' . http_build_query($params) : '?';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Office Portal - HMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .form-select, .form-control { color: #000 !important; background-color: #fff !important; border: 1px solid #ccc !important; }
        .form-select option { color: #000; background-color: #fff; }
        code {
            color: #ffffff !important;
            background: transparent !important;   /* <-- removes the grey box */
            padding: 0 !important;
            font-size: 0.95em !important;
            font-family: 'Courier New', monospace;
        }
        .glass-input { 
            background: rgba(255,255,255,0.1) !important; 
            border: 1px solid rgba(255,255,255,0.2) !important; 
            color: #fff !important; 
            position: relative;
        }

        /* Same visible placeholder color for ALL inputs */
        .glass-input::placeholder {
            color: #cccccc !important;
            opacity: 1 !important;
        }

        /* DOB field – show "DOB" instead of dd-mm-yyyy */
        input[type="date"].glass-input {
            color: transparent;
        }
        input[type="date"].glass-input::before {
            content: "DOB";
            color: #cccccc;
            font-weight: 500;
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            z-index: 1;
        }
        input[type="date"].glass-input:focus::before,
        input[type="date"].glass-input:not(:placeholder-shown)::before {
            content: "";
        }
        input[type="date"].glass-input:focus,
        input[type="date"].glass-input:valid {
            color: #fff;
        }

        /* Dropdowns – selected text visible */
        select.glass-input {
            color: #fff !important;
        }
        select.glass-input option {
            color: #000 !important;
            background: #fff !important;
        }

        .table-dark { --bs-table-bg: rgba(255,255,255,0.05); }
        .badge { font-size: 0.8em; padding: 0.35em 0.65em; }

        .profile-row:hover {
            background: rgba(255,255,255,0.08) !important;
        }
        .profile-link {
            display: block;
            color: inherit !important;
            text-decoration: none !important;
        }

        /* Searchable select (unchanged) */
        .searchable-select { position: relative; }
        .searchable-select input {
            width: 100%; padding: 10px 12px; border: 1px solid rgba(255,255,255,0.3);
            border-radius: 8px; background: rgba(255,255,255,0.12); color: #fff;
            font-size: 0.95rem; transition: all 0.2s;
        }
        .searchable-select input:focus { outline: none; border-color: #0d6efd; background: rgba(255,255,255,0.18); }
        .searchable-select .options {
            position: absolute; top: 100%; left: 0; right: 0; max-height: 220px;
            overflow-y: auto; background: rgba(30,30,50,0.98); border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px; margin-top: 6px; z-index: 1000; display: none;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        .searchable-select .options.show { display: block; }
        .searchable-select .options div {
            padding: 10px 14px; cursor: pointer; color: #fff; font-size: 0.9rem;
            transition: background 0.2s;
        }
        .searchable-select .options div:hover { background: rgba(13, 110, 253, 0.3); }

        .avatar-circle {
            width: 90px;
            height: 90px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.2);
        }
    </style>

</head>
<body class="dashboard-bg">

<!-- SCROLLABLE SIDEBAR -->
<div class="sidebar">
    <div class="p-3 text-center">
        <div class="logo-circle mx-auto mb-3">
            <i class="bi bi-building-fill text-white fs-3"></i>
        </div>
        <h6 class="text-white mb-0">Office</h6>
        <small class="text-white-50"><?= htmlspecialchars($_SESSION['username']) ?></small>
    </div>
    <div class="nav-container">
        <ul class="nav flex-column">
            <li class="nav-item"><a href="?" class="nav-link <?= $active_section=='dashboard'?'active':'' ?>">Dashboard</a></li>
            <li class="nav-item"><a href="<?= url_with_section('addstudent') ?>" class="nav-link <?= $active_section=='addstudent'?'active':'' ?>">Add Student</a></li>
            <li class="nav-item"><a href="<?= url_with_section('addwarden') ?>" class="nav-link <?= $active_section=='addwarden'?'active':'' ?>">Add Warden</a></li>
            <li class="nav-item"><a href="<?= url_with_section('notices') ?>" class="nav-link <?= $active_section=='notices'?'active':'' ?>">Notices</a></li>
            <li class="nav-item"><a href="<?= url_with_section('messbill') ?>" class="nav-link <?= $active_section=='messbill'?'active':'' ?>">Mess Bill</a></li>
            <li class="nav-item"><a href="<?= url_with_section('feepaid') ?>" class="nav-link <?= $active_section=='feepaid'?'active':'' ?>">Mark Fee Paid</a></li>
            <li class="nav-item"><a href="<?= url_with_section('students') ?>" class="nav-link <?= $active_section=='students'?'active':'' ?>">All Students</a></li>
            <li class="nav-item"><a href="<?= url_with_section('wardens') ?>" class="nav-link <?= $active_section=='wardens'?'active':'' ?>">Manage Wardens</a></li>
            <li class="nav-item"><a href="<?= url_with_section('reports') ?>" class="nav-link <?= $active_section=='reports'?'active':'' ?>">Search & Reports</a></li>
            <li class="nav-item mt-4"><a href="#" class="nav-link text-warning" data-bs-toggle="modal" data-bs-target="#changePasswordModal">Change Password</a></li>
            <li class="nav-item"><a href="../logout.php" class="nav-link text-danger">Logout</a></li>
        </ul>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content p-4">
    <div class="container-fluid">

        <!-- ALERTS -->
        <?php if (isset($success)): ?><div class="alert glass-alert alert-success"><?= $success ?></div><?php endif; ?>
        <?php if (isset($error)): ?><div class="alert glass-alert alert-danger"><?= $error ?></div><?php endif; ?>
        <?php if (isset($notice_success)): ?><div class="alert glass-alert alert-success"><?= $notice_success ?></div><?php endif; ?>
        <?php if (isset($bill_success)): ?><div class="alert glass-alert alert-success"><?= $bill_success ?></div><?php endif; ?>
        <?php if (isset($warden_success)): ?><div class="alert glass-alert alert-success"><?= $warden_success ?></div><?php endif; ?>
        <?php if (isset($fee_success)): ?><div class="alert glass-alert alert-success"><?= $fee_success ?></div><?php endif; ?>
        
            <!-- DASHBOARD  -->
        <div id="dashboard" class="content-section <?= $active_section=='dashboard'?'active':'' ?>" style="display: <?= $active_section=='dashboard'?'block':'none' ?>;">
            <div class="welcome-card glass-card p-4 mb-4 text-center">
                <h2 class="text-white mb-2">Office Dashboard</h2>
                <p class="text-white-75 fs-5">Hostel Overview • <?= date('l, d F Y') ?></p>
            </div>

            <?php
            $total_students = count($students);
            $total_wardens = count($wardens);
            $unpaid_fees = $pdo->query("SELECT COUNT(*) FROM students WHERE fee_status = 'not_paid'")->fetchColumn();
            $total_notices = count($notices);

            // Block-wise student count+ 
            $block_a = $pdo->query("SELECT COUNT(*) FROM students WHERE block = 'A'")->fetchColumn();
            $block_b = $pdo->query("SELECT COUNT(*) FROM students WHERE block = 'B'")->fetchColumn();
            $block_c = $pdo->query("SELECT COUNT(*) FROM students WHERE block = 'C'")->fetchColumn();
            ?>

            <!-- MAIN STATISTICS -->
            <div class="row g-4 mb-4">
                <div class="col-md-3 col-6">
                    <div class="glass-card p-4 text-center h-100 border-start border-4 border-primary">
                        <div class="icon-circle bg-primary mx-auto mb-3">
                            <i class="bi bi-people-fill fs-2 text-white"></i>
                        </div>
                        <h3 class="text-white mb-0"><?= number_format($total_students) ?></h3>
                        <p class="text-white-75 small mb-0">Total Students</p>
                    </div>
                </div>

                <div class="col-md-3 col-6">
                    <div class="glass-card p-4 text-center h-100 border-start border-4 border-danger">
                        <div class="icon-circle bg-danger mx-auto mb-3">
                            <i class="bi bi-shield-fill fs-2 text-white"></i>
                        </div>
                        <h3 class="text-white mb-0"><?= number_format($total_wardens) ?></h3>
                        <p class="text-white-75 small mb-0">Total Wardens</p>
                    </div>
                </div>

                <div class="col-md-3 col-6">
                    <div class="glass-card p-4 text-center h-100 border-start border-4 border-warning">
                        <div class="icon-circle bg-warning mx-auto mb-3">
                            <i class="bi bi-x-circle-fill fs-2 text-dark"></i>
                        </div>
                        <h3 class="text-white mb-0"><?= number_format($unpaid_fees) ?></h3>
                        <p class="text-white-75 small mb-0">Fee Not Paid</p>
                    </div>
                </div>

                <div class="col-md-3 col-6">
                    <div class="glass-card p-4 text-center h-100 border-start border-4 border-info">
                        <div class="icon-circle bg-info mx-auto mb-3">
                            <i class="bi bi-bell-fill fs-2 text-white"></i>
                        </div>
                        <h3 class="text-white mb-0"><?= number_format($total_notices) ?></h3>
                        <p class="text-white-75 small mb-0">Total Notices</p>
                    </div>
                </div>
            </div>

            <!-- BLOCK-WISE STUDENT COUNT -->
            <div class="glass-card p-4 mb-4">
                <h5 class="text-white mb-3 text-center">Students by Block</h5>
                <div class="row g-4 text-center">
                    <div class="col-md-4 col-12">
                        <div class="p-4 rounded" style="background: rgba(59, 130, 246, 0.15); border-left: 5px solid #3b82f6;">
                            <h2 class="text-white mb-1 fw-bold"><?= number_format($block_a) ?></h2>
                            <p class="text-white mb-0">Block A</p>
                        </div>
                    </div>
                    <div class="col-md-4 col-12">
                        <div class="p-4 rounded" style="background: rgba(59, 130, 246, 0.15); border-left: 5px solid #3b82f6;">
                            <h2 class="text-white mb-1 fw-bold"><?= number_format($block_b) ?></h2>
                            <p class="text-white mb-0">Block B</p>
                        </div>
                    </div>
                    <div class="col-md-4 col-12">
                        <div class="p-4 rounded" style="background: rgba(59, 130, 246, 0.15); border-left: 5px solid #3b82f6;">
                            <h2 class="text-white mb-1 fw-bold"><?= number_format($block_c) ?></h2>
                            <p class="text-white mb-0">Block C</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RECENT NOTICES (3 ONLY) -->
            <?php if($notices): ?>
            <div class="glass-card p-4">
                <h5 class="text-white mb-3"><i class="bi bi-megaphone-fill text-info me-2"></i>Recent Notices</h5>
                <div class="row g-3">
                    <?php foreach(array_slice($notices, 0, 2) as $n): ?>
                        <div class="col-12">
                            <div class="p-3 rounded d-flex align-items-start" style="background: rgba(255,255,255,0.06); border-left: 4px solid #0d6efd;">
                                <div class="me-3 mt-1">
                                    <i class="bi bi-bell-fill text-info fs-5"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="text-white mb-1"><?= htmlspecialchars($n['title']) ?></h6>
                                    <p class="text-white-75 small mb-2"><?= substr(htmlspecialchars($n['content']), 0, 110) ?>...</p>
                                    <small class="text-white-50">
                                        by <strong><?= htmlspecialchars($n['username']) ?></strong> • <?= date('d M Y', strtotime($n['posted_at'])) ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if($total_notices > 3): ?>
                    <div class="text-center mt-3">
                        <a href="<?= url_with_section('notices') ?>" class="btn btn-outline-info btn-sm">View All Notices →</a>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ADD STUDENT -->
        <div id="addstudent" class="content-section <?= $active_section=='addstudent'?'active':'' ?>" style="display: <?= $active_section=='addstudent'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <h4 class="text-white mb-4">Add New Student</h4>
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control glass-input"  required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Register No</label>
                            <input type="text" name="register_no" class="form-control glass-input"  required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Block</label>
                            <select name="block" class="form-select glass-input" required>
                                <option value="" disabled selected></option>
                                <option value="A">Block A</option>
                                <option value="B">Block B</option>
                                <option value="C">Block C</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" 
                                name="phone" 
                                class="form-control glass-input" 
                                placeholder=" " 
                                maxlength="10" 
                                pattern="[0-9]{10}" 
                                title="Exactly 10 digits required (e.g., 9876543210)" 
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,10)" 
                                required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control glass-input" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" class="form-control glass-input" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Year of Study</label>
                            <select name="year" class="form-select glass-input" required>
                                <option value="" disabled selected></option>
                                <option>1st Year</option>
                                <option>2nd Year</option>
                                <option>3rd Year</option>
                                <option>4th Year</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Branch</label>
                            <select name="branch" class="form-select glass-input" required>
                                <option value="" disabled selected></option>
                                <option>CSE</option><option>ECE</option><option>EEE</option><option>MECH</option>
                                <option>CIVIL</option><option>IT</option><option>AIDS</option><option>Others</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Home Address</label>
                            <textarea name="address" class="form-control glass-input" rows="3"  required></textarea>
                        </div>
                    </div>
                    <button name="add_student" class="btn btn-primary w-100 mt-4 py-2 fs-5">Add Student</button>
                </form>
            </div>
        </div>
        <!-- ADD WARDEN -->
        <div id="addwarden" class="content-section <?= $active_section=='addwarden'?'active':'' ?>" style="display: <?= $active_section=='addwarden'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <h4 class="text-white mb-4">Add New Warden</h4>
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-white">Full Name</label>
                            <input type="text" name="warden_name" class="form-control glass-input"  required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white">Username</label>
                            <input type="text" name="warden_username" class="form-control glass-input"  required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white">Block</label>
                            <select name="block" class="form-select glass-input" required>
                                <option value="" disabled selected></option>
                                <option value="A">Block A</option>
                                <option value="B">Block B</option>
                                <option value="C">Block C</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white">Phone Number</label>
                            <input type="tel" 
                                name="phone" 
                                class="form-control glass-input" 
                                placeholder=" " 
                                maxlength="10" 
                                pattern="[0-9]{10}" 
                                title="Exactly 10 digits required (e.g., 9876543210)" 
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,10)" 
                                required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white">Email Address</label>
                            <input type="email" name="email" class="form-control glass-input" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white">Date of Birth</label>
                            <input type="date" name="dob" class="form-control glass-input" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-white">Home Address</label>
                            <textarea name="address" class="form-control glass-input" rows="3" required></textarea>
                        </div>
                    </div>
                    <button name="add_warden" class="btn btn-primary w-100 mt-4 py-2 fs-5">Add Warden</button>
                </form>
            </div>
        </div>

        <!-- NOTICES -->
        <div id="notices" class="content-section <?= $active_section=='notices'?'active':'' ?>" style="display: <?= $active_section=='notices'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <h4 class="text-white mb-3">Upload Notice</h4>
                <form method="POST" class="mb-4">
                    <input type="text" name="title" class="form-control glass-input mb-2" placeholder="Title" required>
                    <textarea name="content" class="form-control glass-input" rows="3" placeholder="Content..." required></textarea>
                    <button name="upload_notice" class="btn btn-primary w-100 mt-4 py-2 fs-5">Upload Notice</button>
                </form>
                <h5 class="text-white mb-3">All Notices</h5>
                <div style="max-height: 500px; overflow-y: auto;">
                    <?php foreach ($notices as $n): ?>
                        <div class="border-bottom border-white border-opacity-10 pb-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="text-white"><?= htmlspecialchars($n['title']) ?></h6>
                                    <p class="text-white-75 mb-1"><?= nl2br(htmlspecialchars($n['content'])) ?></p>
                                </div>
                                <small class="text-white-50 text-end">
                                    by <strong><?= htmlspecialchars($n['username']) ?></strong><br>
                                    <span class="badge bg-<?= $n['role']=='warden'?'warning':'info' ?>"><?= ucfirst($n['role']) ?></span><br>
                                    <?= date('d M Y', strtotime($n['posted_at'])) ?>
                                </small>
                            </div>
                            <?php if ($n['posted_by'] == $_SESSION['user_id']): ?>
                                <form method="POST" class="d-inline mt-1">
                                    <input type="hidden" name="notice_id" value="<?= $n['id'] ?>">
                                    <button name="delete_notice" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- MESS BILL + SEMESTER RESET (Compact Button) -->
        <div id="messbill" class="content-section <?= $active_section=='messbill'?'active':'' ?>" style="display: <?= $active_section=='messbill'?'block':'none' ?>;">
            
            <!-- Main Upload Section -->
            <div class="glass-card p-4">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <h4 class="text-white mb-0">Upload Mess Bill</h4>
                    
                    <!-- Small Reset Button at Top-Right -->
                    <form method="POST" class="d-inline" onsubmit="return confirm('ARE YOU SURE?\n\nThis will reset mess balance for ALL <?= count($students) ?> students to ₹25,000.00\n\nCannot be undone!');">
                        <button name="reset_mess_bill" class="btn btn-outline-primary btn-sm" title="Reset all students to ₹25,000 (New Semester)">
                            <i class="bi bi-arrow-repeat me-1"></i> Reset All Balances
                        </button>
                    </form>
                </div>

                <!-- Upload Form -->
                <form method="POST">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <div class="searchable-select">
                                <input type="text" id="mess_search" placeholder="Search student..." autocomplete="off">
                                <input type="hidden" name="student_id" id="mess_student_id">
                                <div class="options" id="mess_options">
                                    <?php foreach ($students as $s): ?>
                                        <div data-id="<?= $s['id'] ?>" data-name="<?= htmlspecialchars($s['name']) ?> (<?= $s['register_no'] ?>)">
                                            <?= htmlspecialchars($s['name']) ?> (<?= $s['register_no'] ?>)
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4"><input type="month" name="month" class="form-control glass-input" required></div>
                        <div class="col-md-3"><input type="number" step="0.01" name="amount" class="form-control glass-input" placeholder="0000.00" required></div>
                    </div>
                    <button name="upload_bill" class="btn btn-primary w-100 mt-4 py-2 fs-5">Upload Bill</button>
                </form>
            </div>
        </div>

        <!-- FEE PAID -->
        <div id="feepaid" class="content-section <?= $active_section=='feepaid'?'active':'' ?>" style="display: <?= $active_section=='feepaid'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <h4 class="text-white mb-3">Mark Fee as Paid</h4>
                <?php $unpaid = $pdo->query("SELECT s.id, s.name, s.register_no FROM students s WHERE s.fee_status = 'not_paid'")->fetchAll(); ?>
                <?php if ($unpaid): ?>
                    <form method="POST">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-12">
                                <div class="searchable-select">
                                    <input type="text" id="fee_search" placeholder="Click to see unpaid..." autocomplete="off">
                                    <input type="hidden" name="fee_student_id" id="fee_student_id">
                                    <div class="options" id="fee_options">
                                        <?php foreach ($unpaid as $u): ?>
                                            <div data-id="<?= $u['id'] ?>" data-name="<?= htmlspecialchars($u['name']) ?> (<?= $u['register_no'] ?>)">
                                                <?= htmlspecialchars($u['name']) ?> (<?= $u['register_no'] ?>)
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div><button name="mark_fee_paid" class="btn btn-primary w-100 mt-4 py-2 fs-5">Mark Paid</button></div>
                    </form>
                <?php else: ?>
                    <div class="alert glass-alert alert-info">All fees paid!</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ALL STUDENTS - NOW IDENTICAL TO MANAGE WARDENS STYLE -->
        <div id="students" class="content-section <?= $active_section=='students'?'active':'' ?>" style="display: <?= $active_section=='students'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <h4 class="text-white mb-3">All Students</h4>
                <div class="mb-3">
                    <input type="text" id="studentSearch" class="form-control glass-input" placeholder="Search students by name, reg no, room..." autocomplete="off">
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover table-sm align-middle">
                        <thead class="table-secondary">
                            <tr>
                                <th>Name</th>
                                <th>Reg No.</th>
                                <th>Room No.</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                                <tr class="profile-row" style="cursor: pointer;">
                                    <!-- Clickable Name (whole row) -->
                                    <td class="profile-row">
                                        <button class="btn btn-link text-white p-0 text-start fw-medium profile-link w-100 h-100 text-start"
                                                style="text-decoration: none !important;"
                                                data-type="student"
                                                data-name="<?= htmlspecialchars($s['name']) ?>"
                                                data-username="<?= $s['username'] ?>"
                                                data-regno="<?= $s['register_no'] ?>"
                                                data-block="<?= $s['block'] ?>"
                                                data-room="<?= $s['room_no'] ?? '—' ?>"
                                                data-phone="<?= htmlspecialchars($s['phone'] ?? '') ?>"
                                                data-email="<?= htmlspecialchars($s['email'] ?? '') ?>"
                                                data-dob="<?= $s['dob'] ?>"
                                                data-address="<?= htmlspecialchars($s['address'] ?? '') ?>"
                                                data-year="<?= $s['year'] ?>"
                                                data-branch="<?= $s['branch'] ?>">
                                            <?= htmlspecialchars($s['name']) ?>
                                        </button>
                                    </td>
                                    <td><code><?= $s['register_no'] ?></code></td>
                                    <td><?= $s['room_no'] ?? '—' ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary me-1" data-bs-toggle="modal" data-bs-target="#editStudent<?= $s['id'] ?>">Edit</button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this student and ALL records?');">
                                            <input type="hidden" name="delete_student_id" value="<?= $s['id'] ?>">
                                            <button name="delete_student" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MANAGE WARDENS -->
        <div id="wardens" class="content-section <?= $active_section=='wardens'?'active':'' ?>" style="display: <?= $active_section=='wardens'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <h4 class="text-white mb-3">Manage Wardens</h4>
                <div class="mb-3">
                    <input type="text" id="wardenSearch" class="form-control glass-input" placeholder="Search wardens by name or username..." autocomplete="off">
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover table-sm align-middle">
                        <thead class="table-secondary">
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Block</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wardens as $w): ?>
                                <tr>
                                <td class="profile-row" style="cursor: pointer;">
                                    <button class="btn btn-link text-white p-0 text-start fw-medium profile-link"
                                            style="text-decoration: none !important;"
                                            data-type="warden"
                                            data-name="<?= htmlspecialchars($w['name']) ?>"
                                            data-username="<?= $w['username'] ?>"
                                            data-block="<?= $w['block'] ?>"
                                            data-phone="<?= htmlspecialchars($w['phone'] ?? '') ?>"
                                            data-email="<?= htmlspecialchars($w['email'] ?? '') ?>"
                                            data-dob="<?= $w['dob'] ?>"
                                            data-address="<?= htmlspecialchars($w['address'] ?? '') ?>">
                                        <?= htmlspecialchars($w['name']) ?>
                                    </button>
                                </td>
                                    <td><code><?= $w['username'] ?></code></td>
                                    <td><?= $w['block'] ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary me-1" data-bs-toggle="modal" data-bs-target="#edit<?= $w['user_id'] ?>">Edit</button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete warden?');">
                                            <input type="hidden" name="delete_warden_id" value="<?= $w['user_id'] ?>">
                                            <button name="delete_warden" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SEARCH & REPORTS -->
        <div id="reports" class="content-section <?= $active_section=='reports'?'active':'' ?>" style="display: <?= $active_section=='reports'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <h4 class="text-white mb-3">Search Students & Generate Reports</h4>
                <form method="GET" class="mb-3">
                    <input type="hidden" name="section" value="reports">
                    <div class="row g-2">
                        <div class="col-md-3"><input type="text" name="name" class="form-control glass-input" placeholder="Name" value="<?= htmlspecialchars($_GET['name'] ?? '') ?>"></div>
                        <div class="col-md-3"><input type="text" name="reg_no" class="form-control glass-input" placeholder="Reg No" value="<?= htmlspecialchars($_GET['reg_no'] ?? '') ?>"></div>
                        <div class="col-md-2"><input type="text" name="room" class="form-control glass-input" placeholder="Room" value="<?= htmlspecialchars($_GET['room'] ?? '') ?>"></div>
                        <div class="col-md-2">
                            <select name="fee_status" class="form-select glass-input">
                                <option value="">Fee</option>
                                <option value="paid" <?= ($_GET['fee_status']??'')=='paid'?'selected':'' ?>>Paid</option>
                                <option value="not_paid" <?= ($_GET['fee_status']??'')=='not_paid'?'selected':'' ?>>Not Paid</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <select name="block" class="form-select glass-input">
                                <option value="">Block</option>
                                <option value="A" <?= ($_GET['block']??'')=='A'?'selected':'' ?>>A</option>
                                <option value="B" <?= ($_GET['block']??'')=='B'?'selected':'' ?>>B</option>
                                <option value="C" <?= ($_GET['block']??'')=='C'?'selected':'' ?>>C</option>
                            </select>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-info w-100">Go</button>
                        </div>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-dark table-sm">
                        <thead><tr><th>Name</th><th>Reg No</th><th>Block</th><th>Room</th><th>Fee</th><th>Mess Spent</th><th>Balance</th><th>Att %</th></tr></thead>
                        <tbody>
                            <?php foreach ($search_results as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['name']) ?></td>
                                    <td><?= $r['register_no'] ?></td>
                                    <td><?= $r['block'] ?></td>
                                    <td><?= $r['room_no'] ?? '—' ?></td>
                                    <td><span class="badge bg-<?= $r['fee_status']=='paid'?'success':'danger' ?>"><?= ucfirst($r['fee_status']) ?></span></td>
                                    <td>₹<?= number_format($r['total_mess'],2) ?></td>
                                    <td>₹<?= number_format($r['semester_mess_balance'],2) ?></td>
                                    <td class="text-white-75"><?= $r['att_percent'] ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($search_results): ?>
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="section" value="reports">
                        <button name="export_csv" class="btn btn-success">Export CSV</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- ALL EDIT WARDEN MODALS - FULL VERSION WITH ALL DETAILS -->
<?php foreach ($wardens as $w): ?>
<div class="modal fade" id="edit<?= $w['user_id'] ?>" tabindex="-1" aria-labelledby="editWardenLabel<?= $w['user_id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST">
            <div class="modal-content glass-card border-0 shadow-lg">
                <div class="modal-header border-0 pb-2">
                    <h4 class="modal-title text-white fw-bold" id="editWardenLabel<?= $w['user_id'] ?>">
                        Edit Warden
                    </h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <input type="hidden" name="edit_warden_id" value="<?= $w['user_id'] ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-white fw-medium">Full Name</label>
                            <input type="text" name="edit_name" class="form-control form-control-lg glass-input" 
                                   value="<?= htmlspecialchars($w['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white fw-medium">Username</label>
                            <input type="text" name="edit_username" class="form-control form-control-lg glass-input" 
                                   value="<?= $w['username'] ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-white fw-medium">Hostel Block</label>
                            <select name="edit_block" class="form-select form-select-lg glass-input" required>
                                <option value="A" <?= $w['block']=='A'?'selected':'' ?>>Block A</option>
                                <option value="B" <?= $w['block']=='B'?'selected':'' ?>>Block B</option>
                                <option value="C" <?= $w['block']=='C'?'selected':'' ?>>Block C</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white fw-medium">Phone Number</label>
                            <input type="tel" 
                                name="edit_phone" 
                                class="form-control form-control-lg glass-input" 
                                value="<?= htmlspecialchars($w['phone'] ?? '') ?>" 
                                placeholder=" " 
                                maxlength="10" 
                                pattern="[0-9]{10}" 
                                title="Exactly 10 digits required (e.g., 9876543210)" 
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,10)" 
                                required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white fw-medium">Email Address</label>
                            <input type="email" name="edit_email" class="form-control form-control-lg glass-input" 
                                   value="<?= htmlspecialchars($w['email'] ?? '') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-white fw-medium">Date of Birth</label>
                            <input type="date" name="edit_dob" class="form-control form-control-lg glass-input" 
                                   value="<?= $w['dob'] ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-white fw-medium">Home Address</label>
                            <textarea name="edit_address" class="form-control glass-input" rows="3" required><?= htmlspecialchars($w['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-2">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_warden" class="btn btn-primary btn-lg px-5">
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<!-- ALL EDIT STUDENT MODALS -->
<?php foreach ($students as $s): ?>
<div class="modal fade" id="editStudent<?= $s['id'] ?>" tabindex="-1" aria-labelledby="editStudentLabel<?= $s['id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST">
            <div class="modal-content glass-card border-0 shadow-lg">
                <div class="modal-header border-0 pb-2">
                    <h4 class="modal-title text-white fw-bold" id="editStudentLabel<?= $s['id'] ?>">
                        <i class="bi bi-person-gear me-2"></i>Edit Student
                    </h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <input type="hidden" name="edit_student_id" value="<?= $s['id'] ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-white fw-medium">Full Name</label>
                            <input type="text" name="edit_name" class="form-control form-control-lg glass-input" 
                                   value="<?= htmlspecialchars($s['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white fw-medium">Register No</label>
                            <input type="text" class="form-control form-control-lg bg-white text-dark" 
                                   value="<?= $s['register_no'] ?>" disabled>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-white fw-medium">Block</label>
                            <select name="edit_block" class="form-select form-select-lg glass-input" required>
                                <option value="A" <?= $s['block']=='A'?'selected':'' ?>>Block A</option>
                                <option value="B" <?= $s['block']=='B'?'selected':'' ?>>Block B</option>
                                <option value="C" <?= $s['block']=='C'?'selected':'' ?>>Block C</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-white fw-medium">Phone Number</label>
                            <input type="tel" 
                                name="edit_phone" 
                                class="form-control form-control-lg glass-input" 
                                value="<?= htmlspecialchars($s['phone'] ?? '') ?>" 
                                placeholder=" " 
                                maxlength="10" 
                                pattern="[0-9]{10}" 
                                title="Exactly 10 digits required (e.g., 9876543210)" 
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,10)" 
                                required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-white fw-medium">Email Address</label>
                            <input type="email" name="edit_email" class="form-control form-control-lg glass-input" 
                                   value="<?= htmlspecialchars($s['email'] ?? '') ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-white fw-medium">Date of Birth</label>
                            <input type="date" name="edit_dob" class="form-control form-control-lg glass-input" 
                                   value="<?= $s['dob'] ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-white fw-medium">Year of Study</label>
                            <select name="edit_year" class="form-select form-select-lg glass-input" required>
                                <option value="1st Year" <?= $s['year']=='1st Year'?'selected':'' ?>>1st Year</option>
                                <option value="2nd Year" <?= $s['year']=='2nd Year'?'selected':'' ?>>2nd Year</option>
                                <option value="3rd Year" <?= $s['year']=='3rd Year'?'selected':'' ?>>3rd Year</option>
                                <option value="4th Year" <?= $s['year']=='4th Year'?'selected':'' ?>>4th Year</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-white fw-medium">Branch</label>
                            <select name="edit_branch" class="form-select form-select-lg glass-input" required>
                                <option value="CSE" <?= $s['branch']=='CSE'?'selected':'' ?>>CSE</option>
                                <option value="ECE" <?= $s['branch']=='ECE'?'selected':'' ?>>ECE</option>
                                <option value="EEE" <?= $s['branch']=='EEE'?'selected':'' ?>>EEE</option>
                                <option value="MECH" <?= $s['branch']=='MECH'?'selected':'' ?>>MECH</option>
                                <option value="CIVIL" <?= $s['branch']=='CIVIL'?'selected':'' ?>>CIVIL</option>
                                <option value="IT" <?= $s['branch']=='IT'?'selected':'' ?>>IT</option>
                                <option value="AIDS" <?= $s['branch']=='AIDS'?'selected':'' ?>>AIDS</option>
                                <option value="Others" <?= $s['branch']=='Others'?'selected':'' ?>>Others</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-white fw-medium">Home Address</label>
                            <textarea name="edit_address" class="form-control glass-input" rows="3" required><?= htmlspecialchars($s['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-2">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_student" class="btn btn-primary btn-lg px-5">
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>


<!-- CHANGE PASSWORD MODAL -->
<div class="modal fade" id="changePasswordModal">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content glass-card">
            <div class="modal-header">
                <h5 class="modal-title text-white">Change Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= url_with_section() ?>">
                <div class="modal-body">
                    <?php if (isset($error)): ?><div class="alert alert-danger small"><?= $error ?></div><?php endif; ?>
                    <?php if (isset($success)): ?><div class="alert alert-success small"><?= $success ?></div><?php endif; ?>
                    <input type="password" name="current_password" class="form-control glass-input mb-2" placeholder="Current Password" required>
                    <input type="password" name="new_password" class="form-control glass-input mb-2" placeholder="New Password" required minlength="6">
                    <input type="password" name="confirm_password" class="form-control glass-input" placeholder="Confirm Password" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="change_password" class="btn btn-primary w-100">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- STUDENT & WARDEN PROFILE MODAL -->
<div class="modal fade" id="profileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content glass-card text-white">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="profileTitle">Profile Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="profileBody">
                <!-- Content loaded via JS -->
            </div>
        </div>
    </div>
</div>

<script>
// Profile Modal – Same Beautiful Blue Person Icon for BOTH Student & Warden
document.addEventListener('click', function(e) {
    if (e.target.matches('.profile-link') || e.target.closest('.profile-link')) {
        e.preventDefault();
        const btn = e.target.closest('.profile-link');
        const d = btn.dataset;

        const isStudent = d.type === 'student';
        let html = `
            <div class="text-center mb-4">
                <div class="avatar-circle mx-auto mb-3" style="background: #0d6efd !important;">
                    <i class="bi bi-person-fill fs-1 text-white"></i>
                </div>
                <h4 class="text-white mb-1">${d.name}</h4>
                <p class="text-white-50 mb-0">${isStudent ? 'Student' : 'Warden'}</p>
            </div>
            <div class="row g-4 text-start">
                ${isStudent ? `<div class="col-md-6"><strong>Register No:</strong> ${d.regno}</div>` : ''}
                ${isStudent ? `<div class="col-md-6"><strong>Room:</strong> ${d.room}</div>` : ''}
                <div class="col-md-6"><strong>${isStudent ? 'Username' : 'Login ID'}:</strong> ${d.username}</div>
                <div class="col-md-6"><strong>Block:</strong> Block ${d.block}</div>
                <div class="col-md-6"><strong>Phone:</strong> ${d.phone || '—'}</div>
                <div class="col-md-6"><strong>Email:</strong> ${d.email || '—'}</div>
                <div class="col-md-6"><strong>Date of Birth:</strong> ${d.dob ? new Date(d.dob).toLocaleDateString('en-IN') : '—'}</div>
                ${isStudent ? `<div class="col-md-6"><strong>Year:</strong> ${d.year}</div>` : ''}
                ${isStudent ? `<div class="col-md-6"><strong>Branch:</strong> ${d.branch}</div>` : ''}
                <div class="col-12 mt-3">
                    <strong>Home Address:</strong><br>
                    <small class="text-white-75">${d.address ? d.address.replace(/\n/g, '<br>') : '—'}</small>
                </div>
            </div>
        `;

        document.getElementById('profileTitle').textContent = d.name + ' – Profile';
        document.getElementById('profileBody').innerHTML = html;
        new bootstrap.Modal(document.getElementById('profileModal')).show();
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Searchable dropdown scripts (unchanged)
const messInput = document.getElementById('mess_search');
const messOptions = document.getElementById('mess_options');
messInput.addEventListener('click', () => { messOptions.classList.add('show'); messOptions.scrollTop = 0; });
messInput.addEventListener('input', () => {
    const filter = messInput.value.toLowerCase();
    const items = messOptions.querySelectorAll('div');
    let visible = false;
    items.forEach(item => {
        if (item.textContent.toLowerCase().includes(filter)) { item.style.display = ''; visible = true; }
        else item.style.display = 'none';
    });
    messOptions.classList.toggle('show', visible || filter === '');
});
messOptions.querySelectorAll('div').forEach(opt => {
    opt.addEventListener('click', () => {
        messInput.value = opt.getAttribute('data-name');
        document.getElementById('mess_student_id').value = opt.getAttribute('data-id');
        messOptions.classList.remove('show');
    });
});
document.addEventListener('click', (e) => {
    if (!messInput.contains(e.target) && !messOptions.contains(e.target)) messOptions.classList.remove('show');
});

// Fee dropdown
const feeInput = document.getElementById('fee_search');
const feeOptions = document.getElementById('fee_options');
feeInput.addEventListener('click', () => { feeOptions.classList.add('show'); feeOptions.scrollTop = 0; });
feeInput.addEventListener('input', () => {
    const filter = feeInput.value.toLowerCase();
    const items = feeOptions.querySelectorAll('div');
    let visible = false;
    items.forEach(item => {
        if (item.textContent.toLowerCase().includes(filter)) { item.style.display = ''; visible = true; }
        else item.style.display = 'none';
    });
    feeOptions.classList.toggle('show', visible || filter === '');
});
feeOptions.querySelectorAll('div').forEach(opt => {
    opt.addEventListener('click', () => {
        feeInput.value = opt.getAttribute('data-name');
        document.getElementById('fee_student_id').value = opt.getAttribute('data-id');
        feeOptions.classList.remove('show');
    });
});
document.addEventListener('click', (e) => {
    if (!feeInput.contains(e.target) && !feeOptions.contains(e.target)) feeOptions.classList.remove('show');
});
</script>
<script>
// Live Search for Students
document.getElementById('studentSearch')?.addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#students tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});

// Live Search for Wardens
document.getElementById('wardenSearch')?.addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#wardens tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>
</body>
</html>