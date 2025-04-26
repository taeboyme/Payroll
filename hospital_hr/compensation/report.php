<?php
session_start();
require_once 'config/database.php';
require_once 'classes/CompensationManager.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize managers
$compManager = new CompensationManager($conn);

// Get department statistics
$deptStats = $conn->query("
    SELECT d.department_name,
           COUNT(e.employee_id) as employee_count,
           AVG(cr.current_salary) as avg_salary,
           MIN(cr.current_salary) as min_salary,
           MAX(cr.current_salary) as max_salary
    FROM departments d
    LEFT JOIN employees e ON d.department_id = e.department_id
    LEFT JOIN compensation_reviews cr ON e.employee_id = cr.employee_id
    WHERE cr.status = 'Approved'
    GROUP BY d.department_id
    ORDER BY d.department_name
");

// Get recent reviews
$recentReviews = $conn->query("
    SELECT cr.*, 
           e.first_name, e.last_name,
           d.department_name,
           p.position_title,
           CONCAT(a.first_name, ' ', a.last_name) as approver_name
    FROM compensation_reviews cr
    JOIN employees e ON cr.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN positions p ON e.position_id = p.position_id
    LEFT JOIN employees a ON cr.approver_id = a.employee_id
    ORDER BY cr.review_date DESC
    LIMIT 10
");

// Calculate overall statistics
$overallStats = $conn->query("
    SELECT 
        COUNT(DISTINCT employee_id) as total_employees,
        AVG(current_salary) as avg_salary,
        COUNT(CASE WHEN status = 'Approved' THEN 1 END) as approved_reviews,
        COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_reviews
    FROM compensation_reviews
")->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Compensation Reports - Compensation System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
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
   
    <?php require_once 'includes/navbar.php';?>
    <div class="container mt-4">
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Compensation Overview</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5>Total Employees</h5>
                                        <h2><?php echo $overallStats['total_employees']; ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5>Average Salary</h5>
                                        <h2>₱<?php echo number_format($overallStats['avg_salary'], 2); ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5>Approved Reviews</h5>
                                        <h2><?php echo $overallStats['approved_reviews']; ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5>Pending Reviews</h5>
                                        <h2><?php echo $overallStats['pending_reviews']; ?></h2>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Department Statistics</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="departmentChart"></canvas>
                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Employees</th>
                                        <th>Avg Salary</th>
                                        <th>Range</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($dept = $deptStats->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                            <td><?php echo $dept['employee_count']; ?></td>
                                            <td>$<?php echo number_format($dept['avg_salary'], 2); ?></td>
                                            <td>
                                                $<?php echo number_format($dept['min_salary'], 2); ?> - 
                                                $<?php echo number_format($dept['max_salary'], 2); ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Reviews</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Current</th>
                                        <th>Proposed</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($review = $recentReviews->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($review['position_title']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($review['department_name']); ?></td>
                                            <td>$<?php echo number_format($review['current_salary'], 2); ?></td>
                                            <td>$<?php echo number_format($review['proposed_salary'], 2); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $review['status'] == 'Approved' ? 'success' : 
                                                        ($review['status'] == 'Pending' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo $review['status']; ?>
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

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="print-actions">
                        <button class="btn btn-primary" onclick="handlePrint()">
                            <i class="fas fa-print mr-1"></i> Print Report
                        </button>
                </div>
            </div>
        </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


    <script>
    // Initialize department chart
    const ctx = document.getElementById('departmentChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [<?php 
                $deptStats->data_seek(0);
                echo implode(',', array_map(function($dept) {
                    return '"' . $dept['department_name'] . '"';
                }, iterator_to_array($deptStats)));
            ?>],
            datasets: [{
                label: 'Average Salary',
                data: [<?php 
                    $deptStats->data_seek(0);
                    echo implode(',', array_map(function($dept) {
                        return $dept['avg_salary'];
                    }, iterator_to_array($deptStats)));
                ?>],
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    function exportReport(format) {
        window.location.href = `Export_Compensation_Report.php?type=overview&format=${format}`;
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