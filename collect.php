<?php
require_once 'config.php';

header('Content-Type: application/json');

$pdo = getDB();

if (isset($_GET['export'])) {
    $stmt = $pdo->query("SELECT * FROM feedback ORDER BY created_at DESC");
    $data = $stmt->fetchAll();
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=feedback_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['id', 'question', 'model', 'answer', 'rating', 'confidence', 'note', 'user_agent', 'created_at']);
    
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    $pdo->exec("TRUNCATE TABLE feedback");
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $pdo->prepare("
        INSERT INTO feedback (question, model, answer, rating, confidence, note, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $input['question'],
        $input['model'],
        $input['answer'],
        $input['rating'],
        $input['confidence'],
        $input['note'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
    
    echo json_encode(['success' => true]);
    exit;
}
?>