<?php
// Include session management and database connection
include 'session_helper.php';
include 'db_connect.php';

// Ensure session is started
SessionHelper::startSession();

// Redirect to dashboard if not logged in
if (!SessionHelper::isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// Get user ID from session
$user_id = SessionHelper::getUserId();

$error_message = "";
$success_message = "";
$generated_content = "";
$used_prompt = "";

// Process save request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_content'])) {
    $name = trim($_POST['content_name']);
    $content = $_POST['content_to_save'];
    $prompt = $_POST['original_prompt'];

    if (empty($name)) {
        $error_message = "Please enter a name for the content.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO ai_generated_content (user_id, prompt, generated_content, content_name, is_saved) VALUES (?, ?, ?, ?, TRUE)");
            $stmt->bind_param("isss", $user_id, $prompt, $content, $name);
            $stmt->execute();
            $success_message = "Content saved successfully as '$name'!";
        } catch (Exception $e) {
            $error_message = "Database Error: " . $e->getMessage();
        }
    }
}

// Process AI content generation request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['generate'])) {
    $prompt = trim($_POST['prompt']);
    $used_prompt = $prompt;

    if (empty($prompt)) {
        $error_message = "Please enter a prompt for the AI.";
    } else {
        // Initialize cURL for DeepAI API
        $ch = curl_init();
        $api_key = 'f667fce9-a14b-4a60-92bc-938267731016'; // Replace with your actual DeepAI API key
        
        // Set the API endpoint for text generation
        $url = 'https://api.deepai.org/api/text-generator';
        
        // Enhance the prompt for better quiz generation
        $enhanced_prompt = "Create a set of 5 quiz questions with clear answers based on the following topic. Format each question with its answer clearly. Topic: " . $prompt;
        
        // Set cURL options for DeepAI
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Api-Key: $api_key"
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
            'text' => $enhanced_prompt
        ));

        // Execute the request
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $error_message = "API Error: " . $err;
        } else {
            // Decode the JSON response
            $result = json_decode($response, true);
            
            if (isset($result['output'])) {
                $generated_content = $result['output'];
            } else {
                $error_message = "Error in response: " . $response;
            }
        }
    }
}

// Retrieve saved AI content
$saved_content = [];
try {
    $stmt = $conn->prepare("SELECT content_id, prompt, generated_content, content_name, created_at FROM ai_generated_content WHERE user_id = ? AND is_saved = TRUE ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $saved_content[] = $row;
    }
} catch (Exception $e) {
    $error_message = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Quiz Generator - Flash Quiz App</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --dark-blue: #1a1a2e;
            --violet: #6c1dd8;
            --light-violet: #9058eb;
            --black: #121212;
            --white: #f5f5f5;
            --light-gray: #e0e0e0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--dark-blue), var(--black));
            margin: 0;
            padding: 0;
            color: var(--white);
            min-height: 100vh;
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background-color: rgba(0, 0, 0, 0.5);
            padding: 15px 0;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--violet);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: var(--white);
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
            color: var(--light-violet);
        }
        
        nav ul {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        nav ul li {
            margin-left: 20px;
        }
        
        nav ul li a {
            color: var(--white);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        nav ul li a:hover {
            color: var(--light-violet);
        }
        
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .ai-generator {
            background-color: rgba(0, 0, 0, 0.5);
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(108, 29, 216, 0.2);
            border: 1px solid rgba(108, 29, 216, 0.3);
        }
        
        h1 {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--light-violet);
            text-shadow: 0 0 5px rgba(108, 29, 216, 0.5);
        }
        
        form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        label {
            font-size: 16px;
            font-weight: 500;
        }
        
        textarea, input[type="text"] {
            padding: 15px;
            border-radius: 5px;
            border: 1px solid var(--light-violet);
            background-color: rgba(0, 0, 0, 0.5);
            color: var(--white);
            font-size: 16px;
            outline: none;
            transition: border-color 0.3s;
        }
        
        textarea {
            height: 120px;
            resize: vertical;
        }
        
        textarea:focus, input[type="text"]:focus {
            border-color: var(--violet);
            box-shadow: 0 0 10px rgba(108, 29, 216, 0.3);
        }
        
        button {
            background: linear-gradient(135deg, var(--violet), var(--light-violet));
            color: var(--white);
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 29, 216, 0.4);
        }
        
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .secondary-button {
            background: transparent;
            border: 2px solid var(--light-violet);
        }
        
        .generated-content {
            margin-top: 30px;
            background-color: rgba(0, 0, 0, 0.5);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid rgba(108, 29, 216, 0.3);
        }
        
        .generated-text {
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
            line-height: 1.6;
            margin-bottom: 20px;
            background-color: rgba(0, 0, 0, 0.3);
            padding: 15px;
            border-radius: 5px;
        }
        
        .save-section {
            background-color: rgba(0, 0, 0, 0.3);
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            border: 1px solid var(--light-violet);
        }
        
        .save-section h3 {
            margin-top: 0;
            color: var(--light-violet);
        }
        
        .saved-content {
            background-color: rgba(0, 0, 0, 0.5);
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(108, 29, 216, 0.2);
            border: 1px solid rgba(108, 29, 216, 0.3);
        }
        
        .saved-item {
            background-color: rgba(0, 0, 0, 0.3);
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 3px solid var(--light-violet);
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .saved-item:hover {
            transform: translateX(5px);
        }
        
        .saved-item h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: var(--light-violet);
        }
        
        .saved-item p {
            font-size: 14px;
            color: var(--light-gray);
            margin-bottom: 10px;
        }
        
        .saved-item-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
        
        .saved-item-actions button {
            background: transparent;
            border: none;
            color: var(--light-violet);
            padding: 5px;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .saved-item-actions button:hover {
            color: var(--white);
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .error {
            background-color: rgba(255, 0, 0, 0.1);
            border-left: 3px solid #ff0000;
        }
        
        .success {
            background-color: rgba(0, 255, 0, 0.1);
            border-left: 3px solid #00ff00;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: var(--dark-blue);
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            border: 1px solid var(--light-violet);
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            color: var(--light-violet);
            cursor: pointer;
        }
        
        #modalContent {
            white-space: pre-wrap;
        }
        
        @media (max-width: 768px) {
            .container {
                width: 95%;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            nav ul {
                flex-direction: column;
                gap: 10px;
            }
            
            nav ul li {
                margin-left: 0;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
        }
        
        /* Loader/Spinner for API call */
        .loader {
            display: none;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid var(--light-violet);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Floating AI Indicator */
        .ai-indicator {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, var(--violet), var(--light-violet));
            color: var(--white);
            padding: 15px;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 24px;
            box-shadow: 0 5px 15px rgba(108, 29, 216, 0.5);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(108, 29, 216, 0.7); }
            70% { box-shadow: 0 0 0 15px rgba(108, 29, 216, 0); }
            100% { box-shadow: 0 0 0 0 rgba(108, 29, 216, 0); }
        }

        .copy-feedback {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: var(--violet);
            color: var(--white);
            padding: 15px 25px;
            border-radius: 5px;
            z-index: 1000;
            display: none;
            box-shadow: 0 5px 15px rgba(108, 29, 216, 0.5);
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-brain"></i> Flash Quiz AI
                </div>
                <nav>
                    <ul>
                        <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                        <li><a href="create.php"><i class="fas fa-plus-circle"></i> Create</a></li>
                        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="main-content">
            <?php if (!empty($error_message)): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <div class="ai-generator">
                <h1><i class="fas fa-robot"></i> AI Quiz Generator</h1>
                <p>Enter a topic or subject and our AI will generate quiz questions for you.</p>
                
                <form method="POST" action="" id="aiForm">
                    <div class="form-group">
                        <label for="prompt">What would you like to create a quiz about?</label>
                        <textarea name="prompt" id="prompt" placeholder="Example: Create a quiz about the solar system with 5 questions" required><?php echo htmlspecialchars($used_prompt); ?></textarea>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" name="generate" id="generateBtn">
                            <i class="fas fa-magic"></i> Generate Quiz
                        </button>
                        <button type="button" class="secondary-button" id="exampleBtn">
                            <i class="fas fa-lightbulb"></i> See Examples
                        </button>
                    </div>
                </form>
                
                <div class="loader" id="loader"></div>
                
                <?php if (!empty($generated_content)): ?>
                    <div class="generated-content">
                        <h2><i class="fas fa-file-alt"></i> Generated Quiz Content</h2>
                        <div class="generated-text" id="generatedText"><?php echo nl2br(htmlspecialchars($generated_content)); ?></div>
                        
                        <div class="button-group">
                            <button type="button" class="secondary-button" id="copyBtn">
                                <i class="fas fa-copy"></i> Copy to Clipboard
                            </button>
                            <button type="button" id="showSaveBtn">
                                <i class="fas fa-save"></i> Save to My Collection
                            </button>
                        </div>
                        
                        <div class="save-section" id="saveSection" style="display: none;">
                            <h3><i class="fas fa-save"></i> Save Content</h3>
                            <form method="POST" action="" id="saveForm">
                                <input type="hidden" name="content_to_save" value="<?php echo htmlspecialchars($generated_content); ?>">
                                <input type="hidden" name="original_prompt" value="<?php echo htmlspecialchars($used_prompt); ?>">
                                
                                <div class="form-group">
                                    <label for="content_name">Give this content a name:</label>
                                    <input type="text" name="content_name" id="content_name" placeholder="e.g., Solar System Quiz" required>
                                </div>
                                
                                <div class="button-group">
                                    <button type="submit" name="save_content">
                                        <i class="fas fa-save"></i> Save Content
                                    </button>
                                    <button type="button" class="secondary-button" id="cancelSaveBtn">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($saved_content)): ?>
                <div class="saved-content">
                    <h1><i class="fas fa-bookmark"></i> My Saved Quiz Content</h1>
                    <p>Click on any item to view the full content</p>
                    
                    <?php foreach ($saved_content as $content): ?>
                        <div class="saved-item" data-content="<?php echo htmlspecialchars($content['generated_content']); ?>">
                            <h3><?php echo htmlspecialchars($content['content_name'] ?? substr($content['prompt'], 0, 50) . (strlen($content['prompt']) > 50 ? '...' : '')); ?></h3>
                            <p><strong>Prompt:</strong> <?php echo htmlspecialchars(substr($content['prompt'], 0, 100) . (strlen($content['prompt']) > 100 ? '...' : '')); ?></p>
                            <p><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($content['created_at'])); ?></p>
                            <div class="saved-item-actions">
                                <button class="view-content"><i class="fas fa-eye"></i> View</button>
                                <button class="copy-content" data-content="<?php echo htmlspecialchars($content['generated_content']); ?>">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                                <button class="use-content" data-content-id="<?php echo $content['content_id']; ?>">
                                    <i class="fas fa-file-import"></i> Use in Create
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal for displaying saved content -->
    <div class="modal" id="contentModal">
        <div class="modal-content">
            <span class="close-modal" id="closeModal">&times;</span>
            <h2><i class="fas fa-file-alt"></i> Quiz Content</h2>
            <div id="modalContent"></div>
        </div>
    </div>
    
    <!-- Copy feedback -->
    <div class="copy-feedback" id="copyFeedback">
        <i class="fas fa-check"></i> Content copied to clipboard!
    </div>
    
    <!-- Floating AI Indicator -->
    <div class="ai-indicator">
        <i class="fas fa-robot"></i>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show loader when form is submitted
            const aiForm = document.getElementById('aiForm');
            const loader = document.getElementById('loader');
            const generateBtn = document.getElementById('generateBtn');
            
            if (aiForm) {
                aiForm.addEventListener('submit', function() {
                    loader.style.display = 'block';
                    generateBtn.disabled = true;
                    generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
                });
            }
            
            // Example button functionality
            const exampleBtn = document.getElementById('exampleBtn');
            const promptTextarea = document.getElementById('prompt');
            
            if (exampleBtn && promptTextarea) {
                exampleBtn.addEventListener('click', function() {
                    const examples = [
                        "Create a quiz about World War II with 5 multiple choice questions",
                        "Generate 5 questions about the human digestive system",
                        "Create a quiz about famous artists and their paintings",
                        "Make a quiz about basic JavaScript concepts for beginners",
                        "Generate 5 math problems about algebra with solutions",
                        "Create a quiz about renewable energy sources",
                        "Generate questions about ancient civilizations",
                        "Make a quiz about photography basics"
                    ];
                    
                    const randomExample = examples[Math.floor(Math.random() * examples.length)];
                    promptTextarea.value = randomExample;
                });
            }
            
            // Copy functionality
            function copyToClipboard(text) {
                navigator.clipboard.writeText(text).then(function() {
                    const feedback = document.getElementById('copyFeedback');
                    feedback.style.display = 'block';
                    setTimeout(function() {
                        feedback.style.display = 'none';
                    }, 2000);
                }).catch(function(err) {
                    alert('Failed to copy content to clipboard');
                });
            }
            
            // Copy button functionality for generated content
            const copyBtn = document.getElementById('copyBtn');
            const generatedText = document.getElementById('generatedText');
            
            if (copyBtn && generatedText) {
                copyBtn.addEventListener('click', function() {
                    const textToCopy = generatedText.innerText;
                    copyToClipboard(textToCopy);
                });
            }
            
            // Save section toggle
            const showSaveBtn = document.getElementById('showSaveBtn');
            const saveSection = document.getElementById('saveSection');
            const cancelSaveBtn = document.getElementById('cancelSaveBtn');
            
            if (showSaveBtn && saveSection) {
                showSaveBtn.addEventListener('click', function() {
                    saveSection.style.display = 'block';
                    showSaveBtn.style.display = 'none';
                });
            }
            
            if (cancelSaveBtn && saveSection && showSaveBtn) {
                cancelSaveBtn.addEventListener('click', function() {
                    saveSection.style.display = 'none';
                    showSaveBtn.style.display = 'inline-flex';
                    document.getElementById('content_name').value = '';
                });
            }
            
            // Modal functionality
            const modal = document.getElementById('contentModal');
            const closeModal = document.getElementById('closeModal');
            const modalContent = document.getElementById('modalContent');
            const viewButtons = document.querySelectorAll('.view-content');
            
            if (closeModal && modal) {
                closeModal.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
                
                window.addEventListener('click', function(event) {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            }
            
            if (viewButtons.length > 0 && modalContent && modal) {
                viewButtons.forEach(function(button) {
                    button.addEventListener('click', function() {
                        const content = this.closest('.saved-item').getAttribute('data-content');
                        modalContent.innerHTML = content.replace(/\n/g, '<br>');
                        modal.style.display = 'flex';
                    });
                });
            }
            
            // Copy saved content functionality
            const copyContentButtons = document.querySelectorAll('.copy-content');
            
            if (copyContentButtons.length > 0) {
                copyContentButtons.forEach(function(button) {
                    button.addEventListener('click', function() {
                        const content = this.getAttribute('data-content');
                        copyToClipboard(content);
                    });
                });
            }
            
            // Use content in Create functionality
            const useContentButtons = document.querySelectorAll('.use-content');
            
            if (useContentButtons.length > 0) {
                useContentButtons.forEach(function(button) {
                    button.addEventListener('click', function() {
                        const contentId = this.getAttribute('data-content-id');
                        sessionStorage.setItem('useAiContent', contentId);
                        window.location.href = 'create.php';
                    });
                });
            }
        });
    </script>
</body>
</html>