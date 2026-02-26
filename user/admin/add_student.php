<?php
session_start();
require_once '../../include/conn/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$error = '';
$success = '';

// Fetch classes for dropdown
$classes = mysqli_query($conn, "SELECT id, class_name FROM classes ORDER BY class_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $student_id = trim($_POST['student_id']);
    $class_id = intval($_POST['class_id']);
    $roll_number = trim($_POST['roll_number']);

    if (empty($full_name) || empty($email) || empty($username) || empty($password) || empty($student_id)) {
        $error = 'Please fill all required fields.';
    } else {
        // Check if username or email exists
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' OR email='$email'");
        if (mysqli_num_rows($check) > 0) {
            $error = 'Username or email already exists.';
        } else {
            // Insert into users table
            $insert_user = "INSERT INTO users (username, password, email, full_name, role) 
                           VALUES ('$username', '$password', '$email', '$full_name', 'student')";
            
            if (mysqli_query($conn, $insert_user)) {
                $user_id = mysqli_insert_id($conn);
                
                // Insert into students table
                $insert_student = "INSERT INTO students (user_id, student_id, class_id, roll_number) 
                                 VALUES ($user_id, '$student_id', $class_id, '$roll_number')";
                
                if (mysqli_query($conn, $insert_student)) {
                    $success = 'Student added successfully!';
                } else {
                    $error = 'Error adding student details: ' . mysqli_error($conn);
                }
            } else {
                $error = 'Error creating user: ' . mysqli_error($conn);
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
    <title>Add Student · Admin</title>
    <link rel="stylesheet" href="../../include/css/style.css">
    <style>
        body {
            display: flex;
            min-height: 100vh;
        }
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            background: #fafafa;
        }
        .form-container {
            max-width: 700px;
            margin: 0 auto;
            border: 4px solid #000;
            background: var(--yellow);
            padding: 35px;
            box-shadow: var(--shadow);
        }
    </style>
</head>
<body>
    <!-- Include same sidebar -->
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
            <!-- Same navigation as dashboard -->
            <div class="nav-section">
                <div class="nav-section-title">MAIN</div>
                <a href="dashboard.php" class="nav-item">📊 Dashboard</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">MANAGEMENT</div>
                <a href="teachers.php" class="nav-item">👨‍🏫 Teachers</a>
                <a href="students.php" class="nav-item active">🎓 Students</a>
                <a href="classes.php" class="nav-item">🏫 Classes</a>
                <a href="subjects.php" class="nav-item">📚 Subjects</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">ADD NEW</div>
                <a href="add_teacher.php" class="nav-item yellow">➕ Add Teacher</a>
                <a href="add_student.php" class="nav-item yellow">➕ Add Student</a>
                <a href="add_class.php" class="nav-item yellow">➕ Add Class</a>
                <a href="add_subject.php" class="nav-item yellow">➕ Add Subject</a>
            </div>
        </div>
        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn">🚪 LOGOUT</a>
        </div>
    </div>

    <div class="main-content">
        <div class="content-header">
            <h1>➕ ADD NEW STUDENT</h1>
        </div>

        <div class="form-container">
            <?php if ($error): ?>
                <div class="error-box"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-box"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-group">
                    <label>👤 FULL NAME *</label>
                    <input type="text" name="full_name" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>📧 EMAIL *</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>👥 USERNAME *</label>
                        <input type="text" name="username" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>🔒 PASSWORD *</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>🆔 STUDENT ID *</label>
                        <input type="text" name="student_id" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>🏫 CLASS</label>
                        <select name="class_id">
                            <option value="">-- Select Class --</option>
                            <?php while($class = mysqli_fetch_assoc($classes)): ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>🔢 ROLL NUMBER</label>
                        <input type="text" name="roll_number">
                    </div>
                </div>

                <button type="submit" name="add_student" class="submit-btn">ADD STUDENT →</button>
            </form>
        </div>
    </div>
</body>
</html>