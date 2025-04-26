<?php
session_start();
require_once 'config/database.php';
require_once 'classes/CompensationManager.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$compManager = new CompensationManager($conn);

// Handle search/filter
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$departmentId = isset($_GET['department']) ? $_GET['department'] : '';

// Get departments for filter
$departments = $conn->query("SELECT * FROM departments ORDER BY department_name");

// Build query for employees
$sql = "SELECT e.*, d.department_name, p.position_title 
        FROM employees e 
        LEFT JOIN departments d ON e.department_id = d.department_id
        LEFT JOIN positions p ON e.position_id = p.position_id
        WHERE e.employment_status = 'Active'";

if ($searchTerm) {
    $sql .= " AND (e.first_name LIKE '%$searchTerm%' OR e.last_name LIKE '%$searchTerm%' OR e.employee_id LIKE '%$searchTerm%')";
}
if ($departmentId) {
    $sql .= " AND e.department_id = '$departmentId'";
}

$employees = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Employee Compensation - Compensation System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
   <?php require_once 'includes/navbar.php';?>

    <div class="container mt-5">
        <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3>Employee Compensation Details</h3>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped" id="employeeTable">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Current Salary</th>
                        <th>Last Review</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($employee = $employees->fetch_assoc()): 
                        // Get current salary components
                        $salaryComponents = $compManager->getEmployeeSalary($employee['employee_id']);
                        $totalSalary = 0;
                        foreach ($salaryComponents as $component) {
                            $totalSalary += $component['amount'];
                        }

                        // Get last review
                        $stmt = $conn->prepare("SELECT * FROM compensation_reviews 
                                             WHERE employee_id = ? 
                                             ORDER BY review_date DESC LIMIT 1");
                        $stmt->bind_param("s", $employee['employee_id']);
                        $stmt->execute();
                        $lastReview = $stmt->get_result()->fetch_assoc();
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                            <td><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($employee['department_name']); ?></td>
                            <td><?php echo htmlspecialchars($employee['position_title']); ?></td>
                            <td><?php echo number_format($totalSalary, 2); ?></td>
                            <td>
                                <?php if ($lastReview): ?>
                                    <?php echo $lastReview['review_date']; ?>
                                    (<?php echo $lastReview['status']; ?>)
                                <?php else: ?>
                                    No reviews
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="view_compensation_details.php?employee_id=<?php echo $employee['employee_id']; ?>" class="btn btn-info btn-sm">View Details</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>
    </div>
    <!-- Modal for Salary Details -->
    <div class="modal fade" id="salaryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Salary Details</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="salaryDetails">
                    Loading...
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