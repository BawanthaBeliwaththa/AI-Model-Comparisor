<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Kaggle Connection Test</h1>";

$config_file = __DIR__ . '/kaggle_config.json';
$kaggle_url = null;
if (file_exists($config_file)) {
    $config = json_decode(file_get_contents($config_file), true);
    $kaggle_url = $config['kaggle_url'] ?? null;
}

echo "<p>Kaggle URL: " . ($kaggle_url ? htmlspecialchars($kaggle_url) : 'Not configured') . "</p>";

if (!$kaggle_url) {
    echo "<p style='color:red'>❌ No Kaggle URL configured. Please configure in admin panel.</p>";
    exit;
}

echo "<h2>Test 1: Health Check</h2>";
$ch = curl_init($kaggle_url . '/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['ngrok-skip-browser-warning: true']);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<p>HTTP Code: $http_code</p>";
if ($error) echo "<p>Error: $error</p>";
if ($response) {
    echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
}

echo "<h2>Test 2: Models List</h2>";
$ch = curl_init($kaggle_url . '/models');
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
        echo "<p>✅ Found " . count($data['models']) . " models</p>";
        echo "<ul>";
        foreach (array_slice($data['models'], 0, 5) as $model) {
            echo "<li>" . htmlspecialchars($model) . "</li>";
        }
        if (count($data['models']) > 5) echo "<li>... and " . (count($data['models']) - 5) . " more</li>";
        echo "</ul>";
    } else {
        echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
    }
}

echo "<h2>Test 3: Ask a Question</h2>";
echo "<p>Sending question: 'What is 4 × 4?'</p>";

$ch = curl_init($kaggle_url . '/ask_all');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['question' => 'What is 4 × 4?']));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'ngrok-skip-browser-warning: true'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<p>HTTP Code: $http_code</p>";
if ($error) echo "<p>Error: $error</p>";

if ($response) {
    $data = json_decode($response, true);
    if ($data && isset($data['success']) && $data['success']) {
        echo "<p style='color:green'>✅ Success! Received answers from " . count($data['answers']) . " models</p>";
        echo "<h3>Sample Answer:</h3>";
        if (count($data['answers']) > 0) {
            echo "<div style='background:#f0f0f0; padding:10px; border-radius:5px;'>";
            echo "<strong>Model: " . htmlspecialchars($data['answers'][0]['model']) . "</strong><br>";
            echo htmlspecialchars(substr($data['answers'][0]['answer'], 0, 300));
            echo "</div>";
        }
    } else {
        echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
    }
}
?>