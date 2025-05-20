
<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in or in guest mode
if (!isset($_SESSION['user_id']) && !isset($_SESSION['is_guest'])) {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$is_guest = $_SESSION['is_guest'] ?? false;

// Get user's projects or shared projects
$projects = [];
$error_message = '';
$success_message = '';

if ($user_id) {
    // Get user's own projects
    $query = "
        SELECT p.project_id, p.project_name, p.project_code, p.created_at,
               COUNT(f.flashcard_id) as question_count
        FROM projects p
        LEFT JOIN flashcards f ON p.project_id = f.project_id
        WHERE p.user_id = ?
        GROUP BY p.project_id, p.project_name, p.project_code, p.created_at
        ORDER BY p.created_at DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $own_projects = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get shared projects
    $query = "
        SELECT p.project_id, p.project_name, p.project_code, p.created_at,
               COUNT(f.flashcard_id) as question_count,
               ps.permission_type, ps.is_locked, ps.start_time, ps.end_time
        FROM projects p
        LEFT JOIN flashcards f ON p.project_id = f.project_id
        JOIN shared_projects sp ON p.project_id = sp.project_id
        LEFT JOIN project_sharing ps ON p.project_id = ps.project_id
        WHERE sp.user_id = ?
        GROUP BY p.project_id, p.project_name, p.project_code, p.created_at, ps.permission_type, ps.is_locked, ps.start_time, ps.end_time
        ORDER BY sp.shared_at DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $shared_projects = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $projects = array_merge($own_projects, $shared_projects);
}

// Handle quiz start
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_quiz'])) {
    $project_id = $_POST['project_id'] ?? null;
    $has_points = isset($_POST['has_points']) ? 1 : 0;
    $has_timer = isset($_POST['has_timer']) ? 1 : 0;
    $time_limit = $has_timer ? ($_POST['time_limit'] ?? 60) : null;

    if ($project_id) {
        // Check if project exists and user has access
        $query = "
            SELECT p.*, COUNT(f.flashcard_id) as question_count
            FROM projects p
            LEFT JOIN flashcards f ON p.project_id = f.project_id
            WHERE p.project_id = ?
            GROUP BY p.project_id
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $project = $result->fetch_assoc();
        $stmt->close();

        if ($project && $project['question_count'] > 0) {
            // Calculate points per question if has_points is enabled
            $points_per_question = 10; // default
            if ($has_points && $has_timer && $time_limit) {
                $questions_count = $project['question_count'];
                $default_time = $questions_count; // 1 minute per question by default
                
                if ($time_limit <= $default_time) {
                    $points_per_question = 10;
                } else {
                    // Reduce points based on extra time given
                    $extra_time_ratio = $time_limit / $default_time;
                    $points_per_question = max(1, 10 - floor($extra_time_ratio - 1));
                }
            }

            // Create quiz attempt
            if ($user_id) {
                $query = "
                    INSERT INTO quiz_attempts (user_id, project_id, start_time, score, completed)
                    VALUES (?, ?, NOW(), 0, 0)
                ";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $user_id, $project_id);
                $stmt->execute();
                $attempt_id = $conn->insert_id;
                $stmt->close();

                // Create quiz settings
                $query = "
                    INSERT INTO quiz_settings (project_id, has_points, has_timer, time_limit_minutes, points_per_question)
                    VALUES (?, ?, ?, ?, ?)
                ";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("iiiii", $project_id, $has_points, $has_timer, $time_limit, $points_per_question);
                $stmt->execute();
                $stmt->close();
            }

            // Redirect to quiz taking page
            $redirect_url = "take_quiz.php?project_id=" . $project_id;
            if (isset($attempt_id)) {
                $redirect_url .= "&attempt_id=" . $attempt_id;
            }
            header("Location: " . $redirect_url);
            exit();
        } else {
            $error_message = "Project not found or has no questions!";
        }
    }
}

function checkProjectAccess($project) {
    if (isset($project['permission_type'])) {
        // This is a shared project
        if ($project['is_locked']) {
            return false;
        }
        
        if ($project['start_time'] && $project['end_time']) {
            $now = new DateTime();
            $start = new DateTime($project['start_time']);
            $end = new DateTime($project['end_time']);
            
            if ($now < $start || $now > $end) {
                return false;
            }
        }
        
        return in_array($project['permission_type'], ['use_and_review', 'edit']);
    }
    
    return true; // Own project
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Flash Cards - Flash Quiz App</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #2D1B69 0%, #1a1a2e 50%, #16213e 100%);
            min-height: 100vh;
            color: white;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #8a2be2, #4169e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
        }

        .nav-bar {
            background: rgba(139, 92, 246, 0.1);
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .nav-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 25px;
            background: linear-gradient(45deg, #8b5cf6, #06d6a0);
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-links a:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3);
        }

        @media (max-width: 768px) {
            .nav-links {
                flex-direction: column;
                align-items: center;
            }

            .header h1 {
                font-size: 2.5rem;
            }
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert.success {
            background: rgba(34, 197, 94, 0.1);
            border-color: #22c55e;
            color: #22c55e;
        }

        .alert.error {
            background: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
            color: #ef4444;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 15px;
            border: 2px dashed rgba(255, 255, 255, 0.1);
        }

        .empty-state .icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: rgba(255, 255, 255, 0.7);
        }

        .empty-state p {
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 20px;
        }

        .create-link {
            display: inline-block;
            background: linear-gradient(45deg, #8a2be2, #4169e1);
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .create-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(138, 43, 226, 0.3);
        }

        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .project-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .project-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(45deg, #8a2be2, #4169e1);
        }

        .project-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .project-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 5px;
            color: white;
        }

        .project-code {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
            font-family: 'Courier New', monospace;
            background: rgba(255, 255, 255, 0.1);
            padding: 2px 8px;
            border-radius: 5px;
        }

        .project-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat {
            display: flex;
            align-items: center;
            gap: 5px;
            color: rgba(255, 255, 255, 0.7);
        }

        .project-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .badge-own {
            background: linear-gradient(45deg, #10b981, #059669);
            color: white;
        }

        .badge-shared {
            background: linear-gradient(45deg, #f59e0b, #d97706);
            color: white;
        }

        .badge-locked {
            background: linear-gradient(45deg, #ef4444, #dc2626);
            color: white;
        }

        .quiz-form {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            align-items: center;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #8a2be2;
        }

        .checkbox-container label {
            color: rgba(255, 255, 255, 0.8);
            font-weight: normal;
        }

        .input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .input-group input {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 8px 12px;
            color: white;
            width: 80px;
        }

        .input-group input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .start-btn {
            background: linear-gradient(45deg, #8a2be2, #4169e1);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 1rem;
        }

        .start-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(138, 43, 226, 0.3);
        }

        .start-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #666;
        }

        .time-restriction {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
            color: #ffc107;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .projects-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <nav class="nav-bar fade-in">
        <div class="nav-links">
            <a href="home.php">🏠 Home</a>
            <a href="create.php">✨ Create</a>
            <a href="quiz.php">🎯 Quiz</a>
            <a href="leaderboard.php">🏆 Leaderboards</a>
            <a href="share.php">🔗 Share</a>
            <a href="logout.php">🚪 Logout</a>
        </div>
        </nav>
        
        <div class="header">
            <h1>🧠 Quiz Flash Cards</h1>
            <p>Test your knowledge and compete for the best scores!</p>
        </div>

        <?php if ($error_message): ?>
            <div class="alert error">
                ❌ <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert success">
                ✅ <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($projects)): ?>
            <div class="empty-state">
                <div class="icon">📚</div>
                <h3>No Quiz Projects Available</h3>
                <p>You haven't created any flash card projects yet, and no projects have been shared with you.</p>
                <?php if (!$is_guest): ?>
                    <a href="create.php" class="create-link">🎯 Create Your First Project</a>
                <?php else: ?>
                    <p style="margin-top: 15px; color: rgba(255, 255, 255, 0.6);">
                        💡 Sign up or log in to create and save your own quiz projects!
                    </p>
                    <a href="dashboard.php" class="create-link">🔐 Sign Up / Login</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="projects-grid">
                <?php foreach ($projects as $project): ?>
                    <?php
                    $is_accessible = checkProjectAccess($project);
                    $is_own_project = !isset($project['permission_type']);
                    $question_count = $project['question_count'];
                    ?>
                    <div class="project-card">
                        <div class="project-header">
                            <div>
                                <div class="project-title"><?php echo htmlspecialchars($project['project_name']); ?></div>
                                <div class="project-code">Code: <?php echo htmlspecialchars($project['project_code']); ?></div>
                            </div>
                            <div>
                                <?php if ($is_own_project): ?>
                                    <span class="project-badge badge-own">👤 Your Project</span>
                                <?php elseif (!$is_accessible): ?>
                                    <span class="project-badge badge-locked">🔒 Restricted</span>
                                <?php else: ?>
                                    <span class="project-badge badge-shared">🤝 Shared</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="project-stats">
                            <div class="stat">
                                <span>📝</span>
                                <span><?php echo $question_count; ?> Questions</span>
                            </div>
                            <div class="stat">
                                <span>📅</span>
                                <span><?php echo date('M j, Y', strtotime($project['created_at'])); ?></span>
                            </div>
                        </div>

                        <?php if (!$is_accessible): ?>
                            <?php if (isset($project['start_time']) && isset($project['end_time'])): ?>
                                <?php
                                $start_time = new DateTime($project['start_time']);
                                $end_time = new DateTime($project['end_time']);
                                $now = new DateTime();
                                ?>
                                <div class="time-restriction">
                                    ⏰ Available: <?php echo $start_time->format('M j, Y g:i A'); ?> - <?php echo $end_time->format('M j, Y g:i A'); ?>
                                    <?php if ($now < $start_time): ?>
                                        <br>🔜 Quiz will open in <?php echo $start_time->diff($now)->format('%d days, %h hours'); ?>
                                    <?php elseif ($now > $end_time): ?>
                                        <br>⏰ Quiz period has ended
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($project['is_locked']): ?>
                                <div class="time-restriction">
                                    🔒 This quiz is currently locked by the owner
                                </div>
                            <?php endif; ?>
                        <?php elseif ($question_count > 0): ?>
                            <form method="POST" class="quiz-form">
                                <input type="hidden" name="project_id" value="<?php echo $project['project_id']; ?>">
                                
                                <div class="form-row">
                                    <div class="checkbox-container">
                                        <input type="checkbox" id="points_<?php echo $project['project_id']; ?>" name="has_points" checked>
                                        <label for="points_<?php echo $project['project_id']; ?>">🏆 Track Points</label>
                                    </div>
                                    
                                    <div class="checkbox-container">
                                        <input type="checkbox" id="timer_<?php echo $project['project_id']; ?>" name="has_timer" onchange="toggleTimeLimit(<?php echo $project['project_id']; ?>)">
                                        <label for="timer_<?php echo $project['project_id']; ?>">⏱️ Set Timer</label>
                                    </div>
                                </div>
                                
                                <div class="form-row" id="time_limit_row_<?php echo $project['project_id']; ?>" style="display: none;">
                                    <div class="input-group">
                                        <label>Time Limit:</label>
                                        <input type="number" name="time_limit" min="1" max="999" value="<?php echo $question_count; ?>" placeholder="<?php echo $question_count; ?>">
                                        <span>minutes</span>
                                    </div>
                                    <small style="color: rgba(255, 255, 255, 0.6);">
                                        Default: <?php echo $question_count; ?> min (1 min per question)
                                    </small>
                                </div>
                                
                                <button type="submit" name="start_quiz" class="start-btn">
                                    🚀 Start Quiz
                                </button>
                            </form>
                        <?php else: ?>
                            <div style="text-align: center; color: rgba(255, 255, 255, 0.6); padding: 20px;">
                                ❌ This project has no questions yet
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleTimeLimit(projectId) {
            const checkbox = document.getElementById(`timer_${projectId}`);
            const timeRow = document.getElementById(`time_limit_row_${projectId}`);
            
            if (checkbox.checked) {
                timeRow.style.display = 'flex';
            } else {
                timeRow.style.display = 'none';
            }
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>