<?php
session_start();
require_once '../../include/conn/conn.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php');
    exit;
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $class_id = intval($_GET['delete']);
    
    $check_subjects = mysqli_query($conn, "SELECT id FROM subjects WHERE class_id = $class_id LIMIT 1");
    $check_students = mysqli_query($conn, "SELECT id FROM students WHERE class_id = $class_id LIMIT 1");
    
    if (mysqli_num_rows($check_subjects) > 0 || mysqli_num_rows($check_students) > 0) {
        $error = "Cannot delete class. It has subjects or students assigned.";
    } else {
        mysqli_query($conn, "DELETE FROM classes WHERE id = $class_id");
    }
    header('Location: classes.php');
    exit;
}

$full_name = $_SESSION['full_name'];

$pending_leaves = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as count FROM leave_requests WHERE status='pending'"
))['count'];

$pending_modifies = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as count FROM modify_requests WHERE status='pending'"
))['count'];


$classes = mysqli_query($conn, "SELECT * FROM classes ORDER BY class_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes · Admin</title>
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

        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .add-btn {
            background: var(--blue);
            color: white;
            padding: 12px 20px;
            text-decoration: none;
            border: 4px solid #000;
            font-weight: 800;
            box-shadow: var(--shadow);
            transition: all 0.1s ease;
        }

        .add-btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 #000;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 900;
            color: var(--blue);
        }

        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .class-card {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: transform 0.2s ease;
        }

        .class-card:hover {
            transform: translate(-3px, -3px);
            box-shadow: 6px 6px 0 #000;
        }

        .class-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 3px solid #000;
        }

        .class-name {
            font-size: 20px;
            font-weight: 900;
            background: var(--yellow);
            padding: 5px 10px;
            border: 2px solid #000;
        }

        .class-section {
            background: var(--blue);
            color: white;
            padding: 5px 10px;
            border: 2px solid #000;
            font-weight: 800;
        }

        .class-details {
            margin-bottom: 15px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px;
            border: 2px solid #000;
        }

        .detail-label {
            font-weight: 800;
            background: #eee;
            padding: 2px 5px;
        }

        .detail-value {
            font-weight: 700;
        }

        .class-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .action-btn {
            flex: 1;
            text-align: center;
            padding: 8px;
            border: 2px solid #000;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.1s ease;
        }

        .view-btn {
            background: var(--yellow);
            color: #000;
        }

        .edit-btn {
            background: var(--blue);
            color: white;
        }

        .delete-btn {
            background: var(--red);
            color: white;
        }

        .action-btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: 3px 3px 0 #000;
        }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 50px;
            background: white;
            border: 4px solid #000;
            box-shadow: var(--shadow);
        }

        .empty-state p {
            font-size: 18px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- <div class="sidebar">
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
                <a href="dashboard.php" class="nav-item">
                    <span class="icon">📊</span> Dashboard
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">MANAGEMENT</div>
                <a href="teachers.php" class="nav-item">
                    <span class="icon">👨‍🏫</span> Teachers
                </a>
                <a href="students.php" class="nav-item">
                    <span class="icon">🎓</span> Students
                </a>
                <a href="classes.php" class="nav-item active">
                    <span class="icon">🏫</span> Classes
                </a>
                <a href="subjects.php" class="nav-item">
                    <span class="icon">📚</span> Subjects
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">ADD NEW</div>
                <a href="add_teacher.php" class="nav-item yellow">
                    <span class="icon">➕</span> Add Teacher
                </a>
                <a href="add_student.php" class="nav-item yellow">
                    <span class="icon">➕</span> Add Student
                </a>
                <a href="add_class.php" class="nav-item yellow">
                    <span class="icon">➕</span> Add Class
                </a>
                <a href="add_subject.php" class="nav-item yellow">
                    <span class="icon">➕</span> Add Subject
                </a>
            </div>
        </div>

        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn">🚪 LOGOUT</a>
        </div>
    </div> -->


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
            <h1>🏫 MANAGE CLASSES</h1>
            <div class="date-display">
                <?php echo date('l, d M Y'); ?>
            </div>
        </div>

        <?php
        $total_classes = mysqli_num_rows($classes);
        $total_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_students) as total FROM classes"))['total'] ?? 0;
        $total_subjects = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT class_id) as count FROM subjects"))['count'] ?? 0;
        ?>

        <div class="stats-cards">
            <div class="stat-card">
                <h3>📚 TOTAL CLASSES</h3>
                <div class="stat-number"><?php echo $total_classes; ?></div>
            </div>
            <div class="stat-card">
                <h3>👥 TOTAL STUDENTS</h3>
                <div class="stat-number"><?php echo $total_students; ?></div>
            </div>
            <div class="stat-card">
                <h3>📖 CLASSES WITH SUBJECTS</h3>
                <div class="stat-number"><?php echo $total_subjects; ?></div>
            </div>
        </div>

        <div class="actions-bar">
            <h2>All Classes</h2>
            <a href="add_class.php" class="add-btn">➕ ADD NEW CLASS</a>
        </div>

        <div class="classes-grid">
            <?php if (mysqli_num_rows($classes) > 0): ?>
                <?php while($class = mysqli_fetch_assoc($classes)): ?>
                    <?php
                    $subject_count = mysqli_fetch_assoc(mysqli_query($conn, 
                        "SELECT COUNT(*) as count FROM subjects WHERE class_id = {$class['id']}"
                    ))['count'];
                    
                    $student_count = mysqli_fetch_assoc(mysqli_query($conn, 
                        "SELECT COUNT(*) as count FROM students WHERE class_id = {$class['id']}"
                    ))['count'];
                    ?>
                    <div class="class-card">
                        <div class="class-header">
                            <span class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></span>
                            <span class="class-section">Sec <?php echo htmlspecialchars($class['section']); ?></span>
                        </div>
                        
                        <div class="class-details">
                            <div class="detail-row">
                                <span class="detail-label">Semester</span>
                                <span class="detail-value"><?php echo $class['semester']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Total Students</span>
                                <span class="detail-value"><?php echo $class['total_students']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Enrolled Students</span>
                                <span class="detail-value"><?php echo $student_count; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Subjects</span>
                                <span class="detail-value"><?php echo $subject_count; ?></span>
                            </div>
                        </div>

                        <div class="class-actions">
                            <!-- <a href="view_class.php?id=<?php echo $class['id']; ?>" class="action-btn view-btn">👁️ VIEW</a> -->
                            <a href="edit_class.php?id=<?php echo $class['id']; ?>" class="action-btn edit-btn">✏️ EDIT</a>
                            <a href="?delete=<?php echo $class['id']; ?>" class="action-btn delete-btn" 
                               onclick="return confirm('Delete this class? This action cannot be undone.')">🗑️ DELETE</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>📭 No classes found in the system</p>
                    <a href="add_class.php" class="add-btn">➕ ADD YOUR FIRST CLASS</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>