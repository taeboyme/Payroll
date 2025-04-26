<?php
session_start();
require_once 'config/database.php';
require_once 'classes/CompensationManager.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$compManager = new CompensationManager($conn);

// Get summary statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total_employees FROM employees WHERE employment_status = 'Active'");
$stmt->execute();
$employeeCount = $stmt->get_result()->fetch_assoc()['total_employees'];

$stmt = $conn->prepare("SELECT COUNT(*) as pending_reviews FROM compensation_reviews WHERE status = 'Pending'");
$stmt->execute();
$pendingReviews = $stmt->get_result()->fetch_assoc()['pending_reviews'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Compensation System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php require_once 'includes/navbar.php';?>

    <div class="container mt-4">
        <h2>Dashboard</h2>
        
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Active Employees</h5>
                        <p class="card-text display-4"><?php echo $employeeCount; ?></p>
                        <a href="view_compensation.php" class="text-white">View Details <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Pending Reviews</h5>
                        <p class="card-text display-4"><?php echo $pendingReviews; ?></p>
                        <a href="manage_review.php" class="text-white">View Details <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Quick Actions</h5>
                        <div class="list-group">
                            <a href="view_compensation.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-search"></i> View Employee Compensation
                            </a>
                            <a href="manage_review.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-plus"></i> Create New Salary Review
                            </a>
                            <a href="report.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-chart-bar"></i> Compensation Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Recent Reviews</h5>
                        <?php
                        $stmt = $conn->prepare("
                            SELECT cr.*, e.first_name, e.last_name 
                            FROM compensation_reviews cr
                            JOIN employees e ON cr.employee_id = e.employee_id
                            ORDER BY review_date DESC LIMIT 5
                        ");
                        $stmt->execute();
                        $recentReviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        ?>
                        <div class="list-group">
                            <?php foreach ($recentReviews as $review): ?>
                                <a href="#" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></h6>
                                        <small><?php echo $review['review_date']; ?></small>
                                    </div>
                                    <p class="mb-1">Status: <?php echo htmlspecialchars($review['status']); ?></p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#employeeTable').DataTable({
                "pageLength": 10,
                "order": [[1, "asc"]],
                "language": {
                    "search": "Search employees:",
                    "lengthMenu": "Show _MENU_ employees per page",
                }
            });
        });
    </script>
</body>
</html>