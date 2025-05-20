
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
$username = $_SESSION['username'] ?? $_SESSION['guest_username'] ?? 'Guest';

// Get project ID and attempt ID from URL
$project_id = $_GET['project_id'] ?? null;
$attempt_id = $_GET['attempt_id'] ?? null;

if (!$project_id) {
    header('Location: quiz.php');
    exit();
}

// Get project details
$query = "SELECT * FROM projects WHERE project_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$project = $result->fetch_assoc();
$stmt->close();

if (!$project) {
    header('Location: quiz.php');
    exit();
}

// Get quiz settings
$query = "SELECT * FROM quiz_settings WHERE project_id = ? ORDER BY quiz_id DESC LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$quiz_settings = $result->fetch_assoc();
$stmt->close();

// Get all flashcards for this project
$query = "
    SELECT f.*, qt.type_name,
           GROUP_CONCAT(
               CONCAT(mco.option_label, ':', mco.option_text, ':', mco.is_correct)
               ORDER BY mco.option_label SEPARATOR '|'
           ) as options
    FROM flashcards f
    JOIN question_types qt ON f.type_id = qt.type_id
    LEFT JOIN multiple_choice_options mco ON f.flashcard_id = mco.flashcard_id
    WHERE f.project_id = ?
    GROUP BY f.flashcard_id
    ORDER BY f.flashcard_id
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$flashcards = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($flashcards)) {
    header('Location: quiz.php?error=no_questions');
    exit();
}

// Process multiple choice options
foreach ($flashcards as &$card) {
    $card['multiple_choice_options'] = [];
    if ($card['options']) {
        $options = explode('|', $card['options']);
        foreach ($options as $option) {
            list($label, $text, $is_correct) = explode(':', $option);
            $card['multiple_choice_options'][] = [
                'label' => $label,
                'text' => $text,
                'is_correct' => $is_correct
            ];
        }
    }
}

$total_questions = count($flashcards);
$has_timer = $quiz_settings['has_timer'] ?? 0;
$time_limit = $quiz_settings['time_limit_minutes'] ?? 0;
$has_points = $quiz_settings['has_points'] ?? 1;
$points_per_question = $quiz_settings['points_per_question'] ?? 10;

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    $answers = $_POST['answers'] ?? [];
    $start_time = $_POST['start_time'] ?? null;
    $end_time = time();
    $elapsed_time = $start_time ? ($end_time - $start_time) / 60 : 0; // in minutes
    
    $score = 0;
    $correct_answers = 0;
    
    // Check if time limit exceeded
    $valid_submission = true;
    if ($has_timer && $time_limit && $elapsed_time > $time_limit) {
        $valid_submission = false;
    }
    
    // Calculate score
    foreach ($flashcards as $index => $card) {
        $user_answer = $answers[$index] ?? '';
        $is_correct = false;
        
        switch ($card['type_name']) {
            case 'identification':
                $is_correct = strtolower(trim($user_answer)) === strtolower(trim($card['answer']));
                break;
            case 'multiple_choice':
                // Find the correct option
                foreach ($card['multiple_choice_options'] as $option) {
                    if ($option['is_correct'] == 1 && $option['label'] === $user_answer) {
                        $is_correct = true;
                        break;
                    }
                }
                break;
            case 'true_false':
                $is_correct = strtolower($user_answer) === strtolower($card['answer']);
                break;
        }
        
        if ($is_correct) {
            $correct_answers++;
            if ($has_points) {
                $score += $points_per_question;
            }
        }
        
        // Save individual answer (only for registered users)
        if ($user_id && $attempt_id) {
            $query = "INSERT INTO user_answers (attempt_id, flashcard_id, user_answer, is_correct) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iisi", $attempt_id, $card['flashcard_id'], $user_answer, $is_correct);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Update quiz attempt
    if ($user_id && $attempt_id && $valid_submission) {
        $query = "UPDATE quiz_attempts SET end_time = NOW(), score = ?, completed = 1 WHERE attempt_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $score, $attempt_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Redirect to results
    $redirect_url = "quiz_results.php?project_id=" . $project_id . "&score=" . $score . "&correct=" . $correct_answers . "&total=" . $total_questions;
    if (!$valid_submission) {
        $redirect_url .= "&invalid=1";
    }
    header("Location: " . $redirect_url);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taking Quiz: <?php echo htmlspecialchars($project['project_name']); ?> - Flash Quiz App</title>
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
            max-width: 900px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .quiz-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .project-title {
            font-size: 2rem;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #8a2be2, #4169e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .quiz-info {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .info-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 20px;
            border-radius: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .timer-container {
            background: linear-gradient(45deg, #8a2be2, #4169e1);
            color: white;
            padding: 15px 30px;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: bold;
            margin: 20px auto;
            max-width: 300px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.3);
        }

        .timer-warning {
            animation: pulse 1s infinite;
            background: linear-gradient(45deg, #ef4444, #dc2626);
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .nav-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .nav-btn:hover:not(:disabled) {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .question-counter {
            font-size: 1.1rem;
            font-weight: bold;
        }

        .question-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .question-type {
            display: inline-block;
            background: linear-gradient(45deg, #8a2be2, #4169e1);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .question-text {
            font-size: 1.3rem;
            margin-bottom: 25px;
            line-height: 1.6;
            color: white;
        }

        .answer-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            padding: 15px;
            color: white;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .answer-input:focus {
            outline: none;
            border-color: #8a2be2;
            box-shadow: 0 0 15px rgba(138, 43, 226, 0.3);
        }

        .answer-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .options-container {
            display: grid;
            gap: 15px;
        }

        .option {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .option:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(138, 43, 226, 0.5);
        }

        .option.selected {
            background: rgba(138, 43, 226, 0.2);
            border-color: #8a2be2;
            box-shadow: 0 0 15px rgba(138, 43, 226, 0.3);
        }

        .option-label {
            background: linear-gradient(45deg, #8a2be2, #4169e1);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .option-text {
            flex: 1;
            font-size: 1.1rem;
        }

        .submit-section {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            margin-top: 30px;
            border: 2px dashed rgba(255, 255, 255, 0.2);
        }

        .submit-btn {
            background: linear-gradient(45deg, #8a2be2, #4169e1);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(138, 43, 226, 0.3);
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(138, 43, 226, 0.4);
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(45deg, #8a2be2, #4169e1);
            transition: width 0.3s ease;
        }

        .quit-btn {
            background: linear-gradient(45deg, #ef4444, #dc2626);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        }

        @media (max-width: 768px) {
            .quiz-info {
                flex-direction: column;
                align-items: center;
                gap: 15px;
            }
            
            .navigation {
                flex-direction: column;
                gap: 15px;
            }
            
            .question-text {
                font-size: 1.1rem;
            }
            
            .options-container {
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="quiz-header">
            <h1 class="project-title">🧠 <?php echo htmlspecialchars($project['project_name']); ?></h1>
            <div class="quiz-info">
                <div class="info-item">
                    <span>📝 <?php echo $total_questions; ?> Questions</span>
                </div>
                <?php if ($has_points): ?>
                <div class="info-item">
                    <span>🏆 <?php echo $points_per_question; ?> Points per Question</span>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <span>👤 <?php echo htmlspecialchars($username); ?></span>
                </div>
            </div>
            
            <?php if ($has_timer && $time_limit): ?>
            <div class="timer-container" id="timer">
                <span>⏱️ Time Remaining: <span id="timer-display"><?php echo $time_limit; ?>:00</span></span>
            </div>
            <?php endif; ?>
        </div>

        <form method="POST" id="quiz-form">
            <input type="hidden" name="start_time" value="<?php echo time(); ?>">
            
            <div class="progress-bar">
                <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
            </div>
            
            <div class="navigation">
                <button type="button" class="nav-btn" id="prev-btn" onclick="previousQuestion()" disabled>
                    ⬅️ Previous
                </button>
                <div class="question-counter">
                    Question <span id="current-question">1</span> of <?php echo $total_questions; ?>
                </div>
                <button type="button" class="nav-btn" id="next-btn" onclick="nextQuestion()">
                    Next ➡️
                </button>
            </div>

            <div id="questions-container">
                <?php foreach ($flashcards as $index => $card): ?>
                <div class="question-card" id="question-<?php echo $index; ?>" style="<?php echo $index === 0 ? '' : 'display: none;'; ?>">
                    <div class="question-type">
                        <?php
                        $type_icons = [
                            'identification' => '✏️ Identification',
                            'multiple_choice' => '🔘 Multiple Choice',
                            'true_false' => '✅ True/False'
                        ];
                        echo $type_icons[$card['type_name']] ?? $card['type_name'];
                        ?>
                    </div>
                    
                    <div class="question-text">
                        <?php echo nl2br(htmlspecialchars($card['question'])); ?>
                    </div>
                    
                    <?php if ($card['type_name'] === 'identification'): ?>
                        <input type="text" 
                               name="answers[<?php echo $index; ?>]" 
                               class="answer-input" 
                               placeholder="Type your answer here..." 
                               autocomplete="off">
                    
                    <?php elseif ($card['type_name'] === 'multiple_choice'): ?>
                        <div class="options-container">
                            <?php foreach ($card['multiple_choice_options'] as $option): ?>
                            <div class="option" onclick="selectOption(<?php echo $index; ?>, '<?php echo $option['label']; ?>')">
                                <div class="option-label"><?php echo $option['label']; ?></div>
                                <div class="option-text"><?php echo htmlspecialchars($option['text']); ?></div>
                                <input type="radio" 
                                       name="answers[<?php echo $index; ?>]" 
                                       value="<?php echo $option['label']; ?>" 
                                       style="display: none;">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    
                    <?php elseif ($card['type_name'] === 'true_false'): ?>
                        <div class="options-container">
                            <div class="option" onclick="selectOption(<?php echo $index; ?>, 'True')">
                                <div class="option-label">✅</div>
                                <div class="option-text">True</div>
                                <input type="radio" 
                                       name="answers[<?php echo $index; ?>]" 
                                       value="True" 
                                       style="display: none;">
                            </div>
                            <div class="option" onclick="selectOption(<?php echo $index; ?>, 'False')">
                                <div class="option-label">❌</div>
                                <div class="option-text">False</div>
                                <input type="radio" 
                                       name="answers[<?php echo $index; ?>]" 
                                       value="False" 
                                       style="display: none;">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="submit-section" id="submit-section" style="display: none;">
                <h3>🏁 Ready to Submit?</h3>
                <p style="margin: 15px 0; color: rgba(255, 255, 255, 0.7);">
                    Review your answers carefully. Once submitted, you cannot change them.
                </p>
                <button type="submit" name="submit_quiz" class="submit-btn">
                    🚀 Submit Quiz
                </button>
                <button type="button" class="quit-btn" onclick="confirmQuit()" style="margin-left: 20px;">
                    🚪 Quit Quiz
                </button>
            </div>
        </form>
    </div>

    <script>
        let currentQuestion = 0;
        const totalQuestions = <?php echo $total_questions; ?>;
        const hasTimer = <?php echo $has_timer ? 'true' : 'false'; ?>;
        const timeLimit = <?php echo $time_limit * 60; ?>; // Convert to seconds
        let timeRemaining = timeLimit;
        let timerInterval;

        // Start timer if enabled
        if (hasTimer && timeLimit > 0) {
            timerInterval = setInterval(updateTimer, 1000);
        }

        function updateTimer() {
            timeRemaining--;
            
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            const display = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            document.getElementById('timer-display').textContent = display;
            
            // Warning when 5 minutes left
            if (timeRemaining <= 300) {
                document.getElementById('timer').classList.add('timer-warning');
            }
            
            // Auto-submit when time's up
            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                alert('⏰ Time\'s up! Submitting quiz automatically...');
                document.getElementById('quiz-form').submit();
            }
        }

        function updateNavigation() {
            const prevBtn = document.getElementById('prev-btn');
            const nextBtn = document.getElementById('next-btn');
            const submitSection = document.getElementById('submit-section');
            const currentQuestionSpan = document.getElementById('current-question');
            const progressFill = document.getElementById('progress-fill');
            
            // Update question number
            currentQuestionSpan.textContent = currentQuestion + 1;
            
            // Update progress bar
            const progress = ((currentQuestion + 1) / totalQuestions) * 100;
            progressFill.style.width = progress + '%';
            
            // Update navigation buttons
            prevBtn.disabled = currentQuestion === 0;
            
            if (currentQuestion === totalQuestions - 1) {
                nextBtn.style.display = 'none';
                submitSection.style.display = 'block';
            } else {
                nextBtn.style.display = 'inline-block';
                submitSection.style.display = 'none';
            }
        }

        function showQuestion(index) {
            // Hide all questions
            for (let i = 0; i < totalQuestions; i++) {
                document.getElementById(`question-${i}`).style.display = 'none';
            }
            
            // Show current question
            document.getElementById(`question-${index}`).style.display = 'block';
            
            updateNavigation();
        }

        function nextQuestion() {
            if (currentQuestion < totalQuestions - 1) {
                currentQuestion++;
                showQuestion(currentQuestion);
            }
        }

        function previousQuestion() {
            if (currentQuestion > 0) {
                currentQuestion--;
                showQuestion(currentQuestion);
            }
        }

        function selectOption(questionIndex, value) {
            const options = document.querySelectorAll(`#question-${questionIndex} .option`);
            const radioInput = document.querySelector(`input[name="answers[${questionIndex}]"][value="${value}"]`);
            
            // Remove selected class from all options
            options.forEach(option => option.classList.remove('selected'));
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            radioInput.checked = true;
        }

        function confirmQuit() {
            if (confirm('⚠️ Are you sure you want to quit? Your progress will be lost.')) {
                window.location.href = 'quiz.php';
            }
        }

        // Prevent accidental page refresh
        window.addEventListener('beforeunload', function(e) {
            e.preventDefault();
            e.returnValue = '';
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft' && currentQuestion > 0) {
                previousQuestion();
            } else if (e.key === 'ArrowRight' && currentQuestion < totalQuestions - 1) {
                nextQuestion();
            }
        });

        // Initialize
        updateNavigation();
    </script>
</body>
</html>