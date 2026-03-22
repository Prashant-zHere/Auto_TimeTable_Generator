<?php
session_start();
require_once '../../include/conn/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php');
    exit;
}

$full_name = $_SESSION['full_name'];

$teachers_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM teachers"))['count'];
$students_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM students"))['count'];
$classes_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM classes"))['count'];
$subjects_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM subjects"))['count'];

$pending_leaves = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as count FROM leave_requests WHERE status='pending'"
))['count'];

$pending_modifies = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as count FROM modify_requests WHERE status='pending'"
))['count'];

$recent_leaves = mysqli_query($conn, 
    "SELECT lr.*, u.full_name as teacher_name 
     FROM leave_requests lr 
     JOIN teachers t ON lr.teacher_id = t.id 
     JOIN users u ON t.user_id = u.id 
     WHERE lr.status='pending' 
     ORDER BY lr.requested_at DESC 
     LIMIT 5"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard · College Timetable</title>
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
            background: var(--black);
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

        .sidebar-header .logo-shapes {
            display: flex;
            gap: 5px;
        }

        .sidebar-header .circle {
            width: 16px;
            height: 16px;
            background: var(--yellow);
            border-radius: 50%;
            border: 2px solid black;
        }

        .sidebar-header .square {
            width: 14px;
            height: 14px;
            background: var(--red);
            border: 2px solid black;
        }

        .sidebar-header .triangle {
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
            margin: 2px 0;
        }

        .nav-item:hover {
            background: #333;
            border-left-color: var(--yellow);
        }

        .nav-item.active {
            background: #222;
            border-left-color: var(--red);
        }

        .nav-item .icon {
            width: 24px;
            text-align: center;
            font-size: 18px;
        }

        .nav-item .badge {
            background: var(--red);
            color: white;
            padding: 2px 8px;
            border-radius: 0;
            border: 2px solid #000;
            font-size: 11px;
            margin-left: auto;
            font-weight: 900;
        }

        .nav-item.yellow:hover {
            background: #4a3f1f;
        }

        .nav-item.red:hover {
            background: #4a1f1f;
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

        .date-display {
            background: var(--blue);
            color: white;
            padding: 10px 15px;
            border: 3px solid #000;
            font-weight: 800;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translate(-3px, -3px);
            box-shadow: 6px 6px 0 #000;
        }

        .stat-card h3 {
            font-size: 14px;
            font-weight: 800;
            color: #666;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 900;
            color: var(--blue);
        }

        .stat-label {
            font-size: 12px;
            font-weight: 700;
            color: #999;
        }

        /* Pending Cards */
        .pending-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .pending-card {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .pending-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .pending-header h3 {
            font-size: 18px;
            font-weight: 900;
        }

        .pending-count {
            background: var(--red);
            color: white;
            padding: 5px 12px;
            border: 3px solid #000;
            font-weight: 900;
            font-size: 20px;
        }

        .view-link {
            display: inline-block;
            margin-top: 15px;
            color: var(--blue);
            font-weight: 800;
            text-decoration: none;
            border-bottom: 2px solid var(--blue);
        }

        /* Recent Requests */
        .recent-section {
            background: #eaeaea;
            border: 4px solid #000;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
        }

        .recent-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .recent-header h2 {
            background: var(--yellow);
            padding: 10px 15px;
            border: 3px solid #000;
            display: inline-block;
            font-size: 20px;
        }

        .view-all {
            background: var(--black);
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border: 3px solid #000;
            font-weight: 800;
        }

        .request-item {
            background: white;
            border: 3px solid #000;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .status-badge {
            padding: 5px 10px;
            border: 2px solid #000;
            font-weight: 800;
        }

        .status-pending {
            background: var(--yellow);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 25px;
        }

        .quick-action-btn {
            background: var(--blue);
            color: white;
            text-decoration: none;
            padding: 15px;
            border: 3px solid #000;
            font-weight: 800;
            text-align: center;
            transition: all 0.2s ease;
        }

        .quick-action-btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: 4px 4px 0 #000;
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
                <a href="dashboard.php" class="nav-item active">
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
                <a href="modify_requests.php" class="nav-item red">
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
            <h1>DASHBOARD OVERVIEW</h1>
            <div class="date-display">
                <?php echo date('l, d M Y'); ?>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>👨‍🏫 TEACHERS</h3>
                <div class="stat-number"><?php echo $teachers_count; ?></div>
                <div class="stat-label">total faculty members</div>
            </div>
            <div class="stat-card">
                <h3>🎓 STUDENTS</h3>
                <div class="stat-number"><?php echo $students_count; ?></div>
                <div class="stat-label">enrolled students</div>
            </div>
            <div class="stat-card">
                <h3>🏫 CLASSES</h3>
                <div class="stat-number"><?php echo $classes_count; ?></div>
                <div class="stat-label">active classes</div>
            </div>
            <div class="stat-card">
                <h3>📚 SUBJECTS</h3>
                <div class="stat-number"><?php echo $subjects_count; ?></div>
                <div class="stat-label">total subjects</div>
            </div>
        </div>

        <div class="pending-grid">
            <div class="pending-card">
                <div class="pending-header">
                    <h3>✈️ LEAVE REQUESTS</h3>
                    <span class="pending-count"><?php echo $pending_leaves; ?></span>
                </div>
                <p><?php echo $pending_leaves; ?> pending leave request<?php echo $pending_leaves != 1 ? 's' : ''; ?></p>
                <a href="leave_requests.php" class="view-link">VIEW ALL →</a>
            </div>
            <div class="pending-card">
                <div class="pending-header">
                    <h3>🔄 MODIFY REQUESTS</h3>
                    <span class="pending-count"><?php echo $pending_modifies; ?></span>
                </div>
                <p><?php echo $pending_modifies; ?> pending modification request<?php echo $pending_modifies != 1 ? 's' : ''; ?></p>
                <a href="modify_requests.php" class="view-link">VIEW ALL →</a>
            </div>
        </div>

        <div class="recent-section">
            <div class="recent-header">
                <h2>📋 RECENT LEAVE REQUESTS</h2>
                <a href="leave_requests.php" class="view-all">VIEW ALL →</a>
            </div>
            
            <?php if(mysqli_num_rows($recent_leaves) > 0): ?>
                <?php while($leave = mysqli_fetch_assoc($recent_leaves)): ?>
                    <div class="request-item">
                        <div>
                            <strong><?php echo htmlspecialchars($leave['teacher_name']); ?></strong><br>
                            <small>Date: <?php echo $leave['leave_date']; ?> | Reason: <?php echo htmlspecialchars($leave['reason']); ?></small>
                        </div>
                        <span class="status-badge status-pending">PENDING</span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; padding: 20px; background: white; border: 3px solid #000;">
                    No pending leave requests
                </p>
            <?php endif; ?>
        </div>

        <h3 style="margin-bottom: 15px;">⚡ QUICK ACTIONS</h3>
        <div class="quick-actions">
            <a href="add_teacher.php" class="quick-action-btn">➕ ADD TEACHER</a>
            <a href="add_student.php" class="quick-action-btn">➕ ADD STUDENT</a>
            <a href="add_class.php" class="quick-action-btn">➕ ADD CLASS</a>
            <a href="add_subject.php" class="quick-action-btn">➕ ADD SUBJECT</a>
            <a href="generate_timetable.php" class="quick-action-btn">⚡ GENERATE</a>
            <a href="modify_requests.php" class="quick-action-btn">📊 Modify</a>
            <a href="leave_requests.php" class="quick-action-btn">✈️ LEAVES</a>
            <a href="view_timetable.php" class="quick-action-btn">👁️ VIEW</a>
        </div>
    </div>
</body>
</html>