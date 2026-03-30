<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Kaggle Connection Test</h1>";

$config_file = __DIR__ . '/kaggle_config.json';
$current_url = null;
if (file_exists($config_file)) {
    $config = json_decode(file_get_contents($config_file), true);
    $current_url = $config['kaggle_url'] ?? null;
}

echo "<p><strong>Current URL in config:</strong> " . ($current_url ? htmlspecialchars($current_url) : 'NOT SET') . "</p>";

if (!$current_url) {
    echo "<p style='color:red'>❌ No URL configured. Please go to admin panel and save your Kaggle URL.</p>";
    exit;
}

echo "<h2>Testing connection to: " . htmlspecialchars($current_url) . "</h2>";

echo "<h3>1. Testing /health endpoint...</h3>";
$ch = curl_init($current_url . '/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['ngrok-skip-browser-warning: true']);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Code: $http_code</p>";
if ($response) {
    echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
}

echo "<h3>2. Testing /models endpoint...</h3>";
$ch = curl_init($current_url . '/models');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['ngrok-skip-browser-warning: true']);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Code: $http_code</p>";
if ($response) {
    $data = json_decode($response, true);
    if ($data && isset($data['models'])) {
        echo "<p style='color:green'>✅ Found " . count($data['models']) . " models!</p>";
        echo "<ul>";
        foreach (array_slice($data['models'], 0, 5) as $model) {
            echo "<li>" . htmlspecialchars($model) . "</li>";
        }
        if (count($data['models']) > 5) {
            echo "<li>... and " . (count($data['models']) - 5) . " more</li>";
        }
        echo "</ul>";
    } else {
        echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
    }
}

echo "<h3>3. Testing /ask_all endpoint (this may take 30-60 seconds)...</h3>";
$ch = curl_init($current_url . '/ask_all');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['question' => 'What is 2+2?']));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'ngrok-skip-browser-warning: true'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 90);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$start = time();
$response = curl_exec($ch);
$duration = time() - $start;
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>Time taken: $duration seconds</p>";
echo "<p>HTTP Code: $http_code</p>";

if ($response) {
    $data = json_decode($response, true);
    if ($data && isset($data['success']) && $data['success']) {
        echo "<p style='color:green'>✅ Success! Got answers from " . count($data['answers']) . " models!</p>";
        echo "<h4>Sample answer from first model:</h4>";
        if (count($data['answers']) > 0) {
            echo "<div style='background:#f0f0f0; padding:10px; border-radius:5px;'>";
            echo "<strong>Model: " . htmlspecialchars($data['answers'][0]['model']) . "</strong><br>";
            echo htmlspecialchars(substr($data['answers'][0]['answer'], 0, 300));
            echo "</div>";
        }
    } else {
        echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
    }
} else {
    echo "<p style='color:red'>❌ No response received</p>";
}
?>
