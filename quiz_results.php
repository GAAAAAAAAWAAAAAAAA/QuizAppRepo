<?php
session_start();
require_once('db_connect.php');
// Check if user is logged in or is a guest
if (!isset($_SESSION['user_id']) && !isset($_SESSION['guest_username']) && !isset($_GET['guest'])) {
    header("Location: dashboard.php");
    exit();
}

$is_guest = isset($_SESSION['guest_username']) || isset($_GET['guest']);
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

// Get attempt data
$attempt_data = null;
$project_data = null;
$user_answers = [];
$flashcards = [];

if ($is_guest && isset($_SESSION['guest_attempt'])) {
    // For guest users, get attempt data from session
    $attempt_data = $_SESSION['guest_attempt'];
    $project_id = $attempt_data['project_id'];
    
    // Get project details
    $project_query = "SELECT * FROM projects WHERE project_id = ?";
    $stmt = $conn->prepare($project_query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $project_result = $stmt->get_result();
    
    if ($project_result->num_rows > 0) {
        $project_data = $project_result->fetch_assoc();
    } else {
        header("Location: index.php");
        exit();
    }
    
    // Get answers from session
    $user_answers = $attempt_data['answers'];
    
    // Get flashcards
    $flashcards_query = "SELECT f.*, qt.type_name 
                        FROM flashcards f 
                        JOIN question_types qt ON f.type_id = qt.type_id 
                        WHERE f.project_id = ? 
                        ORDER BY f.flashcard_id";
    $stmt = $conn->prepare($flashcards_query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $flashcards_result = $stmt->get_result();
    
    while ($card = $flashcards_result->fetch_assoc()) {
        $flashcards[$card['flashcard_id']] = $card;
        
        // If multiple choice, get the options
        if ($card['type_name'] == 'multiple_choice') {
            $options_query = "SELECT * FROM multiple_choice_options WHERE flashcard_id = ? ORDER BY option_label";
            $stmt = $conn->prepare($options_query);
            $stmt->bind_param("i", $card['flashcard_id']);
            $stmt->execute();
            $options_result = $stmt->get_result();
            
            $options = [];
            while ($option = $options_result->fetch_assoc()) {
                $options[] = $option;
            }
            
            // Add options to the flashcard
            $flashcards[$card['flashcard_id']]['options'] = $options;
        }
    }
} else {
    // For registered users, get attempt data from database
    $attempt_id = isset($_GET['attempt_id']) ? intval($_GET['attempt_id']) : 0;
    
    if ($attempt_id <= 0) {
        header("Location: index.php");
        exit();
    }
    
    // Get attempt data
    $attempt_query = "SELECT qa.*, p.project_name, p.project_id 
                     FROM quiz_attempts qa 
                     JOIN projects p ON qa.project_id = p.project_id 
                     WHERE qa.attempt_id = ? AND (qa.user_id = ? OR p.user_id = ?)";
    $stmt = $conn->prepare($attempt_query);
    $stmt->bind_param("iii", $attempt_id, $user_id, $user_id);
    $stmt->execute();
    $attempt_result = $stmt->get_result();
    
    if ($attempt_result->num_rows == 0) {
        header("Location: index.php?error=noaccess");
        exit();
    }
    
    $attempt_data = $attempt_result->fetch_assoc();
    $project_data = [
        'project_id' => $attempt_data['project_id'],
        'project_name' => $attempt_data['project_name']
    ];
    
    // Get user answers
    $answers_query = "SELECT ua.*, f.question, f.answer as correct_answer, qt.type_name 
                     FROM user_answers ua 
                     JOIN flashcards f ON ua.flashcard_id = f.flashcard_id 
                     JOIN question_types qt ON f.type_id = qt.type_id 
                     WHERE ua.attempt_id = ? 
                     ORDER BY f.flashcard_id";
    $stmt = $conn->prepare($answers_query);
    $stmt->bind_param("i", $attempt_id);
    $stmt->execute();
    $answers_result = $stmt->get_result();
    
    while ($answer = $answers_result->fetch_assoc()) {
        $user_answers[] = $answer;
        $flashcards[$answer['flashcard_id']] = [
            'flashcard_id' => $answer['flashcard_id'],
            'question' => $answer['question'],
            'answer' => $answer['correct_answer'],
            'type_name' => $answer['type_name']
        ];
        
        // If multiple choice, get the options
        if ($answer['type_name'] == 'multiple_choice') {
            $options_query = "SELECT * FROM multiple_choice_options WHERE flashcard_id = ? ORDER BY option_label";
            $stmt = $conn->prepare($options_query);
            $stmt->bind_param("i", $answer['flashcard_id']);
            $stmt->execute();
            $options_result = $stmt->get_result();
            
            $options = [];
            while ($option = $options_result->fetch_assoc()) {
                $options[] = $option;
            }
            
            // Add options to the flashcard
            $flashcards[$answer['flashcard_id']]['options'] = $options;
        }
    }
}

// Get quiz settings
$quiz_settings_query = "SELECT * FROM quiz_settings WHERE project_id = ?";
$stmt = $conn->prepare($quiz_settings_query);
$stmt->bind_param("i", $project_data['project_id']);
$stmt->execute();
$settings_result = $stmt->get_result();

$quiz_settings = $settings_result->fetch_assoc();

// Calculate statistics
$total_questions = count($user_answers);
$correct_answers = 0;
$incorrect_answers = 0;

foreach ($user_answers as $answer) {
    if (isset($answer['is_correct']) && $answer['is_correct']) {
        $correct_answers++;
    } else {
        $incorrect_answers++;
    }
}

$accuracy = $total_questions > 0 ? ($correct_answers / $total_questions) * 100 : 0;

// Calculate time taken
$time_taken = "N/A";
if (!$is_guest && isset($attempt_data['start_time']) && isset($attempt_data['end_time'])) {
    $start = new DateTime($attempt_data['start_time']);
    $end = new DateTime($attempt_data['end_time']);
    $interval = $start->diff($end);
    
    $hours = $interval->h + ($interval->days * 24);
    $minutes = $interval->i;
    $seconds = $interval->s;
    
    $time_taken = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
} else if ($is_guest && isset($attempt_data['start_time']) && isset($attempt_data['end_time'])) {
    // For guest users with timing data stored in a different format
    $start = new DateTime($attempt_data['start_time']);
    $end = new DateTime($attempt_data['end_time']);
    $interval = $start->diff($end);
    
    $hours = $interval->h + ($interval->days * 24);
    $minutes = $interval->i;
    $seconds = $interval->s;
    
    $time_taken = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}

// Get leaderboard position
$position = "N/A";
if (!$is_guest) {
    $rank_query = "SELECT COUNT(*) + 1 as rank_position
                  FROM quiz_attempts qa
                  WHERE qa.project_id = ? AND qa.score > ? AND qa.completed = 1";
    $stmt = $conn->prepare($rank_query);
    $stmt->bind_param("ii", $project_data['project_id'], $attempt_data['score']);
    $stmt->execute();
    $rank_result = $stmt->get_result();
    $rank_data = $rank_result->fetch_assoc();
    $position = $rank_data['rank_position'];
}

// If guest mode, clear the attempt data from session after viewing results
if ($is_guest && isset($_SESSION['guest_attempt'])) {
    unset($_SESSION['guest_attempt']);
}

// Function to get option text for multiple choice answers
function getOptionText($flashcard_id, $option_label, $flashcards) {
    if (!isset($flashcards[$flashcard_id]) || !isset($flashcards[$flashcard_id]['options'])) {
        return $option_label;
    }
    
    foreach ($flashcards[$flashcard_id]['options'] as $option) {
        if ($option['option_label'] === $option_label) {
            return $option['option_text'];
        }
    }
    
    return $option_label;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results: <?php echo htmlspecialchars($project_data['project_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #6c38cc; /* Grande Violet */
            --secondary-color: #1a237e; /* Dark Blue */
            --black: #121212;
            --white: #ffffff;
            --light-gray: #f0f0f0;
            --accent-color: #9575cd;
            --correct-color: #4caf50;
            --incorrect-color: #f44336;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, var(--black), var(--secondary-color));
            color: var(--white);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        header {
            background-color: var(--black);
            color: var(--white);
            padding: 15px 0;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }
        
        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .logo h1 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(45deg, #8e24aa, #3f51b5);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .container {
            max-width: 900px;
            width: 90%;
            margin: 30px auto;
            flex: 1;
        }
        
        .results-header {
            background-color: var(--primary-color);
            padding: 20px;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        
        .results-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .results-subtitle {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .results-body {
            background-color: var(--white);
            color: var(--black);
            padding: 30px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .score-summary {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
            background-color: var(--light-gray);
            padding: 20px;
            border-radius: 10px;
        }
        
        .score-item {
            text-align: center;
            min-width: 140px;
        }
        
        .score-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .score-value {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .score-value.accuracy {
            color: <?php echo $accuracy >= 70 ? 'var(--correct-color)' : ($accuracy >= 40 ? '#ff9800' : 'var(--incorrect-color)'); ?>;
        }
        
        .congratulations {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: linear-gradient(135deg, #673ab7, #3f51b5);
            color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .answers-list {
            margin-top: 30px;
        }
        
        .answers-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
            padding-bottom: 10px;
        }
        
        .answer-item {
            background-color: var(--light-gray);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .question-text {
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        .answer-details {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .answer-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .answer-label {
            min-width: 120px;
            font-weight: 600;
        }
        
        .answer-value {
            flex: 1;
        }
        
        .correct {
            color: var(--correct-color);
            font-weight: 600;
        }
        
        .incorrect {
            color: var(--incorrect-color);
            font-weight: 600;
        }
        
        .buttons {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            gap: 15px;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: #5a30a7;
        }
        
        .btn-secondary {
            background-color: var(--light-gray);
            color: var(--black);
        }
        
        .btn-secondary:hover {
            background-color: #e0e0e0;
        }
        
        .emoji-icon {
            font-size: 24px;
            margin-right: 10px;
        }
        
        footer {
            text-align: center;
            padding: 20px 0;
            background-color: var(--black);
            color: var(--white);
            margin-top: auto;
        }
        
        /* Results chart */
        .results-chart {
            margin: 30px auto;
            display: flex;
            height: 20px;
            width: 100%;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .chart-correct {
            background-color: var(--correct-color);
            height: 100%;
            transition: width 1s ease-in-out;
        }
        
        .chart-incorrect {
            background-color: var(--incorrect-color);
            height: 100%;
            transition: width 1s ease-in-out;
        }
        
        .chart-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
        
        .chart-label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }
        
        .chart-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .chart-dot.correct {
            background-color: var(--correct-color);
        }
        
        .chart-dot.incorrect {
            background-color: var(--incorrect-color);
        }
        
        /* Responsive styles */
        @media screen and (max-width: 768px) {
            .container {
                width: 95%;
            }
            
            .score-summary {
                flex-direction: column;
                align-items: center;
                gap: 15px;
            }
            
            .score-item {
                width: 100%;
            }
            
            .answer-row {
                flex-direction: column;
            }
            
            .answer-label {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <span class="emoji-icon">🧠</span>
            <h1>Flash Quiz App</h1>
        </div>
    </header>
    
    <div class="container">
        <div class="results-header">
            <div class="results-title">Quiz Results</div>
            <div class="results-subtitle"><?php echo htmlspecialchars($project_data['project_name']); ?></div>
        </div>
        
        <div class="results-body">
            <div class="score-summary">
                <div class="score-item">
                    <div class="score-label">Score</div>
                    <div class="score-value"><?php echo $attempt_data['score']; ?> pts</div>
                </div>
                
                <div class="score-item">
                    <div class="score-label">Accuracy</div>
                    <div class="score-value accuracy"><?php echo round($accuracy, 1); ?>%</div>
                </div>
                
                <div class="score-item">
                    <div class="score-label">Time Taken</div>
                    <div class="score-value"><?php echo $time_taken; ?></div>
                </div>
                
                <?php if (!$is_guest): ?>
                <div class="score-item">
                    <div class="score-label">Leaderboard Position</div>
                    <div class="score-value">#<?php echo $position; ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($accuracy >= 70): ?>
            <div class="congratulations">
                <h2><i class="fas fa-trophy"></i> Congratulations!</h2>
                <p>You've done an excellent job on this quiz!</p>
            </div>
            <?php elseif ($accuracy >= 40): ?>
            <div class="congratulations" style="background: linear-gradient(135deg, #ff9800, #ff5722);">
                <h2><i class="fas fa-star-half-alt"></i> Good effort!</h2>
                <p>You're making progress. Keep practicing to improve your score!</p>
            </div>
            <?php else: ?>
            <div class="congratulations" style="background: linear-gradient(135deg, #7986cb, #3949ab);">
                <h2><i class="fas fa-book"></i> Keep learning!</h2>
                <p>Review the material and try again to improve your understanding.</p>
            </div>
            <?php endif; ?>
            
            <!-- Results visualization -->
            <div class="results-chart">
                <div class="chart-correct" style="width: <?php echo $accuracy; ?>%;"></div>
                <div class="chart-incorrect" style="width: <?php echo 100 - $accuracy; ?>%;"></div>
            </div>
            
            <div class="chart-labels">
                <div class="chart-label">
                    <div class="chart-dot correct"></div>
                    <span>Correct (<?php echo $correct_answers; ?>)</span>
                </div>
                <div class="chart-label">
                    <div class="chart-dot incorrect"></div>
                    <span>Incorrect (<?php echo $incorrect_answers; ?>)</span>
                </div>
            </div>
            
            <div class="answers-list">
                <div class="answers-header">Question Review</div>
                
                <?php foreach ($user_answers as $index => $answer): 
                    $flashcard_id = isset($answer['flashcard_id']) ? $answer['flashcard_id'] : 0;
                    $question = isset($flashcards[$flashcard_id]['question']) ? $flashcards[$flashcard_id]['question'] : '';
                    $correct_answer = isset($flashcards[$flashcard_id]['answer']) ? $flashcards[$flashcard_id]['answer'] : '';
                    $user_answer = isset($answer['user_answer']) ? $answer['user_answer'] : '';
                    $is_correct = isset($answer['is_correct']) && $answer['is_correct'];
                    $question_type = isset($flashcards[$flashcard_id]['type_name']) ? $flashcards[$flashcard_id]['type_name'] : '';
                    
                    // Format user answer based on question type
                    if ($question_type == 'multiple_choice') {
                        $user_answer_text = getOptionText($flashcard_id, $user_answer, $flashcards);
                        $correct_answer_text = '';
                        
                        // Find correct option text
                        if (isset($flashcards[$flashcard_id]['options'])) {
                            foreach ($flashcards[$flashcard_id]['options'] as $option) {
                                if ($option['is_correct']) {
                                    $correct_answer_text = $option['option_text'];
                                    break;
                                }
                            }
                        }
                    } else if ($question_type == 'true_false') {
                        $user_answer_text = $user_answer;
                        $correct_answer_text = $correct_answer;
                    } else {
                        $user_answer_text = $user_answer;
                        $correct_answer_text = $correct_answer;
                    }
                ?>
                <div class="answer-item">
                    <div class="question-text">
                        <strong>Q<?php echo $index + 1; ?>:</strong> <?php echo htmlspecialchars($question); ?>
                    </div>
                    <div class="answer-details">
                        <div class="answer-row">
                            <div class="answer-label">Your Answer:</div>
                            <div class="answer-value <?php echo $is_correct ? 'correct' : 'incorrect'; ?>">
                                <?php echo $is_correct ? '<i class="fas fa-check-circle"></i> ' : '<i class="fas fa-times-circle"></i> '; ?>
                                <?php echo htmlspecialchars($user_answer_text); ?>
                            </div>
                        </div>
                        
                        <?php if (!$is_correct): ?>
                        <div class="answer-row">
                            <div class="answer-label">Correct Answer:</div>
                            <div class="answer-value correct">
                                <?php echo htmlspecialchars($correct_answer_text); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="buttons">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Back to Home
                </a>
                
                <a href="leaderboard.php?project_id=<?php echo $project_data['project_id']; ?>" class="btn btn-primary">
                    <i class="fas fa-trophy"></i> View Leaderboard
                </a>
                
                <a href="quiz.php?project_id=<?php echo $project_data['project_id']; ?>" class="btn btn-primary">
                    <i class="fas fa-redo"></i> Try Again
                </a>
            </div>
        </div>
    </div>
    
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Flash Quiz App. All rights reserved.</p>
    </footer>
    
    <script>
        // Animation for displaying results
        document.addEventListener('DOMContentLoaded', function() {
            // Animate the score value counting up
            const scoreValue = document.querySelector('.score-value');
            const finalScore = <?php echo $attempt_data['score']; ?>;
            let currentScore = 0;
            
            const scoreInterval = setInterval(() => {
                currentScore += Math.ceil(finalScore / 20);
                if (currentScore >= finalScore) {
                    scoreValue.textContent = finalScore + ' pts';
                    clearInterval(scoreInterval);
                } else {
                    scoreValue.textContent = currentScore + ' pts';
                }
            }, 50);
            
            // Clear localStorage answers when viewing results
            const keys = Object.keys(localStorage);
            keys.forEach(key => {
                if (key.startsWith('quiz_answer_')) {
                    localStorage.removeItem(key);
                }
            });
        });
    </script>
</body>
</html>