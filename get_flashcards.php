<?php
session_start();
require_once 'db_connect.php';
include 'session_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: #ef4444;'>Unauthorized access.</p>";
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_POST['project_id'])) {
    echo "<p style='color: #ef4444;'>Project ID not provided.</p>";
    exit();
}

$project_id = $_POST['project_id'];

// Verify project belongs to user
$verify_query = "SELECT project_id FROM projects WHERE project_id = ? AND user_id = ?";
$verify_stmt = $conn->prepare($verify_query);
$verify_stmt->bind_param("ii", $project_id, $user_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    echo "<p style='color: #ef4444;'>Project not found or unauthorized.</p>";
    exit();
}

// Get flashcards for this project
$flashcards_query = "SELECT f.*, qt.type_name 
                    FROM flashcards f 
                    JOIN question_types qt ON f.type_id = qt.type_id 
                    WHERE f.project_id = ? 
                    ORDER BY f.created_at ASC";
$flashcards_stmt = $conn->prepare($flashcards_query);
$flashcards_stmt->bind_param("i", $project_id);
$flashcards_stmt->execute();
$flashcards_result = $flashcards_stmt->get_result();

if ($flashcards_result->num_rows > 0) {
    while ($flashcard = $flashcards_result->fetch_assoc()) {
        // Get multiple choice options if applicable
        $options = [];
        if ($flashcard['type_name'] === 'multiple_choice') {
            $options_query = "SELECT * FROM multiple_choice_options 
                             WHERE flashcard_id = ? 
                             ORDER BY option_label";
            $options_stmt = $conn->prepare($options_query);
            $options_stmt->bind_param("i", $flashcard['flashcard_id']);
            $options_stmt->execute();
            $options_result = $options_stmt->get_result();
            
            while ($option = $options_result->fetch_assoc()) {
                $options[] = $option;
            }
        }
        
        echo '<div class="flashcard-item">';
        echo '<div class="flashcard-content">';
        echo '<div class="flashcard-question">❓ ' . htmlspecialchars($flashcard['question']) . '</div>';
        
        if ($flashcard['type_name'] === 'multiple_choice' && !empty($options)) {
            echo '<div class="flashcard-answer">📝 Options:</div>';
            echo '<ul style="margin-left: 20px; margin-bottom: 10px;">';
            foreach ($options as $option) {
                $icon = $option['is_correct'] ? '✅' : '⚪';
                echo '<li style="color: ' . ($option['is_correct'] ? '#06d6a0' : '#a8a8a8') . '; margin-bottom: 5px;">';
                echo $icon . ' ' . $option['option_label'] . ') ' . htmlspecialchars($option['option_text']);
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<div class="flashcard-answer">✅ ' . htmlspecialchars($flashcard['answer']) . '</div>';
        }
        
        echo '<div class="flashcard-type">🏷️ Type: ' . ucfirst(str_replace('_', ' ', $flashcard['type_name'])) . '</div>';
        echo '</div>';
        
        echo '<div class="project-actions">';
        echo '<button onclick="toggleEdit(' . $flashcard['flashcard_id'] . ')" class="btn btn-secondary">✏️ Edit</button>';
        echo '<button onclick="confirmDelete(\'flashcard\', ' . $flashcard['flashcard_id'] . ')" class="btn btn-danger">🗑️ Delete</button>';
        echo '</div>';
        
        // Edit form (initially hidden)
        echo '<div id="edit_' . $flashcard['flashcard_id'] . '" class="edit-form">';
        echo '<h4 style="margin-bottom: 15px; color: #8b5cf6;">✏️ Edit Flashcard</h4>';
        echo '<form method="POST" action="manage.php">';
        echo '<input type="hidden" name="flashcard_id" value="' . $flashcard['flashcard_id'] . '">';
        
        echo '<div class="form-group">';
        echo '<label>Question:</label>';
        echo '<textarea name="question" rows="3" style="width: 100%; padding: 10px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.1); color: white; border-radius: 8px; resize: vertical;">' . htmlspecialchars($flashcard['question']) . '</textarea>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<label>Answer:</label>';
        echo '<textarea name="answer" rows="3" style="width: 100%; padding: 10px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.1); color: white; border-radius: 8px; resize: vertical;">' . htmlspecialchars($flashcard['answer']) . '</textarea>';
        echo '</div>';
        
        echo '<div class="form-group">';
        echo '<label>Question Type:</label>';
        echo '<select name="type_id" style="width: 100%; padding: 10px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.1); color: white; border-radius: 8px;">';
        
        // Get question types for dropdown
        $types_query = "SELECT * FROM question_types";
        $types_result = $conn->query($types_query);
        while ($type = $types_result->fetch_assoc()) {
            $selected = ($type['type_id'] == $flashcard['type_id']) ? 'selected' : '';
            echo '<option value="' . $type['type_id'] . '" ' . $selected . '>' . ucfirst(str_replace('_', ' ', $type['type_name'])) . '</option>';
        }
        
        echo '</select>';
        echo '</div>';
        
        echo '<div class="project-actions">';
        echo '<button type="submit" name="edit_flashcard" class="btn btn-primary">💾 Save Changes</button>';
        echo '<button type="button" onclick="toggleEdit(' . $flashcard['flashcard_id'] . ')" class="btn btn-secondary">❌ Cancel</button>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>';
        
        echo '</div>';
    }
} else {
    echo '<div style="text-align: center; padding: 40px; color: #a8a8a8;">';
    echo '<div style="font-size: 3rem; margin-bottom: 20px;">📭</div>';
    echo '<div style="font-size: 1.2rem; margin-bottom: 15px;">No flashcards found in this project.</div>';
    echo '<div style="font-size: 1rem;">Start by adding some flashcards to your project!</div>';
    echo '<div style="margin-top: 20px;">';
    echo '<a href="create.php?project_id=' . $project_id . '" class="btn btn-primary">➕ Add Flashcards</a>';
    echo '</div>';
    echo '</div>';
}
?>