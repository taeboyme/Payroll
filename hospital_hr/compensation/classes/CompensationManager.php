<?php
class CompensationManager {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getEmployeeSalary($employeeId) {
        $sql = "SELECT es.*, sc.component_name, sc.component_type 
                FROM employee_salary es 
                JOIN salary_components sc ON es.component_id = sc.component_id 
                WHERE es.employee_id = ? AND (es.effective_to IS NULL OR es.effective_to >= CURDATE())";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $employeeId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getCompensationGrade($gradeId) {
        $sql = "SELECT * FROM compensation_grades WHERE grade_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $gradeId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function createCompensationReview($data) {
        $sql = "INSERT INTO compensation_reviews 
                (employee_id, review_date, current_salary, proposed_salary, status, approver_id) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssddss", 
            $data['employee_id'],
            $data['review_date'],
            $data['current_salary'],
            $data['proposed_salary'],
            $data['status'],
            $data['approver_id']
        );
        return $stmt->execute();
    }

    public function updateEmployeeSalary($data) {
        // Close current active salary record
        $sql = "UPDATE employee_salary 
                SET effective_to = CURDATE() 
                WHERE employee_id = ? AND component_id = ? AND effective_to IS NULL";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $data['employee_id'], $data['component_id']);
        $stmt->execute();

        // Insert new salary record
        $sql = "INSERT INTO employee_salary 
                (employee_id, component_id, amount, effective_from) 
                VALUES (?, ?, ?, CURDATE())";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sid", 
            $data['employee_id'],
            $data['component_id'],
            $data['amount']
        );
        return $stmt->execute();
    }
}
?>