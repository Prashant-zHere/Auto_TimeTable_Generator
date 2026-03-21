<?php
session_start();
require_once '../../include/conn/conn.php';


// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$error = '';
$success = '';
$warning = '';

// Fetch all classes for dropdown
$classes = mysqli_query($conn, "SELECT id, class_name, semester, section FROM classes ORDER BY class_name");

// Fetch all teachers for reference
$teachers = mysqli_query($conn, "
    SELECT t.id, u.full_name, t.employee_id, t.max_periods_per_day 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY u.full_name
");

// Handle timetable generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_timetable'])) {
    $class_id = intval($_POST['class_id']);
    $semester = intval($_POST['semester']);
    $academic_year = trim($_POST['academic_year']);
    $generation_method = $_POST['generation_method']; // 'auto' or 'manual'
    
    // Check if timetable already exists
    $check_existing = mysqli_query($conn, "SELECT id FROM timetable WHERE class_id = $class_id AND semester = $semester AND academic_year = '$academic_year' LIMIT 1");
    $timetable_exists = mysqli_num_rows($check_existing) > 0;
    
    if ($timetable_exists && !isset($_POST['confirm_overwrite'])) {
        $warning = "Timetable already exists for this class. Click Generate again to overwrite.";
    } else {
        if ($generation_method === 'auto') {
            // Auto generation logic
            $result = generateAutoTimetable($conn, $class_id, $semester, $academic_year);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        } else {
            // Manual generation - redirect to manual setup page
            header("Location: manual_timetable.php?class_id=$class_id&semester=$semester&academic_year=$academic_year");
            exit;
        }
    }
}

// Function to auto-generate timetable with breaks
function generateAutoTimetable($conn, $class_id, $semester, $academic_year) {
    // First, clear existing timetable if any
    mysqli_query($conn, "DELETE FROM timetable WHERE class_id = $class_id AND semester = $semester AND academic_year = '$academic_year'");
    
    // Get subjects for this class
    $subjects = mysqli_query($conn, "
        SELECT s.*, ts.teacher_id 
        FROM subjects s 
        LEFT JOIN teacher_subjects ts ON s.id = ts.subject_id AND ts.class_id = $class_id
        WHERE s.class_id = $class_id AND s.semester = $semester
    ");
    
    if (mysqli_num_rows($subjects) == 0) {
        return ['success' => false, 'message' => 'No subjects found for this class. Please add subjects first.'];
    }
    
    // Get time slots for this class (including breaks)
    $slots = mysqli_query($conn, "
        SELECT * FROM time_slots 
        WHERE class_id = $class_id 
        ORDER BY slot_number
    ");
    
    if (mysqli_num_rows($slots) == 0) {
        return ['success' => false, 'message' => 'No time slots defined for this class. Please add time slots first.'];
    }
    
    // Get teachers and their max periods
    $teachers_data = [];
    $teacher_query = mysqli_query($conn, "SELECT id, max_periods_per_day FROM teachers");
    while($t = mysqli_fetch_assoc($teacher_query)) {
        $teachers_data[$t['id']] = [
            'max_periods' => $t['max_periods_per_day'],
            'daily_count' => []
        ];
    }
    
    // Prepare subjects list with required periods
    $subject_list = [];
    while($sub = mysqli_fetch_assoc($subjects)) {
        $subject_list[] = [
            'id' => $sub['id'],
            'name' => $sub['subject_name'],
            'code' => $sub['subject_code'],
            'periods_per_week' => $sub['periods_per_week'],
            'teacher_id' => $sub['teacher_id'],
            'allocated' => 0,
            'subject_type' => $sub['subject_type'] ?? 'Theory'
        ];
    }
    
    // Days (1=Monday to 6=Saturday)
    $days = [1, 2, 3, 4, 5, 6];
    $day_names = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    
    // Get all slots
    $slot_list = [];
    while($slot = mysqli_fetch_assoc($slots)) {
        $slot_list[] = $slot;
    }
    
    $inserted = 0;
    $break_slots = 0;
    $conflicts = 0;
    
    // For each day
    foreach($days as $day) {
        // Determine if Saturday (fewer periods)
        $is_saturday = ($day == 6);
        
        // Filter slots for this day based on day_type
        $day_slots = array_filter($slot_list, function($slot) use ($is_saturday) {
            if ($is_saturday) {
                return $slot['day_type'] == 'saturday' || $slot['day_type'] == 'weekday';
            } else {
                return $slot['day_type'] == 'weekday';
            }
        });
        
        // Sort by slot number
        usort($day_slots, function($a, $b) {
            return $a['slot_number'] - $b['slot_number'];
        });
        
        // Reset daily teacher counts
        $daily_teacher_count = [];
        
        // For each slot on this day
        foreach($day_slots as $slot) {
            // Check if this is a break slot (from time_slots table)
            if($slot['is_break']) {
                // Insert break period (no subject, no teacher)
                $insert = "INSERT INTO timetable 
                          (class_id, day_of_week, slot_id, subject_id, teacher_id, academic_year, semester, is_locked) 
                          VALUES 
                          ($class_id, $day, {$slot['id']}, NULL, NULL, '$academic_year', $semester, 0)";
                
                if (mysqli_query($conn, $insert)) {
                    $inserted++;
                    $break_slots++;
                }
                continue;
            }
            
            // Find subjects that still need periods and have available teacher
            $available_subjects = array_filter($subject_list, function($sub) use ($daily_teacher_count, $teachers_data, $day) {
                if ($sub['allocated'] >= $sub['periods_per_week']) {
                    return false;
                }
                if ($sub['teacher_id']) {
                    $teacher_id = $sub['teacher_id'];
                    $current_daily = isset($daily_teacher_count[$teacher_id][$day]) ? $daily_teacher_count[$teacher_id][$day] : 0;
                    $max_allowed = isset($teachers_data[$teacher_id]) ? $teachers_data[$teacher_id]['max_periods'] : 6;
                    return $current_daily < $max_allowed;
                }
                return true;
            });
            
            if (empty($available_subjects)) {
                $conflicts++;
                // Insert empty period if no subject available
                $insert = "INSERT INTO timetable 
                          (class_id, day_of_week, slot_id, subject_id, teacher_id, academic_year, semester, is_locked) 
                          VALUES 
                          ($class_id, $day, {$slot['id']}, NULL, NULL, '$academic_year', $semester, 0)";
                mysqli_query($conn, $insert);
                $inserted++;
                continue;
            }
            
            // Pick a random subject from available ones
            $selected_index = array_rand($available_subjects);
            $selected = $available_subjects[$selected_index];
            
            // Update allocation
            foreach($subject_list as &$sub) {
                if ($sub['id'] == $selected['id']) {
                    $sub['allocated']++;
                    break;
                }
            }
            
            // Update daily teacher count
            if ($selected['teacher_id']) {
                $teacher_id = $selected['teacher_id'];
                if (!isset($daily_teacher_count[$teacher_id])) {
                    $daily_teacher_count[$teacher_id] = [];
                }
                if (!isset($daily_teacher_count[$teacher_id][$day])) {
                    $daily_teacher_count[$teacher_id][$day] = 0;
                }
                $daily_teacher_count[$teacher_id][$day]++;
            }
            
            // Insert into timetable
            $teacher_id = $selected['teacher_id'] ? $selected['teacher_id'] : 'NULL';
            $insert = "INSERT INTO timetable 
                      (class_id, day_of_week, slot_id, subject_id, teacher_id, academic_year, semester, is_locked) 
                      VALUES 
                      ($class_id, $day, {$slot['id']}, {$selected['id']}, $teacher_id, '$academic_year', $semester, 0)";
            
            if (mysqli_query($conn, $insert)) {
                $inserted++;
            }
        }
    }
    
    // Check if all subjects got their required periods
    $unfulfilled = [];
    foreach($subject_list as $sub) {
        if ($sub['allocated'] < $sub['periods_per_week']) {
            $unfulfilled[] = $sub['name'] . " (needs " . $sub['periods_per_week'] . ", got " . $sub['allocated'] . ")";
        }
    }
    
    if (!empty($unfulfilled)) {
        return [
            'success' => true, 
            'message' => "Timetable generated with $inserted total periods ($break_slots breaks). Warning: Some subjects couldn't be fully allocated: " . implode(", ", $unfulfilled)
        ];
    }
    
    return [
        'success' => true, 
        'message' => "Timetable generated successfully with $inserted total periods ($break_slots breaks)!"
    ];
}

// FIXED: Get generation statistics - properly join with time_slots to get break information
$generation_stats = mysqli_query($conn, "
    SELECT 
        c.class_name, 
        COUNT(t.id) as period_count,
        SUM(CASE WHEN ts.is_break = 1 THEN 1 ELSE 0 END) as break_count,
        SUM(CASE WHEN t.is_locked = 1 THEN 1 ELSE 0 END) as locked_count
    FROM classes c
    LEFT JOIN timetable t ON c.id = t.class_id
    LEFT JOIN time_slots ts ON t.slot_id = ts.id
    GROUP BY c.id
    ORDER BY c.class_name
");

if (!$generation_stats) {
    // If query fails, show error
    $error = "Error fetching statistics: " . mysqli_error($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Timetable · Admin</title>
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

        .generation-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        /* Generation Form */
        .form-container {
            background: var(--yellow);
            border: 4px solid #000;
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .form-container h2 {
            font-size: 24px;
            margin-bottom: 20px;
            border-bottom: 3px solid #000;
            display: inline-block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            font-weight: 800;
            background: var(--blue);
            color: white;
            padding: 5px 10px;
            border: 2px solid #000;
            display: inline-block;
            margin-bottom: 5px;
            box-shadow: 3px 3px 0 #000;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 4px solid #000;
            font-size: 16px;
            background: white;
            box-shadow: 3px 3px 0 #000;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            background: #fff8cc;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .method-selector {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            padding: 15px;
            background: white;
            border: 4px solid #000;
        }

        .method-option {
            flex: 1;
            text-align: center;
        }

        .method-option input[type="radio"] {
            display: none;
        }

        .method-option label {
            display: block;
            padding: 15px;
            border: 3px solid #000;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.1s ease;
        }

        .method-option input[type="radio"]:checked + label {
            background: var(--blue);
            color: white;
            transform: translate(-2px, -2px);
            box-shadow: 3px 3px 0 #000;
        }

        .warning-box {
            background: var(--red);
            color: white;
            padding: 15px;
            border: 4px solid #000;
            margin: 20px 0;
            font-weight: 800;
        }

        .info-box {
            background: var(--blue);
            color: white;
            padding: 15px;
            border: 4px solid #000;
            margin: 20px 0;
        }

        .break-info {
            background: #ffd700;
            color: #000;
            padding: 10px;
            border: 3px solid #000;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .break-info .icon {
            font-size: 24px;
        }

        .submit-btn {
            background: var(--red);
            color: white;
            padding: 16px;
            font-weight: 900;
            font-size: 20px;
            border: 4px solid #000;
            cursor: pointer;
            width: 100%;
            box-shadow: var(--shadow);
            transition: all 0.1s ease;
        }

        .submit-btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 #000;
        }

        /* Status Cards */
        .status-container {
            background: white;
            border: 4px solid #000;
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .status-container h2 {
            font-size: 24px;
            margin-bottom: 20px;
            background: var(--yellow);
            display: inline-block;
            padding: 5px 15px;
            border: 3px solid #000;
        }

        .class-status {
            margin-bottom: 15px;
            border: 3px solid #000;
            padding: 15px;
        }

        .class-status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .class-name-badge {
            background: var(--blue);
            color: white;
            padding: 5px 10px;
            border: 2px solid #000;
            font-weight: 800;
        }

        .period-stats {
            display: flex;
            gap: 10px;
        }

        .stat-badge {
            padding: 3px 8px;
            border: 2px solid #000;
            font-size: 12px;
            font-weight: 700;
        }

        .stat-break {
            background: var(--orange);
            color: white;
        }

        .stat-locked {
            background: var(--red);
            color: white;
        }

        .progress-bar {
            height: 20px;
            background: #eee;
            border: 2px solid #000;
            position: relative;
            margin: 10px 0;
        }

        .progress-fill {
            height: 100%;
            background: var(--green);
            width: 0%;
        }

        .status-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            font-size: 12px;
            font-weight: 600;
        }

        .prerequisites {
            background: #333;
            color: white;
            padding: 20px;
            border: 4px solid #000;
            margin-top: 20px;
        }

        .prerequisites h3 {
            margin-bottom: 10px;
            color: var(--yellow);
        }

        .prerequisites ul {
            list-style: none;
        }

        .prerequisites li {
            padding: 5px 0;
            border-bottom: 1px solid #555;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .prerequisites li:before {
            content: "✓";
            color: var(--green);
            font-weight: 800;
            margin-right: 10px;
        }

        .prerequisites li.missing:before {
            content: "✗";
            color: var(--red);
        }

        .error-box, .success-box {
            margin-bottom: 20px;
        }

        .break-example {
            background: #fff3cd;
            border: 3px solid #000;
            padding: 10px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        @media (max-width: 900px) {
            .generation-container {
                grid-template-columns: 1fr;
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
                <a href="view_timetable.php" class="nav-item">👁️ View Timetable</a>
                <a href="generate_timetable.php" class="nav-item active">⚡ Generate Timetable</a>
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
            <h1>⚡ GENERATE TIMETABLE</h1>
            <div class="date-display">
                <?php echo date('l, d M Y'); ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="error-box"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-box"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($warning): ?>
            <div class="warning-box">⚠️ <?php echo $warning; ?></div>
        <?php endif; ?>

        <div class="generation-container">
            <!-- Generation Form -->
            <div class="form-container">
                <h2>⚙️ GENERATION SETTINGS</h2>
                
                <div class="break-info">
                    <span class="icon">🍽️</span>
                    <span><strong>Break slots detected:</strong> Break periods will be automatically marked and will not have subjects assigned.</span>
                </div>
                
                <form method="post" action="" id="generationForm">
                    <div class="form-group">
                        <label>🏫 SELECT CLASS</label>
                        <select name="class_id" required id="classSelect">
                            <option value="">-- Choose Class --</option>
                            <?php 
                            mysqli_data_seek($classes, 0);
                            while($class = mysqli_fetch_assoc($classes)): 
                            ?>
                                <option value="<?php echo $class['id']; ?>" 
                                        data-semester="<?php echo $class['semester']; ?>">
                                    <?php echo htmlspecialchars($class['class_name']); ?> 
                                    (Sem <?php echo $class['semester']; ?>, Sec <?php echo $class['section']; ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>🔢 SEMESTER</label>
                            <input type="number" name="semester" id="semesterInput" min="1" max="8" required value="1">
                        </div>
                        <div class="form-group">
                            <label>📅 ACADEMIC YEAR</label>
                            <select name="academic_year">
                                <option value="2024-25">2024-25</option>
                                <option value="2025-26">2025-26</option>
                            </select>
                        </div>
                    </div>

                    <div class="method-selector">
                        <div class="method-option">
                            <input type="radio" name="generation_method" id="methodAuto" value="auto" checked>
                            <label for="methodAuto">🤖 AUTO GENERATE</label>
                        </div>
                        <div class="method-option">
                            <input type="radio" name="generation_method" id="methodManual" value="manual">
                            <label for="methodManual">✏️ MANUAL SETUP</label>
                        </div>
                    </div>

                    <input type="hidden" name="confirm_overwrite" id="confirmOverwrite" value="0">

                    <div class="info-box">
                        <strong>📊 Generation Info:</strong><br>
                        • Auto: Random allocation based on subjects and teacher availability<br>
                        • Break slots are automatically handled (no subjects assigned)<br>
                        • Teacher max periods per day are respected<br>
                        • Existing timetable will be overwritten
                    </div>

                    <button type="submit" name="generate_timetable" class="submit-btn" onclick="return confirmGeneration()">
                        ⚡ GENERATE TIMETABLE
                    </button>
                </form>

                <!-- Prerequisites Check -->
                <div class="prerequisites">
                    <h3>📋 PREREQUISITES CHECK</h3>
                    <?php
                    // Check each prerequisite
                    $has_classes = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM classes")) > 0;
                    $has_subjects = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM subjects")) > 0;
                    $has_teachers = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM teachers")) > 0;
                    $has_slots = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM time_slots")) > 0;
                    $has_allocations = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM teacher_subjects")) > 0;
                    ?>
                    <ul>
                        <li class="<?php echo $has_classes ? '' : 'missing'; ?>">Classes must be added</li>
                        <li class="<?php echo $has_subjects ? '' : 'missing'; ?>">Subjects must be added for the class</li>
                        <li class="<?php echo $has_teachers ? '' : 'missing'; ?>">Teachers must be added</li>
                        <li class="<?php echo $has_slots ? '' : 'missing'; ?>">Time slots must be defined for the class</li>
                        <li class="<?php echo $has_allocations ? '' : 'missing'; ?>">Teachers should be allocated to subjects</li>
                    </ul>
                </div>
            </div>

            <!-- Status Panel -->
            <div class="status-container">
                <h2>📊 CURRENT TIMETABLE STATUS</h2>
                
                <?php if ($generation_stats && mysqli_num_rows($generation_stats) > 0): ?>
                    <?php while($stat = mysqli_fetch_assoc($generation_stats)): ?>
                        <?php 
                        $total_periods = 42; // 6 days * 7 slots (approximate)
                        $percentage = $stat['period_count'] > 0 ? min(100, round(($stat['period_count'] / $total_periods) * 100)) : 0;
                        ?>
                        <div class="class-status">
                            <div class="class-status-header">
                                <span class="class-name-badge"><?php echo htmlspecialchars($stat['class_name']); ?></span>
                                <div class="period-stats">
                                    <span class="stat-badge">📊 <?php echo $stat['period_count']; ?> total</span>
                                    <?php if($stat['break_count'] > 0): ?>
                                        <span class="stat-badge stat-break">🍽️ <?php echo $stat['break_count']; ?> breaks</span>
                                    <?php endif; ?>
                                    <?php if($stat['locked_count'] > 0): ?>
                                        <span class="stat-badge stat-locked">🔒 <?php echo $stat['locked_count']; ?> locked</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                            </div>
                            <div class="status-labels">
                                <span>0%</span>
                                <span><?php echo $percentage; ?>% Complete (<?php echo $stat['period_count']; ?>/<?php echo $total_periods; ?>)</span>
                                <span>100%</span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; padding: 20px;">No timetable data available</p>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div style="margin-top: 30px;">
                    <h3 style="margin-bottom: 15px;">⚡ QUICK STATS</h3>
                    <?php
                    // FIXED: Get quick stats with proper joins
                    $stats_query = mysqli_query($conn, "
                        SELECT 
                            COUNT(DISTINCT t.class_id) as class_count,
                            COUNT(t.id) as total_periods,
                            SUM(CASE WHEN ts.is_break = 1 THEN 1 ELSE 0 END) as total_breaks,
                            SUM(CASE WHEN t.is_locked = 1 THEN 1 ELSE 0 END) as locked_periods
                        FROM timetable t
                        LEFT JOIN time_slots ts ON t.slot_id = ts.id
                    ");
                    $stats = mysqli_fetch_assoc($stats_query);
                    ?>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                        <div style="border: 2px solid #000; padding: 10px; text-align: center;">
                            <div style="font-size: 24px; font-weight: 900; color: var(--blue);"><?php echo $stats['class_count'] ?? 0; ?></div>
                            <div>Classes with Timetable</div>
                        </div>
                        <div style="border: 2px solid #000; padding: 10px; text-align: center;">
                            <div style="font-size: 24px; font-weight: 900; color: var(--green);"><?php echo $stats['total_periods'] ?? 0; ?></div>
                            <div>Total Periods</div>
                        </div>
                        <div style="border: 2px solid #000; padding: 10px; text-align: center;">
                            <div style="font-size: 24px; font-weight: 900; color: var(--orange);"><?php echo $stats['total_breaks'] ?? 0; ?></div>
                            <div>Break Periods</div>
                        </div>
                        <div style="border: 2px solid #000; padding: 10px; text-align: center;">
                            <div style="font-size: 24px; font-weight: 900; color: var(--red);"><?php echo $stats['locked_periods'] ?? 0; ?></div>
                            <div>Locked Periods</div>
                        </div>
                    </div>
                </div>

                <!-- Break Example -->
                <div class="break-example">
                    <span style="font-size: 24px;">🍽️</span>
                    <div>
                        <strong>Break slots are handled automatically:</strong><br>
                        <small>Lunch breaks and short breaks will appear as empty periods in the timetable</small>
                    </div>
                </div>

                <!-- Recent Generations -->
                <div style="margin-top: 20px;">
                    <h3 style="margin-bottom: 10px;">🕒 RECENT TIMETABLES</h3>
                    <?php
                    // FIXED: Get recent timetables with break counts
                    $recent = mysqli_query($conn, "
                        SELECT 
                            c.class_name, 
                            COUNT(t.id) as periods,
                            SUM(CASE WHEN ts.is_break = 1 THEN 1 ELSE 0 END) as breaks,
                            MAX(t.id) as last
                        FROM timetable t
                        JOIN classes c ON t.class_id = c.id
                        LEFT JOIN time_slots ts ON t.slot_id = ts.id
                        GROUP BY t.class_id, t.semester, t.academic_year
                        ORDER BY last DESC
                        LIMIT 5
                    ");
                    ?>
                    <?php if ($recent && mysqli_num_rows($recent) > 0): ?>
                        <ul style="list-style: none;">
                            <?php while($rec = mysqli_fetch_assoc($recent)): ?>
                                <li style="padding: 8px; border-bottom: 1px solid #ccc; display: flex; justify-content: space-between;">
                                    <span>📅 <?php echo htmlspecialchars($rec['class_name']); ?></span>
                                    <span>
                                        <?php echo $rec['periods']; ?> periods 
                                        <?php if($rec['breaks'] > 0): ?>
                                            <span style="background: var(--orange); color: white; padding: 2px 5px; border: 1px solid #000; font-size: 11px;">
                                                <?php echo $rec['breaks']; ?> breaks
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p>No timetables generated yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Generation Tips -->
        <div style="margin-top: 30px; background: var(--light-gray); border: 4px solid #000; padding: 20px;">
            <h3 style="margin-bottom: 15px;">💡 GENERATION TIPS</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <div style="border: 2px solid #000; padding: 15px; background: white;">
                    <strong>🤖 Auto Generation</strong>
                    <p style="margin-top: 5px; font-size: 14px;">Randomly assigns subjects to slots while respecting teacher constraints and subject periods. Break slots are automatically skipped.</p>
                </div>
                <div style="border: 2px solid #000; padding: 15px; background: white;">
                    <strong>✏️ Manual Setup</strong>
                    <p style="margin-top: 5px; font-size: 14px;">Step-by-step guided process to create timetable with full control over each period.</p>
                </div>
                <div style="border: 2px solid #000; padding: 15px; background: white;">
                    <strong>🍽️ Break Slots</strong>
                    <p style="margin-top: 5px; font-size: 14px;">Break slots are marked in the time_slots table. They appear as empty periods in the timetable.</p>
                </div>
                <div style="border: 2px solid #000; padding: 15px; background: white;">
                    <strong>📅 Day Types</strong>
                    <p style="margin-top: 5px; font-size: 14px;">Weekday slots appear Mon-Fri. Saturday slots appear only on Saturday. This allows different schedules for Saturday.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-populate semester when class is selected
        document.getElementById('classSelect').addEventListener('change', function() {
            let selected = this.options[this.selectedIndex];
            if (selected.value) {
                let semester = selected.getAttribute('data-semester');
                document.getElementById('semesterInput').value = semester;
            }
        });

        // Confirm generation
        function confirmGeneration() {
            let method = document.querySelector('input[name="generation_method"]:checked').value;
            let classId = document.getElementById('classSelect').value;
            
            if (!classId) {
                alert('Please select a class');
                return false;
            }
            
            let message = method === 'auto' 
                ? 'Generate automatic timetable? Break slots will be preserved and will not have subjects. This will overwrite any existing timetable.'
                : 'Proceed to manual timetable setup?';
                
            return confirm(message);
        }

        // Handle overwrite confirmation
        document.getElementById('generationForm').addEventListener('submit', function(e) {
            let needsConfirm = <?php echo isset($warning) ? 'true' : 'false'; ?>;
            if (needsConfirm && !document.getElementById('confirmOverwrite').value) {
                e.preventDefault();
                if (confirm('Timetable already exists. Click OK to overwrite.')) {
                    document.getElementById('confirmOverwrite').value = '1';
                    this.submit();
                }
            }
        });
    </script>
</body>
</html>