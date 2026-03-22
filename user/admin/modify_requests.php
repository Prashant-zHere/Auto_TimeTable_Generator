<?php
session_start();
require_once '../../include/conn/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action']; // approve or reject
    $admin_comment = isset($_POST['admin_comment']) ? trim($_POST['admin_comment']) : '';
    
    if ($action === 'approve') {
        $status = 'approved';
        $status_text = 'Approved';
        
        $request_query = mysqli_query($conn, "SELECT * FROM modify_requests WHERE id = $request_id");
        if ($request = mysqli_fetch_assoc($request_query)) {
            $change_data = json_decode($request['requested_change'], true);
            if ($change_data) {
                $timetable_id = $request['timetable_id'];
                
                if (isset($change_data['subject_id'])) {
                    $subject_id = $change_data['subject_id'] ? $change_data['subject_id'] : 'NULL';
                    
                    $teacher_id = 'NULL';
                    if ($subject_id != 'NULL') {
                        $subject_query = mysqli_query($conn, "SELECT teacher_id FROM subjects WHERE id = $subject_id");
                        if ($subject_data = mysqli_fetch_assoc($subject_query)) {
                            $teacher_id = $subject_data['teacher_id'] ? $subject_data['teacher_id'] : 'NULL';
                        }
                    }
                    
                    $update_timetable = "UPDATE timetable SET 
                                         subject_id = $subject_id, 
                                         teacher_id = $teacher_id 
                                         WHERE id = $timetable_id";
                    mysqli_query($conn, $update_timetable);
                }
            }
        }
    } else {
        $status = 'rejected';
        $status_text = 'Rejected';
    }
    
    $update = "UPDATE modify_requests SET 
               status = '$status', 
               processed_by = {$_SESSION['user_id']}";
    
    if (!empty($admin_comment)) {
        $check_column = mysqli_query($conn, "SHOW COLUMNS FROM modify_requests LIKE 'admin_comment'");
        if (mysqli_num_rows($check_column) > 0) {
            $update .= ", admin_comment = '$admin_comment'";
        } 
        else
        {

        }
    }
    
    $update .= " WHERE id = $request_id";
    
    if (mysqli_query($conn, $update)) {
        $success = "Modify request $status_text successfully!";
    } else {
        $error = 'Error processing request: ' . mysqli_error($conn);
    }
}

if (isset($_POST['bulk_action']) && isset($_POST['selected_ids'])) {
    $bulk_action = $_POST['bulk_action'];
    $selected_ids = explode(',', $_POST['selected_ids']);
    $count = 0;
    
    if ($bulk_action === 'approve') {
        foreach($selected_ids as $id) {
            $update = "UPDATE modify_requests SET status = 'approved', processed_by = {$_SESSION['user_id']} WHERE id = $id";
            if (mysqli_query($conn, $update)) $count++;
        }
        $success = "$count modify requests approved successfully!";
    } elseif ($bulk_action === 'reject') {
        foreach($selected_ids as $id) {
            $update = "UPDATE modify_requests SET status = 'rejected', processed_by = {$_SESSION['user_id']} WHERE id = $id";
            if (mysqli_query($conn, $update)) $count++;
        }
        $success = "$count modify requests rejected successfully!";
    }
}

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$teacher_filter = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;

$where_clauses = [];
if ($status_filter != 'all') {
    $where_clauses[] = "mr.status = '$status_filter'";
}
if ($date_filter) {
    $where_clauses[] = "DATE(mr.requested_at) = '$date_filter'";
}
if ($teacher_filter > 0) {
    $where_clauses[] = "mr.teacher_id = $teacher_filter";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$requests = mysqli_query($conn, "
    SELECT mr.*, 
           u.full_name as teacher_name, 
           t.employee_id,
           pu.full_name as processed_by_name,
           c.class_name,
           s.subject_name, s.subject_code,
           ts.start_time, ts.end_time, ts.slot_number,
           tt.day_of_week
    FROM modify_requests mr
    JOIN teachers t ON mr.teacher_id = t.id 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN timetable tt ON mr.timetable_id = tt.id
    LEFT JOIN classes c ON tt.class_id = c.id
    LEFT JOIN subjects s ON tt.subject_id = s.id
    LEFT JOIN time_slots ts ON tt.slot_id = ts.id
    LEFT JOIN users pu ON mr.processed_by = pu.id
    $where_sql
    ORDER BY 
        CASE mr.status 
            WHEN 'pending' THEN 1 
            WHEN 'approved' THEN 2 
            WHEN 'rejected' THEN 3 
        END,
        mr.requested_at DESC
");

$stats_query = mysqli_query($conn, "
    SELECT 
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        COUNT(*) as total
    FROM modify_requests
");
$stats = mysqli_fetch_assoc($stats_query);

$all_teachers = mysqli_query($conn, "
    SELECT t.id, u.full_name, t.employee_id 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY u.full_name
");

$day_names = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

$full_name = $_SESSION['full_name'];

$pending_leaves = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as count FROM leave_requests WHERE status='pending'"
))['count'];

$pending_modifies = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as count FROM modify_requests WHERE status='pending'"
))['count'];


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modify Requests · Admin</title>
    <link rel="stylesheet" href="../../include/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            display: flex;
            min-height: 100vh;
            background: #fafafa;
        }

        .sidebar {
            width: 280px;
            background: #000;
            border-right: 4px solid #000;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 4px solid #000;
            background: var(--blue);
        }

        .sidebar-header h2 {
            color: white;
            font-size: 24px;
            font-weight: 900;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-shapes {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .logo-shapes .circle {
            width: 16px;
            height: 16px;
            background: var(--yellow);
            border-radius: 50%;
            border: 2px solid black;
        }

        .logo-shapes .square {
            width: 14px;
            height: 14px;
            background: var(--red);
            border: 2px solid black;
        }

        .logo-shapes .triangle {
            width: 0;
            height: 0;
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-bottom: 16px solid var(--yellow);
        }

        .admin-info {
            padding: 20px;
            background: #333;
            border-bottom: 4px solid #000;
            color: white;
        }

        .admin-name {
            font-weight: 900;
            font-size: 18px;
            margin-bottom: 5px;
        }

        .admin-role {
            background: var(--yellow);
            color: black;
            padding: 3px 8px;
            display: inline-block;
            border: 2px solid #000;
            font-weight: 800;
            font-size: 12px;
        }

        .nav-menu {
            flex: 1;
            padding: 20px 0;
        }

        .nav-section {
            margin-bottom: 25px;
        }

        .nav-section-title {
            color: #ccc;
            font-weight: 800;
            font-size: 12px;
            padding: 0 20px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            font-weight: 700;
            border-left: 4px solid transparent;
            transition: all 0.2s ease;
        }

        .nav-item:hover {
            background: #333;
            border-left-color: var(--yellow);
        }

        .nav-item.active {
            background: #222;
            border-left-color: var(--red);
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 4px solid #000;
            background: #1a1a1a;
        }

        .logout-btn {
            display: block;
            background: var(--red);
            color: white;
            text-decoration: none;
            padding: 12px;
            text-align: center;
            font-weight: 900;
            border: 3px solid #000;
            box-shadow: 3px 3px 0 #000;
            transition: all 0.1s ease;
        }

        .logout-btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 #000;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            background: #fafafa;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: var(--yellow);
            border: 4px solid #000;
            box-shadow: var(--shadow);
        }

        .content-header h1 {
            font-size: 28px;
            font-weight: 900;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 900;
        }

        .stat-pending { color: var(--orange); }
        .stat-approved { color: var(--green); }
        .stat-rejected { color: var(--red); }
        .stat-total { color: var(--blue); }

        .filter-bar {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 150px;
        }

        .filter-group label {
            font-weight: 800;
            display: block;
            margin-bottom: 5px;
            font-size: 12px;
            background: var(--blue);
            color: white;
            padding: 3px 8px;
            border: 2px solid #000;
            width: fit-content;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px;
            border: 3px solid #000;
            font-weight: 600;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .filter-btn {
            padding: 10px 20px;
            border: 3px solid #000;
            font-weight: 800;
            cursor: pointer;
            background: var(--blue);
            color: white;
            text-decoration: none;
        }

        .reset-btn {
            background: var(--red);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border: 3px solid #000;
            font-weight: 800;
        }

        .bulk-actions {
            background: var(--light-gray);
            border: 4px solid #000;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .selected-count {
            background: var(--blue);
            color: white;
            padding: 5px 10px;
            border: 2px solid #000;
            font-weight: 800;
        }

        .bulk-btn {
            padding: 8px 15px;
            border: 3px solid #000;
            font-weight: 800;
            cursor: pointer;
            background: var(--green);
            color: white;
        }

        .bulk-reject {
            background: var(--red);
        }

        .requests-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .request-card {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: transform 0.2s ease;
        }

        .request-card:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 #000;
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .teacher-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .teacher-name {
            font-size: 20px;
            font-weight: 900;
            background: var(--yellow);
            padding: 5px 15px;
            border: 2px solid #000;
        }

        .employee-id {
            background: var(--blue);
            color: white;
            padding: 5px 10px;
            border: 2px solid #000;
            font-size: 12px;
        }

        .request-date {
            background: var(--orange);
            color: white;
            padding: 5px 10px;
            border: 2px solid #000;
            font-weight: 800;
        }

        .status-badge {
            padding: 5px 15px;
            border: 2px solid #000;
            font-weight: 800;
            font-size: 14px;
        }

        .status-pending {
            background: var(--orange);
            color: white;
        }

        .status-approved {
            background: var(--green);
            color: white;
        }

        .status-rejected {
            background: var(--red);
            color: white;
        }

        .current-period {
            background: #e3f2fd;
            border: 3px solid #000;
            padding: 15px;
            margin-bottom: 15px;
        }

        .current-period h4 {
            margin-bottom: 10px;
            color: var(--blue);
        }

        .period-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }

        .detail-item {
            padding: 5px;
            border: 1px solid #000;
        }

        .detail-label {
            font-weight: 800;
            background: #eee;
            padding: 2px 5px;
            font-size: 11px;
        }

        .requested-change {
            background: #fff3e0;
            border: 3px solid #000;
            padding: 15px;
            margin-bottom: 15px;
        }

        .requested-change h4 {
            margin-bottom: 10px;
            color: var(--orange);
        }

        .change-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }

        .reason-box {
            background: #f9f9f9;
            border: 2px solid #000;
            padding: 15px;
            margin-bottom: 15px;
        }

        .admin-comment-box {
            margin-top: 10px;
            padding: 10px;
            background: #e8f5e9;
            border: 2px solid #000;
        }

        .admin-comment-box textarea {
            width: 100%;
            padding: 8px;
            border: 2px solid #000;
            margin-top: 5px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .approve-btn, .reject-btn {
            padding: 8px 20px;
            border: 3px solid #000;
            font-weight: 800;
            cursor: pointer;
            font-size: 14px;
        }

        .approve-btn {
            background: var(--green);
            color: white;
        }

        .reject-btn {
            background: var(--red);
            color: white;
        }

        .checkbox-col {
            width: 40px;
            text-align: center;
        }

        .checkbox-col input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .empty-state {
            text-align: center;
            padding: 50px;
            background: white;
            border: 4px solid #000;
            box-shadow: var(--shadow);
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border: 1px solid #000;
            font-size: 11px;
            font-weight: 800;
        }

        .badge-subject {
            background: var(--blue);
            color: white;
        }

        .error-box, .success-box {
            margin-bottom: 20px;
            padding: 12px;
            border: 3px solid #000;
            font-weight: 800;
        }

        .error-box {
            background: var(--red);
            color: white;
        }

        .success-box {
            background: var(--green);
            color: white;
        }

        @media (max-width: 900px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .request-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>
                <span class="logo-shapes">
                    <span class="circle"></span>
                    <span class="square"></span>
                    <span class="triangle"></span>
                </span>
                ADMIN PANEL
            </h2>
        </div>

        <div class="admin-info">
            <div class="admin-name">👤 <?php echo htmlspecialchars($full_name); ?></div>
            <span class="admin-role">⚙️ ADMINISTRATOR</span>
        </div>

        <div class="nav-menu">
            <div class="nav-section">
                <div class="nav-section-title">MAIN</div>
                <a href="dashboard.php" class="nav-item">
                    <span class="icon">📊</span>
                    Dashboard
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">MANAGEMENT</div>
                <a href="teachers.php" class="nav-item">
                    <span class="icon">👨‍🏫</span>
                    Teachers
                </a>
                <a href="students.php" class="nav-item">
                    <span class="icon">🎓</span>
                    Students
                </a>
                <a href="classes.php" class="nav-item">
                    <span class="icon">🏫</span>
                    Classes
                </a>
                <a href="subjects.php" class="nav-item">
                    <span class="icon">📚</span>
                    Subjects
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">ADD NEW</div>
                <a href="add_teacher.php" class="nav-item yellow">
                    <span class="icon">➕</span>
                    Add Teacher
                </a>
                <a href="add_student.php" class="nav-item yellow">
                    <span class="icon">➕</span>
                    Add Student
                </a>
                <a href="add_class.php" class="nav-item yellow">
                    <span class="icon">➕</span>
                    Add Class
                </a>
                <a href="add_subject.php" class="nav-item yellow">
                    <span class="icon">➕</span>
                    Add Subject
                </a>
                <a href="add_time_slot.php" class="nav-item yellow">
                    <span class="icon">➕</span>
                    Add Time Slot
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">REQUESTS</div>
                <a href="leave_requests.php" class="nav-item red">
                    <span class="icon">✈️</span>
                    Leave Requests
                    <?php if($pending_leaves > 0): ?>
                        <span class="badge"><?php echo $pending_leaves; ?></span>
                    <?php endif; ?>
                </a>
                <a href="modify_requests.php" class="nav-item red active">
                    <span class="icon">🔄</span>
                    Modify Requests
                    <?php if($pending_modifies > 0): ?>
                        <span class="badge"><?php echo $pending_modifies; ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">TIMETABLE</div>
                <a href="view_timetable.php" class="nav-item">
                    <span class="icon">👁️</span>
                    View Timetable
                </a>
                <a href="generate_timetable.php" class="nav-item">
                    <span class="icon">⚡</span>
                    Generate Timetable
                </a>
                <a href="lock_timetable.php" class="nav-item">
                    <span class="icon">🔒</span>
                    Lock Timetable
                </a>
                <!-- <a href="allocations.php" class="nav-item">
                    <span class="icon">📊</span>
                    Teacher Allocations
                </a> -->
            </div>

            <!-- <div class="nav-section">
                <div class="nav-section-title">SETTINGS</div>
                <a href="time_slots.php" class="nav-item">
                    <span class="icon">⏰</span>
                    Time Slots
                </a>
                <a href="profile.php" class="nav-item">
                    <span class="icon">⚙️</span>
                    Profile Settings
                </a>
            </div> -->
        </div>

        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn">🚪 LOGOUT</a>
        </div>
    </div>
    <div class="main-content">
        <div class="content-header">
            <h1>🔄 TIMETABLE MODIFY REQUESTS</h1>
            <div class="date-display">
                <?php echo date('l, d M Y'); ?>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <h3>⏳ PENDING</h3>
                <div class="stat-number stat-pending"><?php echo $stats['pending'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>✅ APPROVED</h3>
                <div class="stat-number stat-approved"><?php echo $stats['approved'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>❌ REJECTED</h3>
                <div class="stat-number stat-rejected"><?php echo $stats['rejected'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>📊 TOTAL</h3>
                <div class="stat-number stat-total"><?php echo $stats['total'] ?? 0; ?></div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="get" action="" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>📊 STATUS</label>
                        <select name="status" onchange="this.form.submit()">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Requests</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>👨‍🏫 TEACHER</label>
                        <select name="teacher_id" onchange="this.form.submit()">
                            <option value="0">All Teachers</option>
                            <?php 
                            mysqli_data_seek($all_teachers, 0);
                            while($teacher = mysqli_fetch_assoc($all_teachers)): 
                            ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo $teacher_filter == $teacher['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['full_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>📅 REQUEST DATE</label>
                        <input type="date" name="date" value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
                    </div>
                    <div class="filter-actions">
                        <a href="modify_requests.php" class="reset-btn">RESET</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions" id="bulkActions" style="display: none;">
            <div class="selected-count" id="selectedCount">0 selected</div>
            <button onclick="bulkAction('approve')" class="bulk-btn">✅ Approve Selected</button>
            <button onclick="bulkAction('reject')" class="bulk-btn bulk-reject">❌ Reject Selected</button>
        </div>

        <?php if ($error): ?>
            <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-box"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Modify Requests List -->
        <div class="requests-container">
            <?php if (mysqli_num_rows($requests) > 0): ?>
                <?php while($req = mysqli_fetch_assoc($requests)): 
                    $requested_change = json_decode($req['requested_change'], true);
                ?>
                    <div class="request-card" data-status="<?php echo $req['status']; ?>">
                        <div class="request-header">
                            <div class="teacher-info">
                                <input type="checkbox" class="request-checkbox" value="<?php echo $req['id']; ?>" style="margin-right: 10px;">
                                <span class="teacher-name">👨‍🏫 <?php echo htmlspecialchars($req['teacher_name']); ?></span>
                                <span class="employee-id">ID: <?php echo htmlspecialchars($req['employee_id']); ?></span>
                            </div>
                            <div>
                                <span class="request-date">📅 <?php echo date('d M Y, h:i A', strtotime($req['requested_at'])); ?></span>
                                <span class="status-badge status-<?php echo $req['status']; ?>">
                                    <?php echo strtoupper($req['status']); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Current Period Information -->
                        <div class="current-period">
                            <h4>📋 CURRENT PERIOD DETAILS</h4>
                            <div class="period-details">
                                <div class="detail-item">
                                    <span class="detail-label">📚 Class</span>
                                    <div><?php echo htmlspecialchars($req['class_name'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">📅 Day</span>
                                    <div><?php echo $day_names[$req['day_of_week']] ?? 'N/A'; ?></div>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">⏰ Time Slot</span>
                                    <div>Slot <?php echo $req['slot_number']; ?>: <?php echo date('h:i A', strtotime($req['start_time'])); ?> - <?php echo date('h:i A', strtotime($req['end_time'])); ?></div>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">📖 Subject</span>
                                    <div>
                                        <?php if($req['subject_code']): ?>
                                            <span class="badge badge-subject"><?php echo htmlspecialchars($req['subject_code']); ?></span>
                                            <?php echo htmlspecialchars($req['subject_name']); ?>
                                        <?php else: ?>
                                            <span class="badge">EMPTY PERIOD</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Requested Change -->
                        <div class="requested-change">
                            <h4>🔄 REQUESTED CHANGE</h4>
                            <div class="change-details">
                                <?php if(isset($requested_change['subject_id']) && $requested_change['subject_id']): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">📖 New Subject</span>
                                        <div>
                                            <?php 
                                            $new_subject = mysqli_fetch_assoc(mysqli_query($conn, "SELECT subject_code, subject_name FROM subjects WHERE id = " . $requested_change['subject_id']));
                                            if($new_subject):
                                            ?>
                                                <span class="badge badge-subject"><?php echo htmlspecialchars($new_subject['subject_code']); ?></span>
                                                <?php echo htmlspecialchars($new_subject['subject_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php elseif(isset($requested_change['action']) && $requested_change['action'] == 'remove'): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">🔴 Action</span>
                                        <div>Remove Subject (Make Empty)</div>
                                    </div>
                                <?php else: ?>
                                    <div class="detail-item">
                                        <span class="detail-label">📝 Custom Request</span>
                                        <div><?php echo htmlspecialchars($req['requested_change']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Reason -->
                        <div class="reason-box">
                            <strong>📝 REASON FOR MODIFICATION:</strong><br>
                            <?php echo nl2br(htmlspecialchars($req['reason'])); ?>
                        </div>

                        <?php if ($req['status'] == 'pending'): ?>
                            <form method="post" action="">
                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                
                                <div class="admin-comment-box">
                                    <label><strong>💬 Admin Comment (Optional):</strong></label>
                                    <textarea name="admin_comment" rows="2" placeholder="Add any remarks or comments..."></textarea>
                                </div>
                                
                                <div class="action-buttons">
                                    <button type="submit" name="action" value="approve" class="approve-btn" onclick="return confirm('Approve this modification request? This will update the timetable.')">
                                        ✅ APPROVE & UPDATE
                                    </button>
                                    <button type="submit" name="action" value="reject" class="reject-btn" onclick="return confirm('Reject this modification request?')">
                                        ❌ REJECT
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>📭 No timetable modify requests found</p>
                    <a href="view_timetable.php" class="btn-primary" style="padding: 10px 20px; text-decoration: none;">View Timetable</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let selectedRequests = [];

        function updateSelectedCount() {
            selectedRequests = [];
            document.querySelectorAll('.request-checkbox:checked').forEach(cb => {
                selectedRequests.push(cb.value);
            });
            
            let countSpan = document.getElementById('selectedCount');
            let bulkDiv = document.getElementById('bulkActions');
            
            if(selectedRequests.length > 0) {
                countSpan.innerText = selectedRequests.length + ' selected';
                bulkDiv.style.display = 'flex';
            } else {
                bulkDiv.style.display = 'none';
            }
        }

        document.querySelectorAll('.request-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });

        function bulkAction(action) {
            if(selectedRequests.length === 0) {
                alert('Please select at least one request');
                return;
            }
            
            let confirmMsg = action === 'approve' 
                ? 'Approve ' + selectedRequests.length + ' modification requests? This will update the timetable.' 
                : 'Reject ' + selectedRequests.length + ' modification requests?';
            
            if(confirm(confirmMsg)) {
                let form = document.createElement('form');
                form.method = 'POST';
                
                let actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'bulk_action';
                actionInput.value = action;
                form.appendChild(actionInput);
                
                let idsInput = document.createElement('input');
                idsInput.type = 'hidden';
                idsInput.name = 'selected_ids';
                idsInput.value = selectedRequests.join(',');
                form.appendChild(idsInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        document.querySelectorAll('.request-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });
    </script>
</body>
</html>