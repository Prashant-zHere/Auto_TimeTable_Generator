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

if (!$teacher) {
    $error = "Teacher information not found. Please contact administrator.";
}

$teacher_db_id = $teacher['id'] ?? 0;
$employee_id = $teacher['employee_id'] ?? 'N/A';
$department = $teacher['department'] ?? 'N/A';
$qualification = $teacher['qualification'] ?? 'N/A';
$experience = $teacher['experience'] ?? 0;
$max_periods = $teacher['max_periods_per_day'] ?? 6;

$current_day = date('N'); 
$current_time = date('H:i:s');

$day_names = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

$today_schedule = [];
if ($teacher_db_id > 0 && $current_day <= 6) {
    $today_query = mysqli_query($conn, "
        SELECT 
            t.*, 
            s.subject_name, s.subject_code, s.subject_type,
            c.class_name,
            ts.start_time, ts.end_time, ts.slot_number, ts.is_break as slot_is_break
        FROM timetable t
        LEFT JOIN subjects s ON t.subject_id = s.id
        LEFT JOIN classes c ON t.class_id = c.id
        JOIN time_slots ts ON t.slot_id = ts.id
        WHERE t.teacher_id = $teacher_db_id
        AND t.day_of_week = $current_day
        AND t.is_locked = 1
        ORDER BY ts.slot_number
    ");
    
    while($row = mysqli_fetch_assoc($today_query)) {
        $today_schedule[] = $row;
    }
}
$current_period = null;
if ($teacher_db_id > 0 && $current_day <= 6) {
    $current_query = mysqli_query($conn, "
        SELECT 
            t.*, 
            s.subject_name, s.subject_code, s.subject_type,
            c.class_name,
            ts.start_time, ts.end_time, ts.slot_number, ts.is_break as slot_is_break
        FROM timetable t
        LEFT JOIN subjects s ON t.subject_id = s.id
        LEFT JOIN classes c ON t.class_id = c.id
        JOIN time_slots ts ON t.slot_id = ts.id
        WHERE t.teacher_id = $teacher_db_id
        AND t.day_of_week = $current_day
        AND ts.start_time <= '$current_time'
        AND ts.end_time > '$current_time'
        AND t.is_locked = 1
        LIMIT 1
    ");
    
    if (mysqli_num_rows($current_query) > 0) {
        $current_period = mysqli_fetch_assoc($current_query);
    }
}

$next_period = null;
if ($teacher_db_id > 0 && $current_day <= 6) {
    $next_query = mysqli_query($conn, "
        SELECT 
            t.*, 
            s.subject_name, s.subject_code, s.subject_type,
            c.class_name,
            ts.start_time, ts.end_time, ts.slot_number, ts.is_break as slot_is_break
        FROM timetable t
        LEFT JOIN subjects s ON t.subject_id = s.id
        LEFT JOIN classes c ON t.class_id = c.id
        JOIN time_slots ts ON t.slot_id = ts.id
        WHERE t.teacher_id = $teacher_db_id
        AND t.day_of_week = $current_day
        AND ts.start_time > '$current_time'
        AND t.is_locked = 1
        ORDER BY ts.start_time ASC
        LIMIT 1
    ");
    
    if (mysqli_num_rows($next_query) > 0) {
        $next_period = mysqli_fetch_assoc($next_query);
    }
}

$total_classes = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT class_id) as count 
    FROM timetable 
    WHERE teacher_id = $teacher_db_id AND is_locked = 1
"))['count'] ?? 0;

$total_periods = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM timetable 
    WHERE teacher_id = $teacher_db_id AND is_locked = 1
"))['count'] ?? 0;

$total_subjects = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT subject_id) as count 
    FROM timetable 
    WHERE teacher_id = $teacher_db_id AND is_locked = 1 AND subject_id IS NOT NULL
"))['count'] ?? 0;

$pending_leaves = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM leave_requests 
    WHERE teacher_id = $teacher_db_id AND status = 'pending'
"))['count'] ?? 0;

$pending_modifies = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM modify_requests 
    WHERE teacher_id = $teacher_db_id AND status = 'pending'
"))['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard · College Timetable</title>
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

        /* Sidebar */
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

        .teacher-info {
            padding: 20px;
            background: #333;
            border-bottom: 4px solid #000;
            color: white;
        }

        .teacher-name {
            font-weight: 900;
            font-size: 18px;
            margin-bottom: 5px;
        }

        .teacher-role {
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

        /* Main Content */
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

        /* Welcome Banner */
        .welcome-banner {
            background: var(--blue);
            color: white;
            border: 4px solid #000;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .welcome-banner h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        /* Stats Grid */
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
            text-align: center;
            box-shadow: var(--shadow);
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 #000;
        }

        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 900;
            color: var(--blue);
        }

        .stat-label {
            font-size: 12px;
            color: #999;
        }

        /* Current Status Card */
        .status-card {
            background: white;
            border: 4px solid #000;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .status-card h3 {
            font-size: 18px;
            margin-bottom: 15px;
            background: var(--yellow);
            display: inline-block;
            padding: 5px 15px;
            border: 2px solid #000;
        }

        .current-period {
            background: #e8f5e9;
            border: 3px solid #000;
            padding: 20px;
            margin-bottom: 15px;
        }

        .period-time {
            font-size: 20px;
            font-weight: 900;
            margin-bottom: 10px;
        }

        .period-subject {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .period-class {
            color: var(--blue);
            font-weight: 600;
        }

        .next-period {
            background: #fff3e0;
            border: 3px solid #000;
            padding: 15px;
        }

        .no-class {
            text-align: center;
            padding: 30px;
            color: #666;
        }

        /* Today's Schedule */
        .schedule-container {
            background: white;
            border: 4px solid #000;
            padding: 25px;
            box-shadow: var(--shadow);
        }

        .schedule-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .schedule-header h3 {
            font-size: 18px;
            background: var(--yellow);
            display: inline-block;
            padding: 5px 15px;
            border: 2px solid #000;
        }

        .view-all-link {
            background: var(--blue);
            color: white;
            padding: 8px 15px;
            border: 2px solid #000;
            text-decoration: none;
            font-weight: 800;
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
        }

        .schedule-table th {
            background: var(--blue);
            color: white;
            border: 2px solid #000;
            padding: 10px;
            text-align: left;
        }

        .schedule-table td {
            border: 2px solid #000;
            padding: 10px;
        }

        .schedule-table tr:nth-child(even) {
            background: #f9f9f9;
        }

        .break-row {
            background: #fff0f0 !important;
        }

        .break-row td {
            color: var(--red);
            font-weight: 800;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 30px;
        }

        .quick-btn {
            background: white;
            border: 4px solid #000;
            padding: 15px;
            text-align: center;
            text-decoration: none;
            font-weight: 800;
            color: #000;
            transition: all 0.1s ease;
            box-shadow: var(--shadow);
        }

        .quick-btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 #000;
            background: var(--yellow);
        }

        .warning-box {
            background: var(--orange);
            color: white;
            padding: 15px;
            border: 4px solid #000;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 800;
        }

        @media (max-width: 900px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .quick-actions {
                grid-template-columns: 1fr;
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
                TEACHER PORTAL
            </h2>
        </div>

        <div class="teacher-info">
            <div class="teacher-name">👨‍🏫 <?php echo htmlspecialchars($full_name); ?></div>
            <span class="teacher-role">🍎 TEACHER</span>
            <div style="margin-top: 10px; font-size: 12px;">
                <div>ID: <?php echo htmlspecialchars($employee_id); ?></div>
                <div>Dept: <?php echo htmlspecialchars($department); ?></div>
            </div>
        </div>

        <div class="nav-menu">
            <div class="nav-section">
                <div class="nav-section-title">MAIN</div>
                <a href="dashboard.php" class="nav-item active">
                    <span class="icon">📊</span> Dashboard
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">VIEW</div>
                <a href="timetable.php?view=teacher" class="nav-item">
                    <span class="icon">📅</span> My Timetable
                </a>
                <a href="timetable.php?view=class" class="nav-item">
                    <span class="icon">🏫</span> Class Timetable
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">REQUESTS</div>
                <a href="leave_request.php" class="nav-item">
                    <span class="icon">✈️</span> Apply for Leave
                </a>
                <a href="modify_request.php" class="nav-item">
                    <span class="icon">🔄</span> Request Modification
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">HISTORY</div>
                <a href="leave_history.php" class="nav-item">
                    <span class="icon">📋</span> Leave History
                </a>
                <a href="modify_history.php" class="nav-item">
                    <span class="icon">📋</span> Modification History
                </a>
            </div>
        </div>

        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn">🚪 LOGOUT</a>
        </div>
    </div>

    <div class="main-content">
        <div class="content-header">
            <h1>🍎 TEACHER DASHBOARD</h1>
            <div class="date-display">
                <?php echo date('l, d M Y'); ?>
            </div>
        </div>

        <div class="welcome-banner">
            <h2>Welcome back, <?php echo htmlspecialchars($full_name); ?>! 👋</h2>
            <p>Here's your teaching schedule and activity overview for today.</p>
        </div>

        <?php if ($teacher_db_id == 0): ?>
            <div class="warning-box">
                ⚠️ Teacher information not found. Please contact administrator.
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>🏫 CLASSES</h3>
                <div class="stat-number"><?php echo $total_classes; ?></div>
                <div class="stat-label">classes assigned</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_periods; ?></div>
                <div class="stat-label">total periods/week</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_subjects; ?></div>
                <div class="stat-label">subjects teaching</div>
            </div>
            <div class="stat-card">
                <h3>⏰ MAX PERIODS/DAY</h3>
                <div class="stat-number"><?php echo $max_periods; ?></div>
                <div class="stat-label">daily limit</div>
            </div>
        </div>

        <div class="status-card">
            <h3>🕐 CURRENT STATUS</h3>
            
            <?php if ($current_day == 7): ?>
                <div class="no-class">
                    <p>😊 Sunday - No classes scheduled. Enjoy your weekend!</p>
                </div>
            <?php elseif ($current_period): ?>
                <div class="current-period">
                    <div class="period-time">
                        ⏰ <?php echo date('h:i A', strtotime($current_period['start_time'])); ?> - <?php echo date('h:i A', strtotime($current_period['end_time'])); ?>
                    </div>
                    <div class="period-subject">
                        📖 <?php echo htmlspecialchars($current_period['subject_code']); ?> - <?php echo htmlspecialchars($current_period['subject_name']); ?>
                        <?php if(isset($current_period['subject_type'])): ?>
                            <span class="subject-type type-<?php echo strtolower($current_period['subject_type']); ?>" style="display: inline-block; padding: 2px 5px; border: 1px solid #000; font-size: 10px; margin-left: 5px;">
                                <?php echo $current_period['subject_type']; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="period-class">
                        🏫 <?php echo htmlspecialchars($current_period['class_name']); ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-class">
                    <p>😊 No class at the moment. Enjoy your break!</p>
                </div>
            <?php endif; ?>

            <?php if ($next_period): ?>
                <div class="next-period">
                    <strong>⏰ Next Period:</strong> 
                    <?php echo date('h:i A', strtotime($next_period['start_time'])); ?> - 
                    <?php echo htmlspecialchars($next_period['subject_code']); ?> | 
                    <?php echo htmlspecialchars($next_period['class_name']); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="schedule-container">
            <div class="schedule-header">
                <h3>📅 TODAY'S SCHEDULE (<?php echo $current_day == 7 ? 'Sunday' : $day_names[$current_day]; ?>)</h3>
                <a href="timetable.php?view=teacher" class="view-all-link">VIEW FULL TIMETABLE →</a>
            </div>

            <?php if ($current_day == 7): ?>
                <div class="no-class" style="padding: 20px;">
                    <p>🎉 Sunday - No classes scheduled. Have a relaxing day!</p>
                </div>
            <?php elseif (empty($today_schedule)): ?>
                <div class="no-class" style="padding: 20px;">
                    <p>No classes scheduled for today.</p>
                </div>
            <?php else: ?>
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Subject</th>
                            <th>Class</th>
                            <th>Type</th>
                        </thead>
                        <tbody>
                            <?php foreach($today_schedule as $period): ?>
                                <tr class="<?php echo $period['slot_is_break'] ? 'break-row' : ''; ?>">
                                    <td>
                                        <?php echo date('h:i A', strtotime($period['start_time'])); ?> - 
                                        <?php echo date('h:i A', strtotime($period['end_time'])); ?>
                                        <br><small>Slot <?php echo $period['slot_number']; ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($period['subject_code']); ?></strong><br>
                                        <?php echo htmlspecialchars($period['subject_name']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($period['class_name']); ?></td>
                                    <td>
                                        <?php if($period['slot_is_break']): ?>
                                            <span style="color: var(--red); font-weight: 800;">🍽️ BREAK</span>
                                        <?php elseif(isset($period['subject_type'])): ?>
                                            <span class="subject-type type-<?php echo strtolower($period['subject_type']); ?>" style="display: inline-block; padding: 2px 5px; border: 1px solid #000;">
                                                <?php echo $period['subject_type']; ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="stats-grid" style="margin-top: 30px; margin-bottom: 0;">
                <div class="stat-card">
                    <h3>✈️ LEAVE REQUESTS</h3>
                    <div class="stat-number" style="color: var(--orange);"><?php echo $pending_leaves; ?></div>
                    <div class="stat-label">pending approval</div>
                </div>
                <div class="stat-card">
                    <h3>🔄 MODIFICATION REQUESTS</h3>
                    <div class="stat-number" style="color: var(--orange);"><?php echo $pending_modifies; ?></div>
                    <div class="stat-label">pending approval</div>
                </div>
                <div class="stat-card">
                    <h3>⏱️ WORKING HOURS TODAY</h3>
                    <div class="stat-number" style="color: var(--green);">
                        <?php 
                        $total_minutes = 0;
                        if ($current_day <= 6) {
                            foreach($today_schedule as $period) {
                                if(!$period['slot_is_break']) {
                                    $start = strtotime($period['start_time']);
                                    $end = strtotime($period['end_time']);
                                    $total_minutes += ($end - $start) / 60;
                                }
                            }
                        }
                        $hours = floor($total_minutes / 60);
                        $minutes = $total_minutes % 60;
                        echo $hours . 'h ' . $minutes . 'm';
                        ?>
                    </div>
                    <div class="stat-label">teaching hours</div>
                </div>
            </div>

            <div class="quick-actions">
                <a href="leave_request.php" class="quick-btn">✈️ APPLY FOR LEAVE</a>
                <a href="modify_request.php" class="quick-btn">🔄 REQUEST MODIFICATION</a>
                <a href="timetable.php?view=teacher" class="quick-btn">📅 VIEW TIMETABLE</a>
            </div>


        </div>

        <script>
            // Auto-refresh current status every minute (optional)
            setTimeout(function() {
                location.reload();
            }, 60000);
        </script>
    </body>
    </html>