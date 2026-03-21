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

// Get class ID from URL
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : '2024-25';
$semester = isset($_GET['semester']) ? intval($_GET['semester']) : 0;

if ($class_id == 0) {
    header('Location: view_timetable.php');
    exit;
}

// Get class info
$class_query = mysqli_query($conn, "SELECT * FROM classes WHERE id = $class_id");
$class_info = mysqli_fetch_assoc($class_query);

if (!$class_info) {
    header('Location: view_timetable.php');
    exit;
}

// Get subjects for this class with teacher information from both sources
$subjects = mysqli_query($conn, "
    SELECT 
        s.*, 
        COALESCE(t1.teacher_name, t2.teacher_name) as teacher_name,
        COALESCE(t1.teacher_id, t2.teacher_id) as teacher_id_assigned
    FROM subjects s
    LEFT JOIN (
        SELECT t.id as teacher_id, u.full_name as teacher_name, ts.subject_id
        FROM teacher_subjects ts
        JOIN teachers t ON ts.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE ts.class_id = $class_id
    ) t1 ON s.id = t1.subject_id
    LEFT JOIN (
        SELECT t.id as teacher_id, u.full_name as teacher_name
        FROM teachers t
        JOIN users u ON t.user_id = u.id
    ) t2 ON s.teacher_id = t2.teacher_id
    WHERE s.class_id = $class_id AND s.semester = " . $class_info['semester'] . "
    ORDER BY s.subject_code
");

// Get teachers for dropdown
$teachers = mysqli_query($conn, "
    SELECT t.id, u.full_name, t.employee_id 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY u.full_name
");

// Get time slots for this class
$time_slots = mysqli_query($conn, "
    SELECT * FROM time_slots 
    WHERE class_id = $class_id 
    ORDER BY slot_number
");

// Handle form submission for updating timetable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_timetable'])) {
    $updates = 0;
    
    foreach($_POST as $key => $value) {
        if (strpos($key, 'period_') === 0) {
            $parts = explode('_', $key);
            $day = intval($parts[1]);
            $slot_id = intval($parts[2]);
            $subject_id = !empty($value) ? intval($value) : 'NULL';
            
            // Get teacher_id from subject (check both subject table and teacher_subjects)
            $teacher_id = 'NULL';
            if ($subject_id != 'NULL') {
                // First check if teacher is assigned directly in subjects table
                $subject_query = mysqli_query($conn, "SELECT teacher_id FROM subjects WHERE id = $subject_id");
                if ($subject_data = mysqli_fetch_assoc($subject_query)) {
                    if ($subject_data['teacher_id']) {
                        $teacher_id = $subject_data['teacher_id'];
                    } else {
                        // If not in subjects, check teacher_subjects table
                        $ts_query = mysqli_query($conn, "SELECT teacher_id FROM teacher_subjects WHERE subject_id = $subject_id AND class_id = $class_id LIMIT 1");
                        if ($ts_data = mysqli_fetch_assoc($ts_query)) {
                            $teacher_id = $ts_data['teacher_id'];
                        }
                    }
                }
            }
            
            // Check if entry exists
            $check = mysqli_query($conn, "SELECT id FROM timetable WHERE class_id = $class_id AND day_of_week = $day AND slot_id = $slot_id");
            
            if (mysqli_num_rows($check) > 0) {
                // Update existing entry
                $update = "UPDATE timetable SET 
                          subject_id = $subject_id, 
                          teacher_id = $teacher_id,
                          academic_year = '$academic_year',
                          semester = " . $class_info['semester'] . "
                          WHERE class_id = $class_id AND day_of_week = $day AND slot_id = $slot_id";
                mysqli_query($conn, $update);
                $updates++;
            } else {
                // Insert new entry
                $insert = "INSERT INTO timetable (class_id, day_of_week, slot_id, subject_id, teacher_id, academic_year, semester, is_locked) 
                          VALUES ($class_id, $day, $slot_id, $subject_id, $teacher_id, '$academic_year', " . $class_info['semester'] . ", 0)";
                mysqli_query($conn, $insert);
                $updates++;
            }
        }
    }
    
    $success = "Timetable updated successfully! $updates changes saved.";
}

// Get current timetable data with teacher information
$timetable_data = [];
$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

$timetable_query = mysqli_query($conn, "
    SELECT 
        t.*, 
        s.subject_name, 
        s.subject_code, 
        s.subject_type,
        COALESCE(u.full_name, ut.full_name) as teacher_name
    FROM timetable t
    LEFT JOIN subjects s ON t.subject_id = s.id
    LEFT JOIN teacher_subjects ts ON s.id = ts.subject_id AND ts.class_id = t.class_id
    LEFT JOIN teachers tea ON t.teacher_id = tea.id
    LEFT JOIN users u ON tea.user_id = u.id
    LEFT JOIN teachers tea2 ON ts.teacher_id = tea2.id
    LEFT JOIN users ut ON tea2.user_id = ut.id
    WHERE t.class_id = $class_id
");

while($row = mysqli_fetch_assoc($timetable_query)) {
    $timetable_data[$row['day_of_week']][$row['slot_id']] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Timetable Editor · Admin</title>
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

        .class-info {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: var(--shadow);
        }

        .class-details {
            background: var(--blue);
            color: white;
            padding: 10px 20px;
            border: 3px solid #000;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: 3px solid #000;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.1s ease;
            display: inline-block;
        }

        .btn-primary {
            background: var(--green);
            color: white;
        }

        .btn-secondary {
            background: var(--red);
            color: white;
        }

        .btn-warning {
            background: var(--orange);
            color: white;
        }

        .btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: 3px 3px 0 #000;
        }

        .editor-container {
            background: white;
            border: 4px solid #000;
            padding: 25px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }

        .editor-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .editor-table th {
            background: var(--blue);
            color: white;
            border: 3px solid #000;
            padding: 12px;
            font-weight: 900;
            text-align: center;
        }

        .editor-table td {
            border: 3px solid #000;
            padding: 10px;
            vertical-align: top;
            min-width: 180px;
        }

        .time-slot-cell {
            background: var(--yellow);
            font-weight: 800;
            text-align: center;
            white-space: nowrap;
        }

        .break-cell {
            background: #fff0f0;
        }

        .break-display {
            text-align: center;
            padding: 15px;
        }

        .break-icon {
            font-size: 24px;
        }

        .break-label {
            font-weight: 900;
            color: var(--red);
            margin-top: 5px;
        }

        .break-time {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
        }

        .select-subject {
            width: 100%;
            padding: 8px;
            border: 2px solid #000;
            font-size: 13px;
            background: white;
            cursor: pointer;
        }

        .teacher-info {
            margin-top: 5px;
            font-size: 11px;
            padding: 3px;
            background: #e8f5e9;
            border-left: 3px solid var(--green);
        }

        .teacher-assigned {
            color: var(--green);
            font-weight: 600;
        }

        .teacher-not-assigned {
            color: var(--red);
            font-weight: 600;
        }

        .empty-badge {
            display: inline-block;
            background: #999;
            color: white;
            padding: 2px 6px;
            font-size: 10px;
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

        .legend-color.break { 
            background: #fff0f0; 
            border: 2px dashed var(--red); 
        }
        .legend-color.empty { 
            background: #f0f0f0; 
        }

        .form-actions {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .save-btn {
            background: var(--green);
            color: white;
            padding: 15px 30px;
            font-size: 18px;
        }

        .cancel-btn {
            background: var(--red);
            color: white;
            padding: 15px 30px;
            font-size: 18px;
            text-decoration: none;
        }

        .error-box, .success-box {
            margin-bottom: 20px;
        }

        .tips-box {
            margin-top: 30px;
            background: #333;
            color: white;
            border: 4px solid #000;
            padding: 20px;
        }

        .tips-box h3 {
            margin-bottom: 15px;
            color: var(--yellow);
        }

        .tips-box ul {
            list-style: none;
        }

        .tips-box li {
            padding: 5px 0;
            border-bottom: 1px solid #555;
        }

        @media (max-width: 900px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
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
                <a href="generate_timetable.php" class="nav-item">⚡ Generate Timetable</a>
                <a href="manual_timetable.php?class_id=<?php echo $class_id; ?>" class="nav-item active">✏️ Manual Edit</a>
                <a href="time_slots.php" class="nav-item">⏰ Time Slots</a>
            </div>
        </div>

        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn">🚪 LOGOUT</a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="content-header">
            <h1>✏️ MANUAL TIMETABLE EDITOR</h1>
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

        <!-- Class Information -->
        <div class="class-info">
            <div class="class-details">
                <strong>📚 Class:</strong> <?php echo htmlspecialchars($class_info['class_name']); ?> | 
                <strong>Semester:</strong> <?php echo $class_info['semester']; ?> | 
                <strong>Section:</strong> <?php echo htmlspecialchars($class_info['section']); ?> |
                <strong>Year:</strong> <?php echo htmlspecialchars($academic_year); ?>
            </div>
            <div class="action-buttons">
                <a href="view_timetable.php?class_id=<?php echo $class_id; ?>" class="btn btn-secondary">← BACK TO VIEW</a>
                <a href="generate_timetable.php?class_id=<?php echo $class_id; ?>" class="btn btn-warning">⚡ AUTO GENERATE</a>
            </div>
        </div>

        <!-- Timetable Editor Form -->
        <form method="post" action="">
            <div class="editor-container">
                <h3 style="margin-bottom: 20px;">📅 Click on any dropdown to change the subject for that period</h3>
                
                <table class="editor-table">
                    <thead>
                        <tr>
                            <th>Time / Day</th>
                            <?php for($day = 1; $day <= 6; $day++): ?>
                                <th><?php echo $days[$day]; ?></th>
                            <?php endfor; ?>
                        </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($time_slots, 0);
                        while($slot = mysqli_fetch_assoc($time_slots)): 
                        ?>
                            <tr class="<?php echo $slot['is_break'] ? 'break-cell' : ''; ?>">
                                <td class="time-slot-cell">
                                    Slot <?php echo $slot['slot_number']; ?>
                                    <?php if($slot['is_break']): ?>
                                        <span style="display: block; font-size: 12px;">🍽️ BREAK</span>
                                    <?php endif; ?>
                                    <small><?php echo date('h:i A', strtotime($slot['start_time'])); ?> - <?php echo date('h:i A', strtotime($slot['end_time'])); ?></small>
                                </td>
                                <?php for($day = 1; $day <= 6; $day++): ?>
                                    <td class="<?php echo $slot['is_break'] ? 'break-cell' : ''; ?>">
                                        <?php if($slot['is_break']): ?>
                                            <div class="break-display">
                                                <div class="break-icon">🍽️</div>
                                                <div class="break-label">BREAK</div>
                                                <div class="break-time">
                                                    <?php echo date('h:i A', strtotime($slot['start_time'])); ?> - 
                                                    <?php echo date('h:i A', strtotime($slot['end_time'])); ?>
                                                </div>
                                            </div>
                                        <?php else: 
                                            $current = isset($timetable_data[$day][$slot['id']]) ? $timetable_data[$day][$slot['id']] : null;
                                        ?>
                                            <select name="period_<?php echo $day; ?>_<?php echo $slot['id']; ?>" class="select-subject" onchange="updateTeacherInfo(this, <?php echo $day; ?>, <?php echo $slot['id']; ?>)">
                                                <option value="">-- Empty Period --</option>
                                                <?php 
                                                mysqli_data_seek($subjects, 0);
                                                while($subject = mysqli_fetch_assoc($subjects)): 
                                                    $selected = ($current && $current['subject_id'] == $subject['id']) ? 'selected' : '';
                                                ?>
                                                    <option value="<?php echo $subject['id']; ?>" 
                                                            data-teacher="<?php echo htmlspecialchars($subject['teacher_name']); ?>" 
                                                            data-teacher-id="<?php echo $subject['teacher_id_assigned']; ?>"
                                                            <?php echo $selected; ?>>
                                                        <?php echo htmlspecialchars($subject['subject_code']); ?> - <?php echo htmlspecialchars($subject['subject_name']); ?>
                                                        <?php if($subject['teacher_name']): ?>
                                                            (👨‍🏫 <?php echo htmlspecialchars($subject['teacher_name']); ?>)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                            <div class="teacher-info" id="teacher_<?php echo $day; ?>_<?php echo $slot['id']; ?>">
                                                <?php 
                                                $teacher_name = '';
                                                if ($current && !empty($current['teacher_name'])) {
                                                    $teacher_name = $current['teacher_name'];
                                                } elseif ($current && $current['subject_id']) {
                                                    // Try to get teacher from subject
                                                    $subj_teacher = mysqli_query($conn, "
                                                        SELECT COALESCE(u.full_name, ut.full_name) as teacher
                                                        FROM subjects s
                                                        LEFT JOIN teacher_subjects ts ON s.id = ts.subject_id AND ts.class_id = $class_id
                                                        LEFT JOIN teachers t ON COALESCE(s.teacher_id, ts.teacher_id) = t.id
                                                        LEFT JOIN users u ON t.user_id = u.id
                                                        LEFT JOIN teachers t2 ON ts.teacher_id = t2.id
                                                        LEFT JOIN users ut ON t2.user_id = ut.id
                                                        WHERE s.id = " . $current['subject_id'] . "
                                                        LIMIT 1
                                                    ");
                                                    if ($subj_teacher && $teacher_data = mysqli_fetch_assoc($subj_teacher)) {
                                                        $teacher_name = $teacher_data['teacher'];
                                                    }
                                                }
                                                ?>
                                                <?php if($teacher_name): ?>
                                                    <span class="teacher-assigned">✅ Teacher: <?php echo htmlspecialchars($teacher_name); ?></span>
                                                <?php else: ?>
                                                    <span class="teacher-not-assigned">⚠️ No teacher assigned to this subject</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <!-- Legend -->
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color break"></div>
                        <span>Break Period (Cannot be edited)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color empty"></div>
                        <span>Empty Period - No Subject</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: var(--blue);"></div>
                        <span>Theory Subject</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: var(--green);"></div>
                        <span>Lab/Practical Subject</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #e8f5e9;"></div>
                        <span>Teacher Assigned</span>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="update_timetable" class="btn btn-primary save-btn">💾 SAVE CHANGES</button>
                <a href="view_timetable.php?class_id=<?php echo $class_id; ?>" class="btn btn-secondary cancel-btn">❌ CANCEL</a>
            </div>
        </form>

        <!-- Quick Tips -->
        <div class="tips-box">
            <h3>💡 QUICK TIPS</h3>
            <ul>
                <li>• <strong>Click on any dropdown</strong> to change the subject for that period</li>
                <li>• <strong>Empty Period</strong> means no class is scheduled</li>
                <li>• <strong>Break periods</strong> cannot be edited - they are fixed</li>
                <li>• <strong>Teacher names</strong> appear automatically based on subject allocation (from both subjects table and teacher_subjects table)</li>
                <li>• <strong>✅ Teacher Assigned</strong> - Subject has a teacher allocated</li>
                <li>• <strong>⚠️ No teacher assigned</strong> - Subject needs teacher allocation</li>
                <li>• Changes are saved immediately when you click "Save Changes"</li>
            </ul>
        </div>
    </div>

    <script>
        // Update teacher info when subject selection changes
        function updateTeacherInfo(selectElement, day, slotId) {
            let selectedOption = selectElement.options[selectElement.selectedIndex];
            let teacherName = selectedOption.getAttribute('data-teacher') || '';
            let teacherDiv = document.getElementById('teacher_' + day + '_' + slotId);
            
            if (teacherDiv) {
                if (teacherName) {
                    teacherDiv.innerHTML = '<span class="teacher-assigned">✅ Teacher: ' + teacherName + '</span>';
                } else {
                    teacherDiv.innerHTML = '<span class="teacher-not-assigned">⚠️ No teacher assigned to this subject</span>';
                }
            }
        }
        
        // Confirm before leaving
        let formChanged = false;
        document.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', () => {
                formChanged = true;
            });
        });
        
        window.addEventListener('beforeunload', (e) => {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
        
        document.querySelector('form').addEventListener('submit', () => {
            formChanged = false;
        });
    </script>
</body>
</html>