<?php
// Database connection
$host = "localhost"; // Update with your actual host
$username = "root"; // Update with your actual username
$password = "root"; // Update with your actual password
$database = "quiz";

// Create connection (without immediately using a DB)
$conn = new mysqli($host, $username, strlen($password) > 0 ? $password : null);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: $conn->connect_error");
}

// Create Database if not exists
if ($conn->multi_query("CREATE DATABASE IF NOT EXISTS `$database`; USE `$database`")) {
    exhaustMySqliQueries($conn);
}

// Initialize Database Structure using `structure.sql`
$tablecount_result = $conn->query("SELECT COUNT(*) AS tcount FROM information_schema.tables WHERE `table_schema` = '$database'");
if (!$tablecount_result) {
    error_log("Query error: $conn->error");
    die();
}
if ($tablecount_result->fetch_assoc()['tcount'] < 1) {
    $structure_sql_filepath = $_SERVER['DOCUMENT_ROOT'] . "/structure.sql";
    if (!file_exists($structure_sql_filepath)) {
        error_log("Structure file missing or access is denied.");
        die();
    }
    $structure_sql = file_get_contents($_SERVER['DOCUMENT_ROOT'] . "/structure.sql");
    if (!$structure_sql) {
        error_log("Structure file cannot be read.");
        die();
    }
    $conn->multi_query($structure_sql);
    exhaustMySqliQueries($conn);
}
$tablecount_result->free();


// Function to exhaust mysqli queries and multi-queries
function exhaustMySqliQueries(mysqli $mysqli_conn): void
{
    do { if ($_ = $mysqli_conn->store_result()) $_->free(); }
    while ($mysqli_conn->more_results() && $mysqli_conn->next_result());
}

// Function to generate unique project code
function generateProjectCode($length = 8)
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// Function to sanitize inputs
function sanitizeInput($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>