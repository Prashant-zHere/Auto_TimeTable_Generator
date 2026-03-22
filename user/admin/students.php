<?php
session_start();
require_once '../../include/conn/conn.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php');
    exit;
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $student_id = intval($_GET['delete']);
    
    $get_user = mysqli_query($conn, "SELECT user_id FROM students WHERE id = $student_id");
    if ($get_user && mysqli_num_rows($get_user) > 0) {
        $user = mysqli_fetch_assoc($get_user);
        $user_id = $user['user_id'];
        mysqli_query($conn, "DELETE FROM users WHERE id = $user_id");
    }
    header('Location: students.php');
    exit;
}

if (isset($_POST['bulk_action']) && isset($_POST['selected_ids'])) {
    $action = $_POST['bulk_action'];
    $selected_ids = explode(',', $_POST['selected_ids']);
    
    if ($action === 'delete') {
        foreach($selected_ids as $student_id) {
            $get_user = mysqli_query($conn, "SELECT user_id FROM students WHERE id = $student_id");
            if ($get_user && mysqli_num_rows($get_user) > 0) {
                $user = mysqli_fetch_assoc($get_user);
                mysqli_query($conn, "DELETE FROM users WHERE id = {$user['user_id']}");
            }
        }
    } elseif ($action === 'assign_class') {
        $class_id = intval($_POST['bulk_class_id']);
        $semester = intval($_POST['bulk_semester']);
        foreach($selected_ids as $student_id) {
            mysqli_query($conn, "UPDATE students SET class_id = $class_id, semester = $semester WHERE id = $student_id");
        }
    }
    header('Location: students.php');
    exit;
}

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) && $_GET['order'] == 'desc' ? 'DESC' : 'ASC';
$next_order = $order == 'ASC' ? 'desc' : 'asc';

$valid_sorts = [
    'name' => 'u.full_name',
    'id' => 's.student_id',
    'class' => 'c.class_name',
    'semester' => 's.semester',
    'roll' => 's.roll_number',
    'date' => 'u.created_at'
];
$sort_column = isset($valid_sorts[$sort]) ? $valid_sorts[$sort] : 'u.full_name';

$students = mysqli_query($conn, "
    SELECT s.*, u.full_name, u.email, u.username, u.created_at, 
           c.class_name, c.semester as class_semester
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    LEFT JOIN classes c ON s.class_id = c.id 
    ORDER BY $sort_column $order, u.full_name ASC
");

$total_students = mysqli_num_rows($students);
$students_with_class = 0;
$students_without_class = 0;

$students_data = [];
while($row = mysqli_fetch_assoc($students)) {
    $students_data[] = $row;
    if(!empty($row['class_id'])) {
        $students_with_class++;
    } else {
        $students_without_class++;
    }
}
$students = $students_data;

$class_distribution = mysqli_query($conn, "
    SELECT c.id, c.class_name, COUNT(s.id) as student_count 
    FROM classes c 
    LEFT JOIN students s ON c.id = s.class_id 
    GROUP BY c.id 
    ORDER BY c.class_name
");

$pending_leaves = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as count FROM leave_requests WHERE status='pending'"
))['count'];

$pending_modifies = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as count FROM modify_requests WHERE status='pending'"
))['count'];
$full_name = $_SESSION['full_name'];

$all_classes = mysqli_query($conn, "SELECT id, class_name, semester FROM classes ORDER BY class_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students · Admin</title>
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

        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
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
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 #000;
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

        .stat-label {
            font-size: 12px;
            color: #999;
        }

        .filter-section {
            background: var(--light-gray);
            border: 4px solid #000;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
        }

        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            font-weight: 800;
            display: block;
            margin-bottom: 5px;
            font-size: 12px;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 3px solid #000;
            font-weight: 600;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .filter-btn {
            padding: 10px 20px;
            border: 3px solid #000;
            font-weight: 800;
            cursor: pointer;
            background: var(--blue);
            color: white;
            box-shadow: 3px 3px 0 #000;
        }

        .reset-btn {
            background: var(--red);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border: 3px solid #000;
            font-weight: 800;
            box-shadow: 3px 3px 0 #000;
        }

        .bulk-actions {
            background: white;
            border: 4px solid #000;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: var(--shadow);
        }

        .bulk-select {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bulk-select select,
        .bulk-select input {
            padding: 8px;
            border: 3px solid #000;
        }

        .apply-btn {
            background: var(--green);
            color: white;
            border: 3px solid #000;
            padding: 8px 15px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 2px 2px 0 #000;
        }

        .selected-count {
            font-weight: 800;
            background: var(--yellow);
            padding: 5px 10px;
            border: 2px solid #000;
        }

        .students-table-container {
            overflow-x: auto;
            border: 4px solid #000;
            box-shadow: var(--shadow);
        }

        .students-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .students-table th {
            background: var(--blue);
            color: white;
            border: 3px solid #000;
            padding: 12px;
            font-weight: 900;
            text-align: left;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .students-table th:hover {
            background: #1542b0;
        }

        .students-table th a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .students-table td {
            border: 3px solid #000;
            padding: 10px;
        }

        .students-table tr:nth-child(even) {
            background: #f9f9f9;
        }

        .students-table tr:hover {
            background: #fff8cc;
        }

        .class-badge {
            background: var(--green);
            color: white;
            padding: 3px 8px;
            border: 2px solid #000;
            font-size: 12px;
            font-weight: 700;
        }

        .no-class-badge {
            background: var(--red);
            color: white;
            padding: 3px 8px;
            border: 2px solid #000;
            font-size: 12px;
            font-weight: 700;
        }

        .action-btns {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            padding: 5px 10px;
            border: 2px solid #000;
            text-decoration: none;
            font-weight: 700;
            font-size: 12px;
            transition: all 0.1s ease;
            display: inline-block;
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
            transform: translate(-1px, -1px);
            box-shadow: 2px 2px 0 #000;
        }

        .class-distribution {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .class-distribution h3 {
            font-size: 18px;
            margin-bottom: 15px;
            background: var(--yellow);
            display: inline-block;
            padding: 5px 15px;
            border: 2px solid #000;
        }

        .distro-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }

        .distro-item {
            border: 2px solid #000;
            padding: 10px;
            text-align: center;
            background: #f9f9f9;
        }

        .distro-class {
            font-weight: 800;
            background: #eee;
            padding: 2px 5px;
            margin-bottom: 5px;
        }

        .distro-count {
            font-size: 20px;
            font-weight: 900;
            color: var(--blue);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .page-btn {
            padding: 8px 12px;
            border: 3px solid #000;
            background: white;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 2px 2px 0 #000;
        }

        .page-btn.active {
            background: var(--blue);
            color: white;
        }

        .checkbox-col {
            width: 40px;
            text-align: center;
        }

        .checkbox-col input[type="checkbox"] {
            width: 18px;
            height: 18px;
            border: 2px solid #000;
            cursor: pointer;
        }

        .export-btn {
            background: var(--purple);
            color: white;
            padding: 8px 15px;
            border: 3px solid #000;
            font-weight: 800;
            text-decoration: none;
            box-shadow: 2px 2px 0 #000;
        }

        @media (max-width: 1200px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 900px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .filter-row {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
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
                <a href="students.php" class="nav-item active">
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
            <h1>🎓 MANAGE STUDENTS</h1>
            <div class="date-display">
                <?php echo date('l, d M Y'); ?>
            </div>
        </div>

        <div class="stats-cards">
            <div class="stat-card">
                <h3>👥 TOTAL STUDENTS</h3>
                <div class="stat-number"><?php echo $total_students; ?></div>
            </div>
            <div class="stat-card">
                <h3>📚 WITH CLASS</h3>
                <div class="stat-number"><?php echo $students_with_class; ?></div>
                <div class="stat-label"><?php echo $total_students > 0 ? round(($students_with_class/$total_students)*100) : 0; ?>% of total</div>
            </div>
            <div class="stat-card">
                <h3>❌ WITHOUT CLASS</h3>
                <div class="stat-number"><?php echo $students_without_class; ?></div>
                <div class="stat-label"><?php echo $total_students > 0 ? round(($students_without_class/$total_students)*100) : 0; ?>% of total</div>
            </div>
            <div class="stat-card">
                <h3>🏫 TOTAL CLASSES</h3>
                <div class="stat-number"><?php echo mysqli_num_rows($class_distribution); ?></div>
            </div>
        </div>

        <div class="class-distribution">
            <h3>📊 CLASS WISE DISTRIBUTION</h3>
            <div class="distro-grid">
                <?php while($distro = mysqli_fetch_assoc($class_distribution)): ?>
                    <div class="distro-item">
                        <div class="distro-class"><?php echo htmlspecialchars($distro['class_name']); ?></div>
                        <div class="distro-count"><?php echo $distro['student_count']; ?></div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="filter-section">
            <div class="filter-row">
                <div class="filter-group">
                    <label>🔍 SEARCH</label>
                    <input type="text" id="searchInput" placeholder="Name, email, student ID..." onkeyup="filterTable()">
                </div>
                <div class="filter-group">
                    <label>🏫 FILTER BY CLASS</label>
                    <select id="classFilter" onchange="filterTable()">
                        <option value="all">All Classes</option>
                        <option value="none">Without Class</option>
                        <?php
                        $class_list = mysqli_query($conn, "SELECT id, class_name FROM classes ORDER BY class_name");
                        while($class = mysqli_fetch_assoc($class_list)) {
                            echo "<option value=\"" . $class['class_name'] . "\">" . $class['class_name'] . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>📅 REGISTRATION DATE</label>
                    <input type="date" id="dateFilter" onchange="filterTable()">
                </div>
                <div class="filter-actions">
                    <button onclick="filterTable()" class="filter-btn">APPLY FILTERS</button>
                    <a href="students.php" class="reset-btn">RESET</a>
                </div>
            </div>
        </div>

        <div class="bulk-actions" id="bulkActions" style="display: none;">
            <div class="bulk-select">
                <span class="selected-count" id="selectedCount">0 selected</span>
                <select id="bulkActionSelect">
                    <option value="">Select Action</option>
                    <option value="delete">Delete Selected</option>
                    <option value="assign_class">Assign to Class</option>
                </select>
                <select id="bulkClassSelect" style="display: none;">
                    <option value="">Select Class</option>
                    <?php 
                    mysqli_data_seek($all_classes, 0);
                    while($class = mysqli_fetch_assoc($all_classes)): 
                    ?>
                        <option value="<?php echo $class['id']; ?>" data-semester="<?php echo $class['semester']; ?>">
                            <?php echo htmlspecialchars($class['class_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <input type="number" id="bulkSemester" placeholder="Semester" style="display: none;" min="1" max="8">
                <button onclick="applyBulkAction()" class="apply-btn">APPLY</button>
            </div>
            <div>
                <a href="#" onclick="exportTableToCSV()" class="export-btn">📥 EXPORT CSV</a>
            </div>
        </div>

        <div class="actions-bar">
            <h2>Student List (<?php echo $total_students; ?>)</h2>
            <div style="display: flex; gap: 10px;">
                <a href="add_student.php" class="add-btn">➕ ADD NEW STUDENT</a>
            </div>
        </div>

        <div class="students-table-container">
            <table class="students-table" id="studentsTable">
                <thead>
                    <tr>
                        <th class="checkbox-col">
                            <input type="checkbox" id="selectAll" onclick="toggleAll()">
                        </th>
                        <th>
                            <a href="?sort=name&order=<?php echo $sort == 'name' ? $next_order : 'asc'; ?>">
                                NAME <?php echo $sort == 'name' ? ($order == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=id&order=<?php echo $sort == 'id' ? $next_order : 'asc'; ?>">
                                STUDENT ID <?php echo $sort == 'id' ? ($order == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th>EMAIL</th>
                        <th>
                            <a href="?sort=class&order=<?php echo $sort == 'class' ? $next_order : 'asc'; ?>">
                                CLASS <?php echo $sort == 'class' ? ($order == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=semester&order=<?php echo $sort == 'semester' ? $next_order : 'asc'; ?>">
                                SEMESTER <?php echo $sort == 'semester' ? ($order == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=roll&order=<?php echo $sort == 'roll' ? $next_order : 'asc'; ?>">
                                ROLL NO <?php echo $sort == 'roll' ? ($order == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=date&order=<?php echo $sort == 'date' ? $next_order : 'asc'; ?>">
                                REGISTERED <?php echo $sort == 'date' ? ($order == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($students)): ?>
                        <?php foreach($students as $student): ?>
                            <tr data-name="<?php echo strtolower($student['full_name'] . ' ' . $student['email'] . ' ' . $student['student_id']); ?>"
                                data-class="<?php echo $student['class_name'] ?? 'none'; ?>"
                                data-date="<?php echo date('Y-m-d', strtotime($student['created_at'])); ?>">
                                <td class="checkbox-col">
                                    <input type="checkbox" class="student-checkbox" value="<?php echo $student['id']; ?>">
                                </td>
                                <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td>
                                    <?php if(!empty($student['class_name'])): ?>
                                        <span class="class-badge"><?php echo htmlspecialchars($student['class_name']); ?></span>
                                    <?php else: ?>
                                        <span class="no-class-badge">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $student['semester'] ?? '-'; ?></td>
                                <td><?php echo htmlspecialchars($student['roll_number'] ?? '-'); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($student['created_at'])); ?></td>
                                <td class="action-btns">
                                    <!-- <a href="view_student.php?id=<?php echo $student['id']; ?>" class="action-btn view-btn">👁️</a> -->
                                     <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="action-btn edit-btn">✏️</a>
                                    <a href="?delete=<?php echo $student['id']; ?>" class="action-btn delete-btn" 
                                       onclick="return confirm('Delete this student?')">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px;">
                                No students found. <a href="add_student.php">Add your first student</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <button class="page-btn" onclick="changePage(1)">1</button>
            <button class="page-btn" onclick="changePage(2)">2</button>
            <button class="page-btn" onclick="changePage(3)">3</button>
            <button class="page-btn" onclick="changePage(4)">4</button>
            <button class="page-btn" onclick="changePage(5)">5</button>
        </div>
    </div>

    <script>
        function filterTable() {
            let searchInput = document.getElementById('searchInput').value.toLowerCase();
            let classFilter = document.getElementById('classFilter').value;
            let dateFilter = document.getElementById('dateFilter').value;
            let rows = document.querySelectorAll('#studentsTable tbody tr');
            
            rows.forEach(row => {
                let name = row.getAttribute('data-name') || '';
                let rowClass = row.getAttribute('data-class') || '';
                let rowDate = row.getAttribute('data-date') || '';
                
                let matchesSearch = name.includes(searchInput);
                let matchesClass = classFilter === 'all' || 
                                  (classFilter === 'none' && rowClass === 'none') || 
                                  rowClass === classFilter;
                let matchesDate = dateFilter === '' || rowDate === dateFilter;
                
                if (matchesSearch && matchesClass && matchesDate) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            updateSelectedCount();
        }

        let selectedStudents = [];

        function toggleAll() {
            let checkboxes = document.querySelectorAll('.student-checkbox');
            let selectAll = document.getElementById('selectAll');
            
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
                if(selectAll.checked) {
                    selectedStudents.push(cb.value);
                } else {
                    selectedStudents = [];
                }
            });
            
            updateBulkActions();
        }

        function updateSelectedCount() {
            selectedStudents = [];
            document.querySelectorAll('.student-checkbox:checked').forEach(cb => {
                selectedStudents.push(cb.value);
            });
            
            let countSpan = document.getElementById('selectedCount');
            let bulkDiv = document.getElementById('bulkActions');
            
            if(selectedStudents.length > 0) {
                countSpan.innerText = selectedStudents.length + ' selected';
                bulkDiv.style.display = 'flex';
            } else {
                bulkDiv.style.display = 'none';
            }
        }

        document.querySelectorAll('.student-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });

        document.getElementById('bulkActionSelect').addEventListener('change', function() {
            let classSelect = document.getElementById('bulkClassSelect');
            let semesterInput = document.getElementById('bulkSemester');
            
            if(this.value === 'assign_class') {
                classSelect.style.display = 'block';
                semesterInput.style.display = 'block';
            } else {
                classSelect.style.display = 'none';
                semesterInput.style.display = 'none';
            }
        });

        document.getElementById('bulkClassSelect').addEventListener('change', function() {
            let selected = this.options[this.selectedIndex];
            if(selected) {
                let semester = selected.getAttribute('data-semester');
                document.getElementById('bulkSemester').value = semester;
            }
        });

        function applyBulkAction() {
            let action = document.getElementById('bulkActionSelect').value;
            if(!action || selectedStudents.length === 0) return;
            
            let form = document.createElement('form');
            form.method = 'POST';
            
            let actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'bulk_action';
            actionInput.value = action;
            form.appendChild(actionInput);
            
            let idsInput = document.createElement('input');
            idsInput.type = 'hidden';
            idsInput.name = 'selected_ids';
            idsInput.value = selectedStudents.join(',');
            form.appendChild(idsInput);
            
            if(action === 'assign_class') {
                let classId = document.getElementById('bulkClassSelect').value;
                let semester = document.getElementById('bulkSemester').value;
                
                if(!classId) {
                    alert('Please select a class');
                    return;
                }
                
                let classInput = document.createElement('input');
                classInput.type = 'hidden';
                classInput.name = 'bulk_class_id';
                classInput.value = classId;
                form.appendChild(classInput);
                
                let semInput = document.createElement('input');
                semInput.type = 'hidden';
                semInput.name = 'bulk_semester';
                semInput.value = semester;
                form.appendChild(semInput);
            }
            
            if(action === 'delete' && !confirm('Delete selected students?')) {
                return;
            }
            
            document.body.appendChild(form);
            form.submit();
        }

        function exportTableToCSV() {
            let csv = [];
            let rows = document.querySelectorAll('#studentsTable tr');
            
            rows.forEach(row => {
                let cols = row.querySelectorAll('td, th');
                let rowData = [];
                cols.forEach((col, index) => {
                    // Skip checkbox column
                    if(index > 0 && index < 8) {
                        rowData.push('"' + col.innerText.replace(/"/g, '""') + '"');
                    }
                });
                csv.push(rowData.join(','));
            });
            
            let csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
            let link = document.createElement('a');
            link.download = 'students_export.csv';
            link.href = window.URL.createObjectURL(csvFile);
            link.click();
        }

        function changePage(page) {
            alert('Page ' + page + ' - Implement server-side pagination for production');
        }
    </script>
</body>
</html>