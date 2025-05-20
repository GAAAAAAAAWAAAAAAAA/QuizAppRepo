<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle project deletion
if (isset($_POST['delete_project'])) {
    $project_id = $_POST['project_id'];
    
    // Verify project belongs to user
    $check_query = "SELECT project_id FROM projects WHERE project_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $project_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $delete_query = "DELETE FROM projects WHERE project_id = ? AND user_id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("ii", $project_id, $user_id);
        
        if ($delete_stmt->execute()) {
            $message = "Project deleted successfully! 🗑️";
        } else {
            $error = "Error deleting project.";
        }
    } else {
        $error = "Project not found or unauthorized.";
    }
}

// Handle flashcard deletion
if (isset($_POST['delete_flashcard'])) {
    $flashcard_id = $_POST['flashcard_id'];
    
    // Verify flashcard belongs to user's project
    $check_query = "SELECT f.flashcard_id FROM flashcards f 
                   JOIN projects p ON f.project_id = p.project_id 
                   WHERE f.flashcard_id = ? AND p.user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $flashcard_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $delete_query = "DELETE FROM flashcards WHERE flashcard_id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $flashcard_id);
        
        if ($delete_stmt->execute()) {
            $message = "Flashcard deleted successfully! 🗂️";
        } else {
            $error = "Error deleting flashcard.";
        }
    } else {
        $error = "Flashcard not found or unauthorized.";
    }
}

// Handle flashcard editing
if (isset($_POST['edit_flashcard'])) {
    $flashcard_id = $_POST['flashcard_id'];
    $question = $_POST['question'];
    $answer = $_POST['answer'];
    $type_id = $_POST['type_id'];
    
    // Update flashcard
    $update_query = "UPDATE flashcards SET question = ?, answer = ?, type_id = ? WHERE flashcard_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ssii", $question, $answer, $type_id, $flashcard_id);
    
    if ($update_stmt->execute()) {
        $message = "Flashcard updated successfully! ✏️";
    } else {
        $error = "Error updating flashcard.";
    }
}

// Handle project sharing settings
if (isset($_POST['update_sharing'])) {
    $project_id = $_POST['project_id'];
    $permission_type = $_POST['permission_type'];
    $is_locked = isset($_POST['is_locked']) ? 1 : 0;
    $start_time = $_POST['start_time'] ? $_POST['start_time'] : null;
    $end_time = $_POST['end_time'] ? $_POST['end_time'] : null;
    
    // Check if sharing settings exist
    $check_sharing = "SELECT sharing_id FROM project_sharing WHERE project_id = ?";
    $check_stmt = $conn->prepare($check_sharing);
    $check_stmt->bind_param("i", $project_id);
    $check_stmt->execute();
    $sharing_result = $check_stmt->get_result();
    
    if ($sharing_result->num_rows > 0) {
        // Update existing sharing settings
        $update_sharing = "UPDATE project_sharing 
                          SET permission_type = ?, is_locked = ?, start_time = ?, end_time = ? 
                          WHERE project_id = ?";
        $update_stmt = $conn->prepare($update_sharing);
        $update_stmt->bind_param("sissi", $permission_type, $is_locked, $start_time, $end_time, $project_id);
    } else {
        // Insert new sharing settings
        $update_sharing = "INSERT INTO project_sharing (project_id, permission_type, is_locked, start_time, end_time) 
                          VALUES (?, ?, ?, ?, ?)";
        $update_stmt = $conn->prepare($update_sharing);
        $update_stmt->bind_param("isiss", $project_id, $permission_type, $is_locked, $start_time, $end_time);
    }
    
    if ($update_stmt->execute()) {
        $message = "Sharing settings updated successfully! 🔗";
    } else {
        $error = "Error updating sharing settings.";
    }
}

// Get all projects for this user
$projects_query = "SELECT p.*, 
                   (SELECT COUNT(*) FROM flashcards WHERE project_id = p.project_id) as flashcard_count,
                   ps.permission_type, ps.is_locked, ps.start_time, ps.end_time
                   FROM projects p 
                   LEFT JOIN project_sharing ps ON p.project_id = ps.project_id
                   WHERE p.user_id = ? 
                   ORDER BY p.created_at DESC";
$projects_stmt = $conn->prepare($projects_query);
$projects_stmt->bind_param("i", $user_id);
$projects_stmt->execute();
$projects_result = $projects_stmt->get_result();

// Get question types
$types_query = "SELECT * FROM question_types";
$types_result = $conn->query($types_query);
$question_types = [];
while ($type = $types_result->fetch_assoc()) {
    $question_types[$type['type_id']] = $type['type_name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Flash Cards - Flash Quiz App</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: white;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px 0;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header h1 {
            font-size: 3.5rem;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #8b5cf6, #06d6a0);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header p {
            font-size: 1.3rem;
            color: #a8a8a8;
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

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
            text-align: center;
            font-weight: 500;
        }

        .message.success {
            background: rgba(6, 214, 160, 0.2);
            border: 1px solid #06d6a0;
            color: #06d6a0;
        }

        .message.error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid #ef4444;
            color: #ef4444;
        }

        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .project-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .project-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(45deg, #8b5cf6, #06d6a0);
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border-color: rgba(139, 92, 246, 0.5);
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .project-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #8b5cf6;
        }

        .project-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-item {
            background: rgba(139, 92, 246, 0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .project-code {
            background: rgba(6, 214, 160, 0.2);
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-family: monospace;
            font-size: 1.1rem;
            border: 1px solid rgba(6, 214, 160, 0.3);
        }

        .sharing-settings {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sharing-title {
            font-weight: 600;
            margin-bottom: 15px;
            color: #06d6a0;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #a8a8a8;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-radius: 8px;
            font-size: 1rem;
        }

        .form-group input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            padding: 10px 0;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin: 5px;
        }

        .btn-primary {
            background: linear-gradient(45deg, #8b5cf6, #06d6a0);
            color: white;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-danger {
            background: linear-gradient(45deg, #ef4444, #dc2626);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .project-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .flashcards-section {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .flashcards-title {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: #06d6a0;
            font-weight: 600;
        }

        .flashcard-item {
            background: rgba(255, 255, 255, 0.08);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 15px;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .flashcard-content {
            margin-bottom: 15px;
        }

        .flashcard-question {
            font-weight: 600;
            margin-bottom: 8px;
            color: #8b5cf6;
        }

        .flashcard-answer {
            color: #a8a8a8;
            margin-bottom: 8px;
        }

        .flashcard-type {
            font-size: 0.9rem;
            color: #06d6a0;
            font-style: italic;
        }

        .edit-form {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .edit-form.active {
            display: block;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            margin: 10% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            border: 1px solid rgba(139, 92, 246, 0.3);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #8b5cf6;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            border: 2px dashed rgba(255, 255, 255, 0.2);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state-text {
            font-size: 1.3rem;
            color: #a8a8a8;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .projects-grid {
                grid-template-columns: 1fr;
            }
            
            .project-actions {
                justify-content: center;
            }
            
            .nav-links {
                flex-direction: column;
                align-items: center;
            }
            
            .header h1 {
                font-size: 2.5rem;
            }
        }

        .loading {
            text-align: center;
            padding: 40px;
        }

        .loading::after {
            content: '';
            display: inline-block;
            width: 30px;
            height: 30px;
            border: 3px solid rgba(139, 92, 246, 0.3);
            border-radius: 50%;
            border-top-color: #8b5cf6;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header fade-in">
            <h1>📚 Manage Flash Cards</h1>
            <p>Edit, organize, and share your quiz projects</p>
        </div>

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

        <?php if ($message): ?>
            <div class="message success fade-in"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error fade-in"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($projects_result->num_rows > 0): ?>
            <div class="projects-grid">
                <?php while ($project = $projects_result->fetch_assoc()): ?>
                    <div class="project-card fade-in">
                        <div class="project-header">
                            <div class="project-title"><?php echo htmlspecialchars($project['project_name']); ?></div>
                            <span style="color: #06d6a0; font-size: 1.5rem;">📝</span>
                        </div>

                        <div class="project-stats">
                            <div class="stat-item">
                                📊 <?php echo $project['flashcard_count']; ?> Cards
                            </div>
                            <div class="stat-item">
                                📅 <?php echo date('M j, Y', strtotime($project['created_at'])); ?>
                            </div>
                        </div>

                        <div class="project-code">
                            🔑 Code: <strong><?php echo $project['project_code']; ?></strong>
                        </div>

                        <!-- Sharing Settings Form -->
                        <div class="sharing-settings">
                            <div class="sharing-title">🔗 Sharing Settings</div>
                            <form method="POST">
                                <input type="hidden" name="project_id" value="<?php echo $project['project_id']; ?>">
                                
                                <div class="form-group">
                                    <label>Permission Type:</label>
                                    <select name="permission_type">
                                        <option value="view_only" <?php echo ($project['permission_type'] == 'view_only') ? 'selected' : ''; ?>>
                                            👁️ View Only
                                        </option>
                                        <option value="use_and_review" <?php echo ($project['permission_type'] == 'use_and_review') ? 'selected' : ''; ?>>
                                            🎯 Use & Review
                                        </option>
                                        <option value="edit" <?php echo ($project['permission_type'] == 'edit') ? 'selected' : ''; ?>>
                                            ✏️ Edit Access
                                        </option>
                                    </select>
                                </div>

                                <div class="checkbox-group">
                                    <input type="checkbox" name="is_locked" id="locked_<?php echo $project['project_id']; ?>" 
                                           <?php echo $project['is_locked'] ? 'checked' : ''; ?>>
                                    <label for="locked_<?php echo $project['project_id']; ?>">🔒 Lock Project</label>
                                </div>

                                <div class="form-group">
                                    <label>⏰ Start Time (Optional):</label>
                                    <input type="datetime-local" name="start_time" 
                                           value="<?php echo $project['start_time']; ?>">
                                </div>

                                <div class="form-group">
                                    <label>⏰ End Time (Optional):</label>
                                    <input type="datetime-local" name="end_time" 
                                           value="<?php echo $project['end_time']; ?>">
                                </div>

                                <button type="submit" name="update_sharing" class="btn btn-primary">
                                    💾 Update Sharing
                                </button>
                            </form>
                        </div>

                        <!-- Project Actions -->
                        <div class="project-actions">
                            <button onclick="showFlashcards(<?php echo $project['project_id']; ?>)" class="btn btn-secondary">
                                👀 View Cards
                            </button>
                            <a href="create.php?project_id=<?php echo $project['project_id']; ?>" class="btn btn-primary">
                                ➕ Add Cards
                            </a>
                            <button onclick="confirmDelete('project', <?php echo $project['project_id']; ?>)" class="btn btn-danger">
                                🗑️ Delete Project
                            </button>
                        </div>

                        <!-- Flashcards Section (Initially Hidden) -->
                        <div id="flashcards_<?php echo $project['project_id']; ?>" class="flashcards-section" style="display: none;">
                            <div class="flashcards-title">📚 Flash Cards</div>
                            <div class="loading">Loading flashcards...</div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state fade-in">
                <div class="empty-state-icon">📭</div>
                <div class="empty-state-text">
                    No projects found! Start creating your first flash card project.
                </div>
                <a href="create.php" class="btn btn-primary">
                    ✨ Create Your First Project
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 style="margin-bottom: 20px; color: #ef4444;">⚠️ Confirm Deletion</h2>
            <p id="deleteMessage" style="margin-bottom: 30px; color: #a8a8a8;"></p>
            <form id="deleteForm" method="POST">
                <input type="hidden" id="deleteType" name="">
                <input type="hidden" id="deleteId" name="">
                <div style="text-align: center;">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">
                        ❌ Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        🗑️ Delete
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Show/Hide flashcards
        async function showFlashcards(projectId) {
            const flashcardsDiv = document.getElementById(`flashcards_${projectId}`);
            
            if (flashcardsDiv.style.display === 'none') {
                flashcardsDiv.style.display = 'block';
                
                // Load flashcards via AJAX
                try {
                    const response = await fetch('get_flashcards.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `project_id=${projectId}`
                    });
                    
                    const data = await response.text();
                    flashcardsDiv.innerHTML = '<div class="flashcards-title">📚 Flash Cards</div>' + data;
                } catch (error) {
                    flashcardsDiv.innerHTML = '<div class="flashcards-title">📚 Flash Cards</div><p style="color: #ef4444;">Error loading flashcards.</p>';
                }
            } else {
                flashcardsDiv.style.display = 'none';
            }
        }

        // Toggle edit form
        function toggleEdit(flashcardId) {
            const editForm = document.getElementById(`edit_${flashcardId}`);
            editForm.classList.toggle('active');
        }

        // Delete confirmation modal
        function confirmDelete(type, id) {
            const modal = document.getElementById('deleteModal');
            const deleteMessage = document.getElementById('deleteMessage');
            const deleteType = document.getElementById('deleteType');
            const deleteId = document.getElementById('deleteId');
            
            if (type === 'project') {
                deleteMessage.textContent = 'Are you sure you want to delete this project? This action will permanently remove all associated flashcards and cannot be undone.';
                deleteType.name = 'delete_project';
                deleteId.name = 'project_id';
            } else if (type === 'flashcard') {
                deleteMessage.textContent = 'Are you sure you want to delete this flashcard? This action cannot be undone.';
                deleteType.name = 'delete_flashcard';
                deleteId.name = 'flashcard_id';
            }
            
            deleteId.value = id;
            modal.style.display = 'block';
        }

        // Close modal
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Modal event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('deleteModal');
            const closeBtn = document.querySelector('.close');
            
            closeBtn.onclick = closeModal;
            
            window.onclick = function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            }
        });

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(function(message) {
                message.style.opacity = '0';
                message.style.transform = 'translateY(-20px)';
                setTimeout(function() {
                    message.style.display = 'none';
                }, 300);
            });
        }, 5000);

        // Copy project code to clipboard
        function copyCode(code) {
            navigator.clipboard.writeText(code).then(function() {
                const tempMessage = document.createElement('div');
                tempMessage.className = 'message success';
                tempMessage.textContent = '📋 Project code copied to clipboard!';
                tempMessage.style.position = 'fixed';
                tempMessage.style.top = '20px';
                tempMessage.style.right = '20px';
                tempMessage.style.zIndex = '9999';
                document.body.appendChild(tempMessage);
                
                setTimeout(function() {
                    tempMessage.remove();
                }, 3000);
            });
        }

        // Add click event to project codes for copying
        document.addEventListener('DOMContentLoaded', function() {
            const projectCodes = document.querySelectorAll('.project-code');
            projectCodes.forEach(function(codeElement) {
                codeElement.style.cursor = 'pointer';
                codeElement.title = 'Click to copy code';
                codeElement.addEventListener('click', function() {
                    const code = this.textContent.split(': ')[1];
                    copyCode(code);
                });
            });
        });

        // Smooth scrolling for better UX
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.project-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Initialize card animations
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.project-card');
            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.5s ease';
            });
        });
    </script>
</body>
</html>