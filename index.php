<?php
/**
 * Professional Attendance Management System
 * Version: 2.0
 * Last Updated: 2025
 */

// Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'attendance_db');

// Database connection with improved error handling
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log($e->getMessage());
    die("System temporarily unavailable. Please contact administrator.");
}

// Prepared statement for auto-delete old records
$yesterday = date('Y-m-d', strtotime('-1 day'));
$stmt = $conn->prepare("DELETE FROM attendance WHERE DATE(check_in_time) < ?");
$stmt->bind_param("s", $yesterday);
$stmt->execute();
$stmt->close();

// Fetch today's attendance with prepared statement
$today = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT u.student_id, u.name, u.class, a.check_in_time, a.check_out_time, a.status
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE DATE(a.check_in_time) = ?
    ORDER BY a.check_in_time DESC
");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

// Calculate statistics
$total_today = $result->num_rows;
$present_count = 0;
$completed_count = 0;

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
    if ($row['status'] === 'Present') $present_count++;
    if ($row['status'] === 'Completed') $completed_count++;
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Professional Attendance Management System">
    <title>Attendance Management System</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            background: #ffffff;
            min-height: 100vh;
        }

        /* Header */
        header {
            background: #0078d4;
            color: white;
            padding: 20px 30px;
            margin: -20px -20px 30px -20px;
            border-bottom: 3px solid #005a9e;
        }

        header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .header-subtitle {
            font-size: 13px;
            opacity: 0.9;
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e1e1e1;
            flex-wrap: wrap;
            gap: 15px;
        }

        .toolbar-left h2 {
            font-size: 18px;
            color: #333;
            font-weight: 600;
        }

        .toolbar-right {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: 1px solid #d1d1d1;
            background: white;
            color: #333;
            font-size: 13px;
            cursor: pointer;
            border-radius: 2px;
            font-family: inherit;
            transition: all 0.2s;
        }

        .btn:hover {
            background: #f3f3f3;
            border-color: #adadad;
        }

        .btn-primary {
            background: #0078d4;
            color: white;
            border-color: #0078d4;
        }

        .btn-primary:hover {
            background: #005a9e;
            border-color: #005a9e;
        }

        .search-box {
            padding: 7px 12px;
            border: 1px solid #d1d1d1;
            font-size: 13px;
            width: 250px;
            border-radius: 2px;
            font-family: inherit;
        }

        .search-box:focus {
            outline: none;
            border-color: #0078d4;
        }

        /* Statistics Bar */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            padding: 20px;
            background: #fafafa;
            border: 1px solid #e1e1e1;
        }

        .stat-item {
            padding: 15px;
            background: white;
            border-left: 3px solid #0078d4;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 24px;
            color: #333;
            font-weight: 600;
        }

        /* Table Section */
        .table-container {
            border: 1px solid #d1d1d1;
            background: white;
        }

        .table-header {
            background: #f3f3f3;
            padding: 12px 20px;
            border-bottom: 1px solid #d1d1d1;
            font-weight: 600;
            font-size: 14px;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #fafafa;
        }

        th {
            padding: 12px 20px;
            text-align: left;
            border-bottom: 1px solid #d1d1d1;
            color: #333;
            font-weight: 600;
            font-size: 13px;
        }

        td {
            padding: 12px 20px;
            border-bottom: 1px solid #f0f0f0;
            color: #333;
            font-size: 13px;
        }

        tbody tr:hover {
            background: #f9f9f9;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 2px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            text-transform: uppercase;
        }

        .status-present {
            background: #dff6dd;
            color: #107c10;
            border: 1px solid #9fd89c;
        }

        .status-completed {
            background: #fff4ce;
            color: #8a6d3b;
            border: 1px solid #f0d291;
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #666;
            font-size: 14px;
        }

        /* Footer */
        footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e1e1;
            text-align: center;
            color: #666;
            font-size: 12px;
        }

        .status-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #0078d4;
            color: white;
            padding: 8px 20px;
            font-size: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container { padding: 10px; }
            header { padding: 15px 20px; margin: -10px -10px 20px -10px; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .search-box { width: 100%; }
            .stats-bar { grid-template-columns: 1fr; padding: 15px; }
            th, td { padding: 10px 12px; font-size: 12px; }
        }

        @media print {
            .toolbar-right, .status-bar, .btn { display: none; }
            header { background: white; color: black; border-bottom: 2px solid black; }
            body { background: white; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Attendance Management System</h1>
            <p class="header-subtitle">Daily Attendance Records - <?= date('l, F j, Y') ?></p>
        </header>

        <div class="toolbar">
            <div class="toolbar-left">
                <h2>Attendance Overview</h2>
            </div>
            <div class="toolbar-right">
                <input type="text" class="search-box" id="searchInput" placeholder="Search by name or ID..." onkeyup="filterTable()">
                <button class="btn" onclick="window.print()">Print Report</button>
                <button class="btn btn-primary" onclick="location.reload()">Refresh</button>
            </div>
        </div>

        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-label">Total Attendance</div>
                <div class="stat-value"><?= $total_today ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Currently Present</div>
                <div class="stat-value"><?= $present_count ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Completed</div>
                <div class="stat-value"><?= $completed_count ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Last Updated</div>
                <div class="stat-value" style="font-size: 16px;"><?= date('h:i A') ?></div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">Daily Attendance Records</div>
            <table id="attendanceTable">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Class</th>
                        <th>Check-in Time</th>
                        <th>Check-out Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($records) > 0): ?>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['student_id']) ?></td>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['class']) ?></td>
                                <td><?= date('h:i A', strtotime($row['check_in_time'])) ?></td>
                                <td><?= $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '—' ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($row['status']) ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                No attendance records found for today
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <footer>
            <p>&copy; <?= date('Y') ?> Attendance Management System. All rights reserved.</p>
        </footer>
    </div>

    <div class="status-bar">
        <span>Ready</span>
        <span>Total Records: <?= $total_today ?> | Auto-refresh: Enabled</span>
    </div>

    <script>
        // Auto-refresh every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);

        // Search/Filter functionality
        function filterTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('attendanceTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < td.length; j++) {
                    if (td[j]) {
                        const txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                
                tr[i].style.display = found ? '' : 'none';
            }
        }

        // Clear search on ESC key
        document.getElementById('searchInput').addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                filterTable();
            }
        });
    </script>
</body>
</html>