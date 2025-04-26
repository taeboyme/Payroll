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
$current_date = date('Y-m-d');
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Fetch employee schedule
$schedule_query = "SELECT * FROM work_schedules 
                  WHERE employee_id = ? 
                  AND MONTH(schedule_date) = ? 
                  AND YEAR(schedule_date) = ?
                  ORDER BY schedule_date";
$schedule_stmt = $conn->prepare($schedule_query);
$schedule_stmt->bind_param("sii", $employee_id, $selected_month, $selected_year);
$schedule_stmt->execute();
$schedules = $schedule_stmt->get_result();

// Fetch time-off requests
$timeoff_query = "SELECT * FROM time_off_requests 
                 WHERE employee_id = ? 
                 AND status != 'Rejected'
                 ORDER BY start_date";
$timeoff_stmt = $conn->prepare($timeoff_query);
$timeoff_stmt->bind_param("s", $employee_id);
$timeoff_stmt->execute();
$timeoff_requests = $timeoff_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Schedule - Attendance System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        
        .legend {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
    </style>
</head>
<body>
     <?php include 'includes/navbar.php';?>

    <?php if (isset($_SESSION['schedule_feedback'])): ?>
    <div class="alert alert-<?php echo $_SESSION['schedule_feedback']['type'] === 'success' ? 'success' : 'danger'; ?> mt-3">
    <?php echo $_SESSION['schedule_feedback']['message']; ?>
    </div>
    <?php unset($_SESSION['schedule_feedback']); ?>
    <?php endif; ?>

   <div class="container mt-4">
    <div class="row">
        <!-- Calendar Section -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Work Schedule</h5>
                    <form class="form-inline" method="GET">
                        <select name="month" class="form-control form-control-sm mr-2">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= ($selected_month == $m) ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <select name="year" class="form-control form-control-sm mr-2">
                            <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                                <option value="<?= $y ?>" <?= ($selected_year == $y) ? 'selected' : '' ?>>
                                    <?= $y ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">Go</button>
                    </form>
                </div>
                <div class="card-body p-3">
                    <div id="calendar" style="max-height: 500px; overflow-y: auto;"></div>
                </div>
                <div class="card-footer bg-light">
                    <h6>Legend</h6>
                    <div class="d-flex flex-wrap">
                        <div class="legend-item mr-3">
                            <span class="legend-color" style="background: #4CAF50;"></span> Regular
                        </div>
                        <div class="legend-item mr-3">
                            <span class="legend-color" style="background: #FF9800;"></span> Overtime
                        </div>
                        <div class="legend-item">
                            <span class="legend-color" style="background: #2196F3;"></span> Time Off
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Schedule -->
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Upcoming Schedule</h5>
                </div>
                <div class="card-body">
                    <?php if ($schedules->num_rows > 0): ?>
                        <?php $schedules->data_seek(0); ?>
                        <?php while($schedule = $schedules->fetch_assoc()): ?>
                            <div class="mb-3">
                                <strong><?= date('D, M d', strtotime($schedule['schedule_date'])) ?></strong><br>
                                <small class="text-muted">
                                    <?= date('h:i A', strtotime($schedule['start_time'])) . ' - ' . date('h:i A', strtotime($schedule['end_time'])) ?>
                                </small>
                                <span class="badge badge-pill 
                                    <?= $schedule['shift_type'] == 'Regular' ? 'badge-success' : 
                                        ($schedule['shift_type'] == 'Overtime' ? 'badge-warning' : 'badge-info'); ?>">
                                    <?= $schedule['shift_type']; ?>
                                </span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted">No upcoming shifts.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Time Off Form -->
        <!-- Create Work Schedule Form -->
<div class="col-md-3 mb-4">
    <div class="card h-70">
        <div class="card-header">
            <h5 class="mb-0">Create Work Schedule</h5>
        </div>
        <div class="card-body">
            <form action="process_schedule.php" method="POST">
                <div class="form-group">
                    <label for="schedule_date">Date</label>
                    <input type="date" class="form-control" name="schedule_date" min="<?= date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="start_time">Start Time</label>
                    <input type="time" class="form-control" name="start_time" required>
                </div>
                <div class="form-group">
                    <label for="end_time">End Time</label>
                    <input type="time" class="form-control" name="end_time" required>
                </div>
                <div class="form-group">
                    <label for="shift_type">Shift Type</label>
                    <select name="shift_type" class="form-control" required>
                        <option value="Regular">Regular</option>
                        <option value="Overtime">Overtime</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Create</button>
            </form>
        </div>
    </div>
</div>



    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://kit.fontawesome.com/your-font-awesome-kit.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek'
                },
                events: [
                    <?php 
                    $schedules->data_seek(0);
                    while($schedule = $schedules->fetch_assoc()): 
                    ?>
                    {
                        title: '<?php echo $schedule['shift_type']; ?>',
                        start: '<?php echo $schedule['schedule_date'] . 'T' . $schedule['start_time']; ?>',
                        end: '<?php echo $schedule['schedule_date'] . 'T' . $schedule['end_time']; ?>',
                        color: '<?php echo $schedule['shift_type'] == 'Regular' ? '#4CAF50' : '#FF9800'; ?>'
                    },
                    <?php endwhile; ?>
                    
                    <?php 
                    while($timeoff = $timeoff_requests->fetch_assoc()): 
                    ?>
                    {
                        title: '<?php echo $timeoff['type']; ?>',
                        start: '<?php echo $timeoff['start_date']; ?>',
                        end: '<?php echo $timeoff['end_date']; ?>',
                        color: '#2196F3'
                    },
                    <?php endwhile; ?>
                ]
            });
            calendar.render();
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>