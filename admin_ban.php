<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$ip = $input['ip'] ?? '';
$pdo = getDB();

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS banned_ips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL UNIQUE,
            banned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            banned_by VARCHAR(50)
        )
    ");
    
    $stmt = $pdo->prepare("INSERT INTO banned_ips (ip_address, banned_by) VALUES (?, ?)");
    $stmt->execute([$ip, $_SESSION['admin_username']]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>