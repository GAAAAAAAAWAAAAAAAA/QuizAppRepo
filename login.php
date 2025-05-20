<?php
    session_start();
    include 'db_connect.php';
    include 'session_helper.php';

    $loginError = "";

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $email = sanitizeInput($_POST['email']);
        $password = sanitizeInput($_POST['password']);
        
        $sql = "SELECT user_id, username, password FROM users WHERE email = ? AND is_guest = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Password is correct, start a new session
                SessionHelper::setUserSession($user['user_id'], $user['username'], $email, false);

                
                header("Location: index.php");
                exit();
            } else {
                $loginError = "Invalid password";
            }
        } else {
            $loginError = "User not found";
        }
    }
    ?>
    
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Flash Quiz App</title>
    <style>
        :root {
            --grande-purple: #6a0dad;
            --dark-purple: #4b0082;
            --dark-blue: #00008b;
            --light-purple: #9370db;
            --off-white: #f8f8ff;
            --dark-gray: #1a1a1a;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, var(--dark-blue), var(--dark-purple), var(--dark-gray));
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
        }
        
        @keyframes gradient {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }
        
        .container {
            background-color: rgba(26, 26, 26, 0.8);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            width: 450px;
            padding: 40px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(106, 13, 173, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .container:before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(to bottom right, 
                transparent, 
                transparent, 
                transparent, 
                var(--light-purple), 
                transparent, 
                transparent, 
                transparent);
            transform: rotate(45deg);
            animation: shine 3s infinite;
            z-index: -1;
        }
        
        @keyframes shine {
            0% {
                left: -50%;
                top: -50%;
            }
            100% {
                left: 150%;
                top: 150%;
            }
        }
        
        .logo {
            text-align: center;
            margin-bottom: 20px;
            font-size: 28px;
            color: var(--off-white);
        }
        
        .logo span {
            color: var(--grande-purple);
            font-weight: bold;
        }
        
        h1 {
            color: var(--off-white);
            text-align: center;
            margin-bottom: 30px;
            font-size: 24px;
            font-weight: 500;
        }
        
        form {
            display: flex;
            flex-direction: column;
        }
        
        .form-group {
            position: relative;
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: var(--off-white);
            font-size: 16px;
            font-weight: 500;
        }
        
        input {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            background-color: rgba(248, 248, 255, 0.1);
            color: var(--off-white);
            font-size: 16px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        input:focus {
            outline: none;
            border-color: var(--grande-purple);
            background-color: rgba(248, 248, 255, 0.15);
        }
        
        input::placeholder {
            color: rgba(248, 248, 255, 0.5);
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
        
        .btn {
            background: linear-gradient(45deg, var(--grande-purple), var(--dark-blue));
            color: var(--off-white);
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            flex: 1;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        .btn-secondary {
            background: rgba(248, 248, 255, 0.1);
            margin-left: 10px;
            border: 1px solid rgba(248, 248, 255, 0.2);
        }
        
        .btn-secondary:hover {
            background: rgba(248, 248, 255, 0.2);
        }
        
        .emoji-decorations {
            position: absolute;
            color: rgba(248, 248, 255, 0.15);
            font-size: 24px;
            z-index: -1;
        }
        
        .emoji-1 {
            top: 20px;
            left: 30px;
            animation: float 4s ease-in-out infinite;
        }
        
        .emoji-2 {
            bottom: 30px;
            right: 20px;
            animation: float 3.5s ease-in-out infinite;
            animation-delay: 0.5s;
        }
        
        .emoji-3 {
            top: 50%;
            right: 30px;
            animation: float 5s ease-in-out infinite;
            animation-delay: 1s;
        }
        
        .emoji-4 {
            bottom: 50px;
            left: 40px;
            animation: float 4.5s ease-in-out infinite;
            animation-delay: 1.5s;
        }
        
        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-10px) rotate(5deg);
            }
            100% {
                transform: translateY(0) rotate(0deg);
            }
        }
        
        .error-message {
            background-color: rgba(255, 0, 0, 0.2);
            border-left: 4px solid #ff3333;
            color: var(--off-white);
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 14px;
        }
        
        p {
            text-align: center;
            color: var(--off-white);
            margin-top: 25px;
            font-size: 15px;
        }
        
        p a {
            color: var(--light-purple);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        p a:hover {
            color: var(--grande-purple);
            text-decoration: underline;
        }
        
        .sparkles {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
        
        .sparkle {
            position: absolute;
            background: rgba(248, 248, 255, 0.5);
            border-radius: 50%;
            width: 3px;
            height: 3px;
            animation: sparkle-fade 2s infinite;
        }
        
        @keyframes sparkle-fade {
            0% {
                opacity: 0;
                transform: scale(0);
            }
            50% {
                opacity: 1;
                transform: scale(1);
            }
            100% {
                opacity: 0;
                transform: scale(0);
            }
        }
        
        .shape {
            position: absolute;
            z-index: -1;
            opacity: 0.1;
        }
        
        .circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--grande-purple);
            top: -30px;
            left: -30px;
        }
        
        .rectangle {
            width: 120px;
            height: 40px;
            background: var(--dark-blue);
            bottom: -10px;
            right: -20px;
            transform: rotate(30deg);
        }
        
        .quiz-icon {
            font-size: 32px;
            margin-right: 10px;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    
    <div class="container">
        <div class="sparkles" id="sparkles"></div>
        
        <div class="shape circle"></div>
        <div class="shape rectangle"></div>
        
        <div class="emoji-decorations emoji-1">📝</div>
        <div class="emoji-decorations emoji-2">🧠</div>
        <div class="emoji-decorations emoji-3">❓</div>
        <div class="emoji-decorations emoji-4">🏆</div>
        
        <div class="logo">
            <span>F</span>lash <span>Q</span>uiz <span>A</span>pp <span class="quiz-icon">🎯</span>
        </div>
        
        <h1>Welcome Back</h1>
        
        <?php if (!empty($loginError)): ?>
            <div class="error-message"><?php echo $loginError; ?></div>
        <?php endif; ?>
        
        <form action="login.php" method="post">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn">Login</button>
                <a href="dashboard.php" class="btn btn-secondary">Back</a>
            </div>
        </form>
        
        <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
    </div>

    <script>
        // Create sparkle effect
        document.addEventListener('DOMContentLoaded', function() {
            const sparklesContainer = document.getElementById('sparkles');
            const sparkleCount = 15;
            
            for (let i = 0; i < sparkleCount; i++) {
                createSparkle(sparklesContainer);
            }
            
            function createSparkle(container) {
                const sparkle = document.createElement('div');
                sparkle.classList.add('sparkle');
                
                // Random position
                const x = Math.floor(Math.random() * 100);
                const y = Math.floor(Math.random() * 100);
                
                sparkle.style.left = `${x}%`;
                sparkle.style.top = `${y}%`;
                
                // Random delay for animation
                sparkle.style.animationDelay = `${Math.random() * 2}s`;
                
                container.appendChild(sparkle);
            }

        });
    </script>
</body>
</html>