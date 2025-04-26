<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule_date = $_POST['schedule_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $shift_type = $_POST['shift_type'];

    $stmt = $conn->prepare("INSERT INTO work_schedules (employee_id, schedule_date, start_time, end_time, shift_type) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $employee_id, $schedule_date, $start_time, $end_time, $shift_type);

    if ($stmt->execute()) {
        $_SESSION['schedule_feedback'] = ['type' => 'success', 'message' => 'Schedule created successfully.'];
    } else {
        $_SESSION['schedule_feedback'] = ['type' => 'danger', 'message' => 'Failed to create schedule.'];
    }

    $stmt->close();
    $conn->close();

    header("Location: schedule.php");
    exit();
}
?>
