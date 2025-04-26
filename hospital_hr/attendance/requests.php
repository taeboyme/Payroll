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

// Fetch all time-off requests for the employee
$query = "SELECT * FROM time_off_requests 
          WHERE employee_id = ? 
          ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$requests = $stmt->get_result();

// Calculate remaining leave balance
$balance_query = "SELECT 
                    (SELECT COUNT(*) FROM time_off_requests 
                     WHERE employee_id = ? 
                     AND type = 'Vacation' 
                     AND status = 'Approved' 
                     AND YEAR(start_date) = YEAR(CURRENT_DATE)) as used_vacation,
                    (SELECT COUNT(*) FROM time_off_requests 
                     WHERE employee_id = ? 
                     AND type = 'Sick' 
                     AND status = 'Approved' 
                     AND YEAR(start_date) = YEAR(CURRENT_DATE)) as used_sick";
$balance_stmt = $conn->prepare($balance_query);
$balance_stmt->bind_param("ss", $employee_id, $employee_id);
$balance_stmt->execute();
$balance = $balance_stmt->get_result()->fetch_assoc();

$total_vacation_days = 15; // Annual vacation days
$total_sick_days = 10;     // Annual sick days
$remaining_vacation = $total_vacation_days - $balance['used_vacation'];
$remaining_sick = $total_sick_days - $balance['used_sick'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Requests - Attendance System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        
        .balance-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .balance-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .balance-value {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }

        .requests-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .requests-table th,
        .requests-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .requests-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-pending { background: #fff3e0; color: #ef6c00; }
        .status-approved { background: #e8f5e9; color: #2e7d32; }
        .status-rejected { background: #ffebee; color: #c62828; }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .request-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 30px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .alert-info { background: #e3f2fd; color: #1565c0; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        .alert-warning { background: #fff3e0; color: #ef6c00; }
    </style>
</head>
<body>
    <!-- Place this inside <body> -->
<?php include 'includes/navbar.php';?>

    <?php if (isset($_SESSION['timeoff_feedback'])): ?>
    <div class="alert alert-<?php echo $_SESSION['timeoff_feedback']['type'] === 'success' ? 'success' : 'danger'; ?> mt-3">
    <?php echo $_SESSION['timeoff_feedback']['message']; ?>
    </div>
    <?php unset($_SESSION['timeoff_feedback']); ?>
    <?php endif; ?>
<div class="container mt-5">
    <h2 class="mb-4">Leave Dashboard</h2>

    <!-- Leave Balance Cards -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-body bg-info text-white">
                    <i class="fas fa-umbrella-beach fa-2x mb-2"></i>
                    <h5 class="card-title">Vacation Days</h5>
                    <p class="card-text display-4"><?php echo $remaining_vacation; ?></p>
                    <span>Remaining</span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-body bg-warning text-white">
                    <i class="fas fa-procedures fa-2x mb-2"></i>
                    <h5 class="card-title">Sick Days</h5>
                    <p class="card-text display-4"><?php echo $remaining_sick; ?></p>
                    <span>Remaining</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Form -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Request Leave</h4>
        </div>
        <div class="card-body">
            <form id="timeOffForm" action="process_timeout.php" method="POST">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" class="form-control" required>
                        <option value="Vacation">Vacation</option>
                        <option value="Sick">Sick Leave</option>
                        <option value="Personal">Personal Leave</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="notes">Notes (Optional)</label>
                        <textarea name="notes" id="notes" class="form-control" rows="4"></textarea>
                    </div>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </form>
        </div>
    </div>

    <!-- Request History Table -->
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Request History</h4>
        </div>
        <div class="card-body">
            <table id="requestTable" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($request = $requests->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($request['type']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($request['end_date'])); ?></td>
                        <td>
                            <span class="badge badge-pill 
                                <?php
                                    if ($request['status'] == 'Approved') echo 'badge-success';
                                    elseif ($request['status'] == 'Rejected') echo 'badge-danger';
                                    else echo 'badge-warning';
                                ?>">
                                <?php echo $request['status']; ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                        <td>
                            <?php if($request['status'] == 'Pending'): ?>
                                <a href="javascript:void(0);" class="btn btn-sm btn-danger" onclick="cancelRequest(<?php echo $request['id']; ?>)">Cancel</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <br/>
    <br/>
    <br/>
</div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://kit.fontawesome.com/your-font-awesome-kit.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.js"></script>
    <script>
    $(document).ready(function () {
        $('#requestTable').DataTable({
            "pageLength": 5,
            "order": [[4, "desc"]],
            "language": {
                "search": "Search requests:",
                "lengthMenu": "Show _MENU_ entries"
            }
        });
    });

    function cancelRequest(id) {
        if (confirm("Are you sure you want to cancel this request?")) {
            window.location.href = 'cancel_request.php?id=' + id;
        }
    }

    document.getElementById('timeOffForm').addEventListener('submit', function(e) {
        const start = new Date(this.start_date.value);
        const end = new Date(this.end_date.value);
        if (end < start) {
            alert("End date must be after start date.");
            e.preventDefault();
        }
    });
</script>

</body>
</html>

<?php $conn->close(); ?>