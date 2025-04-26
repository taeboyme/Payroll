<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Attendance Summary
$attendance_query = "SELECT 
    COUNT(*) as total_days,
    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
    SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days,
    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
    SEC_TO_TIME(AVG(TIME_TO_SEC(TIMEDIFF(time_out, time_in)))) as avg_hours
    FROM attendance_records 
    WHERE employee_id = ? 
    AND MONTH(date) = ? 
    AND YEAR(date) = ?";

$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("sii", $employee_id, $month, $year);
$stmt->execute();
$attendance_summary = $stmt->get_result()->fetch_assoc();

// Attendance Details
$details_query = "SELECT * FROM attendance_records 
                 WHERE employee_id = ? 
                 AND MONTH(date) = ? 
                 AND YEAR(date) = ?
                 ORDER BY date DESC";
$details_stmt = $conn->prepare($details_query);
$details_stmt->bind_param("sii", $employee_id, $month, $year);
$details_stmt->execute();
$attendance_details = $details_stmt->get_result();

// Time-Off Summary
$timeoff_query = "SELECT 
    type,
    COUNT(*) as request_count,
    SUM(DATEDIFF(end_date, start_date) + 1) as total_days
    FROM time_off_requests
    WHERE employee_id = ?
    AND YEAR(start_date) = ?
    AND status = 'Approved'
    GROUP BY type";
$timeoff_stmt = $conn->prepare($timeoff_query);
$timeoff_stmt->bind_param("si", $employee_id, $year);
$timeoff_stmt->execute();
$timeoff_summary = $timeoff_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Reports - Attendance System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border-style: solid;
            border-width: 2px;
            border-color: #d5d8dc;
            margin-bottom: 30px;
        }
        .status-present { background: #e8f5e9; color: #2e7d32; }
        .status-late { background: #fff3e0; color: #ef6c00; }
        .status-absent { background: #ffebee; color: #c62828; }

        @media print {
            .print-actions {
                display: none;
        }
            .printing {
                background: white;
        }
    }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php';?>
<br/>
<div class="container">
    <!-- Filters -->
<div class="print-actions">
    <div class="report-card">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-auto">
                <select name="month" class="form-select">
                    <?php for($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $month == $i ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="year" class="form-select">
                    <?php for($i = date('Y'); $i >= date('Y') - 2; $i--): ?>
                        <option value="<?php echo $i; ?>" <?php echo $year == $i ? 'selected' : ''; ?>>
                            <?php echo $i; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Apply</button>
                <button type="button" class="btn btn-outline-secondary" onclick="handlePrint()">Print</button>
            </div>
        </form>
    </div>
</div>

    <!-- Charts -->
    <div class="row" >
        <div class="col-md-6" >
            <div class="report-card">
                <h5>Attendance Summary</h5>
                <canvas id="attendanceChart" width = "40px"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="report-card">
                <h5>Leave Summary</h5>
                <canvas id="timeoffChart" ></canvas>
            </div>
        </div>
    </div>

    <!-- Monthly Stats -->
    <div class="report-card">
        <h5>Monthly Statistics</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0">
                <tbody>
                    <tr><th>Total Working Days</th><td><?php echo $attendance_summary['total_days']; ?></td></tr>
                    <tr><th>Present Days</th><td><?php echo $attendance_summary['present_days']; ?></td></tr>
                    <tr><th>Late Days</th><td><?php echo $attendance_summary['late_days']; ?></td></tr>
                    <tr><th>Absent Days</th><td><?php echo $attendance_summary['absent_days']; ?></td></tr>
                    <tr><th>Average Hours</th><td><?php echo $attendance_summary['avg_hours'] ? substr($attendance_summary['avg_hours'], 0, 5) : '0:00'; ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Attendance Details -->
    <div class="report-card">
        <h5>Detailed Attendance Record</h5>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Total Hours</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($record = $attendance_details->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                            <td><?php echo $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-'; ?></td>
                            <td><?php echo $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-'; ?></td>
                            <td>
                                <?php
                                if ($record['time_in'] && $record['time_out']) {
                                    $time_in = new DateTime($record['time_in']);
                                    $time_out = new DateTime($record['time_out']);
                                    $interval = $time_in->diff($time_out);
                                    echo $interval->format('%H:%I');
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="badge 
                                    <?php
                                    if ($record['status'] == 'Present') echo 'bg-success';
                                    elseif ($record['status'] == 'Late') echo 'bg-warning text-dark';
                                    elseif ($record['status'] == 'Absent') echo 'bg-danger';
                                    ?>">
                                    <?php echo $record['status']; ?>
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
    const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
    new Chart(attendanceCtx, {
        type: 'pie',
        data: {
            labels: ['Present', 'Late', 'Absent'],
            datasets: [{
                data: [
                    <?php echo $attendance_summary['present_days']; ?>,
                    <?php echo $attendance_summary['late_days']; ?>,
                    <?php echo $attendance_summary['absent_days']; ?>
                ],
                backgroundColor: ['#2ecc71', '#f1c40f', '#e74c3c']
            }]
        },
        options: {
        responsive: true,
        aspectRatio: 2, // Set the aspect ratio (1 for square, adjust as needed)
    }
    });

    const timeoffCtx = document.getElementById('timeoffChart').getContext('2d');
    new Chart(timeoffCtx, {
        type: 'bar',
        data: {
            labels: [<?php 
                $timeoff_summary->data_seek(0);
                while($row = $timeoff_summary->fetch_assoc()) {
                    echo "'" . $row['type'] . "',";
                }
            ?>],
            datasets: [{
                label: 'Days Taken',
                data: [<?php 
                    $timeoff_summary->data_seek(0);
                    while($row = $timeoff_summary->fetch_assoc()) {
                        echo $row['total_days'] . ",";
                    }
                ?>],
                backgroundColor: '#3498db'
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    function exportReport() {
        window.location.href = `export_report.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>`;
    }
</script>
<script>
        function handlePrint() {
            const includeSummary = $('#includeSummary').is(':checked');
    
            // Add print-specific classes
            $('body').addClass('printing');
            $('.no-print').hide();
    
            // Print the document
            window.print();
    
            // Restore original state
            setTimeout(() => {
            $('body').removeClass('printing');
            $('.no-print').show();
            }, 1000);
        }

        function printPreview() {
            $('.print-options').toggleClass('d-none');
        }
    </script>

</body>
</html>

<?php $conn->close(); ?>
