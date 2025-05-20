<?php
class SessionHelper {
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function setUserSession($user_id, $username, $email, $is_guest = false) {
        self::startSession();
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['is_guest'] = $is_guest;
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
    }
    
    public static function destroySession() {
        self::startSession();
        session_unset();
        session_destroy();
    }
    
    public static function isLoggedIn() {
        self::startSession();
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public static function isGuest() {
        self::startSession();
        return isset($_SESSION['is_guest']) && $_SESSION['is_guest'] === true;
    }
    
    public static function getUserId() {
        self::startSession();
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
    
    public static function getUsername() {
        self::startSession();
        return isset($_SESSION['username']) ? $_SESSION['username'] : null;
    }
    
    public static function redirectIfNotLoggedIn($redirect_url = 'dashboard.php') {
        if (!self::isLoggedIn()) {
            header("Location: $redirect_url");
            exit();
        }
    }
    
    public static function redirectIfLoggedIn($redirect_url = 'home.php') {
        if (self::isLoggedIn()) {
            header("Location: $redirect_url");
            exit();
        }
    }
    
    public static function setFlashMessage($type, $message) {
        self::startSession();
        $_SESSION['flash_message'] = [
            'type' => $type,
            'message' => $message
        ];
    }
    
    public static function getFlashMessage() {
        self::startSession();
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $message;
        }
        return null;
    }
}
?>