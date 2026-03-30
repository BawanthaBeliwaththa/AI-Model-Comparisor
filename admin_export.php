<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Unauthorized");
}

$type = $_GET['type'] ?? 'all';
$pdo = getDB();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename=' . $type . '_' . date('Y-m-d_H-i-s') . '.csv');

$output = fopen('php://output', 'w');

switch ($type) {
    case 'feedback':
        fputcsv($output, ['id', 'response_id', 'user_ip', 'is_correct', 'user_confidence', 'user_note', 'created_at']);
        $stmt = $pdo->query("SELECT * FROM feedback ORDER BY created_at DESC");
        while ($row = $stmt->fetch()) {
            fputcsv($output, $row);
        }
        break;
        
    case 'users':
        fputcsv($output, ['id', 'user_ip', 'user_agent', 'question_count', 'created_at', 'last_activity']);
        $stmt = $pdo->query("SELECT * FROM user_sessions ORDER BY last_activity DESC");
        while ($row = $stmt->fetch()) {
            fputcsv($output, $row);
        }
        break;
        
    case 'backup':
        $tables = ['questions', 'responses', 'feedback', 'user_sessions', 'banned_ips'];
        foreach ($tables as $table) {
            fputcsv($output, ["--- TABLE: $table ---"]);
            $stmt = $pdo->query("SELECT * FROM $table");
            $headers = array_keys($stmt->fetch(PDO::FETCH_ASSOC) ?? []);
            if ($headers) {
                fputcsv($output, $headers);
                $stmt = $pdo->query("SELECT * FROM $table");
                while ($row = $stmt->fetch()) {
                    fputcsv($output, $row);
                }
            }
            fputcsv($output, []);
        }
        break;
        
    case 'all':
    default:
        fputcsv($output, ['--- QUESTIONS ---']);
        $stmt = $pdo->query("SELECT * FROM questions ORDER BY created_at DESC");
        $headers = array_keys($stmt->fetch(PDO::FETCH_ASSOC) ?? []);
        if ($headers) {
            fputcsv($output, $headers);
            $stmt = $pdo->query("SELECT * FROM questions ORDER BY created_at DESC");
            while ($row = $stmt->fetch()) {
                fputcsv($output, $row);
            }
        }
        
        fputcsv($output, []);
        fputcsv($output, ['--- RESPONSES ---']);
        $stmt = $pdo->query("SELECT * FROM responses ORDER BY created_at DESC");
        $headers = array_keys($stmt->fetch(PDO::FETCH_ASSOC) ?? []);
        if ($headers) {
            fputcsv($output, $headers);
            $stmt = $pdo->query("SELECT * FROM responses ORDER BY created_at DESC");
            while ($row = $stmt->fetch()) {
                fputcsv($output, $row);
            }
        }
        
        fputcsv($output, []);
        fputcsv($output, ['--- FEEDBACK ---']);
        $stmt = $pdo->query("SELECT * FROM feedback ORDER BY created_at DESC");
        $headers = array_keys($stmt->fetch(PDO::FETCH_ASSOC) ?? []);
        if ($headers) {
            fputcsv($output, $headers);
            $stmt = $pdo->query("SELECT * FROM feedback ORDER BY created_at DESC");
            while ($row = $stmt->fetch()) {
                fputcsv($output, $row);
            }
        }
        
        fputcsv($output, []);
        fputcsv($output, ['--- USER SESSIONS ---']);
        $stmt = $pdo->query("SELECT * FROM user_sessions ORDER BY last_activity DESC");
        $headers = array_keys($stmt->fetch(PDO::FETCH_ASSOC) ?? []);
        if ($headers) {
            fputcsv($output, $headers);
            $stmt = $pdo->query("SELECT * FROM user_sessions ORDER BY last_activity DESC");
            while ($row = $stmt->fetch()) {
                fputcsv($output, $row);
            }
        }
        break;
}

fclose($output);
?>