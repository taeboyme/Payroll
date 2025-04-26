<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit();
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$employee_id = $_SESSION['employee_id'];
$message = '';
$current_month = date('m');
$current_year = date('Y');

// Handle Clock In/Out
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_time = date('Y-m-d H:i:s');
    
    if (isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'clock_in':
                $check_query = "SELECT * FROM attendance_records WHERE employee_id = ? AND date = CURRENT_DATE()";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param("s", $employee_id);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows == 0) {
                    $query = "INSERT INTO attendance_records (employee_id, date, time_in, status) VALUES (?, CURRENT_DATE(), ?, 'Present')";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ss", $employee_id, $current_time);
                    $stmt->execute();
                    $message = "Successfully Time In!";
                }
                break;

            case 'clock_out':
                $query = "UPDATE attendance_records SET time_out = ? WHERE employee_id = ? AND date = CURRENT_DATE() AND time_out IS NULL";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $current_time, $employee_id);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $message = "Successfully Time Out!";
                }
                break;
        }
    }
}

// Get Employee Details
$emp_query = "SELECT e.*, d.department_name 
              FROM employees e 
              LEFT JOIN departments d ON e.department_id = d.department_id 
              WHERE e.employee_id = ?";
$emp_stmt = $conn->prepare($emp_query);
$emp_stmt->bind_param("s", $employee_id);
$emp_stmt->execute();
$employee = $emp_stmt->get_result()->fetch_assoc();

// Get Monthly Statistics
$stats_query = "SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN time_in IS NOT NULL AND time_out IS NOT NULL THEN 1 ELSE 0 END) as complete_days,
                SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days,
                AVG(TIMESTAMPDIFF(HOUR, time_in, time_out)) as avg_hours
                FROM attendance_records 
                WHERE employee_id = ? 
                AND MONTH(date) = ? 
                AND YEAR(date) = ?";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("sii", $employee_id, $current_month, $current_year);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Get Today's Record
$today_query = "SELECT * FROM attendance_records WHERE employee_id = ? AND date = CURRENT_DATE()";
$today_stmt = $conn->prepare($today_query);
$today_stmt->bind_param("s", $employee_id);
$today_stmt->execute();
$today_record = $today_stmt->get_result()->fetch_assoc();

// Get Recent Records
$recent_query = "SELECT * FROM attendance_records WHERE employee_id = ? ORDER BY date DESC LIMIT 5";
$recent_stmt = $conn->prepare($recent_query);
$recent_stmt->bind_param("s", $employee_id);
$recent_stmt->execute();
$recent_records = $recent_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .dashboard-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
        }

        .clock-display {
            font-size: 36px;
            text-align: center;
            margin: 20px 0;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-present { background: #e8f5e9; color: #2e7d32; }
        .status-late { background: #fff3e0; color: #ef6c00; }
        .status-absent { background: #ffebee; color: #c62828; }

        #calendar {
            height: 400px;
            margin-top: 20px;
        }

        .chart-container {
            height: 300px;
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php';?>

    <div class="container mt-4">
        <div class="row">

            <!-- Employee Info -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="fas fa-user"></i> Employee Information
                    </div>
                    <div class="card-body">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></p>
                        <p><strong>Department:</strong> <?php echo htmlspecialchars($employee['department_name']); ?></p>
                        <p><strong>Employee ID:</strong> <?php echo htmlspecialchars($employee['employee_id']); ?></p>
                        <p><strong>Hired Date:</strong> <?php echo htmlspecialchars($employee['hire_date']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Clock In/Out -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="fas fa-clock"></i>&nbsp;&nbsp;Time Clock
                    </div>
                    <div class="card-body text-center">
                        <div id="clock" class="clock-display"></div>
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-info"><?php echo $message; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-center">
                        <form method="POST" style="display:inline;">
                            <button type="submit" name="action" value="clock_in" class="btn btn-success mr-2" <?php echo $today_record ? 'disabled' : ''; ?>>Time In</button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <button type="submit" name="action" value="clock_out" class="btn btn-danger" <?php echo (!$today_record || $today_record['time_out']) ? 'disabled' : ''; ?>>Time Out</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Monthly Stats -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="fas fa-chart-line"></i>&nbsp;&nbsp;Monthly Attendance Statistics
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <h5><?php echo $stats['complete_days']; ?></h5>
                                <small>Days Present</small>
                            </div>
                            <div class="col-4">
                                 <h5><?php echo $stats['late_days']; ?></h5>
                                <small>Days Late</small>
                            </div>
                            <div class="col-4">
                                 <h5><?php echo number_format($stats['avg_hours'], 1); ?></h5>
                                <small>Avg Hours/Day</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calendar -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="fas fa-calendar-alt"></i>&nbsp;&nbsp;Attendance Calendar
                    </div>
                    <div class="card-body">
                        <div id="calendar"></div>
                    </div>
                </div>
            </div>

            <!-- Recent Attendance -->
            <div class="col-md-12 mb-4">
                <div class="card h-100">
                    <div class="card-header align-items-center">
                        <i class="fas fa-history"></i>&nbsp;&nbsp;Recent Attendance Records
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($record = $recent_records->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['date']); ?></td>
                                    <td><?php echo htmlspecialchars($record['time_in']); ?></td>
                                    <td><?php echo $record['time_out'] ? htmlspecialchars($record['time_out']) : '-'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($record['status']); ?>">
                                            <?php echo htmlspecialchars($record['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://kit.fontawesome.com/your-font-awesome-kit.js"></script>

    <script>
        // Real-time clock
        function updateClock() {
            const now = new Date();
            document.getElementById('clock').textContent = now.toLocaleTimeString();
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Initialize Calendar
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek'
                }
            });
            calendar.render();
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>
