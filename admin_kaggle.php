<?php
session_start();

$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $login_error = "Invalid username or password";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$success_message = null;
$error_message = null;

if ($is_logged_in && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_kaggle_url'])) {
    $kaggle_url = trim($_POST['kaggle_url'] ?? '');
    
    if (filter_var($kaggle_url, FILTER_VALIDATE_URL)) {
        $config = [
            'kaggle_url' => rtrim($kaggle_url, '/'),
            'last_updated' => date('Y-m-d H:i:s'),
            'status' => 'active'
        ];
        
        if (file_put_contents('kaggle_config.json', json_encode($config, JSON_PRETTY_PRINT))) {
            $success_message = "✅ Kaggle URL saved successfully!";
        } else {
            $error_message = "❌ Failed to save configuration.";
        }
    } else {
        $error_message = "❌ Invalid URL format";
    }
}

$current_url = null;
if (file_exists('kaggle_config.json')) {
    $config = json_decode(file_get_contents('kaggle_config.json'), true);
    $current_url = $config['kaggle_url'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Kaggle Connection</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 600px; margin: 50px auto; }
        .card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        h1 { margin-bottom: 20px; color: #2d3748; }
        .current-url {
            background: #f7fafc;
            padding: 12px;
            border-radius: 10px;
            font-family: monospace;
            margin: 15px 0;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            margin: 10px 0;
            font-family: monospace;
        }
        button {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
            width: 100%;
            font-weight: 600;
        }
        .alert {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .admin-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .logout-btn {
            background: #ef4444;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <?php if (!$is_logged_in): ?>
                <h1>🔐 Admin Login</h1>
                <?php if (isset($login_error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($login_error); ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="text" name="username" placeholder="Username" required><br>
                    <input type="password" name="password" placeholder="Password" required><br>
                    <button type="submit" name="login">Login</button>
                </form>
                <p style="margin-top: 15px; font-size: 12px; text-align: center;">Default: admin / admin123</p>
            <?php else: ?>
                <div class="admin-info">
                    <h1>⚙️ Kaggle Connection</h1>
                    <a href="?logout=1" class="logout-btn">Logout</a>
                </div>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                
                <h3>Current URL:</h3>
                <div class="current-url">
                    <?php if ($current_url): ?>
                        <code><?php echo htmlspecialchars($current_url); ?></code>
                    <?php else: ?>
                        <span style="color: #ef4444;">Not configured</span>
                    <?php endif; ?>
                </div>
                
                <form method="POST">
                    <input type="text" name="kaggle_url" placeholder="https://your-kaggle-url.ngrok-free.app" value="<?php echo htmlspecialchars($current_url ?? ''); ?>">
                    <button type="submit" name="save_kaggle_url">Save URL</button>
                </form>
                
                <p style="margin-top: 20px; font-size: 12px; color: #718096;">
                    <i class="fas fa-info-circle"></i> Paste your Kaggle notebook ngrok URL here.<br>
                    Users will see answers from ALL models at once!
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
