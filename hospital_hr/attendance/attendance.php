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

// Get filter parameters
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build the query with filters
$query = "SELECT * FROM attendance_records WHERE employee_id = ?";
$params = [$employee_id];
$types = "s";

if ($status) {
    $query .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}

$query .= " AND MONTH(date) = ? AND YEAR(date) = ? ORDER BY date DESC";
$params[] = $month;
$params[] = $year;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Calculate statistics
$stats_query = "SELECT 
    COUNT(*) as total_days,
    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
    SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days,
    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
    SEC_TO_TIME(AVG(TIME_TO_SEC(TIMEDIFF(time_out, time_in)))) as avg_hours
    FROM attendance_records 
    WHERE employee_id = ? AND MONTH(date) = ? AND YEAR(date) = ?";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("sii", $employee_id, $month, $year);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records - Attendance System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .stat-card {
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            border: 1px solid #ddd;
            margin: 10px;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php';?>
    
    <div class="container mt-5">
        <h3 class="mb-4">Attendance Records</h3>
        
        <div class="row">
            <!-- Stats cards -->
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <p class="card-text display-4"><?php echo $stats['present_days']; ?></p>
                        <h5 class="card-title">Present Days</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <p class="card-text display-4"><?php echo $stats['late_days']; ?></p>
                        <h5 class="card-title">Late Days</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <p class="card-text display-4"><?php echo $stats['absent_days']; ?></p>
                        <h5 class="card-title">Absent Days</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <p class="card-text display-4"><?php echo $stats['avg_hours'] ? substr($stats['avg_hours'], 0, 5) : '0:00'; ?></p>
                        <h5 class="card-title">Average Hours</h5>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mt-4">
            <div class="card-header">
                <h5>Filter Attendance Records</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="form-inline">
                    <div class="form-group mr-2">
                        <label for="month" class="mr-2">Month</label>
                        <select name="month" id="month" class="form-control">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $month == $i ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group mr-2">
                        <label for="year" class="mr-2">Year</label>
                        <select name="year" id="year" class="form-control">
                            <?php for ($i = date('Y'); $i >= date('Y') - 2; $i--): ?>
                                <option value="<?php echo $i; ?>" <?php echo $year == $i ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group mr-2">
                        <label for="status" class="mr-2">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="Present" <?php echo $status == 'Present' ? 'selected' : ''; ?>>Present</option>
                            <option value="Late" <?php echo $status == 'Late' ? 'selected' : ''; ?>>Late</option>
                            <option value="Absent" <?php echo $status == 'Absent' ? 'selected' : ''; ?>>Absent</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </form>
            </div>
        </div>

        <!-- Attendance Records Table -->
        <div class="card mt-4">
            <div class="card-body">
                <table id="attendanceTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Total Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td><?php echo $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '-'; ?></td>
                                <td><?php echo $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '-'; ?></td>
                                <td>
                                    <?php
                                    if ($row['time_in'] && $row['time_out']) {
                                        $time_in = new DateTime($row['time_in']);
                                        $time_out = new DateTime($row['time_out']);
                                        $interval = $time_in->diff($time_out);
                                        echo $interval->format('%H:%I');
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php
                                        echo strtolower($row['status']) == 'present' ? 'success' :
                                            (strtolower($row['status']) == 'late' ? 'warning' : 'danger');
                                    ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#attendanceTable').DataTable({
                "pageLength": 10,
                "order": [[0, "desc"]],
                "language": {
                    "search": "Search attendance records:",
                    "lengthMenu": "Show _MENU_ records per page",
                }
            });
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>
