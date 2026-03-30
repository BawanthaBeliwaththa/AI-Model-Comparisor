<?php
session_start();

$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === 'admin' && $password === 'Your_pwd') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $is_logged_in = true;
    } else {
        $login_error = "Invalid credentials";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

function getStats() {
    $stats = [
        'total_visitors' => 0,
        'active_users' => 0,
        'today_visitors' => 0,
        'total_feedback' => 0,
        'correct_feedback' => 0,
        'accuracy' => 0
    ];
    
    $log_file = 'data/visitors.log';
    if (file_exists($log_file)) {
        $lines = file($log_file);
        $stats['total_visitors'] = count($lines);
        
        $today = date('Y-m-d');
        $today_count = 0;
        foreach (array_reverse($lines) as $line) {
            if (strpos($line, $today) === 0) $today_count++;
        }
        $stats['today_visitors'] = $today_count;
    }
    
    $active_file = 'data/active_users.json';
    if (file_exists($active_file)) {
        $active = json_decode(file_get_contents($active_file), true) ?: [];
        $stats['active_users'] = count($active);
    }
    
    $feedback_file = 'data/feedback.csv';
    if (file_exists($feedback_file)) {
        $lines = file($feedback_file);
        if (count($lines) > 1) {
            $stats['total_feedback'] = count($lines) - 1;
            $correct = 0;
            for ($i = 1; $i < count($lines); $i++) {
                $parts = str_getcsv($lines[$i]);
                if (isset($parts[4]) && $parts[4] === 'correct') $correct++;
            }
            $stats['correct_feedback'] = $correct;
            $stats['accuracy'] = $stats['total_feedback'] > 0 ? round(($correct / $stats['total_feedback']) * 100, 1) : 0;
        }
    }
    
    return $stats;
}

function getRecentFeedback($limit = 50) {
    $feedback = [];
    $feedback_file = 'data/feedback.csv';
    if (file_exists($feedback_file)) {
        $lines = file($feedback_file);
        for ($i = 1; $i <= min($limit, count($lines) - 1); $i++) {
            $parts = str_getcsv($lines[count($lines) - $i]);
            $feedback[] = [
                'timestamp' => $parts[0] ?? '',
                'question' => $parts[1] ?? '',
                'model' => $parts[2] ?? '',
                'answer' => substr($parts[3] ?? '', 0, 100),
                'rating' => $parts[4] ?? '',
                'confidence' => $parts[5] ?? '',
                'note' => $parts[6] ?? ''
            ];
        }
    }
    return $feedback;
}

function getUserSessions() {
    $sessions = [];
    $log_file = 'data/visitors.log';
    if (file_exists($log_file)) {
        $lines = array_reverse(file($log_file));
        $ips = [];
        foreach ($lines as $line) {
            $parts = explode('|', trim($line));
            if (count($parts) >= 2) {
                $ip = $parts[1];
                if (!isset($ips[$ip])) {
                    $ips[$ip] = [
                        'ip' => $ip,
                        'first_seen' => $parts[0],
                        'last_seen' => $parts[0],
                        'user_agent' => $parts[2] ?? '',
                        'count' => 1
                    ];
                } else {
                    $ips[$ip]['count']++;
                    $ips[$ip]['last_seen'] = $parts[0];
                }
            }
        }
        $sessions = array_values($ips);
    }
    return $sessions;
}

function getModelPerformance() {
    $models = [];
    $feedback_file = 'data/feedback.csv';
    if (file_exists($feedback_file)) {
        $lines = file($feedback_file);
        for ($i = 1; $i < count($lines); $i++) {
            $parts = str_getcsv($lines[$i]);
            if (count($parts) >= 5) {
                $model = $parts[2] ?? 'Unknown';
                $rating = $parts[4] ?? '';
                if (!isset($models[$model])) {
                    $models[$model] = ['total' => 0, 'correct' => 0];
                }
                $models[$model]['total']++;
                if ($rating === 'correct') $models[$model]['correct']++;
            }
        }
    }
    
    foreach ($models as $model => &$data) {
        $data['accuracy'] = $data['total'] > 0 ? round(($data['correct'] / $data['total']) * 100, 1) : 0;
    }
    
    uasort($models, function($a, $b) {
        return $b['accuracy'] <=> $a['accuracy'];
    });
    
    return $models;
}

$stats = getStats();
$recent_feedback = getRecentFeedback(50);
$user_sessions = getUserSessions();
$model_performance = getModelPerformance();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AI Model Comparison</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        
        .login-card {
            max-width: 400px;
            margin: 100px auto;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 40px;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .login-card input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 12px;
            color: white;
        }
        .login-card button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            cursor: pointer;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .dashboard-header h1 { color: white; font-size: 1.8rem; }
        .logout-btn {
            background: rgba(239,68,68,0.8);
            color: white;
            padding: 10px 20px;
            border-radius: 12px;
            text-decoration: none;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .stat-card h3 { color: rgba(255,255,255,0.7); font-size: 0.85rem; margin-bottom: 10px; }
        .stat-number { font-size: 2rem; font-weight: 800; color: white; }
        
        .card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .card h2 { color: white; font-size: 1.3rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.9);
        }
        th { background: rgba(255,255,255,0.1); font-weight: 600; }
        tr:hover { background: rgba(255,255,255,0.05); }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-correct { background: #10b981; color: white; }
        .badge-incorrect { background: #ef4444; color: white; }
        .badge-pending { background: #f59e0b; color: white; }
        
        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
        .btn {
            padding: 10px 20px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: #1a1a2e;
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            table { font-size: 0.7rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <?php if (!$is_logged_in): ?>
            <div class="login-card">
                <h2 style="color: white; text-align: center; margin-bottom: 20px;">🔐 Admin Login</h2>
                <?php if (isset($login_error)): ?>
                    <div style="color: #ef4444; text-align: center; margin-bottom: 15px;"><?php echo $login_error; ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit" name="login">Login to Dashboard</button>
                </form>
            </div>
            
        <?php else: ?>
            <div class="dashboard-header">
                <h1><i class="fas fa-chart-line"></i> Admin Dashboard</h1>
                <div>
                    <span style="color: white; margin-right: 15px;"><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    <a href="?logout=1" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card"><h3>Total Visitors</h3><div class="stat-number"><?php echo number_format($stats['total_visitors']); ?></div></div>
                <div class="stat-card"><h3>Active Now</h3><div class="stat-number"><?php echo $stats['active_users']; ?></div></div>
                <div class="stat-card"><h3>Today's Visitors</h3><div class="stat-number"><?php echo number_format($stats['today_visitors']); ?></div></div>
                <div class="stat-card"><h3>Total Feedback</h3><div class="stat-number"><?php echo number_format($stats['total_feedback']); ?></div></div>
                <div class="stat-card"><h3>Correct Feedback</h3><div class="stat-number"><?php echo number_format($stats['correct_feedback']); ?></div></div>
                <div class="stat-card"><h3>Accuracy Rate</h3><div class="stat-number"><?php echo $stats['accuracy']; ?>%</div></div>
            </div>
            
            <div class="card">
                <div class="btn-group">
                    <button class="btn btn-success" onclick="exportAll()"><i class="fas fa-download"></i> Export All Data (CSV)</button>
                    <button class="btn btn-primary" onclick="exportFeedback()"><i class="fas fa-file-alt"></i> Export Feedback</button>
                    <button class="btn btn-danger" onclick="clearData()"><i class="fas fa-trash"></i> Clear All Data</button>
                </div>
            </div>
            
            <div class="card">
                <h2><i class="fas fa-trophy"></i> Model Performance</h2>
                <div style="overflow-x: auto;">
                    <table>
                        <thead><tr><th>Rank</th><th>Model</th><th>Total Feedback</th><th>Correct</th><th>Accuracy</th></tr></thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            foreach ($model_performance as $model => $data): 
                                $accuracy = $data['accuracy'];
                                $badge_class = $accuracy >= 70 ? 'badge-correct' : ($accuracy >= 40 ? 'badge-pending' : 'badge-incorrect');
                            ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><strong><?php echo htmlspecialchars($model); ?></strong></td>
                                <td><?php echo $data['total']; ?></td>
                                <td><?php echo $data['correct']; ?></td>
                                <td><span class="badge <?php echo $badge_class; ?>"><?php echo $accuracy; ?>%</span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($model_performance)): ?>
                            <tr><td colspan="5" style="text-align: center;">No feedback data yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card">
                <h2><i class="fas fa-users"></i> User Sessions</h2>
                <div style="overflow-x: auto; max-height: 400px;">
                    <table>
                        <thead><tr><th>IP Address</th><th>First Seen</th><th>Last Seen</th><th>Requests</th><th>User Agent</th></tr></thead>
                        <tbody>
                            <?php foreach (array_slice($user_sessions, 0, 50) as $session): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($session['ip']); ?></code></td>
                                <td><?php echo htmlspecialchars($session['first_seen']); ?></td>
                                <td><?php echo htmlspecialchars($session['last_seen']); ?></td>
                                <td><?php echo $session['count']; ?></td>
                                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars(substr($session['user_agent'], 0, 50)); ?>...</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card">
                <h2><i class="fas fa-comment-dots"></i> Recent Feedback</h2>
                <div style="overflow-x: auto; max-height: 400px;">
                    <table>
                        <thead><tr><th>Time</th><th>Question</th><th>Model</th><th>Rating</th><th>Confidence</th><th>Note</th></tr></thead>
                        <tbody>
                            <?php foreach ($recent_feedback as $f): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(substr($f['timestamp'], 11, 8)); ?></td>
                                <td style="max-width: 150px;"><?php echo htmlspecialchars(substr($f['question'], 0, 40)); ?>...</td>
                                <td><?php echo htmlspecialchars($f['model']); ?></td>
                                <td><span class="badge <?php echo $f['rating'] === 'correct' ? 'badge-correct' : 'badge-incorrect'; ?>"><?php echo $f['rating']; ?></span></td>
                                <td><?php echo $f['confidence']; ?>/5</td>
                                <td style="max-width: 100px;"><?php echo htmlspecialchars(substr($f['note'], 0, 30)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card">
                <h2><i class="fas fa-info-circle"></i> System Info</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <div>PHP Version: <?php echo phpversion(); ?><br>Data Directory: data/</div>
                    <div>
                        Kaggle Status: 
                        <?php
                        $config_file = 'kaggle_config.json';
                        $connected = false;
                        if (file_exists($config_file)) {
                            $config = json_decode(file_get_contents($config_file), true);
                            if ($config && isset($config['kaggle_url'])) {
                                $ch = curl_init($config['kaggle_url'] . '/health');
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                $response = curl_exec($ch);
                                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                curl_close($ch);
                                $connected = $http_code == 200;
                            }
                        }
                        ?>
                        <span class="badge <?php echo $connected ? 'badge-correct' : 'badge-incorrect'; ?>">
                            <?php echo $connected ? 'Connected' : 'Disconnected'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
        
    </div>
    
    <div id="clearModal" class="modal">
        <div class="modal-content">
            <h2 style="color: white;">⚠️ Clear All Data</h2>
            <p style="color: rgba(255,255,255,0.8); margin: 15px 0;">This will delete all feedback and visitor logs. This cannot be undone.</p>
            <div class="btn-group">
                <button class="btn btn-danger" onclick="confirmClear()">Yes, Clear All Data</button>
                <button class="btn btn-primary" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>
    
    <script>
        function exportAll() { window.location.href = 'sim.php?action=export'; }
        function exportFeedback() { window.location.href = 'sim.php?action=export_feedback'; }
        function showClearModal() { document.getElementById('clearModal').style.display = 'flex'; }
        function closeModal() { document.getElementById('clearModal').style.display = 'none'; }
        
        function clearData() { showClearModal(); }
        
        async function confirmClear() {
            const response = await fetch('sim.php?action=clear', { method: 'POST' });
            const result = await response.json();
            if (result.success) {
                alert('Data cleared successfully');
                location.reload();
            } else {
                alert('Error: ' + result.error);
            }
            closeModal();
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>