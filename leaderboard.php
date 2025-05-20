<?php
// Start the session
session_start();
include 'db_connect.php';
include 'session_helper.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$isGuest = isset($_SESSION['is_guest']) && $_SESSION['is_guest'];
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';

// Get the list of all projects for selection
$projectsQuery = "SELECT p.project_id, p.project_name, p.project_code 
                 FROM projects p 
                 LEFT JOIN shared_projects sp ON p.project_id = sp.project_id 
                 WHERE p.user_id = ? OR (sp.user_id = ? AND sp.project_id = p.project_id)";

$stmt = $conn->prepare($projectsQuery);
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$projects = $stmt->get_result();

// Get selected project if any
$selectedProjectId = isset($_GET['project_id']) ? $_GET['project_id'] : null;
$leaderboardData = [];

if ($selectedProjectId) {
    // Get project details
    $projectDetailsQuery = "SELECT project_name, project_code FROM projects WHERE project_id = ?";
    $stmt = $conn->prepare($projectDetailsQuery);
    $stmt->bind_param("i", $selectedProjectId);
    $stmt->execute();
    $projectDetails = $stmt->get_result()->fetch_assoc();
    
    // Get leaderboard data for the selected project
    $leaderboardQuery = "SELECT 
                            u.username,
                            qa.score,
                            qa.start_time,
                            qa.end_time,
                            TIMESTAMPDIFF(MINUTE, qa.start_time, qa.end_time) as time_taken
                        FROM quiz_attempts qa
                        JOIN users u ON qa.user_id = u.user_id
                        WHERE qa.project_id = ? AND qa.completed = 1
                        ORDER BY qa.score DESC, time_taken ASC
                        LIMIT 100";
    
    $stmt = $conn->prepare($leaderboardQuery);
    $stmt->bind_param("i", $selectedProjectId);
    $stmt->execute();
    $leaderboardData = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flash Quiz App - Leaderboards</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --grande-violet: #663399;
            --dark-violet: #4B0082;
            --dark-blue: #00008B;
            --deep-dark-blue: #191970;
            --black: #000000;
            --white: #FFFFFF;
            --light-violet: #9370DB;
            --very-light-violet: #EDE7F6;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #121212;
            color: var(--white);
            position: relative;
            min-height: 100vh;
            padding-bottom: 60px;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(102, 51, 153, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 90% 80%, rgba(0, 0, 139, 0.15) 0%, transparent 50%);
        }
        
        header {
            background: linear-gradient(135deg, var(--grande-violet), var(--dark-blue));
            color: var(--white);
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }
        
        header::before {
            content: "🏆";
            position: absolute;
            font-size: 60px;
            opacity: 0.1;
            top: 50%;
            left: 5%;
            transform: translateY(-50%);
        }
        
        header::after {
            content: "🏆";
            position: absolute;
            font-size: 60px;
            opacity: 0.1;
            top: 50%;
            right: 5%;
            transform: translateY(-50%);
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: rgba(15, 15, 25, 0.7);
            border-radius: 10px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(102, 51, 153, 0.3);
        }
        
        h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            position: relative;
            display: inline-block;
        }
        
        h1::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--grande-violet), var(--dark-blue));
        }
        
        .user-info {
            position: absolute;
            top: 10px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--light-violet), var(--dark-blue));
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--white);
            font-weight: bold;
        }
        
        .project-selector {
            margin: 20px 0;
            padding: 15px;
            background-color: rgba(25, 25, 35, 0.6);
            border-radius: 8px;
            border: 1px solid rgba(102, 51, 153, 0.2);
        }
        
        .project-selector h3 {
            margin-bottom: 15px;
            color: var(--light-violet);
        }
        
        select {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            background-color: rgba(30, 30, 40, 0.8);
            color: var(--white);
            border: 1px solid rgba(102, 51, 153, 0.5);
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        select:hover {
            border-color: var(--grande-violet);
        }
        
        select:focus {
            outline: none;
            border-color: var(--grande-violet);
            box-shadow: 0 0 0 2px rgba(102, 51, 153, 0.3);
        }
        
        button {
            background: linear-gradient(135deg, var(--grande-violet), var(--dark-blue));
            color: var(--white);
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 51, 153, 0.4);
        }
        
        .leaderboard {
            overflow-x: auto;
        }
        
        .leaderboard-info {
            margin-bottom: 20px;
            padding: 15px;
            background-color: rgba(75, 0, 130, 0.2);
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .leaderboard-info h2 {
            font-size: 1.8rem;
            color: var(--light-violet);
        }
        
        .leaderboard-info .project-code {
            background-color: rgba(102, 51, 153, 0.3);
            padding: 8px 15px;
            border-radius: 20px;
            font-family: monospace;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: rgba(25, 25, 35, 0.5);
            border-radius: 8px;
            overflow: hidden;
        }
        
        table th, table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(102, 51, 153, 0.2);
        }
        
        table th {
            background-color: rgba(75, 0, 130, 0.4);
            color: var(--white);
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        
        table tr:hover {
            background-color: rgba(102, 51, 153, 0.15);
        }
        
        tr:nth-child(even) {
            background-color: rgba(25, 25, 35, 0.3);
        }
        
        .rank {
            width: 60px;
            text-align: center;
            font-weight: bold;
        }
        
        .rank-1, .rank-2, .rank-3 {
            font-size: 1.2rem;
        }
        
        .rank-1 {
            color: gold;
        }
        
        .rank-2 {
            color: silver;
        }
        
        .rank-3 {
            color: #cd7f32; /* bronze */
        }
        
        .trophy {
            font-size: 1.5rem;
        }
        
        .score {
            font-weight: bold;
            color: var(--light-violet);
        }
        
        .time-taken {
            color: var(--very-light-violet);
            font-family: monospace;
        }
        
        .date-time {
            font-size: 0.85rem;
            color: #aaa;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #888;
            font-style: italic;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .home-button {
            position: absolute;
            top: 10px;
            left: 20px;
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--white);
            padding: 10px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .home-button:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 15px 0;
            text-align: center;
            background-color: rgba(0, 0, 0, 0.5);
            border-top: 1px solid rgba(102, 51, 153, 0.3);
            font-size: 0.8rem;
            color: #888;
        }
        
        @media (max-width: 768px) {
            .container {
                width: 95%;
                padding: 15px;
            }
            
            .leaderboard-info {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .table-responsive {
                display: block;
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            table {
                min-width: 600px;
            }
            
            .user-info {
                position: static;
                margin-bottom: 15px;
                justify-content: flex-end;
            }
            
            header {
                padding-top: 60px;
            }
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
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="home-button">
            <i class="fas fa-home"></i>
        </a>
        <h1>Flash Quiz Leaderboards</h1>
        <div class="user-info">
            <div class="user-avatar">
                <?php echo substr($username, 0, 1); ?>
            </div>
            <span><?php echo htmlspecialchars($username); ?></span>
        </div>
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
    
    <div class="container">
        <div class="project-selector">
            <h3><i class="fas fa-trophy"></i> Select Quiz Project</h3>
            <form action="leaderboard.php" method="GET">
                <select name="project_id" id="project_id">
                    <option value="">-- Select a Project --</option>
                    <?php
                    if ($projects && $projects->num_rows > 0) {
                        while ($project = $projects->fetch_assoc()) {
                            $selected = ($selectedProjectId == $project['project_id']) ? 'selected' : '';
                            echo '<option value="' . $project['project_id'] . '" ' . $selected . '>' . 
                                  htmlspecialchars($project['project_name']) . ' (Code: ' . $project['project_code'] . ')' . 
                                 '</option>';
                        }
                    }
                    ?>
                </select>
                <button type="submit"><i class="fas fa-search"></i> View Leaderboard</button>
            </form>
        </div>

        <?php if ($selectedProjectId && isset($projectDetails)): ?>
            <div class="leaderboard-info">
                <h2><?php echo htmlspecialchars($projectDetails['project_name']); ?> Leaderboard</h2>
                <div class="project-code">
                    <i class="fas fa-key"></i> Project Code: <?php echo htmlspecialchars($projectDetails['project_code']); ?>
                </div>
            </div>
            
            <div class="leaderboard">
                <?php if ($leaderboardData && $leaderboardData->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th class="rank">Rank</th>
                                    <th>User</th>
                                    <th>Score</th>
                                    <th>Time Taken</th>
                                    <th>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rank = 1;
                                while ($entry = $leaderboardData->fetch_assoc()): 
                                    $rankClass = ($rank <= 3) ? "rank-{$rank}" : "";
                                    $trophy = "";
                                    if ($rank == 1) $trophy = "🥇";
                                    elseif ($rank == 2) $trophy = "🥈";
                                    elseif ($rank == 3) $trophy = "🥉";
                                ?>
                                <tr>
                                    <td class="rank <?php echo $rankClass; ?>">
                                        <?php echo $trophy ? "<span class='trophy'>{$trophy}</span>" : $rank; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($entry['username']); ?></td>
                                    <td class="score"><?php echo number_format($entry['score']); ?> pts</td>
                                    <td class="time-taken">
                                        <?php 
                                        $timeTaken = $entry['time_taken'];
                                        echo floor($timeTaken / 60) . "h " . ($timeTaken % 60) . "m"; 
                                        ?>
                                    </td>
                                    <td class="date-time">
                                        <?php 
                                        $startDate = new DateTime($entry['start_time']);
                                        $endDate = new DateTime($entry['end_time']);
                                        echo $startDate->format('M j, Y g:i A') . '<br>';
                                        echo '<small>to ' . $endDate->format('g:i A') . '</small>';
                                        ?>
                                    </td>
                                </tr>
                                <?php 
                                $rank++;
                                endwhile; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-bar"></i>
                        <p>No quiz attempts recorded for this project yet!</p>
                        <p>Complete a quiz to appear on the leaderboard.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($selectedProjectId): ?>
            <div class="empty-state">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Project not found or you don't have access to it.</p>
                <p>Please select a different project.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-trophy"></i>
                <p>Select a project to view its leaderboard.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <footer>
        Flash Quiz App &copy; <?php echo date('Y'); ?> - All Rights Reserved.
    </footer>

    <script>
        // Add simple animation for the trophy icons
        document.addEventListener('DOMContentLoaded', function() {
            const trophies = document.querySelectorAll('.trophy');
            
            trophies.forEach(trophy => {
                setInterval(() => {
                    trophy.style.transform = 'scale(1.1)';
                    setTimeout(() => {
                        trophy.style.transform = 'scale(1)';
                    }, 500);
                }, 3000);
            });
        });
    </script>
</body>
</html>