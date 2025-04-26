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
$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';

if ($request_id > 0) {
    // Verify the request belongs to the employee and is pending
    $check_query = "SELECT * FROM time_off_requests 
                   WHERE id = ? AND employee_id = ? AND status = 'Pending'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("is", $request_id, $employee_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        // Cancel the request
        $update_query = "UPDATE time_off_requests SET status = 'Cancelled' WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $request_id);
        
        if ($update_stmt->execute()) {
            // Get request details for notification
            $request = $result->fetch_assoc();
            
            // Notify manager about cancellation
            $notify_query = "SELECT e.*, m.email as manager_email 
                           FROM employees e 
                           LEFT JOIN employees m ON e.manager_id = m.employee_id 
                           WHERE e.employee_id = ?";
            $notify_stmt = $conn->prepare($notify_query);
            $notify_stmt->bind_param("s", $employee_id);
            $notify_stmt->execute();
            $emp_result = $notify_stmt->get_result()->fetch_assoc();

            if ($emp_result['manager_email']) {
                $to = $emp_result['manager_email'];
                $subject = "Time-Off Request Cancelled";
                $message = "A time-off request has been cancelled:\n\n";
                $message .= "Employee: " . $emp_result['first_name'] . " " . $emp_result['last_name'] . "\n";
                $message .= "Type: " . $request['type'] . "\n";
                $message .= "Start Date: " . $request['start_date'] . "\n";
                $message .= "End Date: " . $request['end_date'] . "\n";
                
                mail($to, $subject, $message);
            }

            $_SESSION['success_message'] = "Request cancelled successfully.";
        } else {
            $_SESSION['error_message'] = "Error cancelling request.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid request or request cannot be cancelled.";
    }
} else {
    $_SESSION['error_message'] = "Invalid request ID.";
}

$conn->close();
header("Location: requests.php");
exit();
?>