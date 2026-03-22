<?php
session_start();
require_once '../../include/conn/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../index.php');
    exit;
}

$teacher_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

$teacher_query = mysqli_query($conn, "
    SELECT t.*, u.email, u.username 
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    WHERE t.user_id = $teacher_id
");

$teacher = mysqli_fetch_assoc($teacher_query);
$teacher_db_id = $teacher['id'] ?? 0;
$employee_id = $teacher['employee_id'] ?? 'N/A';
$department = $teacher['department'] ?? 'N/A';

$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where_clauses = ["mr.teacher_id = $teacher_db_id"];
if ($status_filter != 'all') {
    $where_clauses[] = "mr.status = '$status_filter'";
}
if ($date_from) {
    $where_clauses[] = "DATE(mr.requested_at) >= '$date_from'";
}
if ($date_to) {
    $where_clauses[] = "DATE(mr.requested_at) <= '$date_to'";
}
$where_sql = "WHERE " . implode(" AND ", $where_clauses);

$modify_requests = mysqli_query($conn, "
    SELECT mr.*, t.day_of_week, ts.slot_number, ts.start_time, ts.end_time,
           s.subject_name, s.subject_code, c.class_name,
           pu.full_name as processed_by_name
    FROM modify_requests mr
    JOIN timetable t ON mr.timetable_id = t.id
    JOIN time_slots ts ON t.slot_id = ts.id
    LEFT JOIN subjects s ON t.subject_id = s.id
    LEFT JOIN classes c ON t.class_id = c.id
    LEFT JOIN users pu ON mr.processed_by = pu.id
    $where_sql
    ORDER BY mr.requested_at DESC
");

$stats_query = mysqli_query($conn, "
    SELECT 
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        COUNT(*) as total
    FROM modify_requests
    WHERE teacher_id = $teacher_db_id
");
$stats = mysqli_fetch_assoc($stats_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modification History · Teacher</title>
    <link rel="stylesheet" href="../../include/css/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { display: flex; min-height: 100vh; background: #fafafa; }
        .sidebar { width: 280px; background: #000; border-right: 4px solid #000; display: flex; flex-direction: column; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; border-bottom: 4px solid #000; background: var(--blue); }
        .sidebar-header h2 { color: white; font-size: 24px; font-weight: 900; display: flex; align-items: center; gap: 10px; }
        .logo-shapes { display: flex; gap: 5px; align-items: center; }
        .logo-shapes .circle { width: 16px; height: 16px; background: var(--yellow); border-radius: 50%; border: 2px solid black; }
        .logo-shapes .square { width: 14px; height: 14px; background: var(--red); border: 2px solid black; }
        .logo-shapes .triangle { width: 0; height: 0; border-left: 8px solid transparent; border-right: 8px solid transparent; border-bottom: 16px solid var(--yellow); }
        .teacher-info { padding: 20px; background: #333; border-bottom: 4px solid #000; color: white; }
        .teacher-name { font-weight: 900; font-size: 18px; margin-bottom: 5px; }
        .teacher-role { background: var(--yellow); color: black; padding: 3px 8px; display: inline-block; border: 2px solid #000; font-weight: 800; font-size: 12px; }
        .nav-menu { flex: 1; padding: 20px 0; }
        .nav-section { margin-bottom: 25px; }
        .nav-section-title { color: #ccc; font-weight: 800; font-size: 12px; padding: 0 20px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: white; text-decoration: none; font-weight: 700; border-left: 4px solid transparent; transition: all 0.2s ease; }
        .nav-item:hover { background: #333; border-left-color: var(--yellow); }
        .nav-item.active { background: #222; border-left-color: var(--red); }
        .sidebar-footer { padding: 20px; border-top: 4px solid #000; background: #1a1a1a; }
        .logout-btn { display: block; background: var(--red); color: white; text-decoration: none; padding: 12px; text-align: center; font-weight: 900; border: 3px solid #000; box-shadow: 3px 3px 0 #000; transition: all 0.1s ease; }
        .logout-btn:hover { transform: translate(-2px, -2px); box-shadow: 5px 5px 0 #000; }
        .main-content { flex: 1; margin-left: 280px; padding: 30px; background: #fafafa; }
        .content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding: 20px; background: var(--yellow); border: 4px solid #000; box-shadow: var(--shadow); }
        .content-header h1 { font-size: 28px; font-weight: 900; }
        .teacher-card { background: white; border: 4px solid #000; padding: 20px; margin-bottom: 30px; box-shadow: var(--shadow); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
        .teacher-details h2 { font-size: 24px; margin-bottom: 10px; }
        .teacher-badge { background: var(--blue); color: white; padding: 5px 10px; border: 2px solid #000; display: inline-block; margin-right: 10px; }
        .stats-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border: 4px solid #000; padding: 20px; text-align: center; box-shadow: var(--shadow); }
        .stat-card h3 { font-size: 14px; color: #666; margin-bottom: 10px; }
        .stat-number { font-size: 32px; font-weight: 900; }
        .stat-pending { color: var(--orange); }
        .stat-approved { color: var(--green); }
        .stat-rejected { color: var(--red); }
        .stat-total { color: var(--blue); }
        .filter-bar { background: white; border: 4px solid #000; padding: 20px; margin-bottom: 30px; display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { font-weight: 800; background: var(--blue); color: white; padding: 3px 8px; border: 2px solid #000; display: inline-block; margin-bottom: 5px; font-size: 12px; }
        .filter-group select, .filter-group input { width: 100%; padding: 10px; border: 3px solid #000; }
        .filter-btn, .reset-btn { padding: 10px 20px; border: 3px solid #000; font-weight: 800; cursor: pointer; text-decoration: none; display: inline-block; }
        .filter-btn { background: var(--blue); color: white; }
        .reset-btn { background: var(--red); color: white; }
        .request-table { width: 100%; border-collapse: collapse; background: white; border: 4px solid #000; }
        .request-table th { background: var(--blue); color: white; border: 2px solid #000; padding: 12px; text-align: left; }
        .request-table td { border: 2px solid #000; padding: 10px; }
        .request-table tr:nth-child(even) { background: #f9f9f9; }
        .status-badge { display: inline-block; padding: 3px 10px; border: 2px solid #000; font-weight: 800; font-size: 12px; }
        .status-pending { background: var(--orange); color: white; }
        .status-approved { background: var(--green); color: white; }
        .status-rejected { background: var(--red); color: white; }
        .empty-state { text-align: center; padding: 50px; background: white; border: 4px solid #000; }
        @media (max-width: 900px) { .main-content { margin-left: 0; padding: 20px; } .stats-cards { grid-template-columns: repeat(2, 1fr); } .request-table { display: block; overflow-x: auto; } }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h2><span class="logo-shapes"><span class="circle"></span><span class="square"></span><span class="triangle"></span></span>TEACHER PORTAL</h2></div>
        <div class="teacher-info"><div class="teacher-name">👨‍🏫 <?php echo htmlspecialchars($full_name); ?></div><span class="teacher-role">🍎 TEACHER</span><div style="margin-top: 10px; font-size: 12px;"><div>ID: <?php echo htmlspecialchars($employee_id); ?></div><div>Dept: <?php echo htmlspecialchars($department); ?></div></div></div>
        <div class="nav-menu">
            <div class="nav-section"><div class="nav-section-title">MAIN</div><a href="dashboard.php" class="nav-item">📊 Dashboard</a></div>
            <div class="nav-section"><div class="nav-section-title">VIEW</div><a href="timetable.php?view=teacher" class="nav-item">📅 My Timetable</a><a href="timetable.php?view=class" class="nav-item">🏫 Class Timetable</a></div>
            <div class="nav-section"><div class="nav-section-title">REQUESTS</div><a href="leave_request.php" class="nav-item">✈️ Apply for Leave</a><a href="modify_request.php" class="nav-item">🔄 Request Modification</a></div>
            <div class="nav-section"><div class="nav-section-title">HISTORY</div><a href="leave_history.php" class="nav-item">📋 Leave History</a><a href="modify_history.php" class="nav-item active">📋 Modification History</a></div>
        </div>
        <div class="sidebar-footer"><a href="../logout.php" class="logout-btn">🚪 LOGOUT</a></div>
    </div>

    <div class="main-content">
        <div class="content-header"><h1>📋 MODIFICATION REQUEST HISTORY</h1><div class="date-display"><?php echo date('l, d M Y'); ?></div></div>
        <div class="teacher-card"><div class="teacher-details"><h2><?php echo htmlspecialchars($full_name); ?></h2><span class="teacher-badge">Employee ID: <?php echo htmlspecialchars($employee_id); ?></span><span class="teacher-badge">Department: <?php echo htmlspecialchars($department); ?></span></div></div>

        <div class="stats-cards">
            <div class="stat-card"><h3>⏳ PENDING</h3><div class="stat-number stat-pending"><?php echo $stats['pending']; ?></div></div>
            <div class="stat-card"><h3>✅ APPROVED</h3><div class="stat-number stat-approved"><?php echo $stats['approved']; ?></div></div>
            <div class="stat-card"><h3>❌ REJECTED</h3><div class="stat-number stat-rejected"><?php echo $stats['rejected']; ?></div></div>
            <div class="stat-card"><h3>📊 TOTAL</h3><div class="stat-number stat-total"><?php echo $stats['total']; ?></div></div>
        </div>

        <div class="filter-bar">
            <div class="filter-group"><label>📊 STATUS</label><select id="statusFilter" onchange="applyFilters()"><option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option><option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option><option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option><option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option></select></div>
            <div class="filter-group"><label>📅 FROM DATE</label><input type="date" id="dateFrom" value="<?php echo $date_from; ?>"></div>
            <div class="filter-group"><label>📅 TO DATE</label><input type="date" id="dateTo" value="<?php echo $date_to; ?>"></div>
            <div class="filter-group"><button onclick="applyFilters()" class="filter-btn">APPLY FILTERS</button><a href="modify_history.php" class="reset-btn" style="margin-left: 10px;">RESET</a></div>
        </div>

        <table class="request-table">
            <thead><tr><th>Period</th><th>Class</th><th>Current Subject</th><th>Requested Change</th><th>Reason</th><th>Status</th><th>Requested On</th> </thead>
            <tbody>
                <?php if (mysqli_num_rows($modify_requests) > 0): while($req = mysqli_fetch_assoc($modify_requests)): 
                    $change = json_decode($req['requested_change'], true);
                ?>
                <tr>
                    <td><?php echo $days[$req['day_of_week']]; ?> | Slot <?php echo $req['slot_number']; ?><br><small><?php echo date('h:i A', strtotime($req['start_time'])); ?> - <?php echo date('h:i A', strtotime($req['end_time'])); ?></small></td>
                    <td><?php echo htmlspecialchars($req['class_name']); ?> 
                    <td><?php echo htmlspecialchars($req['subject_code']); ?> - <?php echo htmlspecialchars($req['subject_name']); ?> 
                    <td><?php echo $change && isset($change['subject_id']) ? 'Change subject' : 'Modification'; ?> 
                    <td><?php echo htmlspecialchars(substr($req['reason'], 0, 40)) . (strlen($req['reason']) > 40 ? '...' : ''); ?> 
                    <td><span class="status-badge status-<?php echo $req['status']; ?>"><?php echo strtoupper($req['status']); ?></span> 
                    <td><?php echo date('d M Y, h:i A', strtotime($req['requested_at'])); ?> 
                <?php endwhile; else: ?>
                <tr><td colspan="7" class="empty-state">No modification requests found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        function applyFilters() {
            let status = document.getElementById('statusFilter').value;
            let dateFrom = document.getElementById('dateFrom').value;
            let dateTo = document.getElementById('dateTo').value;
            let url = 'modify_history.php?status=' + status;
            if(dateFrom) url += '&date_from=' + dateFrom;
            if(dateTo) url += '&date_to=' + dateTo;
            window.location.href = url;
        }
    </script>
</body>
</html>