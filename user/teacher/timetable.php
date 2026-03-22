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

$classes = mysqli_query($conn, "SELECT id, class_name, semester, section FROM classes ORDER BY class_name");

$selected_view = isset($_GET['view']) ? $_GET['view'] : 'teacher';
$selected_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

$class_info = null;
if ($selected_class > 0) {
    $class_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM classes WHERE id = $selected_class"));
}

$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

$teacher_timetable = [];
$teacher_timetable_by_day = [];

if ($teacher_db_id > 0) {
    $teacher_timetable_query = mysqli_query($conn, "
        SELECT 
            t.*, 
            s.subject_name, s.subject_code, s.subject_type,
            c.class_name,
            ts.start_time, ts.end_time, ts.slot_number, ts.is_break as slot_is_break,
            ts.day_type, ts.id as slot_id
        FROM timetable t
        LEFT JOIN subjects s ON t.subject_id = s.id
        LEFT JOIN classes c ON t.class_id = c.id
        JOIN time_slots ts ON t.slot_id = ts.id
        WHERE t.teacher_id = $teacher_db_id
        AND t.is_locked = 1
        ORDER BY t.day_of_week, ts.slot_number
    ");
    
    while($row = mysqli_fetch_assoc($teacher_timetable_query)) {
        $day = $row['day_of_week'];
        $slot_id = $row['slot_id'];
        $teacher_timetable[] = $row;
        $teacher_timetable_by_day[$day][$slot_id] = $row;
    }
}

$class_timetables = [];
$class_time_slots = [];

if ($selected_class > 0) {
    $slots_query = mysqli_query($conn, "
        SELECT * FROM time_slots 
        WHERE class_id = $selected_class 
        ORDER BY slot_number
    ");
    
    while($slot = mysqli_fetch_assoc($slots_query)) {
        $class_time_slots[$slot['id']] = $slot;
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
        WHERE t.class_id = $selected_class 
        AND t.is_locked = 1
        ORDER BY t.day_of_week, ts.slot_number
    ");
    
    while($row = mysqli_fetch_assoc($timetable_query)) {
        $day = $row['day_of_week'];
        $slot_id = $row['slot_id'];
        $class_timetables[$day][$slot_id] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Timetable · College Timetable</title>
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

        /* View Tabs */
        .view-tabs {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            background: white;
            border: 4px solid #000;
            padding: 15px;
            box-shadow: var(--shadow);
        }

        .view-tab {
            padding: 12px 25px;
            border: 3px solid #000;
            font-weight: 800;
            text-decoration: none;
            color: #000;
            background: #f0f0f0;
            transition: all 0.1s ease;
            cursor: pointer;
        }

        .view-tab.active {
            background: var(--blue);
            color: white;
            transform: translate(-2px, -2px);
            box-shadow: 3px 3px 0 #000;
        }

        /* Class Selector */
        .class-selector {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .class-selector label {
            font-weight: 800;
            background: var(--blue);
            color: white;
            padding: 8px 15px;
            border: 2px solid #000;
        }

        .class-selector select {
            padding: 10px;
            border: 3px solid #000;
            font-weight: 600;
            min-width: 250px;
        }

        /* Teacher Info Card */
        .teacher-card {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .teacher-details h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .teacher-badge {
            background: var(--blue);
            color: white;
            padding: 5px 10px;
            border: 2px solid #000;
            display: inline-block;
            margin-right: 10px;
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
        }

        .break-slot-row {
            background: #fff0f0;
        }

        .break-display {
            text-align: center;
            padding: 10px;
        }

        .break-icon {
            font-size: 24px;
        }

        .break-label {
            font-weight: 900;
            color: var(--red);
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

        .warning-box {
            background: var(--orange);
            color: white;
            padding: 15px;
            border: 4px solid #000;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 800;
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
                <a href="dashboard.php" class="nav-item">
                    <span class="icon">📊</span> Dashboard
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">VIEW</div>
                <a href="timetable.php?view=teacher" class="nav-item <?php echo $selected_view == 'teacher' ? 'active' : ''; ?>">
                    <span class="icon">📅</span> My Timetable
                </a>
                <a href="timetable.php?view=class" class="nav-item <?php echo $selected_view == 'class' ? 'active' : ''; ?>">
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
            <h1>🍎 TEACHER TIMETABLE</h1>
            <div class="date-display">
                <?php echo date('l, d M Y'); ?>
            </div>
        </div>

        <div class="teacher-card">
            <div class="teacher-details">
                <h2><?php echo htmlspecialchars($full_name); ?></h2>
                <span class="teacher-badge">Employee ID: <?php echo htmlspecialchars($employee_id); ?></span>
                <span class="teacher-badge">Department: <?php echo htmlspecialchars($department); ?></span>
                <span class="teacher-badge">Qualification: <?php echo htmlspecialchars($qualification); ?></span>
            </div>
            <div>
                <span class="teacher-badge">📊 Experience: <?php echo $experience; ?> years</span>
                <span class="teacher-badge">⏰ Max Periods/Day: <?php echo $max_periods; ?></span>
            </div>
        </div>

        <?php if ($teacher_db_id == 0): ?>
            <div class="warning-box">
                ⚠️ Teacher information not found. Please contact administrator.
            </div>
        <?php endif; ?>

        <div class="view-tabs">
            <a href="?view=teacher" class="view-tab <?php echo $selected_view == 'teacher' ? 'active' : ''; ?>">
                👨‍🏫 MY TIMETABLE
            </a>
            <a href="?view=class&class_id=<?php echo $selected_class > 0 ? $selected_class : (mysqli_fetch_assoc($classes)['id'] ?? 0); ?>" class="view-tab <?php echo $selected_view == 'class' ? 'active' : ''; ?>">
                🏫 CLASS TIMETABLE
            </a>
        </div>

        <div id="teacher-view" style="display: <?php echo $selected_view == 'teacher' ? 'block' : 'none'; ?>;">
            <div class="timetable-container">
                <div class="timetable-header">
                    <span class="class-title">
                        👨‍🏫 My Teaching Schedule
                    </span>
                    <span class="lock-status status-locked">
                        🔒 LOCKED TIMETABLE
                    </span>
                </div>

                <?php if (empty($teacher_timetable)): ?>
                    <div class="warning-box">
                        📋 No teaching schedule found. You are not allocated to any subjects.
                    </div>
                <?php else: ?>
                    <table class="timetable-table">
                        <thead>
                            <tr>
                                <th>Time / Day</th>
                                <?php for($day = 1; $day <= 6; $day++): ?>
                                    <th><?php echo $days[$day]; ?></th>
                                <?php endfor; ?>
                            </thead>
                        <tbody>
                            <?php 
                            $all_slot_nums = [];
                            foreach($teacher_timetable as $period) {
                                $all_slot_nums[$period['slot_number']] = $period['slot_number'];
                            }
                            ksort($all_slot_nums);
                            ?>
                            <?php foreach($all_slot_nums as $slot_num): ?>
                                <?php 
                                $slot_info = null;
                                foreach($teacher_timetable as $p) {
                                    if($p['slot_number'] == $slot_num) {
                                        $slot_info = $p;
                                        break;
                                    }
                                }
                                ?>
                                <tr class="<?php echo ($slot_info && $slot_info['slot_is_break']) ? 'break-slot-row' : ''; ?>">
                                    <td class="time-slot">
                                        Slot <?php echo $slot_num; ?><br>
                                        <small>
                                            <?php 
                                            if($slot_info) {
                                                echo date('h:i A', strtotime($slot_info['start_time'])) . ' - ' . date('h:i A', strtotime($slot_info['end_time']));
                                            } else {
                                                echo '--:-- - --:--';
                                            }
                                            ?>
                                        </small>
                                    </td>
                                    <?php for($day = 1; $day <= 6; $day++): ?>
                                        <td>
                                            <?php 
                                            $found = false;
                                            foreach($teacher_timetable_by_day[$day] ?? [] as $slot_id => $period) {
                                                if($period['slot_number'] == $slot_num) {
                                                    $found = true;
                                                    if($period['slot_is_break']):
                                            ?>
                                                        <div class="break-display">
                                                            <div class="break-icon">🍽️</div>
                                                            <div class="break-label">BREAK</div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="subject-info">
                                                            <?php echo htmlspecialchars($period['subject_code']); ?><br>
                                                            <?php echo htmlspecialchars($period['subject_name']); ?>
                                                            <?php if(isset($period['subject_type'])): ?>
                                                                <span class="subject-type type-<?php echo strtolower($period['subject_type']); ?>">
                                                                    <?php echo $period['subject_type']; ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div >
                                                            🏫 <?php echo htmlspecialchars($period['class_name']); ?>
                                                        </div>
                                                    <?php endif;
                                                    break;
                                                }
                                            }
                                            if(!$found): ?>
                                                <div class="empty-cell">FREE</div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div id="class-view" style="display: <?php echo $selected_view == 'class' ? 'block' : 'none'; ?>;">
            <?php if ($selected_view == 'class'): ?>
                <div class="class-selector">
                    <label>🏫 SELECT CLASS</label>
                    <select id="classSelect" onchange="window.location.href='?view=class&class_id=' + this.value">
                        <?php 
                        mysqli_data_seek($classes, 0);
                        while($class = mysqli_fetch_assoc($classes)): 
                        ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?> (Sem <?php echo $class['semester']; ?>, Sec <?php echo $class['section']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <?php if ($selected_class > 0 && $class_info): ?>
                    <div class="timetable-container">
                        <div class="timetable-header">
                            <span class="class-title">
                                📚 <?php echo htmlspecialchars($class_info['class_name']); ?> Timetable
                            </span>
                            <span class="lock-status status-locked">
                                🔒 LOCKED TIMETABLE
                            </span>
                        </div>

                        <?php if (empty($class_timetables)): ?>
                            <div class="warning-box">
                                📋 No locked timetable available for this class yet.
                            </div>
                        <?php else: ?>
                            <table class="timetable-table">
                                <thead>
                                    <tr>
                                        <th>Time / Day</th>
                                        <?php for($day = 1; $day <= 6; $day++): ?>
                                            <th><?php echo $days[$day]; ?></th>
                                        <?php endfor; ?>
                                    </thead>
                                <tbody>
                                    <?php foreach($class_time_slots as $slot_id => $slot): ?>
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
                                                    <?php if(isset($class_timetables[$day][$slot_id])): 
                                                        $period = $class_timetables[$day][$slot_id];
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
                                                        <div class="empty-cell">FREE</div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <div class="empty-cell">FREE</div>
                                                <?php endif; ?>
                                                </td>
                                            <?php endfor; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

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

    <script>
        document.getElementById('classSelect')?.addEventListener('change', function() {
            if(this.value) {
                window.location.href = '?view=class&class_id=' + this.value;
            }
        });
    </script>
</body>
</html>