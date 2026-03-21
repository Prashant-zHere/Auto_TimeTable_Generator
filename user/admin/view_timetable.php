<?php
session_start();
require_once '../../include/conn/conn.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] == 'csv' && isset($_GET['class_id'])) {
    $export_class_id = intval($_GET['class_id']);
    
    // Get class info
    $class_query = mysqli_query($conn, "SELECT class_name FROM classes WHERE id = $export_class_id");
    $class_data = mysqli_fetch_assoc($class_query);
    $class_name = $class_data['class_name'];
    
    // Get time slots
    $slots_query = mysqli_query($conn, "
        SELECT * FROM time_slots 
        WHERE class_id = $export_class_id 
        ORDER BY slot_number
    ");
    $time_slots = [];
    while($slot = mysqli_fetch_assoc($slots_query)) {
        $time_slots[$slot['id']] = $slot;
    }
    
    // Get timetable data
    $timetable_query = mysqli_query($conn, "
        SELECT 
            t.*, 
            s.subject_name, s.subject_code,
            u.full_name as teacher_name,
            ts.start_time, ts.end_time, ts.slot_number, ts.is_break as slot_is_break
        FROM timetable t
        LEFT JOIN subjects s ON t.subject_id = s.id
        LEFT JOIN teachers tea ON t.teacher_id = tea.id
        LEFT JOIN users u ON tea.user_id = u.id
        JOIN time_slots ts ON t.slot_id = ts.id
        WHERE t.class_id = $export_class_id
        ORDER BY t.day_of_week, ts.slot_number
    ");
    
    $timetable_data = [];
    while($row = mysqli_fetch_assoc($timetable_query)) {
        $day = $row['day_of_week'];
        $slot_id = $row['slot_id'];
        $timetable_data[$day][$slot_id] = $row;
    }
    
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="timetable_' . $class_name . '_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");
    
    // Write header row
    $headers = ['Time / Day'];
    foreach($days as $day) {
        $headers[] = $day;
    }
    fputcsv($output, $headers);
    
    // Write data rows
    foreach($time_slots as $slot_id => $slot) {
        $row = [];
        // Time slot column
        $time_text = 'Slot ' . $slot['slot_number'] . "\n" . 
                     date('H:i', strtotime($slot['start_time'])) . '-' . 
                     date('H:i', strtotime($slot['end_time']));
        if($slot['is_break']) {
            $time_text .= ' (BREAK)';
        }
        $row[] = $time_text;
        
        // Each day column
        for($day = 1; $day <= 6; $day++) {
            if(isset($timetable_data[$day][$slot_id])) {
                $period = $timetable_data[$day][$slot_id];
                if($period['slot_is_break']) {
                    $row[] = 'BREAK';
                } elseif($period['subject_id']) {
                    $cell_text = $period['subject_code'] . "\n" . 
                                 $period['subject_name'] . "\n" . 
                                 ($period['teacher_name'] ?? 'Not Assigned');
                    $row[] = $cell_text;
                } else {
                    $row[] = 'EMPTY';
                }
            } else {
                $row[] = '';
            }
        }
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// Get selected class from URL or default to first class
$selected_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$selected_view = isset($_GET['view']) ? $_GET['view'] : 'weekly'; // weekly or teacher
$selected_teacher = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;

// Fetch all classes for dropdown
$classes = mysqli_query($conn, "SELECT id, class_name, semester, section FROM classes ORDER BY class_name");

// Fetch all teachers for teacher view
$teachers = mysqli_query($conn, "
    SELECT t.id, u.full_name, t.employee_id 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY u.full_name
");

// Get class info if selected
$class_info = null;
if ($selected_class > 0) {
    $class_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM classes WHERE id = $selected_class"));
}

// Get teacher info if selected
$teacher_info = null;
if ($selected_teacher > 0) {
    $teacher_info = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT t.*, u.full_name 
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.id = $selected_teacher
    "));
}

// Fetch timetable data for selected class
$timetable_data = [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$time_slots = [];

if ($selected_class > 0 && $selected_view == 'weekly') {
    // Get time slots for this class (including breaks)
    $slots_query = mysqli_query($conn, "
        SELECT * FROM time_slots 
        WHERE class_id = $selected_class 
        ORDER BY slot_number
    ");
    
    while($slot = mysqli_fetch_assoc($slots_query)) {
        $time_slots[$slot['id']] = $slot;
    }
    
    // Get timetable entries with proper joins to get break info from time_slots
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
        ORDER BY t.day_of_week, ts.slot_number
    ");
    
    while($row = mysqli_fetch_assoc($timetable_query)) {
        $day = $row['day_of_week'];
        $slot_id = $row['slot_id'];
        $timetable_data[$day][$slot_id] = $row;
    }
}

// Fetch teacher-wise timetable
$teacher_timetable = [];
if ($selected_teacher > 0 && $selected_view == 'teacher') {
    $teacher_query = mysqli_query($conn, "
        SELECT 
            t.*, 
            s.subject_name, s.subject_code, s.subject_type,
            c.class_name,
            ts.start_time, ts.end_time, ts.slot_number, ts.is_break as slot_is_break,
            ts.day_type
        FROM timetable t
        LEFT JOIN subjects s ON t.subject_id = s.id
        LEFT JOIN classes c ON t.class_id = c.id
        JOIN time_slots ts ON t.slot_id = ts.id
        WHERE t.teacher_id = $selected_teacher
        ORDER BY t.day_of_week, ts.slot_number
    ");
    
    while($row = mysqli_fetch_assoc($teacher_query)) {
        $day = $row['day_of_week'];
        $teacher_timetable[$day][] = $row;
    }
}

// Get lock status for the class
$lock_status = false;
if ($selected_class > 0) {
    $lock_check = mysqli_query($conn, "SELECT is_locked FROM timetable WHERE class_id = $selected_class LIMIT 1");
    if ($lock_row = mysqli_fetch_assoc($lock_check)) {
        $lock_status = $lock_row['is_locked'];
    }
}

// Get statistics for the selected class
$class_stats = [
    'total_periods' => 0,
    'with_teacher' => 0,
    'break_periods' => 0,
    'empty_periods' => 0
];

if ($selected_class > 0 && !empty($timetable_data)) {
    foreach($timetable_data as $day => $slots) {
        $class_stats['total_periods'] += count($slots);
        foreach($slots as $slot) {
            if($slot['slot_is_break']) {
                $class_stats['break_periods']++;
            } elseif(!empty($slot['teacher_id'])) {
                $class_stats['with_teacher']++;
            } else {
                $class_stats['empty_periods']++;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Timetable · Admin</title>
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

        /* View Selector */
        .view-selector {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            background: white;
            border: 4px solid #000;
            padding: 20px;
            box-shadow: var(--shadow);
            flex-wrap: wrap;
        }

        .view-tabs {
            display: flex;
            gap: 10px;
            border-right: 3px solid #000;
            padding-right: 20px;
        }

        .view-tab {
            padding: 10px 20px;
            border: 3px solid #000;
            font-weight: 800;
            cursor: pointer;
            background: #f0f0f0;
            text-decoration: none;
            color: #000;
            transition: all 0.1s ease;
        }

        .view-tab.active {
            background: var(--blue);
            color: white;
            transform: translate(-2px, -2px);
            box-shadow: 3px 3px 0 #000;
        }

        .class-selector, .teacher-selector {
            flex: 1;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .class-selector select,
        .teacher-selector select {
            padding: 10px;
            border: 3px solid #000;
            font-weight: 600;
            flex: 1;
        }

        .go-btn {
            padding: 10px 20px;
            background: var(--green);
            color: white;
            border: 3px solid #000;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
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
            margin-left: 15px;
        }

        .status-locked {
            background: var(--red);
            color: white;
        }

        .status-unlocked {
            background: var(--green);
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            padding: 10px 15px;
            border: 3px solid #000;
            font-weight: 800;
            text-decoration: none;
            color: white;
            transition: all 0.1s ease;
            cursor: pointer;
            display: inline-block;
        }

        .edit-btn {
            background: var(--blue);
        }

        .print-btn {
            background: var(--purple);
        }

        .export-btn {
            background: var(--orange);
        }

        .action-btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: 3px 3px 0 #000;
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

        .period-cell {
            height: 100%;
        }

        .break-cell {
            background: #fff0f0 !important;
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
            margin-bottom: 3px;
        }

        .break-time {
            font-size: 11px;
            color: #666;
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
        .type-project { background: var(--purple); color: white; }
        .type-elective { background: var(--red); color: white; }

        .teacher-info {
            font-size: 12px;
            color: var(--blue);
            margin-top: 3px;
            font-weight: 600;
        }

        .empty-cell {
            background: #f0f0f0;
            color: #999;
            text-align: center;
            padding: 15px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .empty-cell:before {
            content: "⚡";
            font-size: 20px;
            opacity: 0.5;
        }

        /* Teacher View */
        .teacher-timetable {
            margin-top: 20px;
        }

        .teacher-day {
            margin-bottom: 25px;
            border: 3px solid #000;
        }

        .teacher-day-header {
            background: var(--blue);
            color: white;
            padding: 10px;
            font-weight: 900;
            border-bottom: 2px solid #000;
        }

        .teacher-period {
            display: grid;
            grid-template-columns: 100px 1fr 1fr 1fr;
            gap: 10px;
            padding: 10px;
            border-bottom: 1px solid #000;
        }

        .teacher-period.break-period {
            background: #fff0f0;
        }

        .teacher-period:last-child {
            border-bottom: none;
        }

        .period-time {
            font-weight: 800;
        }

        .break-badge {
            background: var(--red);
            color: white;
            padding: 2px 8px;
            border: 1px solid #000;
            font-size: 11px;
            display: inline-block;
        }

        /* Legend */
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
        .legend-color.project { background: var(--purple); }
        .legend-color.elective { background: var(--red); }
        .legend-color.break { background: #fff0f0; border: 2px dashed var(--red); }

        /* Statistics */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .stat-box {
            border: 3px solid #000;
            padding: 15px;
            text-align: center;
            background: white;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 900;
            color: var(--blue);
        }

        .stat-label {
            font-size: 12px;
            color: #666;
        }

        /* Print Styles */
        @media print {
            .sidebar, .content-header, .view-selector, .action-buttons, .legend, footer, .stats-grid {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            
            .timetable-container {
                box-shadow: none !important;
                border: 2px solid #000 !important;
                padding: 10px !important;
                margin: 0 !important;
                overflow: visible !important;
            }
            
            .timetable-table {
                width: 100% !important;
                min-width: 0 !important;
                border-collapse: collapse !important;
            }
            
            .timetable-table th,
            .timetable-table td {
                border: 1px solid #000 !important;
                padding: 6px !important;
                font-size: 10px !important;
                word-break: break-word !important;
            }
            
            @page {
                size: landscape !important;
                margin: 1cm !important;
            }
            
            thead {
                display: table-header-group !important;
            }
            
            tr {
                page-break-inside: avoid !important;
            }
        }

        @media (max-width: 900px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .view-selector {
                flex-direction: column;
            }
            
            .view-tabs {
                border-right: none;
                border-bottom: 3px solid #000;
                padding-bottom: 15px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
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
            <div class="admin-name">👤 <?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
            <span class="admin-role">⚙️ ADMINISTRATOR</span>
        </div>

        <div class="nav-menu">
            <div class="nav-section">
                <div class="nav-section-title">MAIN</div>
                <a href="dashboard.php" class="nav-item">📊 Dashboard</a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">MANAGEMENT</div>
                <a href="teachers.php" class="nav-item">👨‍🏫 Teachers</a>
                <a href="students.php" class="nav-item">🎓 Students</a>
                <a href="classes.php" class="nav-item">🏫 Classes</a>
                <a href="subjects.php" class="nav-item">📚 Subjects</a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">TIMETABLE</div>
                <a href="view_timetable.php" class="nav-item active">👁️ View Timetable</a>
                <a href="generate_timetable.php" class="nav-item">⚡ Generate Timetable</a>
                <a href="time_slots.php" class="nav-item">⏰ Time Slots</a>
                <a href="lock_timetable.php" class="nav-item">🔒 Lock Timetable</a>
            </div>
        </div>

        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn">🚪 LOGOUT</a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="content-header">
            <h1>👁️ VIEW TIMETABLE</h1>
            <div class="date-display">
                <?php echo date('l, d M Y'); ?>
            </div>
        </div>

        <!-- View Selector -->
        <div class="view-selector">
            <div class="view-tabs">
                <a href="?view=weekly&class_id=<?php echo $selected_class; ?>" 
                   class="view-tab <?php echo $selected_view == 'weekly' ? 'active' : ''; ?>">
                    📅 WEEKLY VIEW
                </a>
                <a href="?view=teacher&teacher_id=<?php echo $selected_teacher; ?>" 
                   class="view-tab <?php echo $selected_view == 'teacher' ? 'active' : ''; ?>">
                    👨‍🏫 TEACHER VIEW
                </a>
            </div>

            <?php if ($selected_view == 'weekly'): ?>
            <div class="class-selector">
                <select id="classSelect" onchange="window.location.href='?view=weekly&class_id=' + this.value">
                    <option value="">-- Select Class --</option>
                    <?php 
                    mysqli_data_seek($classes, 0);
                    while($class = mysqli_fetch_assoc($classes)): 
                    ?>
                        <option value="<?php echo $class['id']; ?>" <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['class_name']); ?> (Sem <?php echo $class['semester']; ?>, Sec <?php echo $class['section']; ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
                <a href="generate_timetable.php?class_id=<?php echo $selected_class; ?>" class="go-btn">⚡ GENERATE</a>
            </div>
            <?php else: ?>
            <div class="teacher-selector">
                <select id="teacherSelect" onchange="window.location.href='?view=teacher&teacher_id=' + this.value">
                    <option value="">-- Select Teacher --</option>
                    <?php 
                    mysqli_data_seek($teachers, 0);
                    while($teacher = mysqli_fetch_assoc($teachers)): 
                    ?>
                        <option value="<?php echo $teacher['id']; ?>" <?php echo $selected_teacher == $teacher['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($teacher['full_name']); ?> (<?php echo $teacher['employee_id']; ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($selected_view == 'weekly' && $selected_class > 0): ?>
            <!-- Weekly Class Timetable -->
            <div class="timetable-container">
                <div class="timetable-header">
                    <div>
                        <span class="class-title">
                            📚 <?php echo htmlspecialchars($class_info['class_name'] ?? 'Class'); ?> Timetable
                        </span>
                        <span class="lock-status <?php echo $lock_status ? 'status-locked' : 'status-unlocked'; ?>">
                            <?php echo $lock_status ? '🔒 LOCKED' : '🔓 UNLOCKED'; ?>
                        </span>
                    </div>
                    <div class="action-buttons">
                        <!-- In view_timetable.php, update the edit button -->
<a href="manual_timetable.php?class_id=<?php echo $selected_class; ?>&academic_year=2024-25&semester=<?php echo $class_info['semester']; ?>" class="action-btn edit-btn">✏️ EDIT</a>
                        <button onclick="window.print()" class="action-btn print-btn">🖨️ PRINT</button>
                        <button onclick="exportToCSV(<?php echo $selected_class; ?>)" class="action-btn export-btn">📥 EXPORT CSV</button>
                    </div>
                </div>

                <table class="timetable-table">
                    <thead>
                        <tr>
                            <th>Time / Day</th>
                            <?php foreach($days as $index => $day): ?>
                                <th><?php echo $day; ?></th>
                            <?php endforeach; ?>
                        </tr>
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
                                    <td class="period-cell <?php echo (isset($timetable_data[$day][$slot_id]) && $timetable_data[$day][$slot_id]['slot_is_break']) ? 'break-cell' : ''; ?>">
                                        <?php if(isset($timetable_data[$day][$slot_id])): 
                                            $period = $timetable_data[$day][$slot_id];
                                            if($period['slot_is_break']): 
                                        ?>
                                            <div class="break-display">
                                                <div class="break-icon">🍽️</div>
                                                <div class="break-label">BREAK</div>
                                                <div class="break-time">
                                                    <?php echo date('h:i A', strtotime($slot['start_time'])); ?> - 
                                                    <?php echo date('h:i A', strtotime($slot['end_time'])); ?>
                                                </div>
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
                                                <?php echo htmlspecialchars($period['teacher_name'] ?? 'Not Assigned'); ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="empty-cell"></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="empty-cell"></div>
                                    <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Legend -->
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
                        <div class="legend-color project"></div>
                        <span>Project</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color elective"></div>
                        <span>Elective</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color break"></div>
                        <span>Break Period</span>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-number"><?php echo $class_stats['total_periods']; ?></div>
                    <div class="stat-label">Total Periods</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $class_stats['with_teacher']; ?></div>
                    <div class="stat-label">With Teacher</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $class_stats['break_periods']; ?></div>
                    <div class="stat-label">Break Periods</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $class_stats['empty_periods']; ?></div>
                    <div class="stat-label">Empty Periods</div>
                </div>
            </div>

        <?php elseif ($selected_view == 'teacher' && $selected_teacher > 0): ?>
            <!-- Teacher Timetable -->
            <div class="timetable-container">
                <div class="timetable-header">
                    <div>
                        <span class="class-title">
                            👨‍🏫 <?php echo htmlspecialchars($teacher_info['full_name'] ?? 'Teacher'); ?>'s Timetable
                        </span>
                        <span class="lock-status status-unlocked">
                            Employee ID: <?php echo $teacher_info['employee_id']; ?>
                        </span>
                    </div>
                    <div class="action-buttons">
                        <button onclick="window.print()" class="action-btn print-btn">🖨️ PRINT</button>
                    </div>
                </div>

                <div class="teacher-timetable">
                    <?php for($day = 1; $day <= 6; $day++): ?>
                        <?php if(isset($teacher_timetable[$day]) && !empty($teacher_timetable[$day])): ?>
                            <div class="teacher-day">
                                <div class="teacher-day-header">
                                    <?php echo $days[$day-1]; ?>
                                </div>
                                <?php 
                                // Sort periods by slot number
                                usort($teacher_timetable[$day], function($a, $b) {
                                    return $a['slot_number'] - $b['slot_number'];
                                });
                                
                                foreach($teacher_timetable[$day] as $period): 
                                ?>
                                    <div class="teacher-period <?php echo $period['slot_is_break'] ? 'break-period' : ''; ?>">
                                        <div class="period-time">
                                            Slot <?php echo $period['slot_number']; ?><br>
                                            <small><?php echo date('h:i A', strtotime($period['start_time'])); ?></small>
                                        </div>
                                        <?php if($period['slot_is_break']): ?>
                                            <div style="grid-column: span 3;">
                                                <span class="break-badge">🍽️ BREAK PERIOD</span>
                                            </div>
                                        <?php else: ?>
                                            <div>
                                                <strong><?php echo htmlspecialchars($period['subject_code']); ?></strong><br>
                                                <?php echo htmlspecialchars($period['subject_name']); ?>
                                                <?php if(isset($period['subject_type'])): ?>
                                                    <span class="subject-type type-<?php echo strtolower($period['subject_type']); ?>">
                                                        <?php echo $period['subject_type']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong>Class:</strong><br>
                                                <?php echo htmlspecialchars($period['class_name']); ?>
                                            </div>
                                            <div>
                                                <strong>Time:</strong><br>
                                                <?php echo date('h:i A', strtotime($period['start_time'])); ?> - 
                                                <?php echo date('h:i A', strtotime($period['end_time'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if(empty($teacher_timetable)): ?>
                    <div style="text-align: center; padding: 50px; border: 3px solid #000;">
                        <h3>No timetable entries found for this teacher</h3>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- No Selection -->
            <div class="timetable-container" style="text-align: center; padding: 50px;">
                <h2>📋 Please select a class or teacher to view timetable</h2>
                <p style="margin-top: 20px;">Use the tabs above to switch between views</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Export to CSV function
        function exportToCSV(classId) {
            if (classId) {
                window.location.href = '?export=csv&class_id=' + classId;
            }
        }
        
        // Print function
        function printTimetable() {
            window.print();
        }

        // Highlight current day
        let today = new Date().getDay();
        let dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        let currentDay = dayNames[today];
        
        let headers = document.querySelectorAll('.timetable-table th');
        headers.forEach((header, index) => {
            if(header.textContent.trim() === currentDay) {
                header.style.background = 'var(--green)';
            }
        });

        // Auto-refresh on class/teacher change
        document.getElementById('classSelect')?.addEventListener('change', function() {
            if(this.value) {
                window.location.href = '?view=weekly&class_id=' + this.value;
            }
        });

        document.getElementById('teacherSelect')?.addEventListener('change', function() {
            if(this.value) {
                window.location.href = '?view=teacher&teacher_id=' + this.value;
            }
        });
    </script>
</body>
</html>