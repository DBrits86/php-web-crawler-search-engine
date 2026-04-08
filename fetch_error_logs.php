<?php
header('Content-Type: application/json');
require_once 'includes/db.php';

try {
    $conn = $pdo->open();

    // Fetch last 50 error logs, newest first
    $stmt = $conn->prepare("SELECT timestamp, level, message FROM error_logs ORDER BY timestamp DESC LIMIT 50");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        'success' => true,
        'logs' => $logs
    ];
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

$pdo->close();
echo json_encode($response);
