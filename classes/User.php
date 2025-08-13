<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class User {
    private $conn;
    private $table = 'users';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($username, $password) {
        $query = "SELECT u.*, r.name as role_name, r.display_name, r.permissions 
                  FROM " . $this->table . " u 
                  LEFT JOIN roles r ON u.role_id = r.id 
                  WHERE u.username = :username AND u.is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(password_verify($password, $user['password'])) {
                return $user;
            }
        }
        return false;
    }

    public function getStudentInfo($user_id) {
        $query = "SELECT s.*, gl.grade_name, sec.section_name, sy.year_label
                  FROM students s 
                  LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
                  LEFT JOIN sections sec ON s.current_section_id = sec.id
                  LEFT JOIN school_years sy ON s.current_school_year_id = sy.id
                  WHERE s.user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function logAudit($user_id, $action, $table_name = null, $record_id = null, $details = null) {
        $query = "INSERT INTO audit_logs (user_id, action, details, table_name, record_id, ip_address, user_agent) 
                  VALUES (:user_id, :action, :details, :table_name, :record_id, :ip_address, :user_agent)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':details', $details);
        $stmt->bindParam(':table_name', $table_name);
        $stmt->bindParam(':record_id', $record_id);
        $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
        $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
        
        return $stmt->execute();
    }
}
?>
