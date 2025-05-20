<?php
session_start();
require_once 'db_connect.php';
include 'session_helper.php';

// Redirect if not logged in or guest
if (!isset($_SESSION['user_id']) && !isset($_SESSION['is_guest'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'Guest User';
$is_guest = $_SESSION['is_guest'] ?? false;

// Default stats
$total_projects = 0;
$total_flashcards = 0;
$total_quizzes_taken = 0;
$best_score = 0;

if (!$is_guest && $user_id) {
    // Count projects
    $project_stmt = $conn->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ?");
    $project_stmt->bind_param("i", $user_id);
    $project_stmt->execute();
    $project_stmt->bind_result($total_projects);
    $project_stmt->fetch();
    $project_stmt->close();

    // Count flashcards
    $flashcard_stmt = $conn->prepare("SELECT COUNT(*) 
        FROM flashcards f 
        JOIN projects p ON f.project_id = p.project_id 
        WHERE p.user_id = ?");
    $flashcard_stmt->bind_param("i", $user_id);
    $flashcard_stmt->execute();
    $flashcard_stmt->bind_result($total_flashcards);
    $flashcard_stmt->fetch();
    $flashcard_stmt->close();

    // Count quizzes taken
    $quiz_stmt = $conn->prepare("SELECT COUNT(*), MAX(score) FROM quiz_attempts WHERE user_id = ?");
    $quiz_stmt->bind_param("i", $user_id);
    $quiz_stmt->execute();
    $quiz_stmt->bind_result($total_quizzes_taken, $best_score);
    $quiz_stmt->fetch();
    $quiz_stmt->close();
}
?>

<script>
        function navigateTo(page) {
            window.location.href = page;
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        // Add dynamic particles
        function createParticle() {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.top = '100%';
            particle.style.width = particle.style.height = Math.random() * 5 + 2 + 'px';
            particle.style.animationDuration = Math.random() * 3 + 3 + 's';
            particle.style.opacity = Math.random() * 0.5 + 0.1;
            
            document.querySelector('.floating-particles').appendChild(particle);
            
            // Remove particle after animation
            setTimeout(() => {
                particle.remove();
            }, 6000);
        }

        // Create particles periodically
        setInterval(createParticle, 2000);

        // Add hover effects and animations
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.menu-card');
            
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-10px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
        });
    </script>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flash Quiz App - Home</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);
            min-height: 100vh;
            color: white;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 50px;
            position: relative;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 200px;
            height: 3px;
            background: linear-gradient(90deg, #8b5cf6, #5b21b6);
            border-radius: 10px;
        }

        .app-title {
            font-size: 3rem;
            font-weight: bold;
            margin-top: 20px;
            background: linear-gradient(45deg, #8b5cf6, #a855f7, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .app-subtitle {
            font-size: 1.2rem;
            color: #d1d5db;
            margin-top: 10px;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 50px;
        }

        .menu-card {
            background: linear-gradient(135deg, #2d1b69, #1a1a2e);
            border: 2px solid transparent;
            border-image: linear-gradient(135deg, #8b5cf6, #5b21b6) 1;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .menu-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(139, 92, 246, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .menu-card:hover::before {
            left: 100%;
        }

        .menu-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(139, 92, 246, 0.3);
            border-image: linear-gradient(135deg, #a855f7, #8b5cf6) 1;
        }

        .menu-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .menu-icon .emoji {
            display: inline-block;
        }

        .menu-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 15px;
            color: #f3f4f6;
        }

        .menu-description {
            color: #d1d5db;
            line-height: 1.5;
            font-size: 0.95rem;
        }

        .user-info {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(45, 27, 105, 0.8);
            padding: 10px 20px;
            border-radius: 25px;
            border: 1px solid #8b5cf6;
        }

        .user-info .username {
            color: #a855f7;
            font-weight: bold;
        }

        .logout-btn {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            margin-left: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3);
        }

        .stats-bar {
            display: flex;
            justify-content: space-around;
            margin: 30px 0;
            background: rgba(45, 27, 105, 0.3);
            border-radius: 15px;
            padding: 20px;
            border: 1px solid #8b5cf6;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #a855f7;
        }

        .stat-label {
            color: #d1d5db;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .app-title {
                font-size: 2rem;
            }
            
            .menu-grid {
                grid-template-columns: 1fr;
            }
            
            .user-info {
                position: relative;
                top: 0;
                right: 0;
                margin-bottom: 20px;
                text-align: center;
            }
            
            .stats-bar {
                flex-direction: column;
                gap: 15px;
            }
        }

        .floating-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
            z-index: -1;
        }

        .particle {
            position: absolute;
            background: rgba(139, 92, 246, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .emoji-colored {
            display: inline-block;
            background: linear-gradient(45deg, #8b5cf6, #a855f7, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: inherit;
        }
        
    </style>
</head>
<body>

    <div class="floating-particles">
        <div class="particle" style="left: 10%; width: 4px; height: 4px; animation-delay: 0s;"></div>
        <div class="particle" style="left: 20%; width: 6px; height: 6px; animation-delay: 1s;"></div>
        <div class="particle" style="left: 30%; width: 3px; height: 3px; animation-delay: 2s;"></div>
        <div class="particle" style="left: 40%; width: 5px; height: 5px; animation-delay: 3s;"></div>
        <div class="particle" style="left: 50%; width: 4px; height: 4px; animation-delay: 4s;"></div>
        <div class="particle" style="left: 60%; width: 6px; height: 6px; animation-delay: 5s;"></div>
        <div class="particle" style="left: 70%; width: 3px; height: 3px; animation-delay: 6s;"></div>
        <div class="particle" style="left: 80%; width: 5px; height: 5px; animation-delay: 7s;"></div>
        <div class="particle" style="left: 90%; width: 4px; height: 4px; animation-delay: 8s;"></div>
    </div>

    <div class="user-info">
        👤 <span class="username"><?php echo htmlspecialchars($username); ?></span>
        <?php if ($is_guest): ?>
            <span style="color: #fbbf24;">(👻 Guest Mode)</span>
        <?php endif; ?>
        <button class="logout-btn" onclick="logout()">🚪 Logout</button>
    </div>

    <div class="container">
        <div class="header">
            <h1 class="app-title">⚡ Flash Quiz App</h1>
            <p class="app-subtitle">Master your knowledge, one flashcard at a time</p>
        </div>

        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-number"><?php echo $total_projects; ?></div>
                <div class="stat-label">📚 Projects Created</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $total_quizzes_taken; ?></div>
                <div class="stat-label">🎯 Quizzes Taken</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $best_score; ?>%</div>
                <div class="stat-label">🏆 Best Score</div>
            </div>
        </div>

        <div class="menu-grid">
            <div class="menu-card" onclick="navigateTo('create.php')">
                <div class="menu-icon">📝</div>
                <h3 class="menu-title">Create Flash Cards</h3>
                <p class="menu-description">Build interactive flashcards with multiple question types including identification, multiple choice, and true/false formats.</p>
            </div>

            <div class="menu-card" onclick="navigateTo('review.php')">
                <div class="menu-icon">👁️</div>
                <h3 class="menu-title">Review Flash Cards</h3>
                <p class="menu-description">Browse through your created projects and review flashcards with an intuitive flip interface and navigation controls.</p>
            </div>

            <div class="menu-card" onclick="navigateTo('quiz.php')">
                <div class="menu-icon">🎯</div>
                <h3 class="menu-title">Quiz Flash Cards</h3>
                <p class="menu-description">Take timed quizzes with scoring system. Earn points based on accuracy and completion time for leaderboard rankings.</p>
            </div>

            <div class="menu-card" onclick="navigateTo('manage.php')">
                <div class="menu-icon">⚙️</div>
                <h3 class="menu-title">Manage Flash Cards</h3>
                <p class="menu-description">Edit, delete, organize your flashcards. Generate sharing codes and control project permissions for collaboration.</p>
            </div>

            <div class="menu-card" onclick="navigateTo('leaderboard.php')">
                <div class="menu-icon">🏆</div>
                <h3 class="menu-title">Leaderboards</h3>
                <p class="menu-description">View top performers, compare scores, and track completion times across different quiz projects and categories.</p>
            </div>

            <div class="menu-card" onclick="navigateTo('share.php')">
                <div class="menu-icon">🤝</div>
                <h3 class="menu-title">Shared Flash Cards</h3>
                <p class="menu-description">Access shared projects using codes, view your own project codes, and participate in collaborative quiz challenges.</p>
            </div>

            <div class="menu-card" onclick="navigateTo('ai.php')">
                <div class="menu-icon">🤖</div>
                <h3 class="menu-title">Create Quiz AI</h3>
                <p class="menu-description">Generate flashcards automatically using AI prompts. Get instant quiz questions and save them as floating guides.</p>
            </div>
        </div>
    </div>

    
</body>
</html>