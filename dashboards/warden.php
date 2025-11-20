<?php
session_start();

// === SECURITY ===
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warden') {
    header("Location: ../index.php");
    exit;
}

include '../includes/db.php';

// === GET WARDEN INFO FIRST ===
$stmt = $pdo->prepare("SELECT w.*, u.username FROM wardens w JOIN users u ON w.user_id = u.id WHERE w.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$warden = $stmt->fetch();
if (!$warden) {
    die("Warden not found!");
}
$warden_block = $warden['block'];

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
        $success = "Password updated successfully!";
    }
}

// === POST NOTICE ===
if (isset($_POST['post_notice'])) {
    $title = trim($_POST['notice_title']);
    $content = trim($_POST['notice_content']);
    if (empty($title) || empty($content)) {
        $error = "Title and content required!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO notices (title, content, posted_by, block) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $content, $_SESSION['user_id'], $warden_block]);
        $success = "Notice posted!";
    }
}

// === DELETE NOTICE ===
if (isset($_POST['delete_notice'])) {
    $nid = (int)$_POST['notice_id'];
    $stmt = $pdo->prepare("DELETE FROM notices WHERE id = ? AND posted_by = ?");
    $deleted = $stmt->execute([$nid, $_SESSION['user_id']]);
    if ($deleted && $stmt->rowCount() > 0) {
        $success = "Notice deleted!";
    } else {
        $error = "Failed to delete notice.";
    }
}

// === ASSIGN ROOM ===
if (isset($_POST['assign_room'])) {
    $student_id = $_POST['student_id'];
    $room_no = $_POST['room_no'];

    $stmt = $pdo->prepare("SELECT occupied, capacity FROM rooms WHERE room_no = ? AND block = ?");
    $stmt->execute([$room_no, $warden_block]);
    $room = $stmt->fetch();

    if (!$room) {
        $error = "Room not found!";
    } elseif ($room['occupied'] >= $room['capacity']) {
        $error = "Room $room_no is full!";
    } else {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE students SET room_no = ? WHERE id = ? AND block = ?");
        $stmt->execute([$room_no, $student_id, $warden_block]);
        $stmt = $pdo->prepare("UPDATE rooms SET occupied = occupied + 1 WHERE room_no = ? AND block = ?");
        $stmt->execute([$room_no, $warden_block]);
        $pdo->commit();
        $success = "Room $room_no assigned!";
    }
}

// === CHANGE ROOM ===
if (isset($_POST['change_room'])) {
    $student_id = $_POST['student_id'];
    $new_room = $_POST['new_room'];

    $stmt = $pdo->prepare("SELECT room_no FROM students WHERE id = ? AND block = ?");
    $stmt->execute([$student_id, $warden_block]);
    $old_room = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT occupied, capacity FROM rooms WHERE room_no = ? AND block = ?");
    $stmt->execute([$new_room, $warden_block]);
    $room = $stmt->fetch();

    if (!$room) {
        $error = "New room not found!";
    } elseif ($room['occupied'] >= $room['capacity']) {
        $error = "Room $new_room is full!";
    } else {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE students SET room_no = ? WHERE id = ?");
        $stmt->execute([$new_room, $student_id]);
        if ($old_room) {
            $stmt = $pdo->prepare("UPDATE rooms SET occupied = occupied - 1 WHERE room_no = ? AND block = ?");
            $stmt->execute([$old_room, $warden_block]);
        }
        $stmt = $pdo->prepare("UPDATE rooms SET occupied = occupied + 1 WHERE room_no = ? AND block = ?");
        $stmt->execute([$new_room, $warden_block]);
        $pdo->commit();
        $success = "Room changed to $new_room!";
    }
}

// === APPROVE/REJECT LEAVE ===
if (isset($_POST['approve_leave'])) {
    $leave_id = $_POST['leave_id'];
    $stmt = $pdo->prepare("SELECT lr.*, s.id as student_id FROM leave_requests lr JOIN students s ON lr.student_id = s.id WHERE lr.id = ? AND s.block = ?");
    $stmt->execute([$leave_id, $warden_block]);
    $leave = $stmt->fetch();

    if ($leave) {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'approved', action_by = ?, action_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $leave_id]);

        $start = new DateTime($leave['from_date']);
        $end = new DateTime($leave['to_date']);
        $insert = $pdo->prepare("INSERT INTO attendance (student_id, date, status) VALUES (?, ?, 'absent') ON DUPLICATE KEY UPDATE status = 'absent'");
        for ($d = $start; $d <= $end; $d->modify('+1 day')) {
            $insert->execute([$leave['student_id'], $d->format('Y-m-d')]);
        }
        $pdo->commit();
        $success = "Leave approved!";
    }
} elseif (isset($_POST['reject_leave'])) {
    $leave_id = $_POST['leave_id'];
    $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'rejected', action_by = ?, action_at = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $leave_id]);
    $success = "Leave rejected.";
}

// === VISITOR / CLEANING ===
if (isset($_POST['approve_visitor']) || isset($_POST['reject_visitor'])) {
    $req_id = $_POST['req_id'];
    $status = isset($_POST['approve_visitor']) ? 'approved' : 'rejected';
    $stmt = $pdo->prepare("UPDATE visitor_requests SET status = ?, action_by = ?, action_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $_SESSION['user_id'], $req_id]);
    $success = "Visitor request $status!";
}
if (isset($_POST['approve_cleaning']) || isset($_POST['reject_cleaning'])) {
    $req_id = $_POST['req_id'];
    $status = isset($_POST['approve_cleaning']) ? 'approved' : 'rejected';
    $stmt = $pdo->prepare("UPDATE cleaning_requests SET status = ?, action_by = ?, action_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $_SESSION['user_id'], $req_id]);
    $success = "Cleaning request $status!";
}

// === RESOLVE COMPLAINT ===
if (isset($_POST['resolve_complaint'])) {
    $cid = $_POST['complaint_id'];
    $stmt = $pdo->prepare("UPDATE complaints SET status = 'resolved', resolved_by = ?, resolved_at = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $cid]);
    $success = "Complaint resolved!";
}

// === SAVE PAST ATTENDANCE ===
if (isset($_POST['save_past_att'])) {
    $date = $_POST['edit_date'];
    $statuses = $_POST['status'];
    $stmt = $pdo->prepare("INSERT INTO attendance (student_id, date, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)");
    foreach ($statuses as $sid => $status) {
        $stmt->execute([$sid, $date, $status]);
    }
    $success = "Attendance saved for $date!";
}

// === GET SECTION & FILTERS ===
$active_section = $_GET['section'] ?? 'dashboard';
$search_q = trim($_GET['q'] ?? '');
$att_date = $_GET['att_date'] ?? date('Y-m-d');
$report_month = $_GET['report_month'] ?? date('Y-m');
$request_tab = $_GET['tab'] ?? 'pending'; // pending or history

// === FETCH DATA ===
$today = date('Y-m-d');

// Students
$students = $pdo->prepare("
    SELECT s.*, u.username, a.status as today_status
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
    WHERE s.block = ?
    ORDER BY s.room_no, s.name
");
$students->execute([$today, $warden_block]);
$students = $students->fetchAll();

// Unassigned
$unassigned = $pdo->prepare("SELECT s.id, s.name, s.register_no FROM students s WHERE s.block = ? AND s.room_no IS NULL");
$unassigned->execute([$warden_block]);
$unassigned = $unassigned->fetchAll();

// Rooms
$rooms = $pdo->prepare("SELECT room_no, capacity, occupied FROM rooms WHERE block = ? ORDER BY room_no");
$rooms->execute([$warden_block]);
$all_rooms = $rooms->fetchAll();

// Past Attendance - NOW WITH REGISTER NUMBER
$past_att = [];
if ($att_date) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.room_no, s.register_no, COALESCE(a.status, 'absent') as status 
        FROM students s 
        LEFT JOIN attendance a ON s.id = a.student_id AND a.date = ?
        WHERE s.block = ?
        ORDER BY s.room_no, s.name
    ");
    $stmt->execute([$att_date, $warden_block]);
    $past_att = $stmt->fetchAll();
}

// === REQUESTS (PENDING + HISTORY) ===
$pending_leaves = $pdo->prepare("SELECT lr.*, s.name, s.room_no FROM leave_requests lr JOIN students s ON lr.student_id = s.id WHERE s.block = ? AND lr.status = 'pending' ORDER BY lr.applied_at DESC");
$pending_leaves->execute([$warden_block]);
$pending_leaves = $pending_leaves->fetchAll();

$history_leaves = $pdo->prepare("SELECT lr.*, s.name, s.room_no, lr.status, lr.action_at FROM leave_requests lr JOIN students s ON lr.student_id = s.id WHERE s.block = ? AND lr.status IN ('approved', 'rejected') ORDER BY lr.action_at DESC LIMIT 50");
$history_leaves->execute([$warden_block]);
$history_leaves = $history_leaves->fetchAll();

$visitor_reqs = $pdo->prepare("SELECT v.*, s.name, s.room_no FROM visitor_requests v JOIN students s ON v.student_id = s.id WHERE s.block = ? AND v.status = 'pending' ORDER BY v.requested_at DESC");
$visitor_reqs->execute([$warden_block]);
$visitor_reqs = $visitor_reqs->fetchAll();

$history_visitors = $pdo->prepare("SELECT v.*, s.name, s.room_no, v.status, v.action_at FROM visitor_requests v JOIN students s ON v.student_id = s.id WHERE s.block = ? AND v.status IN ('approved', 'rejected') ORDER BY v.action_at DESC LIMIT 50");
$history_visitors->execute([$warden_block]);
$history_visitors = $history_visitors->fetchAll();

$cleaning_reqs = $pdo->prepare("SELECT c.*, s.name, s.room_no FROM cleaning_requests c JOIN students s ON c.student_id = s.id WHERE s.block = ? AND c.status = 'pending' ORDER BY c.requested_at DESC");
$cleaning_reqs->execute([$warden_block]);
$cleaning_reqs = $cleaning_reqs->fetchAll();

$history_cleaning = $pdo->prepare("SELECT c.*, s.name, s.room_no, c.status, c.action_at FROM cleaning_requests c JOIN students s ON c.student_id = s.id WHERE s.block = ? AND c.status IN ('approved', 'rejected') ORDER BY c.action_at DESC LIMIT 50");
$history_cleaning->execute([$warden_block]);
$history_cleaning = $history_cleaning->fetchAll();

// Complaints
$complaints = $pdo->prepare("SELECT c.*, s.name, s.room_no FROM complaints c JOIN students s ON c.student_id = s.id WHERE s.block = ? AND c.status = 'open' ORDER BY c.submitted_at DESC");
$complaints->execute([$warden_block]);
$complaints = $complaints->fetchAll();

// Notices
$notices = $pdo->query("
    SELECT n.*, u.username, w.name as warden_name 
    FROM notices n 
    JOIN users u ON n.posted_by = u.id 
    LEFT JOIN wardens w ON u.id = w.user_id 
    WHERE n.block = '$warden_block' OR n.block IS NULL 
    ORDER BY n.posted_at DESC
")->fetchAll();

// === REPORTS ===
$report_stats = [];
$request_summary = [];
if ($report_month) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total, SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) as present
        FROM attendance a JOIN students s ON a.student_id = s.id
        WHERE s.block = ? AND DATE_FORMAT(a.date, '%Y-%m') = ?
    ");
    $stmt->execute([$warden_block, $report_month]);
    $att = $stmt->fetch();
    $report_stats['attendance_pct'] = $att['total'] > 0 ? round(($att['present']/$att['total'])*100, 1) : 0;

    $stmt = $pdo->prepare("
        SELECT 'leave' as type, COUNT(*) as total, 
               SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved,
               SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected
        FROM leave_requests lr JOIN students s ON lr.student_id = s.id
        WHERE s.block = ? AND DATE_FORMAT(lr.applied_at, '%Y-%m') = ?
        UNION ALL
        SELECT 'visitor', COUNT(*), SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END), SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END)
        FROM visitor_requests v JOIN students s ON v.student_id = s.id
        WHERE s.block = ? AND DATE_FORMAT(v.requested_at, '%Y-%m') = ?
        UNION ALL
        SELECT 'cleaning', COUNT(*), SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END), SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END)
        FROM cleaning_requests c JOIN students s ON c.student_id = s.id
        WHERE s.block = ? AND DATE_FORMAT(c.requested_at, '%Y-%m') = ?
    ");
    $stmt->execute([$warden_block, $report_month, $warden_block, $report_month, $warden_block, $report_month]);
    $request_summary = $stmt->fetchAll();
}

// === SEARCH (FULL LIST + CLICK + BACK) ===
$search_results = [];
$selected_student = null;
if ($search_q === '' || $search_q === null) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.register_no, s.room_no 
        FROM students s 
        WHERE s.block = ?
        ORDER BY s.name
    ");
    $stmt->execute([$warden_block]);
    $search_results = $stmt->fetchAll();
} elseif ($search_q) {
    $like = "%$search_q%";
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.register_no, s.room_no 
        FROM students s 
        WHERE s.block = ? AND (s.name LIKE ? OR s.register_no LIKE ?)
        ORDER BY s.name
    ");
    $stmt->execute([$warden_block, $like, $like]);
    $search_results = $stmt->fetchAll();
}

$sid = $_GET['sid'] ?? null;
if ($sid) {
    $stmt = $pdo->prepare("SELECT s.*, u.username FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ? AND s.block = ?");
    $stmt->execute([$sid, $warden_block]);
    $selected_student = $stmt->fetch();

    if ($selected_student) {
        $stmt = $pdo->prepare("SELECT name FROM students WHERE room_no = ? AND id != ? AND block = ?");
        $stmt->execute([$selected_student['room_no'], $sid, $warden_block]);
        $roommates = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $leave = $pdo->query("SELECT 'Leave' as type, from_date, to_date, status FROM leave_requests WHERE student_id = $sid ORDER BY applied_at DESC")->fetchAll();
        $visitor = $pdo->query("SELECT 'Visitor' as type, visitor_name, visit_date, visit_time, status FROM visitor_requests WHERE student_id = $sid ORDER BY requested_at DESC")->fetchAll();
        $cleaning = $pdo->query("SELECT 'Cleaning' as type, issue, preferred_date, status FROM cleaning_requests WHERE student_id = $sid ORDER BY requested_at DESC")->fetchAll();

        $selected_student['roommates'] = $roommates;
        $selected_student['requests'] = array_merge($leave, $visitor, $cleaning);
    }
}

// === HELPER ===
function url_with_section($section = null, $extra = []) {
    $params = $extra;
    if ($section) $params['section'] = $section;
    elseif (isset($_GET['section'])) $params['section'] = $_GET['section'];
    return $params ? '?' . http_build_query($params) : '?';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Warden Portal - Block <?= $warden_block ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
    .form-select, .form-control { 
        color: #000 !important; 
        background-color: #fff !important; 
        border: 1px solid #ccc !important;
    }
    .form-select option { 
        color: #000; 
        background-color: #fff;
    }

    .icon-circle {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
    }
    .col-lg-2-4 {
        flex: 0 0 19.5%;
        max-width: 19.5%;
    }
    @media (max-width: 992px) {
        .col-lg-2-4 { flex: 0 0 33%; max-width: 33%; }
    }
    @media (max-width: 576px) {
        .col-lg-2-4 { flex: 0 0 50%; max-width: 50%; }
    }

    /* SEARCH RESULTS - HIGH CONTRAST & CLEAN */
    .search-result {
        background: rgba(255, 255, 255, 0.18) !important;
        border: 1px solid rgba(255, 255, 255, 0.25) !important;
        color: #ffffff !important;
        padding: 12px 16px !important;
        border-radius: 8px !important;
        margin-bottom: 8px !important;
        transition: all 0.2s ease !important;
        font-weight: 500;
    }

    .search-result:hover {
        background: rgba(255, 255, 255, 0.28) !important;
        border-color: rgba(255, 255, 255, 0.4) !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .search-result strong {
        color: #fff !important;
        font DP: 600;
    }

    .search-result .text-muted {
        color: #e0e0e0 !important;
        font-size: 0.9em;
    }

    .badge {
        font-size: 0.8em;
        padding: 0.35em 0.65em;
    }
</style>
</head>
<body class="dashboard-bg">

<!-- SIDEBAR - NOW WITH PROFILE SECTION -->
<div class="sidebar">
    <div class="p-3 text-center">
        <div class="logo-circle mx-auto mb-3">
            <i class="bi bi-person-fill text-white fs-1"></i>
        </div>
        <h6 class="text-white mb-0"><?= htmlspecialchars($warden['name']) ?></h6>
        <small class="text-white-50">Warden - Block <?= $warden_block ?></small>
    </div>
    <div class="nav-container">
        <ul class="nav flex-column">
            <li class="nav-item"><a href="?" class="nav-link <?= $active_section=='dashboard'?'active':'' ?>">Dashboard</a></li>
            <li class="nav-item"><a href="<?= url_with_section('profile') ?>" class="nav-link <?= $active_section=='profile'?'active':'' ?>">My Profile</a></li>
            <li class="nav-item"><a href="<?= url_with_section('attendance') ?>" class="nav-link <?= $active_section=='attendance'?'active':'' ?>">Attendance</a></li>
            <li class="nav-item"><a href="<?= url_with_section('rooms') ?>" class="nav-link <?= $active_section=='rooms'?'active':'' ?>">Rooms</a></li>
            <li class="nav-item"><a href="<?= url_with_section('requests') ?>" class="nav-link <?= $active_section=='requests'?'active':'' ?>">Requests</a></li>
            <li class="nav-item"><a href="<?= url_with_section('complaints') ?>" class="nav-link <?= $active_section=='complaints'?'active':'' ?>">Complaints</a></li>
            <li class="nav-item"><a href="<?= url_with_section('notices') ?>" class="nav-link <?= $active_section=='notices'?'active':'' ?>">Notices</a></li>
            <li class="nav-item"><a href="<?= url_with_section('search') ?>" class="nav-link <?= $active_section=='search'?'active':'' ?>">Search</a></li>
            <li class="nav-item"><a href="<?= url_with_section('reports') ?>" class="nav-link <?= $active_section=='reports'?'active':'' ?>">Reports</a></li>

            <li class="nav-item mt-4">
                <a href="#" class="nav-link text-warning" data-bs-toggle="modal" data-bs-target="#changePasswordModal">Change Password</a>
            </li>
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

        <!-- WARDEN DASHBOARD - CLEAN, NO GLOW, EXACTLY AS REQUESTED -->
        <div id="dashboard" class="content-section <?= $active_section=='dashboard'?'active':'' ?>" style="display: <?= $active_section=='dashboard'?'block':'none' ?>;">

            <div class="text-center mb-5">
                <h1 class="text-white mb-2 fw-bold">Warden Dashboard</h1>
                <p class="text-white-50 fs-5">Block <?= $warden_block ?> • <?= date('l, d F Y') ?></p>
            </div>

            <?php
            $total_students      = count($students);
            $unassigned_students = count($unassigned);
            $present_today       = 0;
            foreach ($students as $s) {
                if ($s['today_status'] === 'present') $present_today++;
            }
            $total_requests  = count($pending_leaves) + count($visitor_reqs) + count($cleaning_reqs);
            $open_complaints = count($complaints);
            $total_notices   = count($notices);
            ?>

        <!-- TOP ROW - 3 CARDS -->
            <div class="row g-4 justify-content-center mb-5">
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="p-4 rounded-4 text-center" style="background: rgba(30,41,59,0.95); border: 4px solid #0d6efd;">
                        <div class="icon-circle bg-primary mx-auto mb-3">
                            <i class="bi bi-people-fill fs-1"></i>
                        </div>
                        <h2 class="text-white mb-1 fw-bold fs-1"><?= $total_students ?></h2>
                        <p class="text-white-75 small mb-0">Total Students</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 col-12">
                    <div class="p-4 rounded-4 text-center" style="background: rgba(30,41,59,0.95); border: 4px solid #198754;">
                        <div class="icon-circle bg-success mx-auto mb-3">
                            <i class="bi bi-check-circle-fill fs-1"></i>
                        </div>
                        <h2 class="text-white mb-1 fw-bold fs-1"><?= $present_today ?></h2>
                        <p class="text-white-75 small mb-0">Present Today</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 col-12">
                    <div class="p-4 rounded-4 text-center" style="background: rgba(30,41,59,0.95); border: 4px solid #dc3545;">
                        <div class="icon-circle bg-danger mx-auto mb-3">
                            <i class="bi bi-house-door-fill fs-1"></i>
                        </div>
                        <h2 class="text-white mb-1 fw-bold fs-1"><?= $unassigned_students ?></h2>
                        <p class="text-white-75 small mb-0">Unassigned Rooms</p>
                    </div>
                </div>
            </div>

            <!-- BOTTOM ROW - 3 CARDS -->
            <div class="row g-4 justify-content-center mb-5">
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="p-4 rounded-4 text-center" style="background: rgba(30,41,59,0.95); border: 4px solid #ffc107;">
                        <div class="icon-circle bg-warning mx-auto mb-3">
                            <i class="bi bi-bell-fill fs-1 text-dark"></i>
                        </div>
                        <h2 class="text-white mb-1 fw-bold fs-1"><?= $total_requests ?></h2>
                        <p class="text-white-75 small mb-0">Pending Requests</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 col-12">
                    <div class="p-4 rounded-4 text-center" style="background: rgba(30,41,59,0.95); border: 4px solid #6f42c1;">
                        <div class="icon-circle mx-auto mb-3" style="background: linear-gradient(135deg,#7c3aed,#a855f7);">
                            <i class="bi bi-exclamation-triangle-fill fs-1 text-white"></i>
                        </div>
                        <h2 class="text-white mb-1 fw-bold fs-1"><?= $open_complaints ?></h2>
                        <p class="text-white-75 small mb-0">Open Complaints</p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 col-12">
                    <div class="p-4 rounded-4 text-center" style="background: rgba(30,41,59,0.95); border: 4px solid #0dcaf0;">
                        <div class="icon-circle bg-info mx-auto mb-3">
                            <i class="bi bi-megaphone-fill fs-1"></i>
                        </div>
                        <h2 class="text-white mb-1 fw-bold fs-1"><?= $total_notices ?></h2>
                        <p class="text-white-75 small mb-0">Total Notices</p>
                    </div>
                </div>
            </div>

            <!-- RECENT NOTICES - SAME AS OFFICE -->
            <?php if($notices): ?>
            <div class="glass-card p-4 rounded-4" style="background: rgba(15,23,42,0.8); backdrop-filter: blur(12px);">
                <h5 class="text-info mb-4">
                    <i class="bi bi-megaphone-fill me-2"></i> Recent Notices
                </h5>
                <div class="row g-3">
                    <?php foreach(array_slice($notices, 0, 2) as $n): ?>
                        <div class="col-12">
                            <div class="p-3 rounded d-flex align-items-start" style="background: rgba(255,255,255,0.05); border-left: 4px solid #0dcaf0;">
                                <div class="me-3 mt-1">
                                    <i class="bi bi-bell-fill text-info fs-4"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="text-white mb-1"><?= htmlspecialchars($n['title']) ?></h6>
                                    <p class="text-white-75 small mb-2"><?= substr(htmlspecialchars($n['content']), 0, 110) ?>...</p>
                                    <small class="text-info">
                                        by <strong><?= htmlspecialchars($n['warden_name'] ?? $n['username']) ?></strong>
                                        • <?= date('d M Y', strtotime($n['posted_at'])) ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if($total_notices > 3): ?>
                    <div class="text-center mt-4">
                        <a href="<?= url_with_section('notices') ?>" class="btn btn-outline-info rounded-pill px-5">
                            View All Notices →
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- PROFILE SECTION - CLEAN & ACCURATE -->
        <div id="profile" class="content-section <?= $active_section=='profile'?'active':'' ?>" style="display: <?= $active_section=='profile'?'block':'none' ?>;">
            <div class="glass-card p-5 rounded-4">
                <div class="text-center mb-4">
                    <div class="icon-circle bg-primary mx-auto" style="width:100px;height:100px;">
                        <i class="bi bi-person-fill text-white fs-1"></i>
                    </div>
                    <h3 class="text-white mt-3"><?= htmlspecialchars($warden['name']) ?></h3>
                    <p class="text-white fw-bold fs-4">Warden ID: <?= htmlspecialchars($warden['id']) ?></p>
                </div>

                <div class="row g-4 text-white mt-4">
                    <div class="col-md-6">
                        <p><strong>Username:</strong> <?= htmlspecialchars($warden['username']) ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($warden['phone'] ?? 'Not set') ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($warden['email'] ?? 'Not set') ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Date of Birth:</strong> <?= $warden['dob'] ? date('d M Y', strtotime($warden['dob'])) : 'Not set' ?></p>
                        <p><strong>Block Assigned:</strong> Block <?= $warden_block ?></p>
                        <p><strong>Address:</strong> <?= nl2br(htmlspecialchars($warden['address'] ?? 'Not set')) ?></p>
                    </div>
                </div>
            </div>
        </div>        

        <!-- ATTENDANCE - AUTO PRESENT FOR FUTURE DATES -->
        <div id="attendance" class="content-section" style="display: <?= $active_section=='attendance'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <h4 class="text-white mb-4">Edit Attendance</h4>
                <form method="GET" class="mb-4">
                    <input type="hidden" name="section" value="attendance">
                    <div class="input-group w-auto">
                        <input type="date" name="att_date" class="form-control" value="<?= $att_date ?>" max="<?= date('Y-m-d') ?>">
                        <button class="btn btn-primary">Go</button>
                    </div>
                </form>

                <form method="POST">
                    <input type="hidden" name="edit_date" value="<?= $att_date ?>">
                    <?php if ($past_att): ?>
                        <?php foreach ($past_att as $s): 
                            $isFuture = strtotime($att_date) > time();
                            $defaultStatus = $isFuture ? 'present' : ($s['status'] ?? 'absent');
                        ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom border-white border-opacity-10 py-3">
                                <div>
                                    <strong class="text-white"><?= htmlspecialchars($s['name']) ?></strong><br>
                                    <small class="text-white-50">Room: <?= $s['room_no'] ?? '—' ?> | Reg: <?= $s['register_no'] ?? '' ?></small>
                                </div>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="status[<?= $s['id'] ?>]" value="present" 
                                            <?= $defaultStatus=='present'?'checked':'' ?>>
                                        <label class="form-check-label text-success fw-bold">Present</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="status[<?= $s['id'] ?>]" value="absent" 
                                            <?= $defaultStatus=='absent'?'checked':'' ?>>
                                        <label class="form-check-label text-danger fw-bold">Absent</label>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <button name="save_past_att" class="btn btn-success mt-4 w-100 w-100 fw-bold">Save Attendance</button>
                    <?php else: ?>
                        <p class="text-center text-white-50">No students found in Block <?= $warden_block ?>.</p>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- ROOMS -->
        <div id="rooms" class="content-section" style="display: <?= $active_section=='rooms'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <h4 class="text-white mb-3">Room Management</h4>
                <div class="row g-3">
                    <div class="col-md-6">
                        <h5 class="text-white">Assign Room</h5>
                        <?php if ($unassigned): ?>
                            <form method="POST" class="mb-3">
                                <div class="row g-2">
                                    <div class="col">
                                        <select name="student_id" class="form-select" required>
                                            <option value="">Select Student</option>
                                            <?php foreach ($unassigned as $u): ?>
                                                <option value="<?= $u['id'] ?>"><?= $u['name'] ?> (<?= $u['register_no'] ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col">
                                        <select name="room_no" class="form-select" required>
                                            <option value="">Select Room</option>
                                            <?php foreach ($all_rooms as $r): 
                                                $avail = $r['capacity'] - $r['occupied'];
                                                if ($avail > 0): ?>
                                                    <option value="<?= $r['room_no'] ?>"><?= $r['room_no'] ?> (<?= $avail ?> free)</option>
                                                <?php endif; 
                                            endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <button name="assign_room" class="btn btn-primary">Assign</button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <p class="text-white-50">No unassigned students.</p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h5 class="text-white">Change Room</h5>
                        <form method="POST">
                            <div class="row g-2">
                                <div class="col">
                                    <select name="student_id" class="form-select" required>
                                        <option value="">Select Student</option>
                                        <?php foreach ($students as $s): ?>
                                            <option value="<?= $s['id'] ?>"><?= $s['name'] ?> (Room <?= $s['room_no'] ?? '—' ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col">
                                    <select name="new_room" class="form-select" required>
                                        <option value="">Select New Room</option>
                                        <?php foreach ($all_rooms as $r): 
                                            $avail = $r['capacity'] - $r['occupied'];
                                            if ($avail > 0): ?>
                                                <option value="<?= $r['room_no'] ?>"><?= $r['room_no'] ?> (<?= $avail ?> free)</option>
                                            <?php endif; 
                                        endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <button name="change_room" class="btn btn-primary">Change</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- REQUESTS WITH PENDING + HISTORY -->
        <div id="requests" class="content-section" style="display: <?= $active_section=='requests'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item">
                        <a class="nav-link <?= $request_tab=='pending'?'active':'' ?>" href="<?= url_with_section('requests', ['tab'=>'pending']) ?>">Pending</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $request_tab=='history'?'active':'' ?>" href="<?= url_with_section('requests', ['tab'=>'history']) ?>">History</a>
                    </li>
                </ul>

                <?php if ($request_tab === 'pending'): ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <h5 class="text-white">Leave Requests</h5>
                            <?php if ($pending_leaves): ?>
                                <?php foreach ($pending_leaves as $l): ?>
                                    <div class="border-bottom border-white border-opacity-10 pb-2 mb-2">
                                        <strong class="text-white"><?= htmlspecialchars($l['name']) ?> (<?= $l['room_no'] ?>)</strong>
                                        <small class="text-white-50 d-block"><?= date('d M', strtotime($l['from_date'])) ?> - <?= date('d M Y', strtotime($l['to_date'])) ?></small>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="leave_id" value="<?= $l['id'] ?>">
                                            <button name="approve_leave" class="btn btn-sm btn-success">Approve</button>
                                            <button name="reject_leave" class="btn btn-sm btn-danger">Reject</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-white-50">No pending leave requests.</p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <h5 class="text-white">Visitor Requests</h5>
                            <?php if ($visitor_reqs): ?>
                                <?php foreach ($visitor_reqs as $v): ?>
                                    <div class="border-bottom border-white border-opacity-10 pb-2 mb-2">
                                        <strong class="text-white"><?= htmlspecialchars($v['name']) ?> (<?= $v['room_no'] ?>)</strong>
                                        <small class="text-white-50 d-block"><?= htmlspecialchars($v['visitor_name']) ?> • <?= date('d M Y h:i A', strtotime($v['visit_date'] . ' ' . $v['visit_time'])) ?></small>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="req_id" value="<?= $v['id'] ?>">
                                            <button name="approve_visitor" class="btn btn-sm btn-success">Approve</button>
                                            <button name="reject_visitor" class="btn btn-sm btn-danger">Reject</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-white-50">No pending visitor requests.</p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <h5 class="text-white">Cleaning Requests</h5>
                            <?php if ($cleaning_reqs): ?>
                                <?php foreach ($cleaning_reqs as $c): ?>
                                    <div class="border-bottom border-white border-opacity-10 pb-2 mb-2">
                                        <strong class="text-white"><?= htmlspecialchars($c['name']) ?> (<?= $c['room_no'] ?>)</strong>
                                        <small class="text-white-50 d-block"><?= htmlspecialchars(substr($c['issue'], 0, 30)) ?>... • <?= date('d M Y', strtotime($c['preferred_date'])) ?></small>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="req_id" value="<?= $c['id'] ?>">
                                            <button name="approve_cleaning" class="btn btn-sm btn-success">Approve</button>
                                            <button name="reject_cleaning" class="btn btn-sm btn-danger">Reject</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-white-50">No pending cleaning requests.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <h5 class="text-white">Leave History</h5>
                            <?php foreach ($history_leaves as $l): ?>
                                <div class="border-bottom border-white border-opacity-10 pb-2 mb-2">
                                    <strong class="text-white"><?= htmlspecialchars($l['name']) ?></strong>
                                    <small class="text-white-50 d-block"><?= date('d M Y', strtotime($l['from_date'])) ?> - <?= date('d M Y', strtotime($l['to_date'])) ?></small>
                                    <span class="badge bg-<?= $l['status']=='approved'?'success':'danger' ?>"><?= ucfirst($l['status']) ?></span>
                                    <small class="text-white-50 d-block"><?= date('d M h:i A', strtotime($l['action_at'])) ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-md-4">
                            <h5 class="text-white">Visitor History</h5>
                            <?php foreach ($history_visitors as $v): ?>
                                <div class="border-bottom border-white border-opacity-10 pb-2 mb-2">
                                    <strong class="text-white"><?= htmlspecialchars($v['name']) ?></strong>
                                    <small class="text-white-50 d-block"><?= htmlspecialchars($v['visitor_name']) ?> • <?= date('d M Y h:i A', strtotime($v['visit_date'] . ' ' . $v['visit_time'])) ?></small>
                                    <span class="badge bg-<?= $v['status']=='approved'?'success':'danger' ?>"><?= ucfirst($v['status']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-md-4">
                            <h5 class="text-white">Cleaning History</h5>
                            <?php foreach ($history_cleaning as $c): ?>
                                <div class="border-bottom border-white border-opacity-10 pb-2 mb-2">
                                    <strong class="text-white"><?= htmlspecialchars($c['name']) ?></strong>
                                    <small class="text-white-50 d-block"><?= htmlspecialchars(substr($c['issue'], 0, 30)) ?>... • <?= date('d M Y', strtotime($c['preferred_date'])) ?></small>
                                    <span class="badge bg-<?= $c['status']=='approved'?'success':'danger' ?>"><?= ucfirst($c['status']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- COMPLAINTS -->
        <div id="complaints" class="content-section" style="display: <?= $active_section=='complaints'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <h4 class="text-white mb-3">Open Complaints</h4>
                <?php if ($complaints): ?>
                    <?php foreach ($complaints as $c): ?>
                        <div class="border-bottom border-white border-opacity-10 pb-3 mb-3">
                            <h6 class="text-white"><?= htmlspecialchars($c['subject'] ?? 'No Subject') ?></h6>
                            <p class="text-white-75 mb-1"><?= nl2br(htmlspecialchars($c['message'] ?? '')) ?></p>
                            <small class="text-white-50">
                                by <?= htmlspecialchars($c['name']) ?> (Room <?= $c['room_no'] ?? '—' ?>) 
                                • <?= date('d M Y', strtotime($c['submitted_at'])) ?>
                            </small>
                            <form method="POST" class="d-inline mt-1">
                                <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                                <button name="resolve_complaint" class="btn btn-sm btn-success">Resolve</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-white-50">No open complaints.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- NOTICES -->
        <div id="notices" class="content-section" style="display: <?= $active_section=='notices'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <div class="d-flex justify-content-between mb-3">
                    <h4 class="text-white mb-0">Notices</h4>
                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#postNoticeModal">Post</button>
                </div>
                <?php if ($notices): ?>
                    <?php foreach ($notices as $n): ?>
                        <div class="border-bottom border-white border-opacity-10 pb-3 mb-3">
                            <h6 class="text-white"><?= htmlspecialchars($n['title']) ?></h6>
                            <p class="text-white-75 mb-1"><?= nl2br(htmlspecialchars($n['content'])) ?></p>
                            <small class="text-white-50">
                                by <?= htmlspecialchars($n['warden_name'] ?? $n['username']) ?> • <?= date('d M Y, h:i A', strtotime($n['posted_at'])) ?>
                            </small>
                            <?php if ($n['posted_by'] == $_SESSION['user_id']): ?>
                                <form method="POST" class="d-inline ms-2">
                                    <input type="hidden" name="notice_id" value="<?= $n['id'] ?>">
                                    <button name="delete_notice" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-white-50 text-center">No notices.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- SEARCH -->
        <div id="search" class="content-section" style="display: <?= $active_section=='search'?'block':'none' ?>;">
            <div class="glass-card p-4">
                <h4 class="text-white mb-3">Search Student</h4>
                <form method="GET" class="mb-3">
                    <input type="hidden" name="section" value="search">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Name or Reg No" value="<?= htmlspecialchars($search_q) ?>">
                        <button class="btn btn-primary">Search</button>
                    </div>
                </form>

                <?php if ($search_results): ?>
                    <?php if (!$selected_student): ?>
                        <div class="list-group">
                            <?php foreach ($search_results as $s): ?>
                                <a href="?section=search&q=<?= urlencode($search_q) ?>&sid=<?= $s['id'] ?>" class="list-group-item list-group-item-action search-result p-3">
                                    <strong><?= htmlspecialchars($s['name']) ?></strong> 
                                    <span class="text-muted">| Reg: <?= $s['register_no'] ?> | Room: <?= $s['room_no'] ?? '—' ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="glass-card p-3">
                            <h5 class="text-white"><?= htmlspecialchars($selected_student['name']) ?> (<?= $selected_student['register_no'] ?>)</h5>
                            <p><strong>Room:</strong> <?= $selected_student['room_no'] ?? 'Not Assigned' ?></p>
                            <?php if ($selected_student['roommates']): ?>
                                <p><strong>Roommates:</strong> <?= implode(', ', array_map('htmlspecialchars', $selected_student['roommates'])) ?></p>
                            <?php endif; ?>
                            <?php if ($selected_student['requests']): ?>
                                <h6 class="text-white mt-3">Requests</h6>
                                <table class="table table-dark table-sm">
                                    <thead><tr><th>Type</th><th>Details</th><th>Status</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($selected_student['requests'] as $r): ?>
                                            <tr>
                                                <td><?= $r['type'] ?></td>
                                                <td>
                                                    <?php if ($r['type'] == 'Leave'): ?>
                                                        <?= $r['from_date'] ?> to <?= $r['to_date'] ?>
                                                    <?php elseif ($r['type'] == 'Visitor'): ?>
                                                        <?= $r['visitor_name'] ?> on <?= $r['visit_date'] ?>
                                                    <?php else: ?>
                                                        <?= $r['issue'] ?> on <?= $r['preferred_date'] ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge bg-<?= $r['status']=='approved'?'success':($r['status']=='rejected'?'danger':'warning') ?>"><?= ucfirst($r['status']) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-white-50">No requests found.</p>
                            <?php endif; ?>
                            <a href="?section=search&q=<?= urlencode($search_q) ?>" class="btn btn-sm btn-outline-light mt-2">Back to List</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-white-50">No students found.</p>
                <?php endif; ?>
            </div>
        </div>

<!-- REPORTS - DASHBOARD-STYLE COLORED BORDERS + 3+2 LAYOUT -->
<div id="reports" class="content-section" style="display: <?= $active_section=='reports'?'block':'none' ?>;">
    <div class="glass-card p-4">
        <h4 class="text-white mb-4 text-center fw-bold">
            Monthly Report - <?= date('F Y', strtotime($report_month)) ?>
        </h4>
        <form method="GET" class="mb-5 d-flex justify-content-center">
            <input type="hidden" name="section" value="reports">
            <div class="input-group w-auto">
                <input type="month" name="report_month" class="form-control" value="<?= $report_month ?>" required>
                <button class="btn btn-primary px-4">View Report</button>
            </div>
        </form>

        <div class="row g-4 justify-content-center">

            <!-- ROW 1: 3 Cards - Same as Dashboard Style -->
            <div class="col-lg-4 col-md-6">
                <div class="p-4 rounded-4 text-center h-100" style="background: rgba(30,41,59,0.95); border: 4px solid #198754;">
                    <div class="icon-circle bg-success mx-auto mb-3">
                        <i class="bi bi-calendar-x-fill fs-1"></i>
                    </div>
                    <h5 class="text-white mb-3">Leave Requests</h5>
                    <p class="mb-1"><strong>Total:</strong> <?= $request_summary[0]['total'] ?? 0 ?></p>
                    <p class="text-success mb-0">Approved: <?= $request_summary[0]['approved'] ?? 0 ?></p>
                    <p class="text-danger mb-0">Rejected: <?= $request_summary[0]['rejected'] ?? 0 ?></p>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="p-4 rounded-4 text-center h-100" style="background: rgba(30,41,59,0.95); border: 4px solid #0dcaf0;">
                    <div class="icon-circle bg-info mx-auto mb-3">
                        <i class="bi bi-person-plus-fill fs-1"></i>
                    </div>
                    <h5 class="text-white mb-3">Visitor Requests</h5>
                    <p class="mb-1"><strong>Total:</strong> <?= $request_summary[1]['total'] ?? 0 ?></p>
                    <p class="text-success mb-0">Approved: <?= $request_summary[1]['approved'] ?? 0 ?></p>
                    <p class="text-danger mb-0">Rejected: <?= $request_summary[1]['rejected'] ?? 0 ?></p>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="p-4 rounded-4 text-center h-100" style="background: rgba(30,41,59,0.95); border: 4px solid #ffc107;">
                    <div class="icon-circle bg-warning mx-auto mb-3">
                        <i class="bi bi-droplet-fill fs-1 text-dark"></i>
                    </div>
                    <h5 class="text-white mb-3">Cleaning Requests</h5>
                    <p class="mb-1"><strong>Total:</strong> <?= $request_summary[2]['total'] ?? 0 ?></p>
                    <p class="text-success mb-0">Approved: <?= $request_summary[2]['approved'] ?? 0 ?></p>
                    <p class="text-danger mb-0">Rejected: <?= $request_summary[2]['rejected'] ?? 0 ?></p>
                </div>
            </div>

            <!-- ROW 2: 2 Cards - Centered -->
            <div class="col-lg-4  col-md-6">
                <div class="p-4 rounded-4 text-center h-100" style="background: rgba(30,41,59,0.95); border: 4px solid #0d6efd;">
                    <div class="icon-circle bg-primary mx-auto mb-3">
                        <i class="bi bi-graph-up fs-1"></i>
                    </div>
                    <h5 class="text-white mb-3">Monthly Attendance</h5>
                    <h2 class="text-white mb-0 fw-bold"><?= $report_stats['attendance_pct'] ?? 0 ?>%</h2>
                    <small class="text-white-75">Average this month</small>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <?php
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints c JOIN students s ON c.student_id = s.id WHERE s.block = ? AND DATE_FORMAT(c.submitted_at, '%Y-%m') = ?");
                $stmt->execute([$warden_block, $report_month]);
                $complaint_count = $stmt->fetchColumn();
                ?>
                <div class="p-4 rounded-4 text-center h-100" style="background: rgba(30,41,59,0.95); border: 4px solid #dc3545;">
                    <div class="icon-circle bg-danger mx-auto mb-3">
                        <i class="bi bi-exclamation-triangle-fill fs-1"></i>
                    </div>
                    <h5 class="text-white mb-3">Complaints Received</h5>
                    <h2 class="text-white mb-0 fw-bold"><?= $complaint_count ?></h2>
                    <small class="text-white-75">This month</small>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- POST NOTICE MODAL -->
<div class="modal fade" id="postNoticeModal">
    <div class="modal-dialog modal-md">
        <div class="modal-content glass-card">
            <div class="modal-header">
                <h5 class="modal-title text-white">Post New Notice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="text" name="notice_title" class="form-control glass-input mb-2" placeholder="Title" required>
                    <textarea name="notice_content" class="form-control glass-input" rows="4" placeholder="Content..." required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="post_notice" class="btn btn-danger w-100">Post Notice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- CHANGE PASSWORD MODAL - MODERN & CLEAN DESIGN -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content glass-card border-0 shadow-lg">
            <div class="modal-header border-0 pb-2">
                <h5 class="modal-title text-white fw-bold"> Change Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form method="POST" action="<?= url_with_section() ?>">
                <div class="modal-body pt-2">

                    <!-- Success / Error Messages -->
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success small py-2 mb-3 text-center">
                            <i class="bi bi-check-circle-fill"></i> <?= $success ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger small py-2 mb-3 text-center">
                            <i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <!-- Password Fields - Clean & Minimal -->
                    <div class="mb-3">
                        <input type="password" 
                               name="current_password" 
                               class="form-control glass-input" 
                               placeholder="Current Password" 
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <input type="password" 
                               name="new_password" 
                               class="form-control glass-input" 
                               placeholder="New Password" 
                               required 
                               minlength="6">
                    </div>
                    
                    <div class="mb-3">
                        <input type="password" 
                               name="confirm_password" 
                               class="form-control glass-input" 
                               placeholder="Confirm New Password" 
                               required>
                    </div>

                </div>

                <div class="modal-footer border-0 pt-2">
                    <button type="submit" 
                            name="change_password" 
                            class="btn btn-primary w-100 fw-bold py-2 rounded-pill shadow-sm">Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>