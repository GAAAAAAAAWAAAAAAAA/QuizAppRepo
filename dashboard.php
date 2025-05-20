<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flash Quiz App - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --gradient-bg: linear-gradient(135deg, #4b0082, #191970, #000000);
            --purple-main: #6a0dad;
            --dark-blue: #191970;
            --highlight: #9370DB;
            --text-primary: #ffffff;
            --text-secondary: #e0e0e0;
            --card-bg: rgba(0, 0, 0, 0.6);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: var(--gradient-bg);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        .container {
            width: 100%;
            max-width: 1000px;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        
        .app-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        h1 {
            font-weight: 700;
            font-size: 32px;
            color: var(--text-primary);
            position: relative;
            display: inline-block;
        }
        
        h1:after {
            content: '';
            position: absolute;
            width: 60%;
            height: 4px;
            background: var(--highlight);
            bottom: -10px;
            left: 20%;
            border-radius: 5px;
        }
        
        .dashboard-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }
        
        .option {
            background-color: var(--card-bg);
            border-radius: 15px;
            padding: 30px;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .option:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.15);
        }
        
        .option:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: linear-gradient(to bottom, var(--purple-main), var(--highlight));
        }
        
        .option-icon {
            font-size: 40px;
            margin-bottom: 20px;
            color: var(--highlight);
            text-align: center;
        }
        
        h2 {
            font-size: 24px;
            margin-bottom: 15px;
            color: var(--text-primary);
            text-align: center;
        }
        
        .option p {
            color: var(--text-secondary);
            margin-bottom: 25px;
            font-size: 16px;
            line-height: 1.5;
            text-align: center;
            flex-grow: 1;
        }
        
        .btn {
            display: inline-block;
            padding: 14px 20px;
            background: linear-gradient(to right, var(--purple-main), var(--dark-blue));
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        
        .btn:hover {
            background: linear-gradient(to right, #7b1fa2, #283593);
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(50, 50, 93, 0.1), 0 3px 6px rgba(0, 0, 0, 0.08);
        }
        
        input[type="text"] {
            width: 100%;
            padding: 14px 20px;
            margin-bottom: 15px;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: var(--highlight);
            box-shadow: 0 0 0 2px rgba(147, 112, 219, 0.25);
        }
        
        .sparkles {
            position: absolute;
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background-color: var(--highlight);
            box-shadow: 0 0 10px var(--highlight);
            animation: sparkle 2s infinite;
            opacity: 0.7;
        }
        
        @keyframes sparkle {
            0% {
                opacity: 0;
            }
            50% {
                opacity: 0.8;
            }
            100% {
                opacity: 0;
            }
        }
        
        .floating-shape {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--purple-main), transparent);
            filter: blur(40px);
            z-index: -1;
            opacity: 0.3;
        }
        
        .shape-1 {
            width: 300px;
            height: 300px;
            top: -100px;
            right: -50px;
            animation: float 8s infinite alternate ease-in-out;
        }
        
        .shape-2 {
            width: 200px;
            height: 200px;
            bottom: -50px;
            left: -50px;
            animation: float 6s infinite alternate-reverse ease-in-out;
        }
        
        @keyframes float {
            0% {
                transform: translate(0, 0);
            }
            100% {
                transform: translate(20px, 20px);
            }
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .footer a {
            color: var(--highlight);
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 30px 20px;
            }
            
            .dashboard-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Background shapes -->
    <div class="floating-shape shape-1"></div>
    <div class="floating-shape shape-2"></div>
    
    <!-- Sparkle animation -->
    <div class="sparkles" style="top:10%; left:20%;"></div>
    <div class="sparkles" style="top:20%; left:80%;"></div>
    <div class="sparkles" style="top:80%; left:10%;"></div>
    <div class="sparkles" style="top:40%; left:90%;"></div>
    <div class="sparkles" style="top:90%; left:30%;"></div>

    <div class="container">
        <div class="app-header">
            <div class="logo">
                ✨📝
            </div>
            <h1>Welcome to Flash Quiz App</h1>
        </div>
        
        <div class="dashboard-options">
            <div class="option">
                <div class="option-icon">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <h2>Login</h2>
                <p>Access your saved quizzes and track your progress over time</p>
                <a href="login.php" class="btn">Login to Your Account</a>
            </div>
            
            <div class="option">
                <div class="option-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h2>Sign Up</h2>
                <p>Create a new account to save your progress and compete with friends</p>
                <a href="signup.php" class="btn">Create New Account</a>
            </div>
            
            <div class="option">
                <div class="option-icon">
                    <i class="fas fa-user-secret"></i>
                </div>
                <h2>Guest Mode</h2>
                <p>Try the app without creating an account, no strings attached</p>
                <form action="guest_login.php" method="post">
                    <input type="text" name="nickname" placeholder="Enter a nickname" required>
                    <button type="submit" class="btn">Continue as Guest</button>
                </form>
            </div>
        </div>
        
        <div class="footer">
            <p>© 2025 Flash Quiz App | <a href="#">Privacy Policy</a> | <a href="#">Terms of Service</a></p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            for (let i = 0; i < 15; i++) {
                let sparkle = document.createElement('div');
                sparkle.classList.add('sparkles');
                sparkle.style.top = Math.random() * 100 + '%';
                sparkle.style.left = Math.random() * 100 + '%';
                sparkle.style.animationDelay = Math.random() * 2 + 's';
                document.body.appendChild(sparkle);
            }
        });
    </script>
</body>
</html>