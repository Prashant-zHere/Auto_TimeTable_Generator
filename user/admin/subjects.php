<?php
session_start();
require_once '../../include/conn/conn.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php');
    exit;
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $subject_id = intval($_GET['delete']);
    
    $check_timetable = mysqli_query($conn, "SELECT id FROM timetable WHERE subject_id = $subject_id LIMIT 1");
    
    if (mysqli_num_rows($check_timetable) > 0) {
        $error = "Cannot delete subject. It is used in timetable.";
    } else {
        mysqli_query($conn, "DELETE FROM subjects WHERE id = $subject_id");
    }
    header('Location: subjects.php');
    exit;
}

$subjects = mysqli_query($conn, "
    SELECT s.*, c.class_name, 
           ts.teacher_id, u.full_name as teacher_name, t.employee_id,
           ts.academic_year as alloc_academic_year
    FROM subjects s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN teacher_subjects ts ON s.id = ts.subject_id
    LEFT JOIN teachers t ON ts.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY c.class_name, s.semester, s.subject_name
");

$total_subjects = mysqli_num_rows($subjects);
$theory_count = 0;
$lab_count = 0;
$practical_count = 0;
$project_count = 0;
$elective_count = 0;

$subjects_data = [];
while($row = mysqli_fetch_assoc($subjects)) {
    $subjects_data[] = $row;
    switch($row['subject_type']) {
        case 'Theory': $theory_count++; break;
        case 'Lab': $lab_count++; break;
        case 'Practical': $practical_count++; break;
        case 'Project': $project_count++; break;
        case 'Elective': $elective_count++; break;
    }
}
$subjects = $subjects_data;

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
    <title>Manage Subjects · Admin</title>
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
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border: 4px solid #000;
            padding: 15px;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .stat-card h3 {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 900;
        }

        .stat-theory .stat-number { color: var(--blue); }
        .stat-lab .stat-number { color: var(--green); }
        .stat-practical .stat-number { color: var(--orange); }
        .stat-project .stat-number { color: var(--purple); }
        .stat-elective .stat-number { color: var(--red); }

        .filter-bar {
            background: var(--light-gray);
            border: 4px solid #000;
            padding: 15px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            box-shadow: var(--shadow);
        }

        .filter-input {
            flex: 1;
            min-width: 200px;
        }

        .filter-input input,
        .filter-input select {
            width: 100%;
            padding: 10px;
            border: 3px solid #000;
            font-weight: 600;
        }

        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .subject-card {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: transform 0.2s ease;
            position: relative;
        }

        .subject-card:hover {
            transform: translate(-3px, -3px);
            box-shadow: 6px 6px 0 #000;
        }

        .subject-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 3px solid #000;
        }

        .subject-code {
            font-size: 14px;
            font-weight: 900;
            background: var(--blue);
            color: white;
            padding: 5px 10px;
            border: 2px solid #000;
        }

        .subject-name {
            font-size: 16px;
            font-weight: 900;
            background: var(--yellow);
            padding: 5px 10px;
            border: 2px solid #000;
        }

        .class-badge {
            position: absolute;
            top: -10px;
            right: 10px;
            background: var(--red);
            color: white;
            padding: 5px 10px;
            border: 2px solid #000;
            font-weight: 800;
            font-size: 12px;
            transform: rotate(2deg);
        }

        .subject-details {
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

        .type-badge-small {
            display: inline-block;
            padding: 2px 6px;
            border: 2px solid #000;
            font-size: 11px;
            font-weight: 800;
            margin-left: 5px;
        }

        .type-theory { background: var(--blue); color: white; }
        .type-practical { background: var(--orange); color: white; }
        .type-lab { background: var(--green); color: white; }
        .type-project { background: var(--purple); color: white; }
        .type-elective { background: var(--red); color: white; }

        .teacher-info {
            background: var(--purple);
            color: white;
            padding: 8px;
            border: 2px solid #000;
            margin-top: 10px;
            font-weight: 700;
            font-size: 13px;
        }

        .no-teacher {
            background: #999;
            color: white;
            padding: 8px;
            border: 2px solid #000;
            margin-top: 10px;
            font-weight: 700;
            text-align: center;
        }

        .subject-actions {
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
            font-size: 13px;
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
                <a href="dashboard.php" class="nav-item ">
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
                <a href="subjects.php" class="nav-item active">
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
            <h1>📚 MANAGE SUBJECTS</h1>
            <div class="date-display">
                <?php echo date('l, d M Y'); ?>
            </div>
        </div>

        <div class="stats-cards">
            <div class="stat-card stat-theory">
                <h3>📚 THEORY</h3>
                <div class="stat-number"><?php echo $theory_count; ?></div>
            </div>
            <div class="stat-card stat-lab">
                <h3>🔬 LAB</h3>
                <div class="stat-number"><?php echo $lab_count; ?></div>
            </div>
            <div class="stat-card stat-practical">
                <h3>⚙️ PRACTICAL</h3>
                <div class="stat-number"><?php echo $practical_count; ?></div>
            </div>
            <div class="stat-card stat-project">
                <h3>📋 PROJECT</h3>
                <div class="stat-number"><?php echo $project_count; ?></div>
            </div>
            <div class="stat-card stat-elective">
                <h3>⭐ ELECTIVE</h3>
                <div class="stat-number"><?php echo $elective_count; ?></div>
            </div>
        </div>

        <div class="filter-bar">
            <div class="filter-input">
                <input type="text" id="searchInput" placeholder="🔍 Search by subject name or code..." onkeyup="filterSubjects()">
            </div>
            <div class="filter-input">
                <select id="classFilter" onchange="filterSubjects()">
                    <option value="">All Classes</option>
                    <?php
                    $class_list = mysqli_query($conn, "SELECT id, class_name FROM classes ORDER BY class_name");
                    while($class = mysqli_fetch_assoc($class_list)) {
                        echo "<option value=\"" . $class['class_name'] . "\">" . $class['class_name'] . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="filter-input">
                <select id="typeFilter" onchange="filterSubjects()">
                    <option value="">All Types</option>
                    <option value="Theory">Theory</option>
                    <option value="Practical">Practical</option>
                    <option value="Lab">Lab</option>
                    <option value="Project">Project</option>
                    <option value="Elective">Elective</option>
                </select>
            </div>
        </div>

        <div class="actions-bar">
            <h2>All Subjects (<?php echo $total_subjects; ?>)</h2>
            <a href="add_subject.php" class="add-btn">➕ ADD NEW SUBJECT</a>
        </div>

        <div class="subjects-grid" id="subjectsGrid">
            <?php if (!empty($subjects)): ?>
                <?php foreach($subjects as $subject): ?>
                    <div class="subject-card" 
                         data-name="<?php echo strtolower($subject['subject_name'] . ' ' . $subject['subject_code']); ?>" 
                         data-class="<?php echo $subject['class_name']; ?>"
                         data-type="<?php echo $subject['subject_type']; ?>">
                        
                        <span class="class-badge"><?php echo htmlspecialchars($subject['class_name']); ?></span>
                        
                        <div class="subject-header">
                            <span class="subject-code"><?php echo htmlspecialchars($subject['subject_code']); ?></span>
                            <span class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></span>
                        </div>
                        
                        <div class="subject-details">
                            <div class="detail-row">
                                <span class="detail-label">Semester</span>
                                <span class="detail-value"><?php echo $subject['semester']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Type</span>
                                <span class="detail-value">
                                    <?php echo $subject['subject_type']; ?>
                                    <?php if($subject['is_lab']): ?>
                                        <span class="type-badge-small type-lab">LAB</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Credits</span>
                                <span class="detail-value"><?php echo $subject['credits']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Periods/Week</span>
                                <span class="detail-value"><?php echo $subject['periods_per_week']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Academic Year</span>
                                <span class="detail-value"><?php echo $subject['academic_year']; ?></span>
                            </div>
                        </div>

                        <?php if(!empty($subject['teacher_name'])): ?>
                            <div class="teacher-info">
                                <strong>👨‍🏫 Teacher:</strong> <?php echo htmlspecialchars($subject['teacher_name']); ?> (<?php echo htmlspecialchars($subject['employee_id']); ?>)
                            </div>
                        <?php else: ?>
                            <div class="no-teacher">
                                <strong>⚠️ No teacher allocated</strong>
                            </div>
                        <?php endif; ?>

                        <div class="subject-actions">
                            <a href="edit_subject.php?id=<?php echo $subject['id']; ?>" class="action-btn edit-btn">✏️ EDIT</a>
                            <a href="?delete=<?php echo $subject['id']; ?>" class="action-btn delete-btn" 
                               onclick="return confirm('Delete this subject? This action cannot be undone.')">🗑️ DELETE</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>📭 No subjects found in the system</p>
                    <a href="add_subject.php" class="add-btn">➕ ADD YOUR FIRST SUBJECT</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function filterSubjects() {
            let searchInput = document.getElementById('searchInput').value.toLowerCase();
            let classFilter = document.getElementById('classFilter').value;
            let typeFilter = document.getElementById('typeFilter').value;
            let cards = document.querySelectorAll('.subject-card');
            
            cards.forEach(card => {
                let name = card.getAttribute('data-name') || '';
                let subjectClass = card.getAttribute('data-class') || '';
                let subjectType = card.getAttribute('data-type') || '';
                
                let matchesSearch = name.includes(searchInput);
                let matchesClass = classFilter === '' || subjectClass === classFilter;
                let matchesType = typeFilter === '' || subjectType === typeFilter;
                
                if (matchesSearch && matchesClass && matchesType) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>