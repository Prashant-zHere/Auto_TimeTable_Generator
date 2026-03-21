<?php
session_start();
require_once '../../include/conn/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $employee_id = trim($_POST['employee_id']);
    $department = trim($_POST['department']);
    $qualification = trim($_POST['qualification']);
    $experience = intval($_POST['experience']);
    $max_periods = intval($_POST['max_periods']);

    if (empty($full_name) || empty($email) || empty($username) || empty($password) || empty($employee_id)) {
        $error = 'Please fill all required fields.';
    } else {
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' OR email='$email'");
        if (mysqli_num_rows($check) > 0) {
            $error = 'Username or email already exists.';
        } else {
            $insert_user = "INSERT INTO users (username, password, email, full_name, role) 
                           VALUES ('$username', '$password', '$email', '$full_name', 'teacher')";
            
            if (mysqli_query($conn, $insert_user)) {
                $user_id = mysqli_insert_id($conn);
                
                $insert_teacher = "INSERT INTO teachers (user_id, employee_id, department, qualification, experience, max_periods_per_day) 
                                 VALUES ($user_id, '$employee_id', '$department', '$qualification', $experience, $max_periods)";
                
                if (mysqli_query($conn, $insert_teacher)) {
                    $success = 'Teacher added successfully!';
                } else {
                    $error = 'Error adding teacher details: ' . mysqli_error($conn);
                }
            } else {
                $error = 'Error creating user: ' . mysqli_error($conn);
            }
        }
    }
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
    <title>Add Teacher · Admin</title>
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

        .form-row-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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

        .error-box {
            background: var(--red);
            color: white;
            padding: 12px;
            border: 3px solid #000;
            margin-bottom: 20px;
            font-weight: 800;
            box-shadow: var(--shadow);
        }

        .success-box {
            background: var(--green);
            color: white;
            padding: 12px;
            border: 3px solid #000;
            margin-bottom: 20px;
            font-weight: 800;
            box-shadow: var(--shadow);
        }

        .required-field::after {
            content: " *";
            color: var(--red);
            font-weight: 900;
        }

        .info-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            font-weight: 600;
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
    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="content-header">
            <h1>➕ ADD NEW TEACHER</h1>
            <div class="date-display">
                <?php echo date('l, d M Y'); ?>
            </div>
        </div>

        <div class="form-container">
            <h2>👨‍🏫 TEACHER DETAILS</h2>
            
            <?php if ($error): ?>
                <div class="error-box"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-box"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <!-- Personal Information -->
                <div class="form-group">
                    <label class="required-field">👤 FULL NAME</label>
                    <input type="text" name="full_name" placeholder="Enter teacher's full name" required 
                           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="required-field">📧 EMAIL</label>
                        <input type="email" name="email" placeholder="teacher@college.edu" required 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="required-field">👥 USERNAME</label>
                        <input type="text" name="username" placeholder="login username" required 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="required-field">🔒 PASSWORD</label>
                        <input type="password" name="password" placeholder="••••••••" required>
                    </div>
                    <div class="form-group">
                        <label class="required-field">🆔 EMPLOYEE ID</label>
                        <input type="text" name="employee_id" placeholder="e.g., FAC001" required 
                               value="<?php echo isset($_POST['employee_id']) ? htmlspecialchars($_POST['employee_id']) : ''; ?>">
                    </div>
                </div>

                <!-- Professional Information -->
                <div class="form-row">
                    <div class="form-group">
                        <label>🏢 DEPARTMENT</label>
                        <select name="department">
                            <option value="Computer Science" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                            <option value="Information Technology" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                            <option value="Electronics" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Electronics') ? 'selected' : ''; ?>>Electronics</option>
                            <option value="Mechanical" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Mechanical') ? 'selected' : ''; ?>>Mechanical</option>
                            <option value="Civil" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Civil') ? 'selected' : ''; ?>>Civil</option>
                            <option value="Mathematics" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Mathematics') ? 'selected' : ''; ?>>Mathematics</option>
                            <option value="Physics" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Physics') ? 'selected' : ''; ?>>Physics</option>
                            <option value="Chemistry" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Chemistry') ? 'selected' : ''; ?>>Chemistry</option>
                            <option value="English" <?php echo (isset($_POST['department']) && $_POST['department'] == 'English') ? 'selected' : ''; ?>>English</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>🎓 QUALIFICATION</label>
                        <input type="text" name="qualification" placeholder="e.g., M.Tech, Ph.D" 
                               value="<?php echo isset($_POST['qualification']) ? htmlspecialchars($_POST['qualification']) : 'M.Tech'; ?>">
                    </div>
                </div>

                <div class="form-row-3">
                    <div class="form-group">
                        <label>📊 EXPERIENCE (years)</label>
                        <input type="number" name="experience" min="0" max="50" 
                               value="<?php echo isset($_POST['experience']) ? $_POST['experience'] : '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label>⏰ MAX PERIODS/DAY</label>
                        <input type="number" name="max_periods" min="1" max="8" 
                               value="<?php echo isset($_POST['max_periods']) ? $_POST['max_periods'] : '6'; ?>">
                    </div>
                    <div class="form-group">
                        <label>📅 JOINING YEAR</label>
                        <input type="number" name="joining_year" min="2000" max="<?php echo date('Y'); ?>" 
                               value="<?php echo isset($_POST['joining_year']) ? $_POST['joining_year'] : date('Y'); ?>">
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="form-group">
                    <label>📝 SPECIALIZATION</label>
                    <input type="text" name="specialization" placeholder="e.g., Database Systems, Networks" 
                           value="<?php echo isset($_POST['specialization']) ? htmlspecialchars($_POST['specialization']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>📍 ADDRESS</label>
                    <textarea name="address" rows="3" placeholder="Residential address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>📞 PHONE NUMBER</label>
                        <input type="text" name="phone" placeholder="+91 98765 43210" 
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>🎂 DATE OF BIRTH</label>
                        <input type="date" name="dob" 
                               value="<?php echo isset($_POST['dob']) ? $_POST['dob'] : ''; ?>">
                    </div>
                </div>

                <div class="info-text">
                    <span class="required-field">*</span> Required fields
                </div>

                <button type="submit" name="add_teacher" class="submit-btn">ADD TEACHER →</button>
            </form>

            <div class="back-link">
                <a href="teachers.php">← BACK TO TEACHERS</a>
            </div>
        </div>
    </div>
</body>
</html>