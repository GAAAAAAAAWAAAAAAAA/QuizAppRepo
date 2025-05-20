<?php
class ValidationHelper {
    public static function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validatePassword($password) {
        $errors = [];
        
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long";
        }
        
        if (!preg_match("/[A-Za-z]/", $password)) {
            $errors[] = "Password must contain at least one letter";
        }
        
        if (!preg_match("/[0-9]/", $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        return $errors;
    }
    
    public static function validateUsername($username) {
        $errors = [];
        
        if (strlen($username) < 3) {
            $errors[] = "Username must be at least 3 characters long";
        }
        
        if (strlen($username) > 50) {
            $errors[] = "Username cannot exceed 50 characters";
        }
        
        if (!preg_match("/^[a-zA-Z0-9_]+$/", $username)) {
            $errors[] = "Username can only contain letters, numbers, and underscores";
        }
        
        return $errors;
    }
    
    public static function checkUserExists($conn, $username, $email) {
        $stmt = $conn->prepare("SELECT user_id, username, email FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['username'] === $username) {
                return "Username already exists";
            } else {
                return "Email already exists";
            }
        }
        
        $stmt->close();
        return false;
    }
}
?>