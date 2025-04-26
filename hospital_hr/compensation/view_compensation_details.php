<?php
session_start();
require_once 'config/database.php';
require_once 'classes/CompensationManager.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$compManager = new CompensationManager($conn);
$employeeId = $_GET['employee_id'];

// Get employee details
$stmt = $conn->prepare("
    SELECT e.*, d.department_name, p.position_title 
    FROM employees e 
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN positions p ON e.position_id = p.position_id
    WHERE e.employee_id = ?
");
$stmt->bind_param("s", $employeeId);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

// Get salary components
$salaryComponents = $compManager->getEmployeeSalary($employeeId);

// Get compensation history
$stmt = $conn->prepare("
    SELECT cr.*, 
           a.first_name as approver_fname, 
           a.last_name as approver_lname
    FROM compensation_reviews cr
    LEFT JOIN employees a ON cr.approver_id = a.employee_id
    WHERE cr.employee_id = ?
    ORDER BY cr.review_date DESC
");
$stmt->bind_param("s", $employeeId);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Employee Compensation Details - Compensation System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
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
<body>
    <?php require_once 'includes/navbar.php';?>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                            <small class="float-right">ID: <?php echo htmlspecialchars($employee['employee_id']); ?></small>
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($employee['department_name']); ?></p>
                                <p><strong>Position:</strong> <?php echo htmlspecialchars($employee['position_title']); ?></p>
                                <p><strong>Hire Date:</strong> <?php echo date('M d, Y', strtotime($employee['hire_date'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($employee['email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($employee['phone']); ?></p>
                                <p><strong>Status:</strong> <?php echo htmlspecialchars($employee['employment_status']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Current Salary Components</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Component</th>
                                    <th>Amount</th>
                                    <th>Effective From</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalSalary = 0;
                                foreach ($salaryComponents as $component): 
                                    $totalSalary += $component['amount'];
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($component['component_name']); ?></td>
                                        <td class="text-right">₱<?php echo number_format($component['amount'], 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($component['effective_from'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-primary">
                                    <td><strong>Total</strong></td>
                                    <td class="text-right"><strong>₱<?php echo number_format($totalSalary, 2); ?></strong></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Compensation Review History</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Current</th>
                                        <th>Proposed</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reviews as $review): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($review['review_date'])); ?></td>
                                            <td class="text-right">$<?php echo number_format($review['current_salary'], 2); ?></td>
                                            <td class="text-right">$<?php echo number_format($review['proposed_salary'], 2); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $review['status'] == 'Approved' ? 'success' : 
                                                        ($review['status'] == 'Pending' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo $review['status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <div class="print-actions">
                <a href="view_compensation.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-1"></i> Back to List
                </a>
                <button class="btn btn-primary" onclick="handlePrint()">
                    <i class="fas fa-print mr-1"></i> Print Details
                </button>
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