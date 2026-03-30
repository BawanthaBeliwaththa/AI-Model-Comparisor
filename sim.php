<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = $_GET['action'] ?? '';

if (!file_exists('data')) {
    mkdir('data', 0777, true);
}

function getKaggleUrl() {
    $config_file = 'kaggle_config.json';
    if (file_exists($config_file)) {
        $config = json_decode(file_get_contents($config_file), true);
        if ($config && isset($config['kaggle_url'])) {
            return rtrim($config['kaggle_url'], '/');
        }
    }
    return null;
}

if ($action == 'ask_stream') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $question = $data['question'] ?? '';
    $session_id = $data['session_id'] ?? session_id();
    
    if (empty($question)) {
        echo json_encode(['error' => 'No question provided']);
        exit();
    }
    
    $kaggle_url = getKaggleUrl();
    
    if (!$kaggle_url) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        
        $models = ['google/gemini-2.0-flash', 'google/gemini-2.5-flash', 'deepseek-ai/deepseek-v3.1'];
        foreach ($models as $idx => $model) {
            echo "data: " . json_encode([
                'success' => true,
                'model' => $model,
                'answer' => "This is a fallback answer. Please connect your Kaggle notebook.\n\nQuestion: $question",
                'time' => 0.5,
                'index' => $idx,
                'total' => count($models)
            ]) . "\n\n";
            ob_flush();
            flush();
            sleep(1);
        }
        echo "data: " . json_encode(['complete' => true, 'total' => count($models)]) . "\n\n";
        exit();
    }
    
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    
    $ch = curl_init($kaggle_url . '/ask_stream');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'question' => $question,
        'session_id' => $session_id
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
        echo $data;
        ob_flush();
        flush();
        return strlen($data);
    });
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'ngrok-skip-browser-warning: true'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    curl_exec($ch);
    curl_close($ch);
    exit();
}

if ($action == 'feedback') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $feedback_file = 'data/feedback.csv';
    $file_exists = file_exists($feedback_file);
    
    $fp = fopen($feedback_file, 'a');
    if (!$file_exists) {
        fputcsv($fp, ['timestamp', 'question', 'model', 'answer', 'rating', 'confidence', 'note', 'ip']);
    }
    
    fputcsv($fp, [
        $data['timestamp'] ?? date('Y-m-d H:i:s'),
        $data['question'] ?? '',
        $data['model'] ?? '',
        $data['answer'] ?? '',
        $data['rating'] ?? '',
        $data['confidence'] ?? 3,
        $data['note'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
    fclose($fp);
    
    echo json_encode(['success' => true]);
    exit();
}

if ($action == 'models') {
    $kaggle_url = getKaggleUrl();
    
    if (!$kaggle_url) {
        echo json_encode(['models' => [
            'google/gemini-2.0-flash',
            'google/gemini-2.5-flash',
            'deepseek-ai/deepseek-v3.1'
        ], 'count' => 3]);
        exit();
    }
    
    $ch = curl_init($kaggle_url . '/models');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['ngrok-skip-browser-warning: true']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    echo $response ?: json_encode(['models' => [], 'count' => 0]);
    exit();
}

if ($action == 'export') {
    $feedback_file = 'data/feedback.csv';
    if (file_exists($feedback_file)) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=feedback_' . date('Y-m-d') . '.csv');
        readfile($feedback_file);
    } else {
        echo "No data to export";
    }
    exit();
}

if ($action == 'export_feedback') {
    $feedback_file = 'data/feedback.csv';
    if (file_exists($feedback_file)) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=feedback_' . date('Y-m-d') . '.csv');
        readfile($feedback_file);
    } else {
        echo "timestamp,question,model,answer,rating,confidence,note,ip\n";
    }
    exit();
}

if ($action == 'clear') {
    $files = ['data/feedback.csv', 'data/visitors.log', 'data/active_users.json'];
    foreach ($files as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
    echo json_encode(['success' => true]);
    exit();
}

if ($action == 'test') {
    echo json_encode([
        'success' => true,
        'message' => 'API working',
        'kaggle_connected' => getKaggleUrl() ? true : false
    ]);
    exit();
}

echo json_encode(['error' => 'Unknown action: ' . $action]);
?>
