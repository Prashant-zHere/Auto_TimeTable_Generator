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

$teacher_timetable = [];
$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

if ($teacher_db_id > 0) {
    $timetable_query = mysqli_query($conn, "
        SELECT 
            t.*, 
            s.subject_name, s.subject_code,
            c.class_name,
            ts.start_time, ts.end_time, ts.slot_number, ts.is_break as slot_is_break
        FROM timetable t
        LEFT JOIN subjects s ON t.subject_id = s.id
        LEFT JOIN classes c ON t.class_id = c.id
        JOIN time_slots ts ON t.slot_id = ts.id
        WHERE t.teacher_id = $teacher_db_id
        AND t.is_locked = 1
        ORDER BY t.day_of_week, ts.slot_number
    ");
    
    while($row = mysqli_fetch_assoc($timetable_query)) {
        $day = $row['day_of_week'];
        $teacher_timetable[$day][] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_leave'])) {
    $leave_date = $_POST['leave_date'];
    $slot_id = !empty($_POST['slot_id']) ? intval($_POST['slot_id']) : 'NULL';
    $reason = trim($_POST['reason']);
    
    if (empty($leave_date) || empty($reason)) {
        $error = 'Please fill all required fields.';
    } else {
        if (strtotime($leave_date) < strtotime(date('Y-m-d'))) {
            $error = 'Cannot apply for leave on past dates.';
        } else {
            $slot_value = ($slot_id == 'NULL') ? 'NULL' : $slot_id;
            $insert = "INSERT INTO leave_requests (teacher_id, leave_date, slot_id, reason, status) 
                       VALUES ($teacher_db_id, '$leave_date', $slot_value, '$reason', 'pending')";
            
            if (mysqli_query($conn, $insert)) {
                $success = 'Leave request submitted successfully!';
            } else {
                $error = 'Error submitting request: ' . mysqli_error($conn);
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
    <title>Apply for Leave · Teacher</title>
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

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 4px solid #000;
            font-size: 16px;
            background: white;
            box-shadow: 3px 3px 0 #000;
        }

        .form-group input:focus,
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
                <a href="leave_request.php" class="nav-item active">✈️ Apply for Leave</a>
                <a href="modify_request.php" class="nav-item">🔄 Request Modification</a>
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
            <h1>✈️ APPLY FOR LEAVE</h1>
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
            <h2>📝 LEAVE APPLICATION FORM</h2>
            
            <?php if (isset($error)): ?>
                <div class="error-box"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="success-box"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="info-box">
                <p><strong>📌 Important Information:</strong></p>
                <p>• Leave requests are submitted for approval to the administrator</p>
                <p>• Full day leave means all periods for that day</p>
                <p>• Specific slot leave only applies to that particular period</p>
                <p>• Please provide a valid reason for your leave</p>
            </div>

            <form method="post" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label>📅 LEAVE DATE *</label>
                        <input type="date" name="leave_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>⏰ TIME SLOT (Optional)</label>
                        <select name="slot_id">
                            <option value="">-- Full Day Leave --</option>
                            <?php for($i = 1; $i <= 9; $i++): ?>
                                <option value="<?php echo $i; ?>">Slot <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                        <small>Leave blank for full day leave</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>📝 REASON FOR LEAVE *</label>
                    <textarea name="reason" rows="5" placeholder="Please provide a detailed reason for leave (e.g., medical reasons, personal work, family emergency, etc.)" required></textarea>
                </div>

                <button type="submit" name="submit_leave" class="submit-btn">✈️ SUBMIT LEAVE REQUEST</button>
            </form>
        </div>

        
    </div>
</body>
</html>