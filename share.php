<?php
session_start();
require_once 'db_connect.php'; // This must define $conn

if (!isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$is_guest = $_SESSION['is_guest'] ?? false;

$message = '';
$error = '';

// Handle sharing code input
if (isset($_POST['share_code'])) {
    $share_code = trim($_POST['share_code']);

    $stmt = $conn->prepare("SELECT p.*, ps.permission_type, ps.is_locked, ps.start_time, ps.end_time 
                            FROM projects p 
                            LEFT JOIN project_sharing ps ON p.project_id = ps.project_id 
                            WHERE p.project_code = ?");
    $stmt->bind_param("s", $share_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $project = $result->fetch_assoc();
    $stmt->close();

    if ($project) {
        $stmt = $conn->prepare("SELECT * FROM shared_projects WHERE project_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $project['project_id'], $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $already_shared = $result->fetch_assoc();
        $stmt->close();

        if (!$already_shared) {
            $stmt = $conn->prepare("INSERT INTO shared_projects (project_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $project['project_id'], $user_id);
            if ($stmt->execute()) {
                $message = "📚 Project '{$project['project_name']}' successfully added to your collection!";
            } else {
                $error = "❌ Failed to add project. Please try again.";
            }
            $stmt->close();
        } else {
            $error = "📝 You already have access to this project!";
        }
    } else {
        $error = "🔍 Invalid project code. Please check and try again.";
    }
}

// Handle permission changes
if (isset($_POST['update_permissions'])) {
    $project_id = $_POST['project_id'];
    $permission_type = $_POST['permission_type'];
    $is_locked = isset($_POST['is_locked']) ? 1 : 0;
    $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
    $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;

    $stmt = $conn->prepare("SELECT * FROM projects WHERE project_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $project_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $project = $result->fetch_assoc();
    $stmt->close();

    if ($project) {
        // Insert or update project sharing settings
        $stmt = $conn->prepare("INSERT INTO project_sharing (project_id, permission_type, is_locked, start_time, end_time) 
                                VALUES (?, ?, ?, ?, ?) 
                                ON DUPLICATE KEY UPDATE 
                                permission_type = VALUES(permission_type),
                                is_locked = VALUES(is_locked),
                                start_time = VALUES(start_time),
                                end_time = VALUES(end_time)");
        $stmt->bind_param("isiss", $project_id, $permission_type, $is_locked, $start_time, $end_time);

        if ($stmt->execute()) {
            $message = "✅ Sharing permissions updated successfully!";
        } else {
            $error = "❌ Failed to update permissions. Please try again.";
        }
        $stmt->close();
    }
}

        // Get user's own projects
        $stmt = $conn->prepare("SELECT p.*, ps.permission_type, ps.is_locked, ps.start_time, ps.end_time 
                                FROM projects p 
                                LEFT JOIN project_sharing ps ON p.project_id = ps.project_id 
                                WHERE p.user_id = ? 
                                ORDER BY p.created_at DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Get shared projects
        $stmt = $conn->prepare("SELECT p.*, sp.shared_at, u.username AS owner_username, ps.permission_type, ps.is_locked, ps.start_time, ps.end_time
                                FROM shared_projects sp
                                JOIN projects p ON sp.project_id = p.project_id
                                JOIN users u ON p.user_id = u.user_id
                                LEFT JOIN project_sharing ps ON p.project_id = ps.project_id
                                WHERE sp.user_id = ?
                                ORDER BY sp.shared_at DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $shared_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        ?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Share Flash Cards - Flash Quiz App</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            color: white;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #6a4c93 0%, #a663cc 100%);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(106, 76, 147, 0.3);
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1em;
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

        .section {
            background: rgba(255, 255, 255, 0.1);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .section h2 {
            color: #a663cc;
            margin-bottom: 20px;
            font-size: 1.8em;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #e0e0e0;
            font-weight: 500;
        }

        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(166, 99, 204, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: #a663cc;
            box-shadow: 0 0 0 3px rgba(166, 99, 204, 0.2);
        }

        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .btn {
            padding: 12px 30px;
            background: linear-gradient(135deg, #a663cc 0%, #6a4c93 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(166, 99, 204, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(166, 99, 204, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .project-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .project-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            transition: transform 0.3s ease;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .project-card h3 {
            color: #a663cc;
            margin-bottom: 15px;
            font-size: 1.3em;
        }

        .project-info {
            margin-bottom: 15px;
        }

        .project-info span {
            display: block;
            margin-bottom: 5px;
            color: #e0e0e0;
        }

        .code-display {
            background: rgba(0, 0, 0, 0.3);
            padding: 10px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 18px;
            color: #a663cc;
            text-align: center;
            margin: 10px 0;
            border: 2px dashed rgba(166, 99, 204, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .code-display:hover {
            background: rgba(166, 99, 204, 0.1);
            border-color: #a663cc;
        }

        .permission-form {
            margin-top: 15px;
            padding: 15px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }

        .status-locked {
            background: #e74c3c;
            color: white;
        }

        .status-unlocked {
            background: #27ae60;
            color: white;
        }

        .status-view-only {
            background: #3498db;
            color: white;
        }

        .status-edit {
            background: #f39c12;
            color: white;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }

        .message.success {
            background: rgba(46, 204, 113, 0.2);
            border: 1px solid #2ecc71;
            color: #2ecc71;
        }

        .message.error {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid #e74c3c;
            color: #e74c3c;
        }

        .time-display {
            font-size: 12px;
            color: #bbb;
            margin-top: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #888;
        }

        .empty-state img {
            width: 100px;
            opacity: 0.5;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .project-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-buttons {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔗 Share Flash Cards</h1>
            <p>Share your quizzes with others or access shared content</p>
        </div>

        <nav class="nav-bar fade-in">
        <div class="nav-links">
            <a href="index.php">🏠 Home</a>
            <a href="create.php">✨ Create</a>
            <a href="quiz.php">🎯 Quiz</a>
            <a href="leaderboard.php">🏆 Leaderboards</a>
            <a href="share.php">🔗 Share</a>
            <a href="logout.php">🚪 Logout</a>
        </div>
        </nav>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Access Shared Quiz Section -->
        <div class="section">
            <h2>🔍 Access Shared Quiz</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="share_code">📝 Enter Project Code:</label>
                    <input type="text" id="share_code" name="share_code" placeholder="Enter the quiz project code" required>
                </div>
                <button type="submit" class="btn">🔓 Access Quiz</button>
            </form>
        </div>

        <!-- Your Projects Section -->
        <div class="section">
            <h2>📁 Your Projects</h2>
            <?php if (empty($user_projects)): ?>
                <div class="empty-state">
                    <div style="font-size: 4em;">📝</div>
                    <p>No projects created yet. <a href="create.php" style="color: #a663cc;">Create your first project</a>!</p>
                </div>
            <?php else: ?>
                <div class="project-grid">
                    <?php foreach ($user_projects as $project): ?>
                        <div class="project-card">
                            <h3>📚 <?php echo htmlspecialchars($project['project_name']); ?></h3>
                            
                            <div class="project-info">
                                <span><strong>🔑 Project Code:</strong></span>
                                <div class="code-display" onclick="copyToClipboard('<?php echo $project['project_code']; ?>')" title="Click to copy">
                                    <?php echo $project['project_code']; ?>
                                </div>
                                <span><strong>📅 Created:</strong> <?php echo date('M j, Y', strtotime($project['created_at'])); ?></span>
                                
                                <?php if ($project['permission_type']): ?>
                                    <span>
                                        <strong>🔒 Permission:</strong> 
                                        <span class="status-badge status-<?php echo str_replace('_', '-', $project['permission_type']); ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $project['permission_type'])); ?>
                                        </span>
                                        <?php if ($project['is_locked']): ?>
                                            <span class="status-badge status-locked">🔒 Locked</span>
                                        <?php else: ?>
                                            <span class="status-badge status-unlocked">🔓 Unlocked</span>
                                        <?php endif; ?>
                                    </span>
                                    
                                    <?php if ($project['start_time'] || $project['end_time']): ?>
                                        <div class="time-display">
                                            ⏰ Access Window: 
                                            <?php echo $project['start_time'] ? date('M j, Y H:i', strtotime($project['start_time'])) : 'No start limit'; ?>
                                            - 
                                            <?php echo $project['end_time'] ? date('M j, Y H:i', strtotime($project['end_time'])) : 'No end limit'; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <form method="POST" class="permission-form">
                                <input type="hidden" name="project_id" value="<?php echo $project['project_id']; ?>">
                                
                                <div class="form-group">
                                    <label for="permission_<?php echo $project['project_id']; ?>">🔐 Sharing Permission:</label>
                                    <select name="permission_type" id="permission_<?php echo $project['project_id']; ?>">
                                        <option value="view_only" <?php echo ($project['permission_type'] ?? 'view_only') == 'view_only' ? 'selected' : ''; ?>>👀 View Only</option>
                                        <option value="use_and_review" <?php echo ($project['permission_type'] ?? '') == 'use_and_review' ? 'selected' : ''; ?>>📝 Use & Review</option>
                                        <option value="edit" <?php echo ($project['permission_type'] ?? '') == 'edit' ? 'selected' : ''; ?>>✏️ Edit</option>
                                    </select>
                                </div>

                                <div class="checkbox-group">
                                    <input type="checkbox" id="locked_<?php echo $project['project_id']; ?>" name="is_locked" <?php echo $project['is_locked'] ? 'checked' : ''; ?>>
                                    <label for="locked_<?php echo $project['project_id']; ?>">🔒 Lock Quiz (prevent new attempts)</label>
                                </div>

                                <div class="form-group">
                                    <label for="start_time_<?php echo $project['project_id']; ?>">⏰ Access Start Time:</label>
                                    <input type="datetime-local" id="start_time_<?php echo $project['project_id']; ?>" name="start_time" 
                                           value="<?php echo $project['start_time'] ? date('Y-m-d\TH:i', strtotime($project['start_time'])) : ''; ?>">
                                </div>

                                <div class="form-group">
                                    <label for="end_time_<?php echo $project['project_id']; ?>">⏰ Access End Time:</label>
                                    <input type="datetime-local" id="end_time_<?php echo $project['project_id']; ?>" name="end_time" 
                                           value="<?php echo $project['end_time'] ? date('Y-m-d\TH:i', strtotime($project['end_time'])) : ''; ?>">
                                </div>

                                <button type="submit" name="update_permissions" class="btn">✅ Update Permissions</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Shared Projects Section -->
        <div class="section">
            <h2>📤 Shared with You</h2>
            <?php if (empty($shared_projects)): ?>
                <div class="empty-state">
                    <div style="font-size: 4em;">📮</div>
                    <p>No shared projects yet. Ask others to share their quiz codes with you!</p>
                </div>
            <?php else: ?>
                <div class="project-grid">
                    <?php foreach ($shared_projects as $project): ?>
                        <div class="project-card">
                            <h3>📚 <?php echo htmlspecialchars($project['project_name']); ?></h3>
                            
                            <div class="project-info">
                                <span><strong>👤 Owner:</strong> <?php echo htmlspecialchars($project['owner_username']); ?></span>
                                <span><strong>📅 Shared:</strong> <?php echo date('M j, Y', strtotime($project['shared_at'])); ?></span>
                                
                                <span>
                                    <strong>🔐 Your Access:</strong> 
                                    <span class="status-badge status-<?php echo str_replace('_', '-', $project['permission_type'] ?? 'view-only'); ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $project['permission_type'] ?? 'View Only')); ?>
                                    </span>
                                    <?php if ($project['is_locked']): ?>
                                        <span class="status-badge status-locked">🔒 Locked</span>
                                    <?php else: ?>
                                        <span class="status-badge status-unlocked">🔓 Available</span>
                                    <?php endif; ?>
                                </span>

                                <?php if ($project['start_time'] || $project['end_time']): ?>
                                    <div class="time-display">
                                        ⏰ Access Window: 
                                        <?php echo $project['start_time'] ? date('M j, Y H:i', strtotime($project['start_time'])) : 'Always'; ?>
                                        - 
                                        <?php echo $project['end_time'] ? date('M j, Y H:i', strtotime($project['end_time'])) : 'Always'; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div style="margin-top: 15px;">
                                <?php 
                                $can_access = true;
                                $current_time = new DateTime();
                                
                                if ($project['start_time'] && $current_time < new DateTime($project['start_time'])) {
                                    $can_access = false;
                                }
                                if ($project['end_time'] && $current_time > new DateTime($project['end_time'])) {
                                    $can_access = false;
                                }
                                if ($project['is_locked']) {
                                    $can_access = false;
                                }
                                ?>
                                
                                <?php if ($can_access): ?>
                                    <a href="review.php?project_id=<?php echo $project['project_id']; ?>" class="btn">👀 Review</a>
                                    <?php if (in_array($project['permission_type'], ['use_and_review', 'edit'])): ?>
                                        <a href="quiz.php?project_id=<?php echo $project['project_id']; ?>" class="btn">📝 Take Quiz</a>
                                    <?php endif; ?>
                                    <?php if ($project['permission_type'] == 'edit'): ?>
                                        <a href="manage.php?project_id=<?php echo $project['project_id']; ?>" class="btn">✏️ Edit</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn" disabled>
                                        <?php 
                                        if ($project['is_locked']) {
                                            echo "🔒 Locked";
                                        } elseif ($project['start_time'] && $current_time < new DateTime($project['start_time'])) {
                                            echo "⏰ Not Yet Available";
                                        } elseif ($project['end_time'] && $current_time > new DateTime($project['end_time'])) {
                                            echo "⏰ Access Expired";
                                        }
                                        ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Create temporary feedback
                const originalText = event.target.textContent;
                event.target.textContent = 'Copied! ✅';
                event.target.style.background = 'rgba(46, 204, 113, 0.3)';
                
                setTimeout(() => {
                    event.target.textContent = originalText;
                    event.target.style.background = 'rgba(0, 0, 0, 0.3)';
                }, 2000);
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                
                const originalText = event.target.textContent;
                event.target.textContent = 'Copied! ✅';
                event.target.style.background = 'rgba(46, 204, 113, 0.3)';
                
                setTimeout(() => {
                    event.target.textContent = originalText;
                    event.target.style.background = 'rgba(0, 0, 0, 0.3)';
                }, 2000);
            });
        }

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                message.style.transition = 'opacity 0.5s ease';
                message.style.opacity = '0';
                setTimeout(() => {
                    message.remove();
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>