<?php
session_start();
require_once 'config/database.php';
require_once 'classes/CompensationManager.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$compManager = new CompensationManager($conn);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $reviewData = [
                    'employee_id' => $_POST['employee_id'],
                    'review_date' => $_POST['review_date'],
                    'current_salary' => $_POST['current_salary'],
                    'proposed_salary' => $_POST['proposed_salary'],
                    'status' => 'Pending',
                    'approver_id' => $_POST['approver_id'],
                    'comments' => $_POST['comments']
                ];
                if ($compManager->createCompensationReview($reviewData)) {
                    $message = "Review created successfully";
                } else {
                    $error = "Error creating review";
                }
                break;

            case 'approve':
                $stmt = $conn->prepare("UPDATE compensation_reviews 
                                      SET status = 'Approved', 
                                          approved_salary = proposed_salary,
                                          effective_date = ? 
                                      WHERE review_id = ?");
                $effectiveDate = $_POST['effective_date'];
                $reviewId = $_POST['review_id'];
                $stmt->bind_param("si", $effectiveDate, $reviewId);
                if ($stmt->execute()) {
                    $message = "Review approved successfully";
                } else {
                    $error = "Error approving review";
                }
                break;

            case 'reject':
                $stmt = $conn->prepare("UPDATE compensation_reviews 
                                      SET status = 'Rejected' 
                                      WHERE review_id = ?");
                $stmt->bind_param("i", $_POST['review_id']);
                if ($stmt->execute()) {
                    $message = "Review rejected";
                } else {
                    $error = "Error rejecting review";
                }
                break;
        }
    }
}

// Get all active employees for dropdown
$employees = $conn->query("SELECT e.*, d.department_name, p.position_title 
                          FROM employees e 
                          LEFT JOIN departments d ON e.department_id = d.department_id
                          LEFT JOIN positions p ON e.position_id = p.position_id
                          WHERE e.employment_status = 'Active'
                          ORDER BY e.first_name, e.last_name");

// Get pending reviews
$pendingReviews = $conn->query("
    SELECT cr.*, 
           e.first_name, e.last_name, 
           d.department_name,
           p.position_title,
           a.first_name as approver_fname, 
           a.last_name as approver_lname
    FROM compensation_reviews cr
    JOIN employees e ON cr.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN positions p ON e.position_id = p.position_id
    LEFT JOIN employees a ON cr.approver_id = a.employee_id
    WHERE cr.status = 'Pending'
    ORDER BY cr.review_date DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Salary Reviews - Compensation System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php require_once 'includes/navbar.php';?>

    <div class="container mt-4">
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Create New Review</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create">
                            
                            <div class="form-group">
                                <label>Employee</label>
                                <select name="employee_id" class="form-control" required>
                                    <option value="">Select Employee</option>
                                    <?php while ($emp = $employees->fetch_assoc()): ?>
                                        <option value="<?php echo $emp['employee_id']; ?>">
                                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                            (<?php echo htmlspecialchars($emp['position_title']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Review Date</label>
                                <input type="date" name="review_date" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label>Current Salary</label>
                                <input type="number" step="0.01" name="current_salary" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label>Proposed Salary</label>
                                <input type="number" step="0.01" name="proposed_salary" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label>Approver ID</label>
                                <input type="text" name="approver_id" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label>Comments</label>
                                <textarea name="comments" class="form-control" rows="3"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">Create Review</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Pending Reviews</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Current Salary</th>
                                        <th>Proposed Salary</th>
                                        <th>Review Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($review = $pendingReviews->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($review['position_title']); ?>
                                                </small>
                                            </td>
                                            <td><?php echo number_format($review['current_salary'], 2); ?></td>
                                            <td><?php echo number_format($review['proposed_salary'], 2); ?></td>
                                            <td><?php echo $review['review_date']; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-success" 
                                                        onclick="approveReview(<?php echo $review['review_id']; ?>)">
                                                    Approve
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="rejectReview(<?php echo $review['review_id']; ?>)">
                                                    Reject
                                                </button>
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
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Review</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="review_id" id="approveReviewId">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Effective Date</label>
                            <input type="date" name="effective_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Review</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="review_id" id="rejectReviewId">
                    <div class="modal-body">
                        <p>Are you sure you want to reject this review?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    function approveReview(reviewId) {
        $('#approveReviewId').val(reviewId);
        $('#approveModal').modal('show');
    }

    function rejectReview(reviewId) {
        $('#rejectReviewId').val(reviewId);
        $('#rejectModal').modal('show');
    }
    </script>
</body>
</html>
