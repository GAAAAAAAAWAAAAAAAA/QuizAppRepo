<?php
require_once 'db_connect.php';
require_once 'validation_helper.php';
require_once 'session_helper.php';

SessionHelper::redirectIfLoggedIn();

$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = ValidationHelper::sanitizeInput($_POST['username']);
    $email = ValidationHelper::sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "All fields are required";
    } elseif (!ValidationHelper::validateEmail($email)) {
        $error_message = "Invalid email format";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match";
    } else {
        $username_errors = ValidationHelper::validateUsername($username);
        $password_errors = ValidationHelper::validatePassword($password);
        
        if (!empty($username_errors)) {
            $error_message = implode(". ", $username_errors);
        } elseif (!empty($password_errors)) {
            $error_message = implode(". ", $password_errors);
        } else {
            $user_exists = ValidationHelper::checkUserExists($conn, $username, $email);
            
            if ($user_exists) {
                $error_message = $user_exists;
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $email, $hashed_password);
                
                if ($stmt->execute()) {
                    SessionHelper::setFlashMessage('success', 'Account created successfully! You can now log in.');
                    header("Location: login.php");
                    exit();
                } else {
                    $error_message = "Registration failed. Please try again.";
                }
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Flash Quiz App</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #1a1a3e 0%, #2d1b69 50%, #4b0082 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .signup-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 450px;
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .app-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .app-icon {
            font-size: 3rem;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #9370db, #4b0082);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .app-title {
            font-size: 2rem;
            font-weight: bold;
            background: linear-gradient(45deg, #ffffff, #e6e6fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 5px;
        }

        .app-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #e6e6fa;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .form-group input:focus {
            border-color: #9370db;
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 20px rgba(147, 112, 219, 0.3);
        }

        .signup-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(45deg, #9370db, #4b0082);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .signup-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(147, 112, 219, 0.4);
            background: linear-gradient(45deg, #a569bd, #5b2c87);
        }

        .signup-btn:active {
            transform: translateY(0);
        }

        .message {
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
        }

        .error-message {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #ff6b8a;
        }

        .success-message {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.5);
            color: #7fd3c0;
        }

        .footer-links {
            text-align: center;
            margin-top: 25px;
        }

        .footer-links a {
            color: #e6e6fa;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: #9370db;
        }

        .divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(255, 255, 255, 0.2);
        }

        .divider span {
            background: linear-gradient(135deg, #1a1a3e 0%, #2d1b69 50%, #4b0082 100%);
            padding: 0 15px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.8rem;
        }

        .features {
            margin-top: 20px;
            text-align: center;
        }

        .features-list {
            display: flex;
            justify-content: space-around;
            margin-top: 15px;
        }

        .feature-item {
            flex: 1;
            padding: 0 10px;
        }

        .feature-icon {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .feature-text {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.7);
        }

        @media (max-width: 480px) {
            .signup-container {
                margin: 20px;
                padding: 30px 25px;
            }
            
            .app-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="app-header">
            <div class="app-icon">⚡</div>
            <h1 class="app-title">Flash Quiz</h1>
            <p class="app-subtitle">Join the learning revolution</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="message error-message">
                🚫 <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="message success-message">
                ✅ <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">👤 Username</label>
                <input type="text" id="username" name="username" placeholder="Choose a unique username" 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="email">📧 Email Address</label>
                <input type="email" id="email" name="email" placeholder="your.email@example.com" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="password">🔐 Password</label>
                <input type="password" id="password" name="password" placeholder="Create a strong password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">🔒 Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
            </div>

            <button type="submit" class="signup-btn">
                🚀 Create Account
            </button>
        </form>

        <div class="divider">
            <span>Already have an account?</span>
        </div>

        <div class="footer-links">
            <a href="login.php">🔑 Sign In</a> | 
            <a href="dashboard.php">🏠 Back to Dashboard</a>
        </div>

        <div class="features">
            <div class="features-list">
                <div class="feature-item">
                    <div class="feature-icon">📚</div>
                    <div class="feature-text">Create Flashcards</div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">🧠</div>
                    <div class="feature-text">AI Assistance</div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">🏆</div>
                    <div class="feature-text">Leaderboards</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const inputs = document.querySelectorAll('input');
            
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.querySelector('label').style.color = '#9370db';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.querySelector('label').style.color = '#e6e6fa';
                });
            });

            form.addEventListener('submit', function() {
                const submitBtn = document.querySelector('.signup-btn');
                submitBtn.innerHTML = '⏳ Creating Account...';
                submitBtn.disabled = true;
            });

            const successMessage = document.querySelector('.success-message');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    successMessage.style.transform = 'translateY(-20px)';
                }, 5000);
            }
        });
    </script>
</body>
</html>