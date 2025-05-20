<?php
include 'session_helper.php';
include 'db_connect.php';

SessionHelper::startSession();

if (!SessionHelper::isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = SessionHelper::getUserId();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_project':
                $project_name = trim($_POST['project_name']);
                if (empty($project_name)) {
                    $error = 'Project name is required';
                } else {
                    // Generate unique project code
                    do {
                        $project_code = strtoupper(substr(md5(uniqid()), 0, 8));
                        $check_code = $conn->prepare("SELECT COUNT(*) FROM projects WHERE project_code = ?");
                        $check_code->bind_param("s", $project_code);
                        $check_code->execute();
                        $code_exists = $check_code->get_result()->fetch_row()[0] > 0;
                    } while ($code_exists);
                    
                    $stmt = $conn->prepare("INSERT INTO projects (user_id, project_name, project_code) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $user_id, $project_name, $project_code);
                    
                    if ($stmt->execute()) {
                        $project_id = $conn->insert_id;
                        $message = "Project '$project_name' created successfully! Project Code: $project_code";
                        $_SESSION['current_project_id'] = $project_id;
                    } else {
                        $error = 'Failed to create project';
                    }
                }
                break;
                
            case 'select_project':
                $_SESSION['current_project_id'] = $_POST['project_id'];
                $message = 'Project selected successfully!';
                break;
                
            case 'add_flashcard':
                if (!isset($_SESSION['current_project_id'])) {
                    $error = 'Please select a project first';
                    break;
                }
                
                $project_id = $_SESSION['current_project_id'];
                $question = trim($_POST['question']);
                $type_id = $_POST['type_id'];
                
                if (empty($question)) {
                    $error = 'Question is required';
                    break;
                }

                if ($type_id == 2) { // Multiple choice
                    // Validate multiple choice
                    if (!isset($_POST['options']) || !is_array($_POST['options']) || empty($_POST['correct_option'])) {
                        $error = 'All options and a correct answer are required for multiple choice.';
                        break;
                    }

                    $options = array_filter(array_map('trim', $_POST['options']));
                    if (count($options) < 2) {
                        $error = 'At least two options are required.';
                        break;
                    }
                    $answer = $_POST['correct_option']; // Store correct option label as answer
                } else {
                    // Validate for other types
                    $answer = trim($_POST['answer']);
                    if (empty($answer)) {
                        $error = 'Answer is required';
                        break;
                    }
                }
                
                $stmt = $conn->prepare("INSERT INTO flashcards (project_id, question, answer, type_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("issi", $project_id, $question, $answer, $type_id);
                
                if ($stmt->execute()) {
                    $flashcard_id = $conn->insert_id;
                    
                    // Handle multiple choice options
                    if ($type_id == 2 && isset($_POST['options'])) {
                        $options = $_POST['options'];
                        $correct_option = $_POST['correct_option'];
                        $option_labels = ['A', 'B', 'C', 'D', 'E'];
                        
                        for ($i = 0; $i < count($options); $i++) {
                            if (!empty(trim($options[$i]))) {
                                $is_correct = ($option_labels[$i] == $correct_option);
                                $stmt_option = $conn->prepare("INSERT INTO multiple_choice_options (flashcard_id, option_text, is_correct, option_label) VALUES (?, ?, ?, ?)");
                                $stmt_option->bind_param("isis", $flashcard_id, $options[$i], $is_correct, $option_labels[$i]);
                                $stmt_option->execute();
                            }
                        }
                    }
                    
                    $message = 'Flashcard added successfully!';
                } else {
                    $error = 'Failed to add flashcard';
                }
                break;
        }
    }
}

// Get user's projects
$projects_stmt = $conn->prepare("SELECT project_id, project_name, project_code FROM projects WHERE user_id = ? ORDER BY created_at DESC");
$projects_stmt->bind_param("i", $user_id);
$projects_stmt->execute();
$projects = $projects_stmt->get_result();

// Get question types
$types_stmt = $conn->query("SELECT type_id, type_name, description FROM question_types");
$question_types = $types_stmt->fetch_all(MYSQLI_ASSOC);

// Get current project info
$current_project = null;
if (isset($_SESSION['current_project_id'])) {
    $current_stmt = $conn->prepare("SELECT project_name, project_code FROM projects WHERE project_id = ? AND user_id = ?");
    $current_stmt->bind_param("ii", $_SESSION['current_project_id'], $user_id);
    $current_stmt->execute();
    $current_project = $current_stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Flash Cards - Flash Quiz App</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --dark-blue: #1a1b41;
            --grande-violet: #6c25be;
            --light-violet: #8b5cf6;
            --very-light-violet: #ddd6fe;
            --dark-gray: #121212;
            --light-gray: #e0e0e0;
            --white: #ffffff;
            --success: #10b981;
            --error: #ef4444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--dark-gray);
            color: var(--white);
            line-height: 1.6;
        }

        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: linear-gradient(135deg, var(--grande-violet), var(--dark-blue));
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            color: var(--white);
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .header p {
            color: var(--very-light-violet);
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


        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .success {
            background-color: rgba(16, 185, 129, 0.2);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .error {
            background-color: rgba(239, 68, 68, 0.2);
            color: var(--error);
            border-left: 4px solid var(--error);
        }

        .card {
            background-color: var(--dark-blue);
            border-radius: 12px;
            margin-bottom: 2rem;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(108, 37, 190, 0.3);
        }

        .card-header {
            border-bottom: 2px solid var(--grande-violet);
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .card-header h3 {
            margin: 0;
            color: var(--white);
            font-size: 1.5rem;
        }

        .card-header i {
            color: var(--light-violet);
            margin-right: 10px;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--very-light-violet);
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(108, 37, 190, 0.3);
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--white);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--light-violet);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.3);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%238b5cf6' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 40px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--grande-violet), var(--light-violet));
            color: var(--white);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--light-violet), var(--grande-violet));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 37, 190, 0.4);
        }

        .btn-secondary {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--very-light-violet);
        }

        .btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .current-project {
            background-color: rgba(108, 37, 190, 0.2);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid var(--grande-violet);
        }

        .current-project span {
            font-weight: bold;
            color: var(--light-violet);
        }

        .code-display {
            font-family: 'Courier New', monospace;
            background-color: rgba(0, 0, 0, 0.3);
            padding: 5px 10px;
            border-radius: 4px;
            margin-left: 5px;
        }

        .section-divider {
            height: 1px;
            background: linear-gradient(to right, transparent, var(--grande-violet), transparent);
            margin: 2rem 0;
        }

        .grid-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
        }

        .option-input {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }

        .option-input label {
            flex: 0 0 80px;
            margin-bottom: 0;
        }

        .option-letter {
            background-color: var(--grande-violet);
            color: var(--white);
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-right: 10px;
            font-weight: bold;
        }

        .options-container {
            display: none;
            background-color: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border: 1px solid rgba(108, 37, 190, 0.3);
        }

        .btn-group {
            display: flex;
            justify-content: flex-start;
            flex-wrap: wrap;
        }

        .project-status {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .status-badge {
            background-color: var(--grande-violet);
            color: var(--white);
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .flash-count {
            color: var(--light-violet);
            font-weight: bold;
        }

        .page-subtitle {
            color: var(--very-light-violet);
            text-align: center;
            margin-bottom: 2rem;
            font-size: 1.2rem;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card {
            animation: fadeIn 0.5s ease-out;
        }

        .tooltip {
            position: relative;
            display: inline-block;
            cursor: help;
        }

        .tooltip .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: var(--dark-gray);
            color: var(--white);
            text-align: center;
            border-radius: 6px;
            padding: 10px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--grande-violet);
            font-size: 0.9rem;
        }

        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-book-open"></i> Create Flash Cards</h1>
            <p>Design and build your quiz collection</p>
        </div>

        <!-- Navigation Bar -->
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
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-folder-plus"></i>
                <h3>Project Management</h3>
            </div>
            
            <?php if ($current_project): ?>
                <div class="current-project">
                    <div class="project-status">
                        <div>
                            <i class="fas fa-project-diagram"></i> Current Project:
                            <span><?php echo htmlspecialchars($current_project['project_name']); ?></span>
                        </div>
                        <div class="status-badge">Active</div>
                    </div>
                    <div>
                        <i class="fas fa-key"></i> Project Code: 
                        <code class="code-display"><?php echo htmlspecialchars($current_project['project_code']); ?></code>
                        <span class="tooltip">
                            <i class="fas fa-info-circle"></i>
                            <span class="tooltip-text">Use this code to share your project with others</span>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="grid-container">
                <!-- Create New Project -->
                <div>
                    <h4><i class="fas fa-plus-circle"></i> Create New Project</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_project">
                        <div class="form-group">
                            <label for="project_name">Project Name:</label>
                            <input type="text" id="project_name" name="project_name" required placeholder="Enter project name...">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Project
                        </button>
                    </form>
                </div>
                
                <!-- Select Existing Project -->
                <div>
                    <h4><i class="fas fa-list"></i> Select Existing Project</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="select_project">
                        <div class="form-group">
                            <label for="project_id">Choose Project:</label>
                            <select name="project_id" id="project_id" required>
                                <option value="">-- Select a Project --</option>
                                <?php while ($project = $projects->fetch_assoc()): ?>
                                    <option value="<?php echo $project['project_id']; ?>" 
                                            <?php echo (isset($_SESSION['current_project_id']) && $_SESSION['current_project_id'] == $project['project_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($project['project_name']); ?> (<?php echo $project['project_code']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check-circle"></i> Select Project
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Add Flashcard Section -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-layer-group"></i>
                <h3>Create Flashcards</h3>
            </div>
            
            <?php if (!isset($_SESSION['current_project_id'])): ?>
                <div class="message error">
                    <i class="fas fa-info-circle"></i> Please select or create a project first to add flashcards.
                </div>
            <?php else: ?>
                <form method="POST" id="flashcardForm">
                    <input type="hidden" name="action" value="add_flashcard">
                    
                    <div class="form-group">
                        <label for="question">
                            <i class="fas fa-question-circle"></i> Question:
                        </label>
                        <textarea id="question" name="question" required placeholder="Enter your question here..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="type_id">
                            <i class="fas fa-list-ol"></i> Question Type:
                        </label>
                        <select name="type_id" id="type_id" required onchange="toggleOptions()">
                            <option value="">-- Select Question Type --</option>
                            <?php foreach ($question_types as $type): ?>
                                <option value="<?php echo $type['type_id']; ?>">
                                    <?php echo ucfirst($type['type_name']); ?> - <?php echo $type['description']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- For Identification and True/False -->
                    <div class="form-group" id="answerGroup">
                        <label for="answer">
                            <i class="fas fa-check-circle"></i> Answer:
                        </label>
                        <textarea id="answer" name="answer" placeholder="Enter the correct answer..."></textarea>
                    </div>
                    
                    <!-- For Multiple Choice -->
                    <div class="options-container" id="optionsContainer">
                        <h4><i class="fas fa-list-ul"></i> Multiple Choice Options</h4>
                        <div class="option-input">
                            <div class="option-letter">A</div>
                            <input type="text" name="options[]" placeholder="Enter option A">
                        </div>
                        <div class="option-input">
                            <div class="option-letter">B</div>
                            <input type="text" name="options[]" placeholder="Enter option B">
                        </div>
                        <div class="option-input">
                            <div class="option-letter">C</div>
                            <input type="text" name="options[]" placeholder="Enter option C">
                        </div>
                        <div class="option-input">
                            <div class="option-letter">D</div>
                            <input type="text" name="options[]" placeholder="Enter option D">
                        </div>
                        <div class="option-input">
                            <div class="option-letter">E</div>
                            <input type="text" name="options[]" placeholder="Enter option E">
                        </div>
                        <div class="form-group">
                            <label for="correct_option">
                                <i class="fas fa-star"></i> Correct Answer:
                            </label>
                            <select name="correct_option" id="correct_option">
                                <option value="">-- Select Correct Option --</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                                <option value="E">E</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Add Flashcard
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                            <i class="fas fa-sync"></i> Clear Form
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleOptions() {
            const typeSelect = document.getElementById('type_id');
            const optionsContainer = document.getElementById('optionsContainer');
            const answerGroup = document.getElementById('answerGroup');
            
            if (typeSelect.value == '2') { // Multiple choice
                optionsContainer.style.display = 'block';
                answerGroup.style.display = 'none';
                document.getElementById('answer').required = false;
            } else {
                optionsContainer.style.display = 'none';
                answerGroup.style.display = 'block';
                document.getElementById('answer').required = true;
            }
        }
        
        function resetForm() {
            document.getElementById('flashcardForm').reset();
            toggleOptions();
        }
        
        // Handle True/False selection
        document.getElementById('type_id').addEventListener('change', function() {
            if (this.value == '3') { // True/False
                const answerField = document.getElementById('answer');
                answerField.innerHTML = '';
                const select = document.createElement('select');
                select.name = 'answer';
                select.id = 'answer';
                select.required = true;
                select.className = answerField.className;
                select.innerHTML = '<option value="">-- Select Answer --</option><option value="True">True</option><option value="False">False</option>';
                answerField.parentNode.replaceChild(select, answerField);
            } else if (this.value != '2') {
                // For identification, restore textarea
                const currentAnswer = document.querySelector('[name="answer"]');
                if (currentAnswer.tagName === 'SELECT') {
                    const textarea = document.createElement('textarea');
                    textarea.id = 'answer';
                    textarea.name = 'answer';
                    textarea.placeholder = 'Enter the correct answer...';
                    textarea.required = true;
                    textarea.className = currentAnswer.className;
                    currentAnswer.parentNode.replaceChild(textarea, currentAnswer);
                }
            }
        });
    </script>
</body>
</html>