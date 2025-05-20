<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Changed from dashboard.php to login.php
    exit();
}

$user_id = $_SESSION['user_id'];
$projects = array();

// Get projects accessible to this user (owned or shared)
$sql = "SELECT p.project_id, p.project_name, p.project_code, 
        CASE WHEN p.user_id = ? THEN 1 ELSE 0 END as is_owner
        FROM projects p
        LEFT JOIN shared_projects sp ON p.project_id = sp.project_id AND sp.user_id = ?
        WHERE p.user_id = ? OR sp.shared_id IS NOT NULL
        ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $projects[] = $row;
}

// Handle project selection
$selected_project = null;
$flashcards = array();
$current_card = 0;

if (isset($_GET['project_id']) && is_numeric($_GET['project_id'])) { // Added validation
    $project_id = intval($_GET['project_id']); // Properly sanitize input
    
    // Verify that the user has access to this project
    $check_access = $conn->prepare("SELECT p.project_id, p.project_name, p.project_code, 
                                  CASE WHEN p.user_id = ? THEN 1 ELSE 0 END as is_owner
                                  FROM projects p
                                  LEFT JOIN shared_projects sp ON p.project_id = sp.project_id AND sp.user_id = ?
                                  WHERE (p.user_id = ? OR sp.shared_id IS NOT NULL) AND p.project_id = ?");
    $check_access->bind_param("iiii", $user_id, $user_id, $user_id, $project_id);
    $check_access->execute();
    $project_result = $check_access->get_result();
    
    if ($project_result->num_rows > 0) {
        $selected_project = $project_result->fetch_assoc();
        
        // Get flashcards for this project
        $cards_query = $conn->prepare("SELECT f.*, qt.type_name 
                                      FROM flashcards f 
                                      JOIN question_types qt ON f.type_id = qt.type_id 
                                      WHERE f.project_id = ? 
                                      ORDER BY f.created_at");
        $cards_query->bind_param("i", $project_id);
        $cards_query->execute();
        $cards_result = $cards_query->get_result();
        
        while ($card = $cards_result->fetch_assoc()) {
            // For multiple choice, get the options
            if ($card['type_id'] == 2) { // Multiple choice type
                $options_query = $conn->prepare("SELECT * FROM multiple_choice_options WHERE flashcard_id = ? ORDER BY option_label");
                $options_query->bind_param("i", $card['flashcard_id']);
                $options_query->execute();
                $options_result = $options_query->get_result();
                
                $options = array();
                while ($option = $options_result->fetch_assoc()) {
                    $options[] = $option;
                }
                
                $card['options'] = $options;
            }
            
            $flashcards[] = $card;
        }
        
        // Get current card index (if specified)
        if (isset($_GET['card']) && is_numeric($_GET['card'])) {
            $current_card = max(0, min(count($flashcards) - 1, intval($_GET['card'])));
        }
    }
}

// Close result sets to prevent memory leaks
if (isset($result)) {
    $result->close();
}
if (isset($project_result)) {
    $project_result->close();
}
if (isset($cards_result)) {
    $cards_result->close();
}
if (isset($options_result)) {
    $options_result->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Flash Cards - Flash Quiz App</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        :root {
            --dark-blue: #1a1a2e;
            --grande-violet: #6a0dad;
            --light-violet: #9d4edd;
            --black: #121212;
            --white: #f8f8f8;
            --gray: #333340;
        }
        
        body {
            background-color: var(--dark-blue);
            color: var(--white);
            font-family: 'Arial', sans-serif;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--grande-violet);
            padding-bottom: 15px;
        }
        
        h1, h2 {
            color: var(--white);
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .btn {
            background-color: var(--grande-violet);
            color: var(--white);
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background-color: var(--light-violet);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106, 13, 173, 0.4);
        }
        
        .btn.btn-secondary {
            background-color: var(--gray);
        }
        
        .btn.btn-secondary:hover {
            background-color: #4a4a5a;
        }
        
        .btn.btn-back {
            background-color: #4a5568;
            color: var(--white);
        }
        
        .btn.btn-back:hover {
            background-color: #2d3748;
        }
        
        .btn.disabled {
            background-color: #4a4a5a;
            cursor: not-allowed;
            opacity: 0.6;
            pointer-events: none; /* Added to make disabled buttons unclickable */
        }
        
        .project-list {
            list-style-type: none;
            padding: 0;
        }
        
        .project-list li {
            margin-bottom: 15px;
            background-color: var(--gray);
            border-radius: 5px;
            transition: transform 0.3s ease;
        }
        
        .project-list li:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .project-list a {
            display: block;
            padding: 15px;
            color: var(--white);
            text-decoration: none;
            font-weight: bold;
            border-left: 4px solid var(--grande-violet);
        }
        
        .shared-badge {
            background-color: var(--light-violet);
            color: var(--white);
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.8em;
            margin-left: 10px;
        }
        
        .flashcard-container {
            perspective: 1000px;
            width: 100%;
            max-width: 600px;
            height: 400px;
            margin: 0 auto;
            position: relative;
        }
        
        .flashcard {
            width: 100%;
            height: 100%;
            transform-style: preserve-3d;
            transition: transform 0.6s;
            cursor: pointer;
        }
        
        .flashcard.flipped {
            transform: rotateY(180deg);
        }
        
        .flashcard-front, .flashcard-back {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        
        .flashcard-front {
            background: linear-gradient(145deg, var(--gray), var(--dark-blue));
            z-index: 2;
            border: 2px solid var(--grande-violet);
        }
        
        .flashcard-back {
            background: linear-gradient(145deg, var(--grande-violet), var(--dark-blue));
            transform: rotateY(180deg);
            border: 2px solid var(--light-violet);
        }
        
        .question-content, .answer-content {
            font-size: 1.2em;
            text-align: center;
            margin: 20px 0;
            max-height: 250px;
            overflow-y: auto;
            padding: 10px;
            width: 100%;
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

        .navigation-controls {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
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
        
        .card-progress {
            text-align: center;
            margin: 20px 0;
            color: var(--white);
            font-weight: bold;
        }
        
        .flip-instruction {
            color: var(--light-violet);
            font-style: italic;
            margin-top: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }
        
        .options-list {
            list-style-type: none;
            padding: 0;
            text-align: left;
            width: 100%;
        }
        
        .options-list li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .correct-answer {
            color: #4CAF50;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .review-section {
            background-color: rgba(26, 26, 46, 0.7);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .back-button-container {
            margin-bottom: 20px;
        }
        
        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .project-header h2 {
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Review Flash Cards</h1>
        </header>
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
        
        <?php if (!$selected_project): ?>
            <!-- Project Selection -->
            <div class="project-selection">
                <h2>Select a Project to Review</h2>
                
                <?php if (!empty($projects)): ?>
                    <ul class="project-list">
                        <?php foreach ($projects as $project): ?>
                            <li>
                                <a href="review.php?project_id=<?php echo htmlspecialchars($project['project_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($project['project_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if (!$project['is_owner']): ?>
                                        <span class="shared-badge">(Shared)</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>You don't have any projects yet. <a href="create.php" class="btn">Create one now</a>.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Flashcard Review -->
            <div class="review-section">
                <div class="back-button-container">
                    <a href="review.php" class="btn btn-back">← Back to Projects</a>
                </div>
                
                <div class="project-header">
                    <h2>Project: <?php echo htmlspecialchars($selected_project['project_name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                </div>
                
                <?php if (empty($flashcards)): ?>
                    <p>This project doesn't have any flashcards yet. <a href="create.php?project_id=<?php echo htmlspecialchars($selected_project['project_id'], ENT_QUOTES, 'UTF-8'); ?>" class="btn">Add some now</a>.</p>
                <?php else: ?>
                    <div class="card-progress">
                        <p>Card <?php echo $current_card + 1; ?> of <?php echo count($flashcards); ?></p>
                    </div>
                    
                    <div class="flashcard-container">
                        <div class="flashcard" id="flashcard">
                            <div class="flashcard-front">
                                <h2>Question:</h2>
                                <div class="question-content">
                                    <?php echo htmlspecialchars($flashcards[$current_card]['question'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <p class="flip-instruction">Click to flip</p>
                            </div>
                            <div class="flashcard-back">
                                <h2>Answer:</h2>
                                <div class="answer-content">
                                    <?php if ($flashcards[$current_card]['type_id'] == 2): // Multiple choice ?>
                                        <ul class="options-list">
                                            <?php if (!empty($flashcards[$current_card]['options'])): ?>
                                                <?php foreach ($flashcards[$current_card]['options'] as $option): ?>
                                                    <li>
                                                        <?php echo htmlspecialchars($option['option_label'] . '. ' . $option['option_text'], ENT_QUOTES, 'UTF-8'); ?>
                                                        <?php if (isset($option['is_correct']) && $option['is_correct']): ?>
                                                            <span class="correct-answer">(Correct)</span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <li>No options available</li>
                                            <?php endif; ?>
                                        </ul>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($flashcards[$current_card]['answer'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </div>
                                <p class="flip-instruction">Click to flip back</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="navigation-controls">
                        <a href="review.php?project_id=<?php echo htmlspecialchars($selected_project['project_id'], ENT_QUOTES, 'UTF-8'); ?>&card=<?php echo max(0, $current_card - 1); ?>" class="btn <?php echo ($current_card <= 0) ? 'disabled' : ''; ?>">Previous</a>
                        <a href="quiz.php?project_id=<?php echo htmlspecialchars($selected_project['project_id'], ENT_QUOTES, 'UTF-8'); ?>" class="btn">Take Quiz</a>
                        <a href="review.php?project_id=<?php echo htmlspecialchars($selected_project['project_id'], ENT_QUOTES, 'UTF-8'); ?>&card=<?php echo min(count($flashcards) - 1, $current_card + 1); ?>" class="btn <?php echo ($current_card >= count($flashcards) - 1) ? 'disabled' : ''; ?>">Next</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Flip flashcard on click
        const flashcard = document.getElementById('flashcard');
        if (flashcard) {
            flashcard.addEventListener('click', function() {
                this.classList.toggle('flipped');
            });
        }
    </script>
</body>
</html>