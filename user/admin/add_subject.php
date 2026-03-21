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

// Fetch classes for dropdown
$classes = mysqli_query($conn, "SELECT id, class_name, semester FROM classes ORDER BY class_name");

// Fetch teachers for optional allocation
$teachers = mysqli_query($conn, "
    SELECT t.id, u.full_name, t.employee_id 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY u.full_name
");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
    // Get all fields including new ones
    $subject_code = trim($_POST['subject_code']);
    $subject_name = trim($_POST['subject_name']);
    $class_id = intval($_POST['class_id']);
    $semester = intval($_POST['semester']);
    $periods_per_week = intval($_POST['periods_per_week']);
    $subject_type = $_POST['subject_type'];
    $credits = intval($_POST['credits']);
    $is_lab = isset($_POST['is_lab']) ? 1 : 0;
    $academic_year = trim($_POST['academic_year']);

    // Validation
    if (empty($subject_code) || empty($subject_name) || empty($class_id) || empty($semester)) {
        $error = 'Please fill all required fields.';
    } elseif (!preg_match('/^[A-Za-z0-9]+$/', $subject_code)) {
        $error = 'Subject code should contain only letters and numbers.';
    } else {
        // Check if subject code already exists
        $check = mysqli_query($conn, "SELECT id FROM subjects WHERE subject_code = '$subject_code'");
        if (mysqli_num_rows($check) > 0) {
            $error = 'Subject code already exists. Please use a different code.';
        } else {
            // Insert into subjects table with all fields
            $insert = "INSERT INTO subjects 
                      (subject_code, subject_name, class_id, semester, periods_per_week, 
                       subject_type, credits, is_lab, academic_year) 
                      VALUES 
                      ('$subject_code', '$subject_name', $class_id, $semester, $periods_per_week, 
                       '$subject_type', $credits, $is_lab, '$academic_year')";
            
            if (mysqli_query($conn, $insert)) {
                $subject_id = mysqli_insert_id($conn);
                
                // Optional: If teacher is selected, allocate them to this subject
                if (!empty($_POST['teacher_id']) && $_POST['teacher_id'] != '') {
                    $teacher_id = intval($_POST['teacher_id']);
                    
                    // Insert into teacher_subjects table
                    $allocate = "INSERT INTO teacher_subjects (teacher_id, subject_id, class_id, academic_year) 
                                VALUES ($teacher_id, $subject_id, $class_id, '$academic_year')";
                    mysqli_query($conn, $allocate);
                }
                
                $success = 'Subject added successfully!';
            } else {
                $error = 'Error adding subject: ' . mysqli_error($conn);
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
    <title>Add Subject · Admin</title>
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

        .form-row-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: white;
            border: 4px solid #000;
            box-shadow: 3px 3px 0 #000;
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

        .section-divider {
            margin: 25px 0 15px;
            border-top: 3px solid #000;
            position: relative;
        }

        .section-divider span {
            background: var(--yellow);
            padding: 5px 15px;
            border: 2px solid #000;
            font-weight: 800;
            position: absolute;
            top: -15px;
            left: 20px;
        }

        .optional-note {
            background: #333;
            color: white;
            padding: 10px;
            border: 2px solid #000;
            margin: 15px 0;
            font-weight: 700;
            text-align: center;
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

        .example-text {
            font-size: 11px;
            color: #555;
            margin-top: 3px;
        }

        .type-preview {
            display: flex;
            gap: 10px;
            margin-top: 5px;
            flex-wrap: wrap;
        }

        .type-badge {
            padding: 3px 8px;
            border: 2px solid #000;
            font-size: 11px;
            font-weight: 700;
        }

        .type-theory { background: var(--blue); color: white; }
        .type-practical { background: var(--orange); color: white; }
        .type-lab { background: var(--green); color: white; }
        .type-project { background: var(--purple); color: white; }
        .type-elective { background: var(--red); color: white; }
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
            <h1>➕ ADD NEW SUBJECT</h1>
            <div class="date-display">
                <?php echo date('l, d M Y'); ?>
            </div>
        </div>

        <div class="form-container">
            <h2>📚 SUBJECT INFORMATION</h2>
            
            <?php if ($error): ?>
                <div class="error-box"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-box"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <!-- Basic Subject Fields -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="required-field">🔤 SUBJECT CODE</label>
                        <input type="text" name="subject_code" placeholder="e.g., CS301, MATH101" required 
                               value="<?php echo isset($_POST['subject_code']) ? htmlspecialchars($_POST['subject_code']) : ''; ?>"
                               pattern="[A-Za-z0-9]+" title="Only letters and numbers allowed">
                        <div class="example-text">Example: CS301, MATH101, PHY202</div>
                    </div>
                    <div class="form-group">
                        <label class="required-field">📝 SUBJECT NAME</label>
                        <input type="text" name="subject_name" placeholder="e.g., Data Structures" required 
                               value="<?php echo isset($_POST['subject_name']) ? htmlspecialchars($_POST['subject_name']) : ''; ?>">
                        <div class="example-text">Full subject name</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="required-field">🏫 CLASS</label>
                        <select name="class_id" required id="classSelect">
                            <option value="">-- Select Class --</option>
                            <?php 
                            mysqli_data_seek($classes, 0);
                            while($class = mysqli_fetch_assoc($classes)): 
                            ?>
                                <option value="<?php echo $class['id']; ?>" 
                                    data-semester="<?php echo $class['semester']; ?>"
                                    <?php echo (isset($_POST['class_id']) && $_POST['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="required-field">🔢 SEMESTER</label>
                        <input type="number" name="semester" id="semesterInput" min="1" max="8" required 
                               value="<?php echo isset($_POST['semester']) ? $_POST['semester'] : '1'; ?>">
                    </div>
                </div>

                <!-- New Fields: Subject Type and Credits -->
                <div class="form-row-3">
                    <div class="form-group">
                        <label>📋 SUBJECT TYPE</label>
                        <select name="subject_type" id="subjectType">
                            <option value="Theory" <?php echo (isset($_POST['subject_type']) && $_POST['subject_type'] == 'Theory') ? 'selected' : ''; ?>>Theory</option>
                            <option value="Practical" <?php echo (isset($_POST['subject_type']) && $_POST['subject_type'] == 'Practical') ? 'selected' : ''; ?>>Practical</option>
                            <option value="Lab" <?php echo (isset($_POST['subject_type']) && $_POST['subject_type'] == 'Lab') ? 'selected' : ''; ?>>Lab</option>
                            <option value="Project" <?php echo (isset($_POST['subject_type']) && $_POST['subject_type'] == 'Project') ? 'selected' : ''; ?>>Project</option>
                            <option value="Elective" <?php echo (isset($_POST['subject_type']) && $_POST['subject_type'] == 'Elective') ? 'selected' : ''; ?>>Elective</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>⭐ CREDITS</label>
                        <select name="credits">
                            <option value="1" <?php echo (isset($_POST['credits']) && $_POST['credits'] == 1) ? 'selected' : ''; ?>>1 Credit</option>
                            <option value="2" <?php echo (isset($_POST['credits']) && $_POST['credits'] == 2) ? 'selected' : ''; ?>>2 Credits</option>
                            <option value="3" <?php echo (isset($_POST['credits']) && $_POST['credits'] == 3) ? 'selected' : ''; ?>>3 Credits</option>
                            <option value="4" <?php echo (isset($_POST['credits']) && $_POST['credits'] == 4) ? 'selected' : ''; ?> selected>4 Credits</option>
                            <option value="5" <?php echo (isset($_POST['credits']) && $_POST['credits'] == 5) ? 'selected' : ''; ?>>5 Credits</option>
                            <option value="6" <?php echo (isset($_POST['credits']) && $_POST['credits'] == 6) ? 'selected' : ''; ?>>6 Credits</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>⏰ PERIODS/WEEK</label>
                        <input type="number" name="periods_per_week" min="1" max="10" 
                               value="<?php echo isset($_POST['periods_per_week']) ? $_POST['periods_per_week'] : '4'; ?>">
                    </div>
                </div>

                <!-- Academic Year -->
                <div class="form-row">
                    <div class="form-group">
                        <label>📅 ACADEMIC YEAR</label>
                        <select name="academic_year">
                            <option value="2024-25" <?php echo (isset($_POST['academic_year']) && $_POST['academic_year'] == '2024-25') ? 'selected' : ''; ?>>2024-25</option>
                            <option value="2025-26" <?php echo (isset($_POST['academic_year']) && $_POST['academic_year'] == '2025-26') ? 'selected' : ''; ?>>2025-26</option>
                        </select>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_lab" id="is_lab" value="1"
                            <?php echo (isset($_POST['is_lab']) && $_POST['is_lab'] == '1') ? 'checked' : ''; ?>>
                        <label for="is_lab">🔬 This is a Lab Course</label>
                    </div>
                </div>

                <!-- Teacher Allocation Section -->
                <div class="section-divider">
                    <span>OPTIONAL TEACHER ALLOCATION</span>
                </div>

                <div class="optional-note">
                    ⚡ You can allocate a teacher now or do it later from the teacher allocation page
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>👨‍🏫 ALLOCATE TEACHER</label>
                        <select name="teacher_id">
                            <option value="">-- Not Allocated --</option>
                            <?php 
                            mysqli_data_seek($teachers, 0);
                            while($teacher = mysqli_fetch_assoc($teachers)): 
                            ?>
                                <option value="<?php echo $teacher['id']; ?>"
                                    <?php echo (isset($_POST['teacher_id']) && $_POST['teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['full_name']) . ' (' . htmlspecialchars($teacher['employee_id']) . ')'; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <!-- Type Preview -->
                <div class="type-preview">
                    <span class="type-badge type-theory">Theory</span>
                    <span class="type-badge type-practical">Practical</span>
                    <span class="type-badge type-lab">Lab</span>
                    <span class="type-badge type-project">Project</span>
                    <span class="type-badge type-elective">Elective</span>
                </div>

                <div class="info-text">
                    <span class="required-field">*</span> Required fields
                </div>

                <button type="submit" name="add_subject" class="submit-btn">ADD SUBJECT →</button>
            </form>

            <div class="back-link">
                <a href="subjects.php">← BACK TO SUBJECTS</a>
            </div>
        </div>
    </div>

    <script>
        // Auto-populate semester based on selected class
        document.getElementById('classSelect').addEventListener('change', function() {
            let selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                let semester = selectedOption.getAttribute('data-semester');
                document.getElementById('semesterInput').value = semester;
            }
        });

        // Auto-adjust periods based on subject type
        document.getElementById('subjectType').addEventListener('change', function() {
            let periodsInput = document.querySelector('input[name="periods_per_week"]');
            switch(this.value) {
                case 'Theory':
                    periodsInput.value = '4';
                    break;
                case 'Practical':
                case 'Lab':
                    periodsInput.value = '3';
                    break;
                case 'Project':
                    periodsInput.value = '2';
                    break;
                case 'Elective':
                    periodsInput.value = '3';
                    break;
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            let subjectCode = document.querySelector('input[name="subject_code"]').value;
            let subjectName = document.querySelector('input[name="subject_name"]').value;
            
            if (subjectCode.length < 3) {
                alert('Subject code must be at least 3 characters long');
                e.preventDefault();
            }
            
            if (subjectName.length < 3) {
                alert('Subject name must be at least 3 characters long');
                e.preventDefault();
            }
        });
    </script>
</body>
</html>