<?php
session_start();

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
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

// Fetch Student
$stmt = $pdo->prepare("
    SELECT s.*, u.username 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: ../logout.php");
    exit;
}

// === DASHBOARD DATA ===

// Recent Notices (block-specific + general)
$notices_stmt = $pdo->prepare("
    SELECT n.*, u.username, w.name as warden_name 
    FROM notices n 
    JOIN users u ON n.posted_by = u.id 
    LEFT JOIN wardens w ON u.id = w.user_id 
    WHERE n.block = ? OR n.block IS NULL 
    ORDER BY n.posted_at DESC 
    LIMIT 5
");
$notices_stmt->execute([$student['block']]);
$recent_notices = $notices_stmt->fetchAll();

// Requests Summary
$pending_requests = $pdo->prepare("
    SELECT 'leave' as type, COUNT(*) as count FROM leave_requests WHERE student_id = ? AND status = 'pending'
    UNION ALL
    SELECT 'visitor', COUNT(*) FROM visitor_requests WHERE student_id = ? AND status = 'pending'
    UNION ALL
    SELECT 'cleaning', COUNT(*) FROM cleaning_requests WHERE student_id = ? AND status = 'pending'
");
$pending_requests->execute([$student['id'], $student['id'], $student['id']]);
$pending_count = array_sum(array_column($pending_requests->fetchAll(), 'count'));

$approved_requests = $pdo->prepare("
    SELECT COUNT(*) FROM (
        SELECT 1 FROM leave_requests WHERE student_id = ? AND status = 'approved'
        UNION ALL SELECT 1 FROM visitor_requests WHERE student_id = ? AND status = 'approved'
        UNION ALL SELECT 1 FROM cleaning_requests WHERE student_id = ? AND status = 'approved'
    ) AS t
");
$approved_requests->execute([$student['id'], $student['id'], $student['id']]);
$approved_count = $approved_requests->fetchColumn();

// Complaints
$open_complaints = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE student_id = ? AND status = 'open'");
$open_complaints->execute([$student['id']]);
$open_complaints_count = $open_complaints->fetchColumn();

$resolved_complaints = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE student_id = ? AND status = 'resolved'");
$resolved_complaints->execute([$student['id']]);
$resolved_count = $resolved_complaints->fetchColumn();

// Latest Mess Bill
$latest_bill = $pdo->prepare("
    SELECT mb.*, u.username 
    FROM mess_bills mb 
    JOIN users u ON mb.uploaded_by = u.id 
    WHERE mb.student_id = ? 
    ORDER BY mb.month DESC 
    LIMIT 1
");
$latest_bill->execute([$student['id']]);
$latest_bill = $latest_bill->fetch();

// === APPLY LEAVE ===
if (isset($_POST['apply_leave'])) {
    $from = $_POST['from_date'];
    $to = $_POST['to_date'];
    $reason = $_POST['reason'];

    if (strtotime($to) < strtotime($from)) {
        $error = "To date cannot be before From date!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO leave_requests (student_id, from_date, to_date, reason) VALUES (?, ?, ?, ?)");
        $stmt->execute([$student['id'], $from, $to, $reason]);
        $leave_success = "Leave applied from $from to $to!";
    }
}

// === VISITOR REQUEST ===
if (isset($_POST['visitor_req'])) {
    $stmt = $pdo->prepare("INSERT INTO visitor_requests (student_id, visitor_name, relation, visit_date, visit_time) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$student['id'], $_POST['visitor_name'], $_POST['relation'], $_POST['visit_date'], $_POST['visit_time']]);
    $success = "Visitor request sent!";
}

// === CLEANING REQUEST ===
if (isset($_POST['cleaning_req'])) {
    if (empty($student['room_no'])) {
        $error = "You must be assigned a room before requesting cleaning!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO cleaning_requests (student_id, room_no, issue, preferred_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$student['id'], $student['room_no'], $_POST['issue'], $_POST['preferred_date']]);
        $success = "Cleaning request sent!";
    }
}

// === LODGE COMPLAINT ===
if (isset($_POST['complaint'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    if (empty($subject) || empty($message)) {
        $error = "Subject and message are required!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO complaints (student_id, subject, message) VALUES (?, ?, ?)");
        $stmt->execute([$student['id'], $subject, $message]);
        $success = "Complaint lodged successfully!";
    }
}

// === GET CURRENT SECTION FROM URL ===
$active_section = $_GET['section'] ?? 'dashboard';

// === FILTERS ===
$notice_filter = $_GET['notice_search'] ?? '';
$att_month = $_GET['att_month'] ?? '';
$bill_month = $_GET['bill_month'] ?? '';

// === ADD target_audience COLUMN IF NOT EXISTS ===
try {
    $pdo->query("SELECT target_audience FROM notices LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE notices ADD COLUMN target_audience VARCHAR(20) DEFAULT 'both'");
}

// === NOTICES ===
$sql = "SELECT n.*, u.username FROM notices n JOIN users u ON n.posted_by = u.id WHERE (n.target_audience IN ('both', 'students') OR n.target_audience IS NULL)";
$params = [];
if ($notice_filter) {
    $sql .= " AND (n.title LIKE ? OR n.content LIKE ?)";
    $like = "%$notice_filter%";
    $params = [$like, $like];
}
$sql .= " ORDER BY n.posted_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notices = $stmt->fetchAll();

// === MESS BILLS ===
$sql = "SELECT mb.*, u.username FROM mess_bills mb JOIN users u ON mb.uploaded_by = u.id WHERE mb.student_id = ?";
$params = [$student['id']];
if ($bill_month) {
    $sql .= " AND mb.month = ?";
    $params[] = $bill_month;
}
$sql .= " ORDER BY mb.month DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bills = $stmt->fetchAll();

// === ATTENDANCE ===
$attendance = [];
$today = date('Y-m-d');
if ($att_month) {
    $stmt = $pdo->prepare("
        SELECT date, status 
        FROM attendance 
        WHERE student_id = ? AND DATE_FORMAT(date, '%Y-%m') = ? AND date <= ?
        ORDER BY date
    ");
    $stmt->execute([$student['id'], $att_month, $today]);
    $attendance = $stmt->fetchAll();
} else {
    $current_month = date('Y-m');
    $stmt = $pdo->prepare("
        SELECT date, status 
        FROM attendance 
        WHERE student_id = ? AND DATE_FORMAT(date, '%Y-%m') = ? AND date <= ?
        ORDER BY date
    ");
    $stmt->execute([$student['id'], $current_month, $today]);
    $attendance = $stmt->fetchAll();
}

$present_count = count(array_filter($attendance, fn($a) => $a['status'] == 'present'));
$absent_count = count(array_filter($attendance, fn($a) => $a['status'] == 'absent'));
$total_days = count($attendance);
$att_percent = $total_days > 0 ? round(($present_count / $total_days) * 100, 1) : 0;

// === LEAVE HISTORY ===
$leaves = $pdo->prepare("SELECT lr.*, u.username as action_by FROM leave_requests lr LEFT JOIN users u ON lr.action_by = u.id WHERE lr.student_id = ? ORDER BY lr.applied_at DESC");
$leaves->execute([$student['id']]);
$leaves = $leaves->fetchAll();

// === HELPER: Build URL with section preserved ===
function url_with_section($section = null, $extra = []) {
    $params = $extra;
    if ($section !== null) {
        $params['section'] = $section;
    } elseif (isset($_GET['section'])) {
        $params['section'] = $_GET['section'];
    }
    return $params ? '?' . http_build_query($params) : '?';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Portal - HMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dashboard-bg">

<!-- SCROLLABLE SIDEBAR -->
<div class="sidebar">
    <div class="p-3 text-center">
        <div class="logo-circle mx-auto mb-3">
            <i class="bi bi-person-fill text-white fs-3"></i>
        </div>
        <h6 class="text-white mb-0"><?= htmlspecialchars($student['name']) ?></h6>
        <small class="text-white-50">Student</small>
    </div>
    <div class="nav-container">
        <ul class="nav flex-column">
            <!-- DASHBOARD: Always clear section -->
            <li class="nav-item">
                <a href="?" class="nav-link <?= $active_section=='dashboard'?'active':'' ?>" data-section="dashboard">
                    Dashboard
                </a>
            </li>

            <!-- OTHER LINKS: Preserve current section or set new -->
            <li class="nav-item"><a href="<?= url_with_section('profile') ?>" class="nav-link <?= $active_section=='profile'?'active':'' ?>" data-section="profile">Profile</a></li>
            <li class="nav-item"><a href="<?= url_with_section('leave') ?>" class="nav-link <?= $active_section=='leave'?'active':'' ?>" data-section="leave">Apply Leave</a></li>
            <li class="nav-item"><a href="<?= url_with_section('cleaning') ?>" class="nav-link <?= $active_section=='cleaning'?'active':'' ?>" data-section="cleaning">Room Cleaning</a></li>
            <li class="nav-item"><a href="<?= url_with_section('visitor') ?>" class="nav-link <?= $active_section=='visitor'?'active':'' ?>" data-section="visitor">Visitor Pass</a></li>
            <li class="nav-item"><a href="<?= url_with_section('complaint') ?>" class="nav-link <?= $active_section=='complaint'?'active':'' ?>" data-section="complaint">Lodge Complaint</a></li>
            <li class="nav-item"><a href="<?= url_with_section('notices') ?>" class="nav-link <?= $active_section=='notices'?'active':'' ?>" data-section="notices">Notices</a></li>
            <li class="nav-item"><a href="<?= url_with_section('attendance') ?>" class="nav-link <?= $active_section=='attendance'?'active':'' ?>" data-section="attendance">Attendance</a></li>
            <li class="nav-item"><a href="<?= url_with_section('messbill') ?>" class="nav-link <?= $active_section=='messbill'?'active':'' ?>" data-section="messbill">Mess Bill</a></li>
            <li class="nav-item"><a href="<?= url_with_section('leavehistory') ?>" class="nav-link <?= $active_section=='leavehistory'?'active':'' ?>" data-section="leavehistory">Leave History</a></li>

            <li class="nav-item mt-4">
                <a href="#" class="nav-link text-warning" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                    Change Password
                </a>
            </li>
            <li class="nav-item">
                <a href="../logout.php" class="nav-link text-danger">Logout</a>
            </li>
        </ul>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content p-4">
    <div class="container-fluid">

        <!-- ALERTS -->
        <?php if (isset($success)): ?><div class="alert glass-alert alert-success"><?= $success ?></div><?php endif; ?>
        <?php if (isset($error)): ?><div class="alert glass-alert alert-danger"><?= $error ?></div><?php endif; ?>
        <?php if (isset($leave_success)): ?><div class="alert glass-alert alert-success"><?= $leave_success ?></div><?php endif; ?>
        
        <!-- DASHBOARD - PROFESSIONAL & INFO-RICH -->
        <div id="dashboard" class="content-section <?= $active_section=='dashboard'?'active':'' ?>" style="display: <?= $active_section=='dashboard'?'block':'none' ?>;">
            <div class="text-center mb-5">
                <h1 class="text-white mb-2 fw-bold">Welcome back, <?= htmlspecialchars($student['name']) ?>!</h1>
                <p class="text-white-50 fs-5">Room <?= $student['room_no'] ?? '—' ?> • Block <?= $student['block'] ?> • <?= date('l, d F Y') ?></p>
            </div>

            <!-- TOP ROW: 4 CARDS -->
            <div class="row g-4 justify-content-center mb-5">
                <div class="col-lg-3 col-md-6">
                    <div class="p-4 rounded-4 text-center" style="background: rgba(30,41,59,0.95); border: 4px solid #0d6efd;">
                        <div class="icon-circle bg-primary mx-auto mb-3"><i class="bi bi-bell-fill fs-1"></i></div>
                        <h2 class="text-white mb-1 fw-bold fs-1"><?= count($recent_notices) ?></h2>
                        <p class="text-white-75 small mb-0">Active Notices</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="p-4 rounded-4 text-center" style="background: rgba(30,41,59,0.95); border: 4px solid #ffc107;">
                        <div class="icon-circle bg-warning mx-auto mb-3"><i class="bi bi-hourglass-split fs-1 text-dark"></i></div>
                        <h2 class="text-white mb-1 fw-bold fs-1"><?= $pending_count ?></h2>
                        <p class="text-white-75 small mb-0">Pending Requests</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="p-4 rounded-4 text-center" style="background: rgba(30,41,59,0.95); border: 4px solid #198754;">
                        <div class="icon-circle bg-success mx-auto mb-3"><i class="bi bi-check-circle-fill fs-1"></i></div>
                        <h2 class="text-white mb-1 fw-bold fs-1"><?= $approved_count ?></h2>
                        <p class="text-white-75 small mb-0">Approved This Month</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="p-4 rounded-4 text-center" style="background: rgba(30,41,59,0.95); border: 4px solid #dc3545;">
                        <div class="icon-circle bg-danger mx-auto mb-3"><i class="bi bi-exclamation-triangle-fill fs-1"></i></div>
                        <h2 class="text-white mb-1 fw-bold fs-1"><?= $open_complaints_count ?></h2>
                        <p class="text-white-75 small mb-0">Open Complaints</p>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Recent Notices -->
                <div class="col-lg-12">
                    <div class="glass-card p-4">
                        <h5 class="text-info mb-3"><i class="bi bi-megaphone-fill me-2"></i>Recent Notices</h5>
                        <?php if ($recent_notices): ?>
                            <?php foreach (array_slice($recent_notices, 0, 2) as $n): ?>
                                <div class="p-3 mb-3 rounded d-flex align-items-start" style="background: rgba(255,255,255,0.05); border-left: 4px solid #0dcaf0;">
                                    <div class="me-3 mt-1"><i class="bi bi-bell-fill text-info fs-4"></i></div>
                                    <div class="flex-grow-1">
                                        <h6 class="text-white mb-1"><?= htmlspecialchars($n['title']) ?></h6>
                                        <p class="text-white-75 small mb-2"><?= substr(htmlspecialchars($n['content']), 0, 100) ?>...</p>
                                        <small class="text-info">by <?= htmlspecialchars($n['warden_name'] ?? $n['username']) ?> • <?= date('d M Y', strtotime($n['posted_at'])) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-white-50 text-center">No notices yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- PROFILE SECTION - SAME CLEAN & PREMIUM LOOK AS WARDEN (FIXED) -->
        <div id="profile" class="content-section <?= $active_section=='profile'?'active':'' ?>" 
            style="display: <?= $active_section=='profile'?'block':'none' ?>;">

            <div class="glass-card p-5 rounded-4">
                
                <!-- Profile Header - Same as Warden -->
                <div class="text-center mb-4">
                    <div class="icon-circle bg-primary mx-auto" style="width:100px; height:100px;">
                        <i class="bi bi-person-fill text-white fs-1"></i>
                    </div>
                    <h3 class="text-white mt-3 fw-bold"><?= htmlspecialchars($student['name']) ?></h3>
                    <p class="text-white fw-bold fs-4">Reg No: <?= htmlspecialchars($student['register_no']) ?></p>
                </div>

                <!-- Details Grid - Clean 2 Columns -->
                <div class="row g-4 text-white mt-4">
                    <div class="col-md-6">
                        <p><strong>Username:</strong> <?= htmlspecialchars($student['username']) ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($student['phone'] ?? 'Not set') ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($student['email'] ?? 'Not set') ?></p>
                        <p><strong>Date of Birth:</strong> <?= $student['dob'] ? date('d M Y', strtotime($student['dob'])) : 'Not set' ?></p>
                        <p><strong>Block Assigned:</strong> Block <?= $student['block'] ?></p>
                        <p><strong>Address: </strong><?= nl2br(htmlspecialchars($student['address'] ?? 'Not updated')) ?>
                        </p>
                    </div>

                    <div class="col-md-6">
                        <p><strong>Year of Study:</strong> <?= htmlspecialchars($student['year'] ?? 'Not set') ?></p>
                        <p><strong>Branch:</strong> <?= htmlspecialchars($student['branch'] ?? 'Not set') ?></p>
                        <p><strong>Room No:</strong> <?= $student['room_no'] ?? 'Not Assigned' ?></p>
                        <p><strong>Mess Balance:</strong> <span class="text-white ">₹<?= number_format($student['semester_mess_balance'], 2) ?></span></p>
                        <p><strong>Fee Status:</strong> 
                            <span class="badge bg-<?= $student['fee_status']=='paid'?'success':'danger' ?> small px-2 py-1">
                                <?= ucfirst($student['fee_status']) ?>
                            </span>
                        </p>
                    </div>
                </div>

            </div>
        </div>
        <!-- LEAVE -->
        <div id="leave" class="content-section <?= $active_section=='leave'?'active':'' ?>" style="display: <?= $active_section=='leave'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <h4 class="text-white mb-3">Apply Leave</h4>
                <form method="POST" action="<?= url_with_section('leave') ?>">
                    <div class="row g-2">
                        <div class="col"><input type="date" name="from_date" class="form-control glass-input" required></div>
                        <div class="col"><input type="date" name="to_date" class="form-control glass-input" required></div>
                    </div>
                    <textarea name="reason" class="form-control glass-input mt-2" rows="3" placeholder="Reason..." required></textarea>
                    <button type="submit" name="apply_leave" class="btn btn-primary mt-3 w-100">Apply Leave</button>
                </form>
            </div>
        </div>

        <!-- CLEANING -->
        <div id="cleaning" class="content-section <?= $active_section=='cleaning'?'active':'' ?>" style="display: <?= $active_section=='cleaning'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <h4 class="text-white mb-3">Room Cleaning Request</h4>
                <form method="POST" action="<?= url_with_section('cleaning') ?>">
                    <textarea name="issue" class="form-control glass-input" rows="3" placeholder="Describe issue..." required></textarea>
                    <input type="date" name="preferred_date" class="form-control glass-input mt-2">
                    <button type="submit" name="cleaning_req" class="btn btn-primary mt-3 w-100">Request Cleaning</button>
                </form>
            </div>
        </div>

        <!-- VISITOR -->
        <div id="visitor" class="content-section <?= $active_section=='visitor'?'active':'' ?>" style="display: <?= $active_section=='visitor'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <h4 class="text-white mb-3">Visitor Request</h4>
                <form method="POST" action="<?= url_with_section('visitor') ?>">
                    <div class="row g-2">
                        <div class="col"><input type="text" name="visitor_name" class="form-control glass-input" placeholder="Name" required></div>
                        <div class="col"><input type="text" name="relation" class="form-control glass-input" placeholder="Relation" required></div>
                    </div>
                    <div class="row g-2 mt-2">
                        <div class="col"><input type="date" name="visit_date" class="form-control glass-input" required></div>
                        <div class="col"><input type="time" name="visit_time" class="form-control glass-input" required></div>
                    </div>
                    <button type="submit" name="visitor_req" class="btn btn-primary mt-3 w-100">Submit Request</button>
                </form>
            </div>
        </div>

        <!-- COMPLAINT -->
        <div id="complaint" class="content-section <?= $active_section=='complaint'?'active':'' ?>" style="display: <?= $active_section=='complaint'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <h4 class="text-white mb-3">Lodge a Complaint</h4>
                <form method="POST" action="<?= url_with_section('complaint') ?>">
                    <input type="text" name="subject" class="form-control glass-input mb-2" placeholder="Subject" required>
                    <textarea name="message" class="form-control glass-input" rows="5" placeholder="Describe your issue..." required></textarea>
                    <button type="submit" name="complaint" class="btn btn-primary mt-3 w-100">Submit Complaint</button>
                </form>
            </div>
        </div>

        <!-- NOTICES -->
        <div id="notices" class="content-section <?= $active_section=='notices'?'active':'' ?>" style="display: <?= $active_section=='notices'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="text-white mb-0">Notices</h4>
                    <form method="GET" action="" class="w-50">
                        <input type="hidden" name="section" value="notices">
                        <div class="input-group">
                            <input type="text" name="notice_search" class="form-control glass-input border-0" 
                                   placeholder="Search notices..." value="<?= htmlspecialchars($notice_filter) ?>">
                            <button type="submit" class="btn btn-outline-light border-0">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php if ($notices): ?>
                        <?php foreach ($notices as $n): ?>
                            <div class="border-bottom border-white border-opacity-10 pb-3 mb-3">
                                <div class="d-flex justify-content-between">
                                    <h6 class="text-white"><?= htmlspecialchars($n['title']) ?></h6>
                                    <small class="text-white-50"><?= date('d M Y', strtotime($n['posted_at'])) ?></small>
                                </div>
                                <p class="text-white-75 mb-1"><?= nl2br(htmlspecialchars($n['content'])) ?></p>
                                <small class="text-white-50">by <?= htmlspecialchars($n['username']) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-white-50">No notices found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ATTENDANCE -->
        <div id="attendance" class="content-section <?= $active_section=='attendance'?'active':'' ?>" style="display: <?= $active_section=='attendance'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="text-white mb-0">Attendance</h4>
                    <form method="GET" action="" class="w-50">
                        <input type="hidden" name="section" value="attendance">
                        <div class="input-group">
                            <input type="month" name="att_month" class="form-control glass-input border-0" 
                                   value="<?= $att_month ?: date('Y-m') ?>">
                            <button type="submit" class="btn btn-outline-light border-0">
                                <i class="bi bi-calendar"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="row text-center mb-3">
                    <div class="col"><h3 class="text-white"><?= $present_count ?></h3><p class="text-success">Present</p></div>
                    <div class="col"><h3 class="text-white"><?= $absent_count ?></h3><p class="text-danger">Absent</p></div>
                    <div class="col"><h3 class="text-white"><?= $att_percent ?>%</h3><p class="text-primary">Attendance</p></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-sm">
                        <thead><tr><th>Date</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($attendance as $a): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($a['date'])) ?></td>
                                    <td><span class="badge bg-<?= $a['status']=='present'?'success':'danger' ?>"><?= ucfirst($a['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($attendance)): ?>
                                <tr><td colspan="2" class="text-center text-white-50">No records</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MESS BILL -->
        <div id="messbill" class="content-section <?= $active_section=='messbill'?'active':'' ?>" style="display: <?= $active_section=='messbill'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="text-white mb-0">Mess Bills</h4>
                    <form method="GET" action="" class="w-50">
                        <input type="hidden" name="section" value="messbill">
                        <div class="input-group">
                            <input type="month" name="bill_month" class="form-control glass-input border-0" 
                                   value="<?= $bill_month ?>">
                            <button type="submit" class="btn btn-outline-light border-0">
                                <i class="bi bi-funnel"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <?php if ($bills): ?>
                    <table class="table table-dark table-sm">
                        <thead><tr><th>Month</th><th>Amount</th><th>By</th></tr></thead>
                        <tbody>
                            <?php foreach ($bills as $b): ?>
                                <tr>
                                    <td><?= date('M Y', strtotime($b['month'])) ?></td>
                                    <td class="text-danger">-₹<?= number_format($b['amount'], 2) ?></td>
                                    <td><small><?= htmlspecialchars($b['username']) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center text-white-50">No bills found.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- LEAVE HISTORY -->
        <div id="leavehistory" class="content-section <?= $active_section=='leavehistory'?'active':'' ?>" style="display: <?= $active_section=='leavehistory'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <h4 class="text-white mb-3">Leave History</h4>
                <?php if ($leaves): ?>
                    <table class="table table-dark table-sm">
                        <thead><tr><th>Dates</th><th>Reason</th><th>Status</th><th>Action By</th></tr></thead>
                        <tbody>
                            <?php foreach ($leaves as $l): ?>
                                <tr>
                                    <td><?= date('d M', strtotime($l['from_date'])) ?> - <?= date('d M Y', strtotime($l['to_date'])) ?></td>
                                    <td><?= htmlspecialchars(substr($l['reason'], 0, 25)) ?>...</td>
                                    <td><span class="badge bg-<?= $l['status']=='approved'?'success':($l['status']=='rejected'?'danger':'warning') ?>"><?= ucfirst($l['status']) ?></span></td>
                                    <td><small><?= $l['action_by'] ? htmlspecialchars($l['action_by']) : '—' ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center text-white-50">No leave requests.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- CHANGE PASSWORD MODAL -->
<div class="modal fade" id="changePasswordModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content glass-card">
            <div class="modal-header">
                <h5 class="modal-title text-white">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= url_with_section() ?>">
                <div class="modal-body">
                    <?php if (isset($error)): ?><div class="alert alert-danger small"><?= $error ?></div><?php endif; ?>
                    <?php if (isset($success)): ?><div class="alert alert-success small"><?= $success ?></div><?php endif; ?>
                    <input type="password" name="current_password" class="form-control glass-input mb-2" placeholder="Current" required>
                    <input type="password" name="new_password" class="form-control glass-input mb-2" placeholder="New" required minlength="6">
                    <input type="password" name="confirm_password" class="form-control glass-input" placeholder="Confirm" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="change_password" class="btn btn-primary w-100">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>