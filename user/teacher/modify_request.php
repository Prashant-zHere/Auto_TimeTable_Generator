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

$teacher_subjects = mysqli_query($conn, "
    SELECT s.id, s.subject_code, s.subject_name
    FROM teacher_subjects ts
    JOIN subjects s ON ts.subject_id = s.id
    WHERE ts.teacher_id = $teacher_db_id
    UNION
    SELECT s.id, s.subject_code, s.subject_name
    FROM subjects s
    WHERE s.teacher_id = $teacher_db_id
");

$teacher_periods = mysqli_query($conn, "
    SELECT t.id, t.day_of_week, ts.slot_number, ts.start_time, ts.end_time,
           s.subject_name, s.subject_code, c.class_name
    FROM timetable t
    JOIN time_slots ts ON t.slot_id = ts.id
    LEFT JOIN subjects s ON t.subject_id = s.id
    LEFT JOIN classes c ON t.class_id = c.id
    WHERE t.teacher_id = $teacher_db_id
    AND t.is_locked = 1
    ORDER BY t.day_of_week, ts.slot_number
");

$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_modify'])) {
    $timetable_id = intval($_POST['timetable_id']);
    $new_subject_id = !empty($_POST['new_subject_id']) ? intval($_POST['new_subject_id']) : 'NULL';
    $reason = trim($_POST['modify_reason']);
    
    if (empty($timetable_id) || empty($reason)) {
        $error = 'Please select a period and provide a reason for modification.';
    } else {
        $period_query = mysqli_query($conn, "
            SELECT t.*, s.subject_name, s.subject_code, c.class_name
            FROM timetable t
            LEFT JOIN subjects s ON t.subject_id = s.id
            LEFT JOIN classes c ON t.class_id = c.id
            WHERE t.id = $timetable_id
        ");
        $period = mysqli_fetch_assoc($period_query);
        
        $requested_change = json_encode([
            'action' => 'change_subject',
            'subject_id' => $new_subject_id,
            'timetable_id' => $timetable_id,
            'current_subject' => $period['subject_name'],
            'current_class' => $period['class_name']
        ]);
        
        $insert = "INSERT INTO modify_requests (teacher_id, timetable_id, requested_change, reason, status) 
                   VALUES ($teacher_db_id, $timetable_id, '$requested_change', '$reason', 'pending')";
        
        if (mysqli_query($conn, $insert)) {
            $success = 'Modification request submitted successfully!';
        } else {
            $error = 'Error submitting request: ' . mysqli_error($conn);
        }
    }
}

$recent_modifies = mysqli_query($conn, "
    SELECT mr.*, t.day_of_week, ts.slot_number, ts.start_time, ts.end_time,
           s.subject_name, s.subject_code, c.class_name
    FROM modify_requests mr
    JOIN timetable t ON mr.timetable_id = t.id
    JOIN time_slots ts ON t.slot_id = ts.id
    LEFT JOIN subjects s ON t.subject_id = s.id
    LEFT JOIN classes c ON t.class_id = c.id
    WHERE mr.teacher_id = $teacher_db_id
    ORDER BY mr.requested_at DESC 
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Modification · Teacher</title>
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

        /* Form Container */
        .form-container {
            background: white;
            border: 4px solid #000;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .form-container h2 {
            font-size: 24px;
            margin-bottom: 20px;
            background: var(--yellow);
            display: inline-block;
            padding: 5px 15px;
            border: 2px solid #000;
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
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 4px solid #000;
            font-size: 16px;
            background: white;
            box-shadow: 3px 3px 0 #000;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            background: #fff8cc;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .submit-btn {
            background: var(--red);
            color: white;
            padding: 15px 30px;
            font-weight: 900;
            font-size: 18px;
            border: 3px solid #000;
            cursor: pointer;
            transition: all 0.1s ease;
            width: 100%;
        }

        .submit-btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: 3px 3px 0 #000;
        }

        .info-box {
            background: #e3f2fd;
            border: 3px solid #000;
            padding: 15px;
            margin-bottom: 20px;
        }

        .info-box p {
            margin: 5px 0;
        }

        .current-period-preview {
            background: #f9f9f9;
            border: 2px solid #000;
            padding: 10px;
            margin-top: 5px;
            display: none;
        }

        .current-period-preview.show {
            display: block;
        }

        /* Recent Requests */
        .recent-container {
            background: white;
            border: 4px solid #000;
            padding: 25px;
            box-shadow: var(--shadow);
        }

        .recent-container h3 {
            font-size: 20px;
            margin-bottom: 15px;
            background: var(--yellow);
            display: inline-block;
            padding: 5px 15px;
            border: 2px solid #000;
        }

        .request-item {
            border: 2px solid #000;
            padding: 15px;
            margin-bottom: 10px;
            background: #f9f9f9;
        }

        .request-status {
            display: inline-block;
            padding: 3px 10px;
            border: 2px solid #000;
            font-weight: 800;
            font-size: 12px;
        }

        .status-pending {
            background: var(--orange);
            color: white;
        }

        .status-approved {
            background: var(--green);
            color: white;
        }

        .status-rejected {
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

        .error-box, .success-box {
            margin-bottom: 20px;
            padding: 12px;
            border: 3px solid #000;
            font-weight: 800;
        }

        .error-box {
            background: var(--red);
            color: white;
        }

        .success-box {
            background: var(--green);
            color: white;
        }

        @media (max-width: 900px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .form-row {
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
                <a href="dashboard.php" class="nav-item">📊 Dashboard</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">VIEW</div>
                <a href="timetable.php?view=teacher" class="nav-item">📅 My Timetable</a>
                <a href="timetable.php?view=class" class="nav-item">🏫 Class Timetable</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">REQUESTS</div>
                <a href="leave_request.php" class="nav-item">✈️ Apply for Leave</a>
                <a href="modify_request.php" class="nav-item active">🔄 Request Modification</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">HISTORY</div>
                <a href="leave_history.php" class="nav-item">📋 Leave History</a>
                <a href="modify_history.php" class="nav-item">📋 Modification History</a>
            </div>
        </div>

        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn">🚪 LOGOUT</a>
        </div>
    </div>

    <div class="main-content">
        <div class="content-header">
            <h1>🔄 REQUEST TIMETABLE MODIFICATION</h1>
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

        <div class="form-container">
            <h2>📝 MODIFICATION REQUEST FORM</h2>
            
            <?php if (isset($error)): ?>
                <div class="error-box"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="success-box"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="info-box">
                <p><strong>📌 Important Information:</strong></p>
                <p>• Modification requests are submitted for approval to the administrator</p>
                <p>• You can request to change a subject in your timetable</p>
                <p>• Please provide a valid reason for the modification</p>
                <p>• Approved changes will be applied to the timetable</p>
            </div>

            <form method="post" action="" id="modifyForm">
                <div class="form-group">
                    <label>📅 SELECT PERIOD TO MODIFY *</label>
                    <select name="timetable_id" id="periodSelect" required>
                        <option value="">-- Select Period --</option>
                        <?php while($period = mysqli_fetch_assoc($teacher_periods)): ?>
                            <option value="<?php echo $period['id']; ?>" 
                                    data-day="<?php echo $days[$period['day_of_week']]; ?>"
                                    data-slot="<?php echo $period['slot_number']; ?>"
                                    data-time="<?php echo date('h:i A', strtotime($period['start_time'])); ?> - <?php echo date('h:i A', strtotime($period['end_time'])); ?>"
                                    data-class="<?php echo htmlspecialchars($period['class_name']); ?>"
                                    data-subject="<?php echo htmlspecialchars($period['subject_code']); ?> - <?php echo htmlspecialchars($period['subject_name']); ?>">
                                <?php echo $days[$period['day_of_week']]; ?> | 
                                Slot <?php echo $period['slot_number']; ?> (<?php echo date('h:i A', strtotime($period['start_time'])); ?> - <?php echo date('h:i A', strtotime($period['end_time'])); ?>) | 
                                <?php echo htmlspecialchars($period['class_name']); ?> | 
                                <?php echo htmlspecialchars($period['subject_code']); ?> - <?php echo htmlspecialchars($period['subject_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div id="periodPreview" class="current-period-preview"></div>

                <div class="form-group">
                    <label>📖 NEW SUBJECT (Optional)</label>
                    <select name="new_subject_id">
                        <option value="">-- Keep Current Subject --</option>
                        <?php while($subj = mysqli_fetch_assoc($teacher_subjects)): ?>
                            <option value="<?php echo $subj['id']; ?>">
                                <?php echo htmlspecialchars($subj['subject_code']); ?> - <?php echo htmlspecialchars($subj['subject_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <small>Select a subject to replace current one</small>
                </div>

                <div class="form-group">
                    <label>📝 REASON FOR MODIFICATION *</label>
                    <textarea name="modify_reason" rows="5" placeholder="Please explain why you need this modification (e.g., schedule conflict, subject change, teacher availability, etc.)" required></textarea>
                </div>

                <button type="submit" name="submit_modify" class="submit-btn">🔄 SUBMIT MODIFICATION REQUEST</button>
            </form>
        </div>

        <div class="recent-container">
            <h3>📋 RECENT MODIFICATION REQUESTS</h3>
            
            <?php if (mysqli_num_rows($recent_modifies) > 0): ?>
                <?php while($modify = mysqli_fetch_assoc($recent_modifies)): ?>
                    <div class="request-item">
                        <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <strong>📅 Period:</strong> <?php echo $days[$modify['day_of_week']]; ?> | 
                                Slot <?php echo $modify['slot_number']; ?> (<?php echo date('h:i A', strtotime($modify['start_time'])); ?> - <?php echo date('h:i A', strtotime($modify['end_time'])); ?>)<br>
                                <strong>🏫 Class:</strong> <?php echo htmlspecialchars($modify['class_name']); ?><br>
                                <strong>📖 Current Subject:</strong> <?php echo htmlspecialchars($modify['subject_code']); ?> - <?php echo htmlspecialchars($modify['subject_name']); ?>
                            </div>
                            <div>
                                <span class="request-status status-<?php echo $modify['status']; ?>">
                                    <?php echo strtoupper($modify['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div style="margin-top: 10px;">
                            <strong>Reason:</strong> <?php echo htmlspecialchars($modify['reason']); ?>
                        </div>
                        <div style="margin-top: 5px; font-size: 12px; color: #666;">
                            Requested on: <?php echo date('d M Y, h:i A', strtotime($modify['requested_at'])); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="warning-box">No modification requests found.</div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('periodSelect').addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const previewDiv = document.getElementById('periodPreview');
            
            if (selected.value) {
                const day = selected.getAttribute('data-day');
                const slot = selected.getAttribute('data-slot');
                const time = selected.getAttribute('data-time');
                const className = selected.getAttribute('data-class');
                const subject = selected.getAttribute('data-subject');
                
                previewDiv.innerHTML = `
                    <strong>📋 Selected Period Details:</strong><br>
                    📅 Day: ${day}<br>
                    ⏰ Slot ${slot}: ${time}<br>
                    🏫 Class: ${className}<br>
                    📖 Current Subject: ${subject}
                `;
                previewDiv.classList.add('show');
            } else {
                previewDiv.classList.remove('show');
            }
        });
    </script>
</body>
</html>