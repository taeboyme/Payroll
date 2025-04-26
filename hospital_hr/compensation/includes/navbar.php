<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user role and name
$user_id = $_SESSION['user_id'];
$query = "SELECT first_name, last_name, role, username FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            Compensation System
        </a>
        
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                
                <?php if (in_array($user['role'], ['admin', 'hr'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="view_compensation.php">
                        <i class="fas fa-users"></i> Employees Compensation
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (in_array($user['role'], ['admin', 'accountant'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="manage_review.php">
                        <i class="fas fa-money-bill-wave"></i> Salary Reviews
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array($user['role'], ['admin', 'accountant'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="report.php">
                        <i class="fas fa-chart-bar"></i> Report
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                        <i class="fas fa-user-circle"></i>&nbsp; 
                        <?php echo $user['username']; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a class="dropdown-item" href="change_password.php">
                            <i class="fas fa-key"></i> Change Password
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

<script>
$(document).ready(function() {
    // Highlight active menu item
    var currentPage = window.location.pathname.split('/').pop();
    $('.nav-link').each(function() {
        var href = $(this).attr('href');
        if (href === currentPage) {
            $(this).addClass('active');
            $(this).closest('.dropdown').find('.dropdown-toggle').addClass('active');
        }
    });
});
</script>