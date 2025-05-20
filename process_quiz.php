<?php
session_start();
require_once 'db_connect.php';
include 'db_connect.php';

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Check if user is logged in or in guest mode
if (!isset($_SESSION['user_id']) && !isset($_SESSION['guest_username'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Get user ID (0 for guest users)
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

// Get data from POST request
$attempt_id = isset($_POST['attempt_id']) ? intval($_POST['attempt_id']) : 0;
$score = isset($_POST['score']) ? intval($_POST['score']) : 0;
$time_taken = isset($_POST['time_taken']) ? intval($_POST['time_taken']) : 0;
$answers = isset($_POST['answers']) ? json_decode($_POST['answers'], true) : [];

// Validate attempt_id
if (!$attempt_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid attempt ID']);
    exit();
}

// Get attempt details to verify
$stmt = $conn->prepare("SELECT * FROM quiz_attempts WHERE attempt_id = ?");
$stmt->bind_param("i", $attempt_id);
$stmt->execute();
$result = $stmt->get_result();
$attempt = $result->fetch_assoc();

// Check if attempt exists and belongs to current user
if (!$attempt || ($attempt['user_id'] != $user_id && $user_id != 0)) {
    echo json_encode(['success' => false, 'message' => 'Invalid quiz attempt']);
    exit();
}

// Update attempt with end time, score, and mark as completed
$end_time = date('Y-m-d H:i:s');
$stmt = $conn->prepare("UPDATE quiz_attempts SET end_time = ?, score = ?, completed = TRUE WHERE attempt_id = ?");
$stmt->bind_param("sii", $end_time, $score, $attempt_id);
$stmt->execute();

// Process and store user answers
if (!empty($answers)) {
    foreach ($answers as $answer) {
        $flashcard_id = $answer['flashcardId'];
        $user_answer = $answer['answer'];
        $is_correct = $answer['isCorrect'] ? 1 : 0;
        
        $stmt = $conn->prepare("INSERT INTO user_answers (attempt_id, flashcard_id, user_answer, is_correct) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisi", $attempt_id, $flashcard_id, $user_answer, $is_correct);
        $stmt->execute();
    }
}

// Return success response
echo json_encode(['success' => true, 'message' => 'Quiz completed successfully']);