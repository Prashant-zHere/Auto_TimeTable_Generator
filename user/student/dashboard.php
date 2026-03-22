<?php
session_start();
require_once '../../include/conn/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit;
}

$student_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

$student_query = mysqli_query($conn, "
    SELECT s.*, c.class_name, c.semester, c.section
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.user_id = $student_id
");

$student = mysqli_fetch_assoc($student_query);

if (!$student) {
    $error = "Student information not found. Please contact administrator.";
}

$class_id = $student['class_id'] ?? 0;
$class_name = $student['class_name'] ?? 'Not Assigned';
$semester = $student['semester'] ?? 'N/A';
$section = $student['section'] ?? 'N/A';
$student_id_num = $student['student_id'] ?? 'N/A';
$roll_number = $student['roll_number'] ?? 'N/A';

$timetable_data = [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$time_slots = [];

if ($class_id > 0) {
    $slots_query = mysqli_query($conn, "
        SELECT * FROM time_slots 
        WHERE class_id = $class_id 
        ORDER BY slot_number
    ");
    
    while($slot = mysqli_fetch_assoc($slots_query)) {
        $time_slots[$slot['id']] = $slot;
    }
    
    $timetable_query = mysqli_query($conn, "
        SELECT 
            t.*, 
            s.subject_name, s.subject_code, s.subject_type,
            u.full_name as teacher_name,
            ts.start_time, ts.end_time, ts.slot_number, ts.is_break as slot_is_break,
            ts.day_type
        FROM timetable t
        LEFT JOIN subjects s ON t.subject_id = s.id
        LEFT JOIN teachers tea ON t.teacher_id = tea.id
        LEFT JOIN users u ON tea.user_id = u.id
        JOIN time_slots ts ON t.slot_id = ts.id
        WHERE t.class_id = $class_id 
        AND t.is_locked = 1
        ORDER BY t.day_of_week, ts.slot_number
    ");
    
    while($row = mysqli_fetch_assoc($timetable_query)) {
        $day = $row['day_of_week'];
        $slot_id = $row['slot_id'];
        $timetable_data[$day][$slot_id] = $row;
    }
}

$class_info = null;
if ($class_id > 0) {
    $class_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM classes WHERE id = $class_id"));
}

$is_timetable_locked = false;
if ($class_id > 0) {
    $lock_check = mysqli_query($conn, "SELECT COUNT(*) as locked_count FROM timetable WHERE class_id = $class_id AND is_locked = 1");
    if ($lock_check) {
        $lock_data = mysqli_fetch_assoc($lock_check);
        $is_timetable_locked = $lock_data['locked_count'] > 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard · College Timetable</title>
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

        .student-info {
            padding: 20px;
            background: #333;
            border-bottom: 4px solid #000;
            color: white;
        }

        .student-name {
            font-weight: 900;
            font-size: 18px;
            margin-bottom: 5px;
        }

        .student-role {
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

        /* Student Info Card */
        .student-card {
            background: white;
            border: 4px solid #000;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .student-details h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .student-badge {
            background: var(--blue);
            color: white;
            padding: 5px 10px;
            border: 2px solid #000;
            display: inline-block;
            margin-right: 10px;
        }

        .class-info {
            background: var(--green);
            color: white;
            padding: 5px 10px;
            border: 2px solid #000;
            display: inline-block;
        }

        /* Timetable Container */
        .timetable-container {
            background: white;
            border: 4px solid #000;
            padding: 25px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }

        .timetable-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .class-title {
            font-size: 24px;
            font-weight: 900;
            background: var(--yellow);
            padding: 10px 20px;
            border: 3px solid #000;
            display: inline-block;
        }

        .lock-status {
            padding: 8px 15px;
            border: 3px solid #000;
            font-weight: 800;
        }

        .status-locked {
            background: var(--green);
            color: white;
        }

        .status-unlocked {
            background: var(--red);
            color: white;
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

        /* Timetable Table */
        .timetable-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .timetable-table th {
            background: var(--blue);
            color: white;
            border: 3px solid #000;
            padding: 12px;
            font-weight: 900;
            text-align: center;
        }

        .timetable-table td {
            border: 3px solid #000;
            padding: 10px;
            vertical-align: top;
            height: 100px;
            min-width: 120px;
        }

        .time-slot {
            background: var(--yellow);
            padding: 5px;
            font-weight: 800;
            text-align: center;
            border-bottom: 2px solid #000;
            margin-bottom: 5px;
        }

        .break-slot-row {
            background: #fff0f0;
        }

        .break-slot-row .time-slot {
            background: var(--orange);
            color: white;
        }

        .break-display {
            text-align: center;
            padding: 10px;
        }

        .break-icon {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .break-label {
            font-weight: 900;
            color: var(--red);
            font-size: 16px;
        }

        .subject-info {
            font-weight: 900;
            font-size: 14px;
        }

        .subject-type {
            display: inline-block;
            padding: 2px 5px;
            border: 1px solid #000;
            font-size: 10px;
            margin-top: 3px;
        }

        .type-theory { background: var(--blue); color: white; }
        .type-lab { background: var(--green); color: white; }
        .type-practical { background: var(--orange); color: white; }

        .teacher-info {
            font-size: 11px;
            color: var(--blue);
            margin-top: 3px;
        }

        .empty-cell {
            background: #f0f0f0;
            color: #999;
            text-align: center;
            padding: 15px;
        }

        .empty-cell:before {
            content: "⚡";
            font-size: 20px;
            opacity: 0.5;
        }

        .legend {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            padding: 15px;
            background: var(--light-gray);
            border: 3px solid #000;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border: 2px solid #000;
        }

        .legend-color.theory { background: var(--blue); }
        .legend-color.lab { background: var(--green); }
        .legend-color.practical { background: var(--orange); }
        .legend-color.break { background: #fff0f0; border: 2px dashed var(--red); }

        @media (max-width: 900px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .student-card {
                flex-direction: column;
                text-align: center;
            }
        }

        @media print {
            .sidebar, .content-header, .legend, footer {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            
            .timetable-container {
                box-shadow: none;
                border: 2px solid #000;
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
                STUDENT PORTAL
            </h2>
        </div>

        <div class="student-info">
            <div class="student-name">👤 <?php echo htmlspecialchars($full_name); ?></div>
            <span class="student-role">🎓 STUDENT</span>
        </div>

        <div class="nav-menu">
            <div class="nav-section">
                <div class="nav-section-title">MAIN</div>
                <a href="dashboard.php" class="nav-item active">
                    <span class="icon">📅</span>
                    My Timetable
                </a>
            </div>
        </div>

        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn">🚪 LOGOUT</a>
        </div>
    </div>

    <div class="main-content">
        <div class="content-header">
            <h1>📅 MY TIMETABLE</h1>
            <div class="date-display">
                <?php echo date('l, d M Y'); ?>
            </div>
        </div>

        <div class="student-card">
            <div class="student-details">
                <h2><?php echo htmlspecialchars($full_name); ?></h2>
                <span class="student-badge">Student ID: <?php echo htmlspecialchars($student_id_num); ?></span>
                <span class="student-badge">Roll No: <?php echo htmlspecialchars($roll_number); ?></span>
            </div>
            <div class="class-info">
                <strong>📚 Class:</strong> <?php echo htmlspecialchars($class_name); ?> | 
                <strong>Semester:</strong> <?php echo $semester; ?> | 
                <strong>Section:</strong> <?php echo htmlspecialchars($section); ?>
            </div>
        </div>

        <?php if ($class_id == 0): ?>
            <div class="warning-box">
                ⚠️ You are not assigned to any class yet. Please contact your administrator.
            </div>
        <?php elseif (!$is_timetable_locked): ?>
            <div class="warning-box">
                🔒 Timetable is not yet locked. Please check back later when the timetable is finalized by your administrator.
            </div>
        <?php elseif (empty($timetable_data)): ?>
            <div class="warning-box">
                📋 No timetable has been generated for your class yet. Please check back later.
            </div>
        <?php else: ?>
            <div class="timetable-container">
                <div class="timetable-header">
                    <span class="class-title">
                        📚 <?php echo htmlspecialchars($class_name); ?> Timetable (<?php echo $semester; ?>th Semester)
                    </span>
                    <span class="lock-status status-locked">
                        🔒 LOCKED - FINAL VERSION
                    </span>
                </div>

                <table class="timetable-table">
                    <thead>
                        <tr>
                            <th>Time / Day</th>
                            <?php foreach($days as $index => $day): ?>
                                <th><?php echo $day; ?></th>
                            <?php endforeach; ?>
                        </thead>
                    <tbody>
                        <?php foreach($time_slots as $slot_id => $slot): ?>
                            <tr class="<?php echo $slot['is_break'] ? 'break-slot-row' : ''; ?>">
                                <td class="time-slot">
                                    Slot <?php echo $slot['slot_number']; ?>
                                    <?php if($slot['is_break']): ?>
                                        <span style="display: block; font-size: 12px;">🍽️ BREAK</span>
                                    <?php endif; ?>
                                    <small><?php echo date('h:i A', strtotime($slot['start_time'])); ?> - <?php echo date('h:i A', strtotime($slot['end_time'])); ?></small>
                                </td>
                                <?php for($day = 1; $day <= 6; $day++): ?>
                                    <td>
                                        <?php if(isset($timetable_data[$day][$slot_id])): 
                                            $period = $timetable_data[$day][$slot_id];
                                            if($slot['is_break']): 
                                        ?>
                                            <div class="break-display">
                                                <div class="break-icon">🍽️</div>
                                                <div class="break-label">BREAK</div>
                                            </div>
                                        <?php elseif($period['subject_id']): ?>
                                            <div class="subject-info">
                                                <?php echo htmlspecialchars($period['subject_code']); ?><br>
                                                <?php echo htmlspecialchars($period['subject_name']); ?>
                                                <?php if(isset($period['subject_type'])): ?>
                                                    <span class="subject-type type-<?php echo strtolower($period['subject_type']); ?>">
                                                        <?php echo $period['subject_type']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="teacher-info">
                                                👨‍🏫 <?php echo htmlspecialchars($period['teacher_name'] ?? 'Not Assigned'); ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="empty-cell">FREE PERIOD</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="empty-cell">FREE PERIOD</div>
                                    <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color theory"></div>
                        <span>Theory</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color lab"></div>
                        <span>Lab</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color practical"></div>
                        <span>Practical</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color break"></div>
                        <span>Break Period</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>