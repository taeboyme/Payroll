<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$type = $_POST['type'] ?? '';
$notes = trim($_POST['notes'] ?? '');

// Simple validation
if (empty($start_date) || empty($end_date) || empty($type)) {
    $_SESSION['timeoff_feedback'] = [
        'type' => 'error',
        'message' => 'Please fill out all required fields.'
    ];
    header("Location: requests.php");
    exit();
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Insert request into database
$query = "INSERT INTO time_off_requests (employee_id, start_date, end_date, type, notes, status)
          VALUES (?, ?, ?, ?, ?, 'Pending')";

$stmt = $conn->prepare($query);
$stmt->bind_param("sssss", $employee_id, $start_date, $end_date, $type, $notes);

if ($stmt->execute()) {
    $_SESSION['timeoff_feedback'] = [
        'type' => 'success',
        'message' => 'Leave request submitted successfully!'
    ];
} else {
    $_SESSION['timeoff_feedback'] = [
        'type' => 'error',
        'message' => 'Error submitting request. Please try again.'
    ];
}

$stmt->close();
$conn->close();

header("Location: requests.php");
exit();
