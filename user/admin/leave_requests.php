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

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action']; // approve or reject
    $admin_comment = isset($_POST['admin_comment']) ? trim($_POST['admin_comment']) : '';
    
    if ($action === 'approve') {
        $status = 'approved';
        $status_text = 'Approved';
    } else {
        $status = 'rejected';
        $status_text = 'Rejected';
    }
    
    $update = "UPDATE leave_requests SET 
               status = '$status', 
               processed_by = {$_SESSION['user_id']}, 
               processed_at = NOW() 
               WHERE id = $request_id";
    
    if (mysqli_query($conn, $update)) {
        $success = "Leave request $status_text successfully!";
        
        // If approved, also update timetable for that day (optional)
        if ($action === 'approve') {
            // Get request details to update timetable
            $req_query = mysqli_query($conn, "SELECT * FROM leave_requests WHERE id = $request_id");
            if ($req = mysqli_fetch_assoc($req_query)) {
                // Mark that period as empty in timetable or assign substitute teacher
                // This can be implemented as needed
            }
        }
    } else {
        $error = 'Error processing request: ' . mysqli_error($conn);
    }
}

// Handle bulk action
if (isset($_POST['bulk_action']) && isset($_POST['selected_ids'])) {
    $bulk_action = $_POST['bulk_action'];
    $selected_ids = explode(',', $_POST['selected_ids']);
    $count = 0;
    
    if ($bulk_action === 'approve') {
        foreach($selected_ids as $id) {
            $update = "UPDATE leave_requests SET status = 'approved', processed_by = {$_SESSION['user_id']}, processed_at = NOW() WHERE id = $id";
            if (mysqli_query($conn, $update)) $count++;
        }
        $success = "$count leave requests approved successfully!";
    } elseif ($bulk_action === 'reject') {
        foreach($selected_ids as $id) {
            $update = "UPDATE leave_requests SET status = 'rejected', processed_by = {$_SESSION['user_id']}, processed_at = NOW() WHERE id = $id";
            if (mysqli_query($conn, $update)) $count++;
        }
        $success = "$count leave requests rejected successfully!";
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$teacher_filter = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;

// Build query
$where_clauses = [];
if ($status_filter != 'all') {
    $where_clauses[] = "lr.status = '$status_filter'";
}
if ($date_filter) {
    $where_clauses[] = "lr.leave_date = '$date_filter'";
}
if ($teacher_filter > 0) {
    $where_clauses[] = "lr.teacher_id = $teacher_filter";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Fetch all leave requests
$requests = mysqli_query($conn, "
    SELECT lr.*, 
           u.full_name as teacher_name, 
           t.employee_id,
           ts.start_time, ts.end_time, ts.slot_number,
           pu.full_name as processed_by_name
    FROM leave_requests lr 
    JOIN teachers t ON lr.teacher_id = t.id 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN time_slots ts ON lr.slot_id = ts.id
    LEFT JOIN users pu ON lr.processed_by = pu.id
    $where_sql
    ORDER BY 
        CASE lr.status 
            WHEN 'pending' THEN 1 
            WHEN 'approved' THEN 2 
            WHEN 'rejected' THEN 3 
        END,
        lr.leave_date DESC,
        lr.requested_at DESC
");

// Get statistics
$stats_query = mysqli_query($conn, "
    SELECT 
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        COUNT(*) as total
    FROM leave_requests
");
$stats = mysqli_fetch_assoc($stats_query);

// Fetch all teachers for filter dropdown
$all_teachers = mysqli_query($conn, "
    SELECT t.id, u.full_name, t.employee_id 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY u.full_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Requests · Admin</title>
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

        /* Statistics Cards */
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
            text-align: center;
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
        }

        .stat-pending { color: var(--orange); }
        .stat-approved { color: var(--green); }
        .stat-rejected { color: var(--red); }
        .stat-total { color: var(--blue); }

        /* Filter Bar */
        .filter-bar {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            margin-bottom: 30px;
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
            min-width: 150px;
        }

        .filter-group label {
            font-weight: 800;
            display: block;
            margin-bottom: 5px;
            font-size: 12px;
            background: var(--blue);
            color: white;
            padding: 3px 8px;
            border: 2px solid #000;
            width: fit-content;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px;
            border: 3px solid #000;
            font-weight: 600;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .filter-btn {
            padding: 10px 20px;
            border: 3px solid #000;
            font-weight: 800;
            cursor: pointer;
            background: var(--blue);
            color: white;
            text-decoration: none;
        }

        .reset-btn {
            background: var(--red);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border: 3px solid #000;
            font-weight: 800;
        }

        /* Bulk Actions */
        .bulk-actions {
            background: var(--light-gray);
            border: 4px solid #000;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .selected-count {
            background: var(--blue);
            color: white;
            padding: 5px 10px;
            border: 2px solid #000;
            font-weight: 800;
        }

        .bulk-btn {
            padding: 8px 15px;
            border: 3px solid #000;
            font-weight: 800;
            cursor: pointer;
            background: var(--green);
            color: white;
        }

        .bulk-reject {
            background: var(--red);
        }

        /* Request Cards */
        .requests-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .request-card {
            background: white;
            border: 4px solid #000;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: transform 0.2s ease;
            position: relative;
        }

        .request-card:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 #000;
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .teacher-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .teacher-name {
            font-size: 20px;
            font-weight: 900;
            background: var(--yellow);
            padding: 5px 15px;
            border: 2px solid #000;
        }

        .employee-id {
            background: var(--blue);
            color: white;
            padding: 5px 10px;
            border: 2px solid #000;
            font-size: 12px;
        }

        .request-date {
            background: var(--orange);
            color: white;
            padding: 5px 10px;
            border: 2px solid #000;
            font-weight: 800;
        }

        .status-badge {
            padding: 5px 15px;
            border: 2px solid #000;
            font-weight: 800;
            font-size: 14px;
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

        .request-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-box {
            border: 2px solid #000;
            padding: 10px;
        }

        .detail-label {
            font-weight: 800;
            background: #eee;
            display: inline-block;
            padding: 2px 8px;
            border: 1px solid #000;
            font-size: 11px;
            margin-bottom: 5px;
        }

        .detail-value {
            font-weight: 700;
            font-size: 14px;
        }

        .reason-box {
            background: #f9f9f9;
            border: 2px solid #000;
            padding: 15px;
            margin-bottom: 15px;
        }

        .reason-box strong {
            display: block;
            margin-bottom: 5px;
        }

        .admin-comment {
            margin-top: 10px;
            padding: 10px;
            background: #e8f5e9;
            border: 2px solid #000;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .approve-form, .reject-form {
            display: inline;
        }

        .approve-btn, .reject-btn {
            padding: 8px 20px;
            border: 3px solid #000;
            font-weight: 800;
            cursor: pointer;
            font-size: 14px;
        }

        .approve-btn {
            background: var(--green);
            color: white;
        }

        .reject-btn {
            background: var(--red);
            color: white;
        }

        .checkbox-col {
            width: 40px;
            text-align: center;
        }

        .checkbox-col input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .empty-state {
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

        @media (max-width: 900px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .request-header {
                flex-direction: column;
                align-items: flex-start;
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
            <div class="admin-name">👤 <?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
            <span class="admin-role">⚙️ ADMINISTRATOR</span>
        </div>

        <div class="nav-menu">
            <div class="nav-section">
                <div class="nav-section-title">MAIN</div>
                <a href="dashboard.php" class="nav-item">📊 Dashboard</a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">MANAGEMENT</div>
                <a href="teachers.php" class="nav-item">👨‍🏫 Teachers</a>
                <a href="students.php" class="nav-item">🎓 Students</a>
                <a href="classes.php" class="nav-item">🏫 Classes</a>
                <a href="subjects.php" class="nav-item">📚 Subjects</a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">REQUESTS</div>
                <a href="leave_requests.php" class="nav-item active">✈️ Leave Requests</a>
                <a href="modify_requests.php" class="nav-item">🔄 Modify Requests</a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">TIMETABLE</div>
                <a href="view_timetable.php" class="nav-item">👁️ View Timetable</a>
                <a href="generate_timetable.php" class="nav-item">⚡ Generate Timetable</a>
                <a href="time_slots.php" class="nav-item">⏰ Time Slots</a>
            </div>
        </div>

        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn">🚪 LOGOUT</a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="content-header">
            <h1>✈️ LEAVE REQUESTS MANAGEMENT</h1>
            <div class="date-display">
                <?php echo date('l, d M Y'); ?>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <h3>⏳ PENDING</h3>
                <div class="stat-number stat-pending"><?php echo $stats['pending']; ?></div>
            </div>
            <div class="stat-card">
                <h3>✅ APPROVED</h3>
                <div class="stat-number stat-approved"><?php echo $stats['approved']; ?></div>
            </div>
            <div class="stat-card">
                <h3>❌ REJECTED</h3>
                <div class="stat-number stat-rejected"><?php echo $stats['rejected']; ?></div>
            </div>
            <div class="stat-card">
                <h3>📊 TOTAL</h3>
                <div class="stat-number stat-total"><?php echo $stats['total']; ?></div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="get" action="" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>📊 STATUS</label>
                        <select name="status" onchange="this.form.submit()">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Requests</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>👨‍🏫 TEACHER</label>
                        <select name="teacher_id" onchange="this.form.submit()">
                            <option value="0">All Teachers</option>
                            <?php 
                            mysqli_data_seek($all_teachers, 0);
                            while($teacher = mysqli_fetch_assoc($all_teachers)): 
                            ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo $teacher_filter == $teacher['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['full_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>📅 LEAVE DATE</label>
                        <input type="date" name="date" value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
                    </div>
                    <div class="filter-actions">
                        <a href="leave_requests.php" class="reset-btn">RESET</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions" id="bulkActions" style="display: none;">
            <div class="selected-count" id="selectedCount">0 selected</div>
            <button onclick="bulkAction('approve')" class="bulk-btn">✅ Approve Selected</button>
            <button onclick="bulkAction('reject')" class="bulk-btn bulk-reject">❌ Reject Selected</button>
        </div>

        <?php if ($error): ?>
            <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-box"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Leave Requests List -->
        <div class="requests-container">
            <?php if (mysqli_num_rows($requests) > 0): ?>
                <?php while($req = mysqli_fetch_assoc($requests)): ?>
                    <div class="request-card" data-status="<?php echo $req['status']; ?>">
                        <div class="request-header">
                            <div class="teacher-info">
                                <input type="checkbox" class="request-checkbox" value="<?php echo $req['id']; ?>" style="margin-right: 10px;">
                                <span class="teacher-name">👨‍🏫 <?php echo htmlspecialchars($req['teacher_name']); ?></span>
                                <span class="employee-id">ID: <?php echo htmlspecialchars($req['employee_id']); ?></span>
                            </div>
                            <div>
                                <span class="request-date">📅 <?php echo date('d M Y', strtotime($req['leave_date'])); ?></span>
                                <span class="status-badge status-<?php echo $req['status']; ?>">
                                    <?php echo strtoupper($req['status']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="request-details">
                            <?php if($req['slot_id']): ?>
                                <div class="detail-box">
                                    <div class="detail-label">⏰ TIME SLOT</div>
                                    <div class="detail-value">
                                        Slot <?php echo $req['slot_number']; ?>: 
                                        <?php echo date('h:i A', strtotime($req['start_time'])); ?> - 
                                        <?php echo date('h:i A', strtotime($req['end_time'])); ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="detail-box">
                                    <div class="detail-label">📆 DURATION</div>
                                    <div class="detail-value">Full Day Leave</div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="detail-box">
                                <div class="detail-label">📅 REQUESTED ON</div>
                                <div class="detail-value"><?php echo date('d M Y, h:i A', strtotime($req['requested_at'])); ?></div>
                            </div>
                            
                            <?php if($req['processed_at']): ?>
                                <div class="detail-box">
                                    <div class="detail-label">⚙️ PROCESSED ON</div>
                                    <div class="detail-value">
                                        <?php echo date('d M Y, h:i A', strtotime($req['processed_at'])); ?><br>
                                        <small>By: <?php echo htmlspecialchars($req['processed_by_name']); ?></small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="reason-box">
                            <strong>📝 REASON FOR LEAVE:</strong><br>
                            <?php echo nl2br(htmlspecialchars($req['reason'])); ?>
                        </div>

                        <?php if ($req['status'] == 'pending'): ?>
                            <div class="action-buttons">
                                <form method="post" class="approve-form" style="display: inline;">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="approve-btn" onclick="return confirm('Approve this leave request?')">✅ APPROVE</button>
                                </form>
                                <form method="post" class="reject-form" style="display: inline;">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="reject-btn" onclick="return confirm('Reject this leave request?')">❌ REJECT</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>📭 No leave requests found</p>
                    <a href="teachers.php" class="btn-primary" style="padding: 10px 20px; text-decoration: none;">View Teachers</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Bulk actions
        let selectedRequests = [];

        function updateSelectedCount() {
            selectedRequests = [];
            document.querySelectorAll('.request-checkbox:checked').forEach(cb => {
                selectedRequests.push(cb.value);
            });
            
            let countSpan = document.getElementById('selectedCount');
            let bulkDiv = document.getElementById('bulkActions');
            
            if(selectedRequests.length > 0) {
                countSpan.innerText = selectedRequests.length + ' selected';
                bulkDiv.style.display = 'flex';
            } else {
                bulkDiv.style.display = 'none';
            }
        }

        // Add click handlers to checkboxes
        document.querySelectorAll('.request-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });

        // Select all functionality
        function toggleAll() {
            let checkboxes = document.querySelectorAll('.request-checkbox');
            let allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });
            updateSelectedCount();
        }

        // Bulk action function
        function bulkAction(action) {
            if(selectedRequests.length === 0) {
                alert('Please select at least one request');
                return;
            }
            
            let confirmMsg = action === 'approve' 
                ? 'Approve ' + selectedRequests.length + ' leave requests?' 
                : 'Reject ' + selectedRequests.length + ' leave requests?';
            
            if(confirm(confirmMsg)) {
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
                idsInput.value = selectedRequests.join(',');
                form.appendChild(idsInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Add Select All checkbox to header (optional)
        let firstCheckbox = document.querySelector('.request-checkbox');
        if(firstCheckbox) {
            let headerRow = document.querySelector('.request-header');
            if(headerRow) {
                // Add select all functionality
            }
        }
    </script>
</body>
</html>