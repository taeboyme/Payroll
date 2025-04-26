<?php
require_once 'config/database.php';
require_once 'classes/CompensationManager.php';

if (!isset($_GET['employee_id'])) {
    die('Employee ID not provided');
}

$compManager = new CompensationManager($conn);
$employeeId = $_GET['employee_id'];

// Get employee details
$stmt = $conn->prepare("SELECT e.*, d.department_name, p.position_title 
                       FROM employees e 
                       LEFT JOIN departments d ON e.department_id = d.department_id
                       LEFT JOIN positions p ON e.position_id = p.position_id
                       WHERE e.employee_id = ?");
$stmt->bind_param("s", $employeeId);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

// Get salary components
$salaryComponents = $compManager->getEmployeeSalary($employeeId);

// Get compensation history
$stmt = $conn->prepare("SELECT * FROM compensation_reviews 
                       WHERE employee_id = ? 
                       ORDER BY review_date DESC");
$stmt->bind_param("s", $employeeId);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid">
    <h4><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h4>
    <p>
        <strong>Position:</strong> <?php echo htmlspecialchars($employee['position_title']); ?><br>
        <strong>Department:</strong> <?php echo htmlspecialchars($employee['department_name']); ?>
    </p>

    <h5>Current Salary Components</h5>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Component</th>
                <th>Amount</th>
                <th>Effective From</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($salaryComponents as $component): ?>
                <tr>
                    <td><?php echo htmlspecialchars($component['component_name']); ?></td>
                    <td><?php echo number_format($component['amount'], 2); ?></td>
                    <td><?php echo $component['effective_from']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h5>Compensation Review History</h5>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Review Date</th>
                <th>Current Salary</th>
                <th>Proposed Salary</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reviews as $review): ?>
                <tr>
                    <td><?php echo $review['review_date']; ?></td>
                    <td><?php echo number_format($review['current_salary'], 2); ?></td>
                    <td><?php echo number_format($review['proposed_salary'], 2); ?></td>
                    <td><?php echo htmlspecialchars($review['status']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>