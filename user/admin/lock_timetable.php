<?php
session_start();
require_once '../../include/conn/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php');
    exit;
}

$error = '';
$success = '';
$warning = '';

$selected_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

$classes = mysqli_query($conn, "SELECT id, class_name, semester, section FROM classes ORDER BY class_name");

$class_info = null;
if ($selected_class > 0) {
    $class_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM classes WHERE id = $selected_class"));
}

$lock_status = false;
$locked_periods_count = 0;
$total_periods_count = 0;

if ($selected_class > 0) {
    $lock_check = mysqli_query($conn, "SELECT is_locked FROM timetable WHERE class_id = $selected_class LIMIT 1");
    if ($lock_check && mysqli_num_rows($lock_check) > 0) {
        $lock_row = mysqli_fetch_assoc($lock_check);
        $lock_status = $lock_row['is_locked'];
    }
    
    $periods_query = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(CASE WHEN is_locked = 1 THEN 1 ELSE 0 END) as locked FROM timetable WHERE class_id = $selected_class");
    if ($periods_query) {
        $periods_data = mysqli_fetch_assoc($periods_query);
        $total_periods_count = $periods_data['total'];
        $locked_periods_count = $periods_data['locked'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lock_all'])) {
    $class_id = intval($_POST['class_id']);
    $action = $_POST['action']; // 'lock' or 'unlock'
    
    if ($action == 'lock') {
        $update = "UPDATE timetable SET is_locked = 1 WHERE class_id = $class_id";
        if (mysqli_query($conn, $update)) {
            $success = "All periods for this class have been locked successfully!";
            $lock_status = true;
            $locked_periods_count = $total_periods_count;
        } else {
            $error = "Error locking periods: " . mysqli_error($conn);
        }
    } elseif ($action == 'unlock') {
        $update = "UPDATE timetable SET is_locked = 0 WHERE class_id = $class_id";
        if (mysqli_query($conn, $update)) {
            $success = "All periods for this class have been unlocked successfully!";
            $lock_status = false;
            $locked_periods_count = 0;
        } else {
            $error = "Error unlocking periods: " . mysqli_error($conn);
        }
    }
}

if (isset($_GET['toggle_period']) && is_numeric($_GET['toggle_period'])) {
    $timetable_id = intval($_GET['toggle_period']);
    $class_id_param = $selected_class;
    
    $current = mysqli_query($conn, "SELECT is_locked FROM timetable WHERE id = $timetable_id");
    if ($current && mysqli_num_rows($current) > 0) {
        $row = mysqli_fetch_assoc($current);
        $new_status = $row['is_locked'] ? 0 : 1;
        
        $update = "UPDATE timetable SET is_locked = $new_status WHERE id = $timetable_id";
        if (mysqli_query($conn, $update)) {
            $success = "Period status updated successfully!";
            $periods_query = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(CASE WHEN is_locked = 1 THEN 1 ELSE 0 END) as locked FROM timetable WHERE class_id = $class_id_param");
            if ($periods_query) {
                $periods_data = mysqli_fetch_assoc($periods_query);
                $locked_periods_count = $periods_data['locked'];
                $lock_status = ($locked_periods_count == $total_periods_count);
            }
        } else {
            $error = "Error updating period: " . mysqli_error($conn);
        }
    }
    header("Location: lock_timetable.php?class_id=$class_id_param");
    exit;
}

$timetable_data = [];
$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
$time_slots = [];

if ($selected_class > 0) {
    $slots_query = mysqli_query($conn, "SELECT * FROM time_slots WHERE class_id = $selected_class ORDER BY slot_number");
    while($slot = mysqli_fetch_assoc($slots_query)) {
        $time_slots[$slot['id']] = $slot;
    }
    
    $timetable_query = mysqli_query($conn, "
        SELECT t.*, s.subject_name, s.subject_code, u.full_name as teacher_name
        FROM timetable t
        LEFT JOIN subjects s ON t.subject_id = s.id
        LEFT JOIN teachers tea ON t.teacher_id = tea.id
        LEFT JOIN users u ON tea.user_id = u.id
        WHERE t.class_id = $selected_class
        ORDER BY t.day_of_week, t.slot_id
    ");
    
    while($row = mysqli_fetch_assoc($timetable_query)) {
        $timetable_data[$row['day_of_week']][$row['slot_id']] = $row;
    }
}

$all_classes_status = mysqli_query($conn, "
    SELECT 
        c.id, 
        c.class_name, 
        c.semester, 
        c.section,
        COUNT(t.id) as total_periods,
        SUM(CASE WHEN t.is_locked = 1 THEN 1 ELSE 0 END) as locked_periods
    FROM classes c
    LEFT JOIN timetable t ON c.id = t.class_id
    GROUP BY c.id
    ORDER BY c.class_name
");


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
    <title>Lock Timetable · Admin</title>
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

        /* Class Selector */
        .class-selector {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: var(--shadow);
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

        .class-selector .go-btn {
            padding: 10px 20px;
            background: var(--green);
            color: white;
            border: 3px solid #000;
            font-weight: 800;
            text-decoration: none;
        }

        /* Lock Status Cards */
        .status-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .status-card {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .status-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .status-number {
            font-size: 32px;
            font-weight: 900;
        }

        .status-locked {
            color: var(--red);
        }

        .status-unlocked {
            color: var(--green);
        }

        .status-total {
            color: var(--blue);
        }

        /* Lock/Unlock Actions */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .lock-btn, .unlock-btn {
            flex: 1;
            padding: 15px;
            border: 4px solid #000;
            font-weight: 900;
            font-size: 18px;
            cursor: pointer;
            text-align: center;
            transition: all 0.1s ease;
        }

        .lock-btn {
            background: var(--red);
            color: white;
        }

        .unlock-btn {
            background: var(--green);
            color: white;
        }

        .lock-btn:hover, .unlock-btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: 3px 3px 0 #000;
        }

        /* Timetable Table */
        .timetable-container {
            background: white;
            border: 4px solid #000;
            padding: 25px;
            box-shadow: var(--shadow);
            overflow-x: auto;
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
            margin-bottom: 5px;
        }

        .break-slot-row {
            background: #fff0f0;
        }

        .locked-row {
            background: #ffe6e6;
            position: relative;
        }

        .locked-row:before {
            content: "🔒";
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 12px;
            opacity: 0.6;
        }

        .period-cell {
            height: 100%;
            position: relative;
        }

        .lock-toggle {
            position: absolute;
            bottom: 5px;
            right: 5px;
            font-size: 12px;
            text-decoration: none;
            padding: 2px 5px;
            border: 1px solid #000;
            background: white;
            font-weight: 800;
        }

        .lock-toggle.locked {
            background: var(--red);
            color: white;
        }

        .lock-toggle.unlocked {
            background: var(--green);
            color: white;
        }

        .subject-info {
            font-weight: 900;
            font-size: 14px;
        }

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

        .legend-color.locked { background: #ffe6e6; }
        .legend-color.break { background: #fff0f0; }

        .error-box, .success-box {
            margin-bottom: 20px;
        }

        .warning-box {
            background: var(--orange);
            color: white;
            padding: 15px;
            border: 4px solid #000;
            margin-bottom: 20px;
            font-weight: 800;
        }

        @media (max-width: 900px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .status-cards {
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
                <a href="lock_timetable.php" class="nav-item active">
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
            <h1>🔒 LOCK TIMETABLE</h1>
            <div class="date-display">
                <?php echo date('l, d M Y'); ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-box"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Class Selector -->
        <div class="class-selector">
            <label>🏫 SELECT CLASS</label>
            <select id="classSelect" onchange="window.location.href='?class_id=' + this.value">
                <option value="">-- Choose Class --</option>
                <?php 
                mysqli_data_seek($classes, 0);
                while($class = mysqli_fetch_assoc($classes)): 
                ?>
                    <option value="<?php echo $class['id']; ?>" <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($class['class_name']); ?> (Sem <?php echo $class['semester']; ?>, Sec <?php echo $class['section']; ?>)
                    </option>
                <?php endwhile; ?>
            </select>
            <a href="view_timetable.php?class_id=<?php echo $selected_class; ?>" class="go-btn">👁️ VIEW TIMETABLE</a>
        </div>

        <?php if ($selected_class > 0 && $class_info): ?>
            <div class="status-cards">
                <div class="status-card">
                    <h3>📊 TOTAL PERIODS</h3>
                    <div class="status-number status-total"><?php echo $total_periods_count; ?></div>
                </div>
                <div class="status-card">
                    <h3>🔒 LOCKED PERIODS</h3>
                    <div class="status-number status-locked"><?php echo $locked_periods_count; ?></div>
                </div>
                <div class="status-card">
                    <h3>🔓 UNLOCKED PERIODS</h3>
                    <div class="status-number status-unlocked"><?php echo $total_periods_count - $locked_periods_count; ?></div>
                </div>
            </div>

            <div class="action-buttons">
                <form method="post" style="flex: 1;">
                    <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                    <input type="hidden" name="action" value="lock">
                    <button type="submit" name="lock_all" class="lock-btn" onclick="return confirm('Lock ALL periods for <?php echo htmlspecialchars($class_info['class_name']); ?>? This will prevent any further modifications.')">
                        🔒 LOCK ALL PERIODS
                    </button>
                </form>
                <form method="post" style="flex: 1;">
                    <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                    <input type="hidden" name="action" value="unlock">
                    <button type="submit" name="lock_all" class="unlock-btn" onclick="return confirm('Unlock ALL periods for <?php echo htmlspecialchars($class_info['class_name']); ?>? This will allow modifications again.')">
                        🔓 UNLOCK ALL PERIODS
                    </button>
                </form>
            </div>

            <?php if ($locked_periods_count == $total_periods_count && $total_periods_count > 0): ?>
                <div class="warning-box">
                    ⚠️ ALL periods are locked! No changes can be made to this timetable.
                </div>
            <?php endif; ?>

            <div class="timetable-container">
                <h3 style="margin-bottom: 20px;">📅 Click the lock icon on any period to toggle lock status</h3>
                
                <table class="timetable-table">
                    <thead>
                        <tr>
                            <th>Time / Day</th>
                            <?php for($day = 1; $day <= 6; $day++): ?>
                                <th><?php echo $days[$day]; ?></th>
                            <?php endfor; ?>
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
                                    <td class="period-cell <?php echo (isset($timetable_data[$day][$slot_id]) && $timetable_data[$day][$slot_id]['is_locked']) ? 'locked-row' : ''; ?>">
                                        <?php if($slot['is_break']): ?>
                                            <div class="break-display">
                                                <div class="break-icon">🍽️</div>
                                                <div class="break-label">BREAK</div>
                                            </div>
                                        <?php elseif(isset($timetable_data[$day][$slot_id])): 
                                            $period = $timetable_data[$day][$slot_id];
                                        ?>
                                            <?php if($period['subject_id']): ?>
                                                <div class="subject-info">
                                                    <?php echo htmlspecialchars($period['subject_code']); ?><br>
                                                    <?php echo htmlspecialchars($period['subject_name']); ?>
                                                </div>
                                                <div class="teacher-info">
                                                    👨‍🏫 <?php echo htmlspecialchars($period['teacher_name'] ?? 'Not Assigned'); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="empty-cell">EMPTY</div>
                                            <?php endif; ?>
                                            
                                            <a href="?class_id=<?php echo $selected_class; ?>&toggle_period=<?php echo $period['id']; ?>" 
                                               class="lock-toggle <?php echo $period['is_locked'] ? 'locked' : 'unlocked'; ?>"
                                               onclick="return confirm('<?php echo $period['is_locked'] ? 'Unlock' : 'Lock'; ?> this period?')">
                                                <?php echo $period['is_locked'] ? '🔒 LOCKED' : '🔓 UNLOCKED'; ?>
                                            </a>
                                        <?php else: ?>
                                            <div class="empty-cell">NO TIMETABLE</div>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color locked"></div>
                        <span>Locked Period (Cannot be edited)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color break"></div>
                        <span>Break Period</span>
                    </div>
                </div>
            </div>

        <?php elseif ($selected_class > 0 && !$class_info): ?>
            <div class="error-box">Class not found. Please select a valid class.</div>
        <?php else: ?>
            <div class="timetable-container">
                <h3 style="margin-bottom: 20px;">📊 ALL CLASSES LOCK STATUS</h3>
                <table class="timetable-table">
                    <thead>
                        <tr>
                            <th>Class Name</th>
                            <th>Semester</th>
                            <th>Section</th>
                            <th>Total Periods</th>
                            <th>Locked Periods</th>
                            <th>Status</th>
                            <th>Action</th>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($all_classes_status) > 0): ?>
                                <?php while($class = mysqli_fetch_assoc($all_classes_status)): ?>
                                    <?php 
                                    $is_fully_locked = ($class['total_periods'] > 0 && $class['locked_periods'] == $class['total_periods']);
                                    $is_partially_locked = ($class['locked_periods'] > 0 && $class['locked_periods'] < $class['total_periods']);
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                                        <td><?php echo $class['semester']; ?></td>
                                        <td><?php echo htmlspecialchars($class['section']); ?></td>
                                        <td><?php echo $class['total_periods']; ?></td>
                                        <td><?php echo $class['locked_periods']; ?></td>
                                        <td>
                                            <?php if ($class['total_periods'] == 0): ?>
                                                <span style="color: #999;">No timetable</span>
                                            <?php elseif ($is_fully_locked): ?>
                                                <span style="color: var(--red); font-weight: 800;">🔒 Fully Locked</span>
                                            <?php elseif ($is_partially_locked): ?>
                                                <span style="color: var(--orange); font-weight: 800;">🔓 Partially Locked</span>
                                            <?php else: ?>
                                                <span style="color: var(--green); font-weight: 800;">🔓 Fully Unlocked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="?class_id=<?php echo $class['id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">Manage</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 30px;">No classes found. Please add classes first.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <script>
            document.getElementById('classSelect')?.addEventListener('change', function() {
                if(this.value) {
                    window.location.href = '?class_id=' + this.value;
                }
            });
        </script>
    </body>
    </html>