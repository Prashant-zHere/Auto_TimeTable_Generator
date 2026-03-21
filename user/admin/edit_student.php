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

// Get student ID from URL
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($student_id == 0) {
    header('Location: students.php');
    exit;
}

// Fetch student details
$student_query = mysqli_query($conn, "
    SELECT s.*, u.id as user_id, u.username, u.email, u.full_name, c.class_name 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    LEFT JOIN classes c ON s.class_id = c.id 
    WHERE s.id = $student_id
");

$student = mysqli_fetch_assoc($student_query);

if (!$student) {
    header('Location: students.php');
    exit;
}

// Fetch classes for dropdown
$classes = mysqli_query($conn, "SELECT id, class_name, semester FROM classes ORDER BY class_name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $student_id_num = trim($_POST['student_id']);
    $class_id = !empty($_POST['class_id']) ? intval($_POST['class_id']) : 'NULL';
    $semester = !empty($_POST['semester']) ? intval($_POST['semester']) : 'NULL';
    $roll_number = trim($_POST['roll_number']);
    $password = $_POST['password'];

    if (empty($full_name) || empty($email) || empty($username) || empty($student_id_num)) {
        $error = 'Please fill all required fields.';
    } else {
        // Check if username or email exists for other users
        $check = mysqli_query($conn, "SELECT id FROM users WHERE (username='$username' OR email='$email') AND id != {$student['user_id']}");
        if (mysqli_num_rows($check) > 0) {
            $error = 'Username or email already exists for another user.';
        } else {
            // Check if student ID exists for other students
            $check_student = mysqli_query($conn, "SELECT id FROM students WHERE student_id='$student_id_num' AND id != $student_id");
            if (mysqli_num_rows($check_student) > 0) {
                $error = 'Student ID already exists for another student.';
            } else {
                // Update users table
                $update_user = "UPDATE users SET 
                              username = '$username', 
                              email = '$email', 
                              full_name = '$full_name'";
                
                if (!empty($password)) {
                    $update_user .= ", password = '$password'";
                }
                
                $update_user .= " WHERE id = {$student['user_id']}";
                
                if (mysqli_query($conn, $update_user)) {
                    // Update students table
                    $update_student = "UPDATE students SET 
                                     student_id = '$student_id_num',
                                     class_id = $class_id,
                                     semester = $semester,
                                     roll_number = '$roll_number'
                                     WHERE id = $student_id";
                    
                    if (mysqli_query($conn, $update_student)) {
                        $success = 'Student information updated successfully!';
                        // Refresh student data
                        $student_query = mysqli_query($conn, "
                            SELECT s.*, u.id as user_id, u.username, u.email, u.full_name, c.class_name 
                            FROM students s 
                            JOIN users u ON s.user_id = u.id 
                            LEFT JOIN classes c ON s.class_id = c.id 
                            WHERE s.id = $student_id
                        ");
                        $student = mysqli_fetch_assoc($student_query);
                    } else {
                        $error = 'Error updating student details: ' . mysqli_error($conn);
                    }
                } else {
                    $error = 'Error updating user: ' . mysqli_error($conn);
                }
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
    <title>Edit Student · Admin</title>
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
            max-width: 800px;
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

        .info-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
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
                <a href="dashboard.php" class="nav-item active">
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
            <h1>✏️ EDIT STUDENT</h1>
            <div class="date-display">
                <?php echo date('l, d M Y'); ?>
            </div>
        </div>

        <div class="form-container">
            <h2>🎓 <?php echo htmlspecialchars($student['full_name']); ?></h2>
            
            <?php if ($error): ?>
                <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-box"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-group">
                    <label class="required-field">👤 FULL NAME</label>
                    <input type="text" name="full_name" required value="<?php echo htmlspecialchars($student['full_name']); ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="required-field">📧 EMAIL</label>
                        <input type="email" name="email" required value="<?php echo htmlspecialchars($student['email']); ?>">
                    </div>
                    <div class="form-group">
                        <label class="required-field">👥 USERNAME</label>
                        <input type="text" name="username" required value="<?php echo htmlspecialchars($student['username']); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>🔒 PASSWORD</label>
                        <input type="password" name="password" placeholder="Leave blank to keep current password">
                        <div class="info-text">Leave empty to keep current password</div>
                    </div>
                    <div class="form-group">
                        <label class="required-field">🆔 STUDENT ID</label>
                        <input type="text" name="student_id" required value="<?php echo htmlspecialchars($student['student_id']); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>🏫 CLASS</label>
                        <select name="class_id" id="classSelect">
                            <option value="">-- Not Assigned --</option>
                            <?php while($class = mysqli_fetch_assoc($classes)): ?>
                                <option value="<?php echo $class['id']; ?>" 
                                        data-semester="<?php echo $class['semester']; ?>"
                                        <?php echo ($student['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>🔢 SEMESTER</label>
                        <input type="number" name="semester" id="semesterInput" min="1" max="8" 
                               value="<?php echo $student['semester']; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>🔢 ROLL NUMBER</label>
                    <input type="text" name="roll_number" value="<?php echo htmlspecialchars($student['roll_number']); ?>">
                </div>

                <button type="submit" name="update_student" class="submit-btn">💾 UPDATE STUDENT</button>
            </form>

            <div class="back-link">
                <a href="students.php">← BACK TO STUDENTS</a>
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
    </script>
</body>
</html>