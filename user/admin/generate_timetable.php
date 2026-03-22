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

$classes = mysqli_query($conn, "SELECT id, class_name, semester, section FROM classes ORDER BY class_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_timetable'])) {
    $class_id = intval($_POST['class_id']);
    $semester = intval($_POST['semester']);
    $academic_year = trim($_POST['academic_year']);
    $generation_method = $_POST['generation_method'];
    
    $check_existing = mysqli_query($conn, "SELECT id FROM timetable WHERE class_id = $class_id AND semester = $semester AND academic_year = '$academic_year' LIMIT 1");
    $timetable_exists = mysqli_num_rows($check_existing) > 0;
    
    if ($timetable_exists && !isset($_POST['confirm_overwrite'])) {
        $warning = "Timetable already exists for this class. Click Generate again to overwrite.";
    } else {
        if ($generation_method === 'auto') {
            $result = generateAutoTimetable($conn, $class_id, $semester, $academic_year);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        } else {
            header("Location: manual_timetable.php?class_id=$class_id&semester=$semester&academic_year=$academic_year");
            exit;
        }
    }
}

function isTeacherAvailable($conn, $teacher_id, $day_of_week, $slot_id, $current_class_id, $academic_year) {
    $query = "
        SELECT t.id, c.class_name
        FROM timetable t
        JOIN classes c ON t.class_id = c.id
        WHERE t.teacher_id = $teacher_id
        AND t.day_of_week = $day_of_week
        AND t.slot_id = $slot_id
        AND t.academic_year = '$academic_year'
        AND t.class_id != $current_class_id
    ";
    
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) > 0) {
        $conflict = mysqli_fetch_assoc($result);
        return ['available' => false, 'conflict_class' => $conflict['class_name']];
    }
    
    return ['available' => true, 'conflict_class' => null];
}

function getTeacherMaxPeriods($conn, $teacher_id) {
    $query = "SELECT max_periods_per_day FROM teachers WHERE id = $teacher_id";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row ? $row['max_periods_per_day'] : 6;
}

function getAllTeachersDailyLoad($conn, $day_of_week, $academic_year) {
    $query = "
        SELECT teacher_id, COUNT(*) as period_count
        FROM timetable
        WHERE day_of_week = $day_of_week
        AND academic_year = '$academic_year'
        GROUP BY teacher_id
    ";
    
    $result = mysqli_query($conn, $query);
    $loads = [];
    while($row = mysqli_fetch_assoc($result)) {
        $loads[$row['teacher_id']] = $row['period_count'];
    }
    return $loads;
}

function isSubjectScheduled($conn, $subject_id, $class_id, $day_of_week, $slot_id) {
    $query = "
        SELECT id FROM timetable
        WHERE subject_id = $subject_id
        AND class_id = $class_id
        AND day_of_week = $day_of_week
        AND slot_id = $slot_id
    ";
    $result = mysqli_query($conn, $query);
    return mysqli_num_rows($result) > 0;
}

function getSubjectWeeklyCount($conn, $subject_id, $class_id) {
    $query = "
        SELECT COUNT(*) as count
        FROM timetable
        WHERE subject_id = $subject_id
        AND class_id = $class_id
    ";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}

function generateAutoTimetable($conn, $class_id, $semester, $academic_year) {
    // Clear existing timetable for this class only
    mysqli_query($conn, "DELETE FROM timetable WHERE class_id = $class_id AND semester = $semester AND academic_year = '$academic_year'");
    
    // Get subjects for this class
    $subjects = mysqli_query($conn, "
        SELECT s.*, COALESCE(ts.teacher_id, s.teacher_id) as teacher_id
        FROM subjects s 
        LEFT JOIN teacher_subjects ts ON s.id = ts.subject_id AND ts.class_id = $class_id
        WHERE s.class_id = $class_id AND s.semester = $semester
        ORDER BY s.periods_per_week DESC
    ");
    
    if (mysqli_num_rows($subjects) == 0) {
        return ['success' => false, 'message' => 'No subjects found for this class. Please add subjects first.'];
    }
    
    // Get time slots for this class (excluding breaks)
    $slots = mysqli_query($conn, "
        SELECT * FROM time_slots 
        WHERE class_id = $class_id 
        AND is_break = 0
        ORDER BY slot_number
    ");
    
    if (mysqli_num_rows($slots) == 0) {
        return ['success' => false, 'message' => 'No time slots defined for this class. Please add time slots first.'];
    }
    
    // Get break slots separately
    $break_slots = mysqli_query($conn, "
        SELECT * FROM time_slots 
        WHERE class_id = $class_id 
        AND is_break = 1
        ORDER BY slot_number
    ");
    
    $days = [1, 2, 3, 4, 5, 6];
    $day_names = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    
    // Store subjects with their requirements
    $subject_list = [];
    $total_periods_needed = 0;
    
    while($sub = mysqli_fetch_assoc($subjects)) {
        $subject_list[] = [
            'id' => $sub['id'],
            'name' => $sub['subject_name'],
            'code' => $sub['subject_code'],
            'periods_per_week' => $sub['periods_per_week'],
            'teacher_id' => $sub['teacher_id'],
            'allocated' => 0
        ];
        $total_periods_needed += $sub['periods_per_week'];
    }
    
    // Get all non-break slots per day
    $slot_list = [];
    while($slot = mysqli_fetch_assoc($slots)) {
        $slot_list[] = $slot;
    }
    
    // Calculate total available slots
    $available_slots_per_day = count($slot_list);
    $total_available = $available_slots_per_day * 6; // 6 days
    
    // Check if we have enough slots
    if ($total_available < $total_periods_needed) {
        return ['success' => false, 'message' => "Not enough time slots! Need $total_periods_needed periods but only $total_available slots available. Please add more time slots."];
    }
    
    // Track teacher schedule across all classes
    $teacher_schedule = [];
    $existing_schedules = mysqli_query($conn, "
        SELECT teacher_id, day_of_week, slot_id 
        FROM timetable 
        WHERE academic_year = '$academic_year' 
        AND class_id != $class_id
        AND teacher_id IS NOT NULL
    ");
    
    while($existing = mysqli_fetch_assoc($existing_schedules)) {
        $teacher_schedule[$existing['teacher_id']][$existing['day_of_week']][$existing['slot_id']] = true;
    }
    
    // Track daily teacher count for THIS class
    $daily_teacher_count = [];
    
    // Create all possible period slots
    $all_periods = [];
    foreach($days as $day) {
        $is_saturday = ($day == 6);
        foreach($slot_list as $slot) {
            // Check if slot is valid for this day
            if ($is_saturday) {
                if ($slot['day_type'] == 'saturday' || $slot['day_type'] == 'weekday') {
                    $all_periods[] = [
                        'day' => $day,
                        'slot' => $slot
                    ];
                }
            } else {
                if ($slot['day_type'] == 'weekday') {
                    $all_periods[] = [
                        'day' => $day,
                        'slot' => $slot
                    ];
                }
            }
        }
    }
    
    // Shuffle periods for random distribution
    shuffle($all_periods);
    
    $inserted = 0;
    $break_inserted = 0;
    $conflicts_skipped = 0;
    $allocation_log = [];
    
    // Create a copy of subjects for allocation
    $remaining_subjects = $subject_list;
    
    // Sort subjects by periods_per_week (highest first)
    usort($remaining_subjects, function($a, $b) {
        return $b['periods_per_week'] - $a['periods_per_week'];
    });
    
    // Allocate periods to subjects
    foreach($all_periods as $period) {
        $day = $period['day'];
        $slot = $period['slot'];
        $slot_id = $slot['id'];
        
        // Find a subject that still needs periods
        $selected_subject = null;
        $selected_index = -1;
        
        // Try to allocate subjects that still need periods
        for($i = 0; $i < count($remaining_subjects); $i++) {
            $sub = $remaining_subjects[$i];
            
            // Check if subject still needs periods
            if ($sub['allocated'] >= $sub['periods_per_week']) {
                continue;
            }
            
            $teacher_id = $sub['teacher_id'];
            $can_assign = true;
            
            // Check teacher availability
            if ($teacher_id) {
                // Check teacher's daily limit for THIS class
                $current_daily = isset($daily_teacher_count[$teacher_id][$day]) ? $daily_teacher_count[$teacher_id][$day] : 0;
                $max_allowed = getTeacherMaxPeriods($conn, $teacher_id);
                
                if ($current_daily >= $max_allowed) {
                    $can_assign = false;
                }
                
                // Check teacher conflict with other classes
                if ($can_assign && isset($teacher_schedule[$teacher_id][$day][$slot_id])) {
                    $can_assign = false;
                    $conflicts_skipped++;
                }
            }
            
            if ($can_assign) {
                $selected_subject = $sub;
                $selected_index = $i;
                break;
            }
        }
        
        if ($selected_subject) {
            // Allocate this subject to the period
            $teacher_id = $selected_subject['teacher_id'];
            
            // Update allocation
            $remaining_subjects[$selected_index]['allocated']++;
            
            // Update daily teacher count
            if ($teacher_id) {
                if (!isset($daily_teacher_count[$teacher_id])) {
                    $daily_teacher_count[$teacher_id] = [];
                }
                if (!isset($daily_teacher_count[$teacher_id][$day])) {
                    $daily_teacher_count[$teacher_id][$day] = 0;
                }
                $daily_teacher_count[$teacher_id][$day]++;
                
                // Update global teacher schedule
                $teacher_schedule[$teacher_id][$day][$slot_id] = true;
            }
            
            // Insert into timetable
            $teacher_id_val = $teacher_id ? $teacher_id : 'NULL';
            $insert = "INSERT INTO timetable 
                      (class_id, day_of_week, slot_id, subject_id, teacher_id, academic_year, semester, is_locked) 
                      VALUES 
                      ($class_id, $day, $slot_id, {$selected_subject['id']}, $teacher_id_val, '$academic_year', $semester, 0)";
            
            if (mysqli_query($conn, $insert)) {
                $inserted++;
                $allocation_log[] = "Day {$day_names[$day]}: Slot {$slot['slot_number']} - {$selected_subject['code']} ({$selected_subject['name']})";
            }
        } else {
            // No subject available for this slot, leave empty
            $insert = "INSERT INTO timetable 
                      (class_id, day_of_week, slot_id, subject_id, teacher_id, academic_year, semester, is_locked) 
                      VALUES 
                      ($class_id, $day, $slot_id, NULL, NULL, '$academic_year', $semester, 0)";
            mysqli_query($conn, $insert);
            $inserted++;
        }
    }
    
    // Insert break slots
    $break_slot_list = [];
    while($break = mysqli_fetch_assoc($break_slots)) {
        $break_slot_list[] = $break;
    }
    
    foreach($days as $day) {
        $is_saturday = ($day == 6);
        foreach($break_slot_list as $break) {
            if ($is_saturday) {
                if ($break['day_type'] == 'saturday' || $break['day_type'] == 'weekday') {
                    $insert = "INSERT INTO timetable 
                              (class_id, day_of_week, slot_id, subject_id, teacher_id, academic_year, semester, is_locked) 
                              VALUES 
                              ($class_id, $day, {$break['id']}, NULL, NULL, '$academic_year', $semester, 0)";
                    if (mysqli_query($conn, $insert)) {
                        $break_inserted++;
                    }
                }
            } else {
                if ($break['day_type'] == 'weekday') {
                    $insert = "INSERT INTO timetable 
                              (class_id, day_of_week, slot_id, subject_id, teacher_id, academic_year, semester, is_locked) 
                              VALUES 
                              ($class_id, $day, {$break['id']}, NULL, NULL, '$academic_year', $semester, 0)";
                    if (mysqli_query($conn, $insert)) {
                        $break_inserted++;
                    }
                }
            }
        }
    }
    
    // Check allocation results
    $unfulfilled = [];
    $over_allocated = [];
    $total_allocated = 0;
    
    foreach($remaining_subjects as $sub) {
        $total_allocated += $sub['allocated'];
        if ($sub['allocated'] < $sub['periods_per_week']) {
            $unfulfilled[] = $sub['name'] . " (needs " . $sub['periods_per_week'] . ", got " . $sub['allocated'] . ")";
        } elseif ($sub['allocated'] > $sub['periods_per_week']) {
            $over_allocated[] = $sub['name'] . " (needs " . $sub['periods_per_week'] . ", got " . $sub['allocated'] . ")";
        }
    }
    
    $message = "Timetable generated successfully!";
    $message .= " Total periods: " . ($inserted + $break_inserted) . " ($break_inserted breaks).";
    $message .= " Subjects allocated: $total_allocated out of $total_periods_needed required periods.";
    
    if ($conflicts_skipped > 0) {
        $message .= " Skipped $conflicts_skipped teacher conflicts.";
    }
    if (!empty($unfulfilled)) {
        $message .= " ⚠️ Warning: Under-allocated subjects: " . implode(", ", $unfulfilled);
    }
    if (!empty($over_allocated)) {
        $message .= " ⚠️ Warning: Over-allocated subjects: " . implode(", ", $over_allocated);
    }
    
    return ['success' => true, 'message' => $message];
}

$generation_stats = mysqli_query($conn, "
    SELECT 
        c.id as class_id,
        c.class_name, 
        c.semester,
        c.section,
        COUNT(t.id) as period_count,
        SUM(CASE WHEN t.is_locked = 1 THEN 1 ELSE 0 END) as locked_count,
        SUM(CASE WHEN t.subject_id IS NOT NULL THEN 1 ELSE 0 END) as filled_count,
        SUM(CASE WHEN t.subject_id IS NULL AND ts.is_break = 0 THEN 1 ELSE 0 END) as empty_count,
        SUM(CASE WHEN ts.is_break = 1 THEN 1 ELSE 0 END) as break_count
    FROM classes c
    LEFT JOIN timetable t ON c.id = t.class_id
    LEFT JOIN time_slots ts ON t.slot_id = ts.id
    GROUP BY c.id
    ORDER BY c.class_name
");

$max_periods_query = mysqli_query($conn, "
    SELECT 
        c.id as class_id,
        (SUM(CASE WHEN ts.day_type = 'weekday' THEN 1 ELSE 0 END) * 5) + 
        (SUM(CASE WHEN ts.day_type = 'saturday' THEN 1 ELSE 0 END) * 1) as max_possible_periods,
        COUNT(ts.id) as total_slots,
        SUM(CASE WHEN ts.day_type = 'weekday' THEN 1 ELSE 0 END) as weekday_slots,
        SUM(CASE WHEN ts.day_type = 'saturday' THEN 1 ELSE 0 END) as saturday_slots
    FROM classes c
    LEFT JOIN time_slots ts ON c.id = ts.class_id
    GROUP BY c.id
");

$max_periods_map = [];
while($row = mysqli_fetch_assoc($max_periods_query)) {
    $max_periods_map[$row['class_id']] = [
        'max_periods' => $row['max_possible_periods'] ?: 0,
        'total_slots' => $row['total_slots'],
        'weekday_slots' => $row['weekday_slots'],
        'saturday_slots' => $row['saturday_slots']
    ];
}

$stats_data = [];
if ($generation_stats) {
    while($stat = mysqli_fetch_assoc($generation_stats)) {
        $stats_data[] = $stat;
    }
}

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
    <title>Generate Timetable · Admin</title>
    <link rel="stylesheet" href="../../include/css/style.css">
    <style>
        /* Keep all existing styles */
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

        .badge {
            background: var(--red);
            color: white;
            padding: 2px 8px;
            border: 2px solid #000;
            font-size: 11px;
            margin-left: auto;
            font-weight: 900;
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

        .generation-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

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
        }

        .method-option input[type="radio"]:checked + label {
            background: var(--blue);
            color: white;
            transform: translate(-2px, -2px);
            box-shadow: 3px 3px 0 #000;
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
            margin-top: 20px;
        }

        .submit-btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 #000;
        }

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
            margin-bottom: 20px;
            border: 3px solid #000;
            padding: 15px;
            background: #fafafa;
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
            font-size: 16px;
        }

        .period-stats {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .stat-badge {
            padding: 3px 8px;
            border: 2px solid #000;
            font-size: 11px;
            font-weight: 700;
        }

        .stat-filled {
            background: var(--green);
            color: white;
        }

        .stat-empty {
            background: #999;
            color: white;
        }

        .stat-break {
            background: var(--orange);
            color: white;
        }

        .stat-locked {
            background: var(--red);
            color: white;
        }

        .stat-max {
            background: var(--blue);
            color: white;
        }

        .progress-bar {
            height: 25px;
            background: #eee;
            border: 2px solid #000;
            position: relative;
            margin: 10px 0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--green);
            width: 0%;
            transition: width 0.3s ease;
        }

        .status-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            font-size: 12px;
            font-weight: 600;
        }

        .completion-status {
            margin-top: 8px;
            font-size: 12px;
            font-weight: 800;
        }

        .completion-status.complete { color: var(--green); }
        .completion-status.partial { color: var(--orange); }
        .completion-status.low { color: var(--red); }

        .error-box, .success-box, .warning-box {
            margin-bottom: 20px;
            padding: 12px;
            border: 3px solid #000;
            font-weight: 800;
        }

        .error-box { background: var(--red); color: white; }
        .success-box { background: var(--green); color: white; }
        .warning-box { background: var(--orange); color: white; }

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

        @media (max-width: 900px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .generation-container {
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
                <a href="generate_timetable.php" class="nav-item active">
                    <span class="icon">⚡</span>
                    Generate Timetable
                </a>
                <a href="lock_timetable.php" class="nav-item">
                    <span class="icon">🔒</span>
                    Lock Timetable
                </a>
            </div>
        </div>

        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn">🚪 LOGOUT</a>
        </div>
    </div>

    <div class="main-content">
        <div class="content-header">
            <h1>⚡ GENERATE TIMETABLE</h1>
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

        <?php if ($warning): ?>
            <div class="warning-box">⚠️ <?php echo htmlspecialchars($warning); ?></div>
        <?php endif; ?>

        <div class="generation-container">
            <div class="form-container">
                <h2>⚙️ GENERATION SETTINGS</h2>
                
                <div class="break-info">
                    <span class="icon">🍽️</span>
                    <span><strong>Break slots detected:</strong> Break periods will be automatically marked and will not have subjects assigned.</span>
                </div>
                
                <div class="info-box" style="background: var(--orange);">
                    <strong>🔄 Allocation Algorithm:</strong><br>
                    • Subjects are allocated based on their required periods per week<br>
                    • Subjects with higher requirements are prioritized<br>
                    • Teachers cannot be scheduled in multiple classes at the same time<br>
                    • Teacher's daily period limit is respected across ALL classes<br>
                    • Break slots are automatically skipped
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

                    <button type="submit" name="generate_timetable" class="submit-btn" onclick="return confirmGeneration()">
                        ⚡ GENERATE TIMETABLE
                    </button>
                </form>
            </div>

            <div class="status-container">
                <h2>📊 CURRENT TIMETABLE STATUS</h2>
                
                <?php if (!empty($stats_data)): ?>
                    <?php foreach($stats_data as $stat): ?>
                        <?php 
                        $class_id = $stat['class_id'];
                        $max_info = isset($max_periods_map[$class_id]) ? $max_periods_map[$class_id] : ['max_periods' => 0];
                        $max_periods = $max_info['max_periods'];
                        
                        $total_non_break = $stat['period_count'] - $stat['break_count'];
                        $filled_percentage = $total_non_break > 0 ? round(($stat['filled_count'] / $total_non_break) * 100) : 0;
                        
                        if ($filled_percentage == 100) {
                            $status_class = 'complete';
                            $status_message = '✅ Fully Generated';
                        } elseif ($filled_percentage >= 75) {
                            $status_class = 'partial';
                            $status_message = '⚡ Good Progress';
                        } elseif ($filled_percentage >= 50) {
                            $status_class = 'partial';
                            $status_message = '📊 Partial Generation';
                        } elseif ($filled_percentage > 0) {
                            $status_class = 'low';
                            $status_message = '⚠️ Needs Attention';
                        } else {
                            $status_class = 'low';
                            $status_message = '❌ Not Generated';
                        }
                        ?>
                        <div class="class-status">
                            <div class="class-status-header">
                                <span class="class-name-badge"><?php echo htmlspecialchars($stat['class_name']); ?></span>
                                <div class="period-stats">
                                    <span class="stat-badge stat-filled">📖 Filled: <?php echo $stat['filled_count']; ?></span>
                                    <span class="stat-badge stat-empty">⚡ Empty: <?php echo $stat['empty_count']; ?></span>
                                    <?php if($stat['break_count'] > 0): ?>
                                        <span class="stat-badge stat-break">🍽️ Breaks: <?php echo $stat['break_count']; ?></span>
                                    <?php endif; ?>
                                    <span class="stat-badge stat-max">📊 Total: <?php echo $stat['filled_count']; ?>/<?php echo $stat['period_count']; ?></span>
                                    <?php if($stat['locked_count'] > 0): ?>
                                        <span class="stat-badge stat-locked">🔒 Locked: <?php echo $stat['locked_count']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $filled_percentage; ?>%;"></div>
                            </div>
                            
                            <div class="status-labels">
                                <span>0%</span>
                                <span><?php echo $filled_percentage; ?>% Filled</span>
                                <span>100%</span>
                            </div>
                            
                            <div class="completion-status <?php echo $status_class; ?>">
                                <?php echo $status_message; ?> 
                                (<?php echo $stat['filled_count']; ?> out of <?php echo $total_non_break; ?> non-break periods filled)
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; padding: 20px;">No timetable data available. Generate a timetable to see status.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('classSelect').addEventListener('change', function() {
            let selected = this.options[this.selectedIndex];
            if (selected.value) {
                let semester = selected.getAttribute('data-semester');
                document.getElementById('semesterInput').value = semester;
            }
        });

        function confirmGeneration() {
            let method = document.querySelector('input[name="generation_method"]:checked').value;
            let classId = document.getElementById('classSelect').value;
            
            if (!classId) {
                alert('Please select a class');
                return false;
            }
            
            let message = method === 'auto' 
                ? 'Generate automatic timetable?\n\n- Subjects will be allocated based on their required periods\n- Break slots will be preserved\n- Teacher conflicts will be automatically resolved\n- Teachers cannot be scheduled in multiple classes at the same time\n\nThis will overwrite any existing timetable for this class.'
                : 'Proceed to manual timetable setup?';
                
            return confirm(message);
        }

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