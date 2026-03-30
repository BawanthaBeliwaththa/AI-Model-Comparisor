<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$pdo = getDB();

try {
    $pdo->beginTransaction();
    
    if ($input['questions'] ?? false) {
        $pdo->exec("DELETE FROM responses");
        $pdo->exec("DELETE FROM questions");
    }
    
    if ($input['feedback'] ?? false) {
        $pdo->exec("DELETE FROM feedback");
    }
    
    if ($input['sessions'] ?? false) {
        $pdo->exec("DELETE FROM user_sessions");
    }
    
    $pdo->commit();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>