<?php
session_start();
require_once '../../include/conn/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php');
    exit;
}

$error = '';
$success = '';

$class_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($class_id == 0) {
    header('Location: classes.php');
    exit;
}

$class_query = mysqli_query($conn, "SELECT * FROM classes WHERE id = $class_id");
$class = mysqli_fetch_assoc($class_query);

if (!$class) {
    header('Location: classes.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_class'])) {
    $class_name = trim($_POST['class_name']);
    $semester = intval($_POST['semester']);
    $section = trim($_POST['section']);
    $total_students = intval($_POST['total_students']);

    if (empty($class_name) || empty($semester) || empty($section)) {
        $error = 'Please fill all required fields.';
    } else {
        $check = mysqli_query($conn, "SELECT id FROM classes WHERE class_name='$class_name' AND id != $class_id");
        if (mysqli_num_rows($check) > 0) {
            $error = 'Class name already exists.';
        } else {
            $update = "UPDATE classes SET 
                      class_name = '$class_name',
                      semester = $semester,
                      section = '$section',
                      total_students = $total_students
                      WHERE id = $class_id";
            
            if (mysqli_query($conn, $update)) {
                $success = 'Class updated successfully!';
                $class_query = mysqli_query($conn, "SELECT * FROM classes WHERE id = $class_id");
                $class = mysqli_fetch_assoc($class_query);
            } else {
                $error = 'Error updating class: ' . mysqli_error($conn);
            }
        }
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
    <title>Edit Class · Admin</title>
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
            max-width: 600px;
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

        .back-link a:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 #000;
        }

        .error-box, .success-box {
            margin-bottom: 20px;
        }

        .required-field::after {
            content: " *";
            color: var(--red);
            font-weight: 900;
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
                <a href="classes.php" class="nav-item active">
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
            <h1>✏️ EDIT CLASS</h1>
            <div class="date-display">
                <?php echo date('l, d M Y'); ?>
            </div>
        </div>

        <div class="form-container">
            <h2>🏫 <?php echo htmlspecialchars($class['class_name']); ?></h2>
            
            <?php if ($error): ?>
                <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-box"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-group">
                    <label class="required-field">📚 CLASS NAME</label>
                    <input type="text" name="class_name" required value="<?php echo htmlspecialchars($class['class_name']); ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="required-field">🔢 SEMESTER</label>
                        <input type="number" name="semester" min="1" max="8" required value="<?php echo $class['semester']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="required-field">📝 SECTION</label>
                        <input type="text" name="section" maxlength="1" required value="<?php echo htmlspecialchars($class['section']); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>👥 TOTAL STUDENTS</label>
                    <input type="number" name="total_students" min="0" max="200" value="<?php echo $class['total_students']; ?>">
                </div>

                <button type="submit" name="update_class" class="submit-btn">💾 UPDATE CLASS</button>
            </form>

            <div class="back-link">
                <a href="classes.php">← BACK TO CLASSES</a>
            </div>
        </div>
    </div>
</body>
</html>