<?php
session_start();
require_once '../../include/conn/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php');
    exit;
}

$error = '';
$success = '';

$classes = mysqli_query($conn, "SELECT id, class_name, semester, section FROM classes ORDER BY class_name");

$selected_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : (isset($_POST['class_id']) ? intval($_POST['class_id']) : 0);

$existing_slots = [];
$existing_breaks = [];
if ($selected_class > 0) {
    $slot_query = mysqli_query($conn, "SELECT slot_number, is_break FROM time_slots WHERE class_id = $selected_class ORDER BY slot_number");
    while($slot = mysqli_fetch_assoc($slot_query)) {
        $existing_slots[] = $slot['slot_number'];
        if($slot['is_break']) {
            $existing_breaks[] = $slot['slot_number'];
        }
    }
}

$class_info = null;
if ($selected_class > 0) {
    $class_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM classes WHERE id = $selected_class"));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_time_slot'])) {
    $class_id = intval($_POST['class_id']);
    $slot_number = intval($_POST['slot_number']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $day_type = $_POST['day_type'];
    $is_break = isset($_POST['is_break']) ? 1 : 0;

    if (empty($class_id) || empty($slot_number) || empty($start_time) || empty($end_time)) {
        $error = 'Please fill all required fields.';
    } elseif ($start_time >= $end_time) {
        $error = 'End time must be after start time.';
    } else {
        // Check if this slot number already exists for this class
        $check = mysqli_query($conn, "SELECT id FROM time_slots WHERE class_id = $class_id AND slot_number = $slot_number");
        if (mysqli_num_rows($check) > 0) {
            $error = 'Slot number ' . $slot_number . ' already exists for this class. Please use a different slot number.';
        } else {
            $insert = "INSERT INTO time_slots (class_id, slot_number, start_time, end_time, day_type, is_break) 
                      VALUES ($class_id, $slot_number, '$start_time', '$end_time', '$day_type', $is_break)";
            
            if (mysqli_query($conn, $insert)) {
                $success = 'Time slot added successfully!';
                $_POST = array();
            } else {
                $error = 'Error adding time slot: ' . mysqli_error($conn);
            }
        }
    }
}

$next_slot_number = 1;
if (!empty($existing_slots)) {
    $next_slot_number = max($existing_slots) + 1;
}
$pending_leaves = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as count FROM leave_requests WHERE status='pending'"
))['count'];

$pending_modifies = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as count FROM modify_requests WHERE status='pending'"
))['count'];

$full_name = $_SESSION['full_name'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Time Slot · Admin</title>
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

        .form-container {
            max-width: 700px;
            margin: 0 auto;
            border: 4px solid #000;
            background: var(--yellow);
            padding: 35px;
            box-shadow: var(--shadow);
        }

        .form-container h2 {
            font-size: 32px;
            margin-bottom: 30px;
            border-bottom: 3px solid #000;
            display: inline-block;
        }

        .class-selector {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .class-selector h3 {
            font-size: 18px;
            margin-bottom: 15px;
            background: var(--blue);
            color: white;
            display: inline-block;
            padding: 5px 15px;
            border: 2px solid #000;
        }

        .class-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }

        .class-card {
            border: 3px solid #000;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.1s ease;
            background: #f9f9f9;
        }

        .class-card:hover {
            transform: translate(-2px, -2px);
            box-shadow: 3px 3px 0 #000;
            background: var(--yellow);
        }

        .class-card.selected {
            background: var(--blue);
            color: white;
            border: 3px solid #000;
            transform: translate(-2px, -2px);
            box-shadow: 3px 3px 0 #000;
        }

        .class-card.selected .class-name {
            color: white;
        }

        .class-name {
            font-weight: 900;
            font-size: 16px;
        }

        .class-detail {
            font-size: 12px;
            color: #666;
        }

        .class-card.selected .class-detail {
            color: #ddd;
        }

        .selected-class-info {
            background: var(--blue);
            color: white;
            padding: 15px;
            border: 4px solid #000;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: white;
            border: 4px solid #000;
            box-shadow: 3px 3px 0 #000;
            margin: 15px 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            border: 3px solid #000;
            box-shadow: none;
            margin-right: 10px;
        }

        .checkbox-group label {
            background: transparent;
            color: #000;
            box-shadow: none;
            padding: 0;
            margin: 0;
            font-weight: 800;
        }

        .day-type-selector {
            display: flex;
            gap: 20px;
            margin: 15px 0;
            padding: 15px;
            background: white;
            border: 4px solid #000;
        }

        .day-type-option {
            flex: 1;
            text-align: center;
        }

        .day-type-option input[type="radio"] {
            display: none;
        }

        .day-type-option label {
            display: block;
            padding: 10px;
            border: 3px solid #000;
            font-weight: 800;
            cursor: pointer;
            background: #f0f0f0;
        }

        .day-type-option input[type="radio"]:checked + label {
            background: var(--blue);
            color: white;
        }

        .slot-preview {
            background: #333;
            color: white;
            padding: 15px;
            border: 4px solid #000;
            margin: 20px 0;
        }

        .existing-slots {
            background: white;
            border: 4px solid #000;
            padding: 15px;
            margin-top: 20px;
        }

        .existing-slots h4 {
            font-size: 16px;
            margin-bottom: 10px;
            background: var(--yellow);
            display: inline-block;
            padding: 3px 10px;
            border: 2px solid #000;
        }

        .slot-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .slot-tag {
            padding: 5px 10px;
            border: 2px solid #000;
            font-weight: 700;
            font-size: 13px;
        }

        .slot-tag.weekday {
            background: var(--green);
            color: white;
        }

        .slot-tag.saturday {
            background: var(--orange);
            color: white;
        }

        .slot-tag.break {
            background: var(--red);
            color: white;
        }

        .slot-tag.break:after {
            content: " BREAK";
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
            transition: all 0.1s ease;
        }

        .submit-btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 #000;
        }

        .back-link {
            margin-top: 20px;
            text-align: center;
        }

        .back-link a {
            color: #000;
            font-weight: 800;
            text-decoration: none;
            border: 2px solid #000;
            padding: 8px 15px;
            background: #fff;
            box-shadow: 3px 3px 0 #000;
            display: inline-block;
            transition: all 0.1s ease;
        }

        .error-box, .success-box {
            margin-bottom: 20px;
        }

        .info-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .break-indicator {
            display: inline-block;
            background: var(--red);
            color: white;
            padding: 2px 8px;
            border: 2px solid #000;
            font-size: 11px;
            font-weight: 800;
            margin-left: 10px;
        }

        .sample-timetable {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            margin-top: 30px;
        }

        .sample-timetable h3 {
            margin-bottom: 15px;
            background: var(--yellow);
            display: inline-block;
            padding: 5px 15px;
            border: 2px solid #000;
        }

        .sample-row {
            display: grid;
            grid-template-columns: 80px 1fr 1fr 1fr;
            gap: 10px;
            padding: 8px;
            border-bottom: 2px solid #000;
        }

        .sample-row.header {
            font-weight: 900;
            background: var(--blue);
            color: white;
        }

        .break-row {
            background: #ffeeee;
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
                <a href="add_time_slot.php" class="nav-item yellow active">
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
            <h1>➕ ADD NEW TIME SLOT</h1>
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

        <div class="class-selector">
            <h3>📌 1. SELECT CLASS</h3>
            <div class="class-grid">
                <?php while($class = mysqli_fetch_assoc($classes)): ?>
                    <div class="class-card <?php echo ($selected_class == $class['id']) ? 'selected' : ''; ?>" 
                         onclick="window.location.href='?class_id=<?php echo $class['id']; ?>'">
                        <div class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></div>
                        <div class="class-detail">Sem <?php echo $class['semester']; ?> | Sec <?php echo $class['section']; ?></div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <?php if ($selected_class > 0 && $class_info): ?>
            <div class="selected-class-info">
                <div>
                    <strong>SELECTED CLASS:</strong> <?php echo htmlspecialchars($class_info['class_name']); ?> 
                    (Semester <?php echo $class_info['semester']; ?>, Section <?php echo $class_info['section']; ?>)
                </div>
                <div>
                    <strong>Total Slots:</strong> <?php echo count($existing_slots); ?> 
                    (<?php echo count($existing_breaks); ?> breaks)
                </div>
            </div>

            <?php if (!empty($existing_slots)): ?>
                <div class="existing-slots">
                    <h4>📋 EXISTING SLOTS FOR THIS CLASS</h4>
                    <div class="slot-tags">
                        <?php
                        $slot_details = mysqli_query($conn, "SELECT slot_number, day_type, is_break FROM time_slots WHERE class_id = $selected_class ORDER BY slot_number");
                        while($slot = mysqli_fetch_assoc($slot_details)):
                            $class = $slot['day_type'];
                            if($slot['is_break']) $class .= ' break';
                        ?>
                            <span class="slot-tag <?php echo $class; ?>">
                                Slot <?php echo $slot['slot_number']; ?> 
                                (<?php echo $slot['day_type'] == 'weekday' ? 'M-F' : 'Sat'; ?>)
                                <?php if($slot['is_break']): ?>🍽️<?php endif; ?>
                            </span>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <h2>⏰ 2. ADD TIME SLOT DETAILS</h2>
                
                <form method="post" action="">
                    <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                    
                    <div class="form-group">
                        <label class="required-field">🔢 SLOT NUMBER</label>
                        <input type="number" name="slot_number" id="slot_number" min="1" max="20" required 
                               value="<?php echo isset($_POST['slot_number']) ? $_POST['slot_number'] : $next_slot_number; ?>"
                               placeholder="Enter slot number">
                        <div class="info-text">
                            Suggested next slot: <?php echo $next_slot_number; ?> 
                            (Slot numbers must be unique for this class)
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="required-field">⏱️ START TIME</label>
                            <input type="time" name="start_time" id="start_time" required 
                                   value="<?php echo isset($_POST['start_time']) ? $_POST['start_time'] : '09:00'; ?>">
                        </div>
                        <div class="form-group">
                            <label class="required-field">⏱️ END TIME</label>
                            <input type="time" name="end_time" id="end_time" required 
                                   value="<?php echo isset($_POST['end_time']) ? $_POST['end_time'] : '10:00'; ?>">
                        </div>
                    </div>

                    <div class="day-type-selector">
                        <div class="day-type-option">
                            <input type="radio" name="day_type" id="day_weekday" value="weekday" 
                                   <?php echo (!isset($_POST['day_type']) || $_POST['day_type'] == 'weekday') ? 'checked' : ''; ?>>
                            <label for="day_weekday">📅 Weekday (Mon-Fri)</label>
                        </div>
                        <div class="day-type-option">
                            <input type="radio" name="day_type" id="day_saturday" value="saturday"
                                   <?php echo (isset($_POST['day_type']) && $_POST['day_type'] == 'saturday') ? 'checked' : ''; ?>>
                            <label for="day_saturday">📆 Saturday Only</label>
                        </div>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" name="is_break" id="is_break" value="1"
                               <?php echo (isset($_POST['is_break']) && $_POST['is_break'] == '1') ? 'checked' : ''; ?>>
                        <label for="is_break">🍽️ This is a BREAK slot (lunch/short break)</label>
                    </div>

                    <div class="slot-preview" id="slotPreview">
                        <strong>Preview:</strong><br>
                        Slot <span id="previewSlot"><?php echo isset($_POST['slot_number']) ? $_POST['slot_number'] : $next_slot_number; ?></span><br>
                        Time: <span id="previewTime"><?php echo isset($_POST['start_time']) ? $_POST['start_time'] : '09:00'; ?> - <?php echo isset($_POST['end_time']) ? $_POST['end_time'] : '10:00'; ?></span><br>
                        Type: <span id="previewDay">Weekday</span>
                        <span id="previewBreak" style="display: none;" class="break-indicator">BREAK</span>
                    </div>

                    <button type="submit" name="add_time_slot" class="submit-btn">ADD TIME SLOT →</button>
                </form>

                <div style="margin-top: 20px;">
                    <h4 style="margin-bottom: 10px;">⚡ Quick Add Common Slots</h4>
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                        <button onclick="quickAdd(1, '09:00', '10:00', false)" class="action-btn view-btn">Slot 1 (9-10)</button>
                        <button onclick="quickAdd(2, '10:00', '11:00', false)" class="action-btn view-btn">Slot 2 (10-11)</button>
                        <button onclick="quickAdd(3, '11:00', '12:00', false)" class="action-btn view-btn">Slot 3 (11-12)</button>
                        <button onclick="quickAdd(4, '12:00', '13:00', false)" class="action-btn view-btn">Slot 4 (12-1)</button>
                        <button onclick="quickAdd(5, '13:00', '14:00', true)" class="action-btn view-btn" style="background: var(--red);">🍽️ Lunch (1-2)</button>
                        <button onclick="quickAdd(6, '14:00', '15:00', false)" class="action-btn view-btn">Slot 5 (2-3)</button>
                        <button onclick="quickAdd(7, '15:00', '16:00', false)" class="action-btn view-btn">Slot 6 (3-4)</button>
                        <button onclick="quickAdd(8, '16:00', '17:00', false)" class="action-btn view-btn">Slot 7 (4-5)</button>
                    </div>
                </div>

                <div class="back-link">
                    <a href="time_slots.php?class_id=<?php echo $selected_class; ?>">← VIEW ALL TIME SLOTS</a>
                </div>
            </div>

            <div class="sample-timetable">
                <h3>📋 SAMPLE TIMETABLE WITH BREAKS</h3>
                <div class="sample-row header">
                    <div>Slot</div>
                    <div>Time</div>
                    <div>Type</div>
                    <div>Description</div>
                </div>
                <div class="sample-row">
                    <div>1</div>
                    <div>09:00 - 10:00</div>
                    <div>Period</div>
                    <div>First period</div>
                </div>
                <div class="sample-row">
                    <div>2</div>
                    <div>10:00 - 11:00</div>
                    <div>Period</div>
                    <div>Second period</div>
                </div>
                <div class="sample-row">
                    <div>3</div>
                    <div>11:00 - 12:00</div>
                    <div>Period</div>
                    <div>Third period</div>
                </div>
                <div class="sample-row">
                    <div>4</div>
                    <div>12:00 - 13:00</div>
                    <div>Period</div>
                    <div>Fourth period</div>
                </div>
                <div class="sample-row break-row">
                    <div>5</div>
                    <div>13:00 - 14:00</div>
                    <div><span class="break-indicator">BREAK</span></div>
                    <div>Lunch Break</div>
                </div>
                <div class="sample-row">
                    <div>6</div>
                    <div>14:00 - 15:00</div>
                    <div>Period</div>
                    <div>Fifth period</div>
                </div>
                <div class="sample-row">
                    <div>7</div>
                    <div>15:00 - 16:00</div>
                    <div>Period</div>
                    <div>Sixth period</div>
                </div>
                <div class="sample-row">
                    <div>8</div>
                    <div>16:00 - 17:00</div>
                    <div>Period</div>
                    <div>Seventh period</div>
                </div>
                <p style="margin-top: 10px;"><small>Note: Break slots will be displayed differently in timetable and won't have subjects assigned.</small></p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function updatePreview() {
            let slot = document.getElementById('slot_number').value;
            let start = document.getElementById('start_time').value;
            let end = document.getElementById('end_time').value;
            let dayType = document.querySelector('input[name="day_type"]:checked');
            let isBreak = document.getElementById('is_break').checked;
            
            document.getElementById('previewSlot').textContent = slot || '?';
            document.getElementById('previewTime').textContent = (start || '09:00') + ' - ' + (end || '10:00');
            document.getElementById('previewDay').textContent = dayType ? (dayType.value == 'weekday' ? 'Weekday' : 'Saturday') : 'Weekday';
            
            let breakSpan = document.getElementById('previewBreak');
            if(isBreak) {
                breakSpan.style.display = 'inline';
            } else {
                breakSpan.style.display = 'none';
            }
        }

        document.getElementById('slot_number').addEventListener('input', updatePreview);
        document.getElementById('start_time').addEventListener('input', updatePreview);
        document.getElementById('end_time').addEventListener('input', updatePreview);
        document.querySelectorAll('input[name="day_type"]').forEach(radio => {
            radio.addEventListener('change', updatePreview);
        });
        document.getElementById('is_break').addEventListener('change', updatePreview);

        function quickAdd(slot, start, end, isBreak) {
            document.getElementById('slot_number').value = slot;
            document.getElementById('start_time').value = start;
            document.getElementById('end_time').value = end;
            document.getElementById('is_break').checked = isBreak;
            updatePreview();
        }

        document.querySelector('form').addEventListener('submit', function(e) {
            let startTime = document.getElementById('start_time').value;
            let endTime = document.getElementById('end_time').value;
            
            if (startTime >= endTime) {
                alert('End time must be after start time');
                e.preventDefault();
            }
        });

        updatePreview();
    </script>
</body>
</html>