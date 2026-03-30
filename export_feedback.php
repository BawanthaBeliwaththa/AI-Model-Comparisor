<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Unauthorized");
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename=feedback_' . date('Y-m-d') . '.csv');

$feedback_file = __DIR__ . '/feedback.csv';
if (file_exists($feedback_file)) {
    readfile($feedback_file);
} else {
    echo "timestamp,question,model,answer,is_correct,correction,user_agent\n";
}
?>