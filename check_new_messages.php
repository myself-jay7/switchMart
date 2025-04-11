<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit();
}

$current_user_id = $_SESSION['user_id'];
$other_user_id = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : 0;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

// Check for new messages
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM messages 
    WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
    AND message_id > ?
");
$stmt->execute([$current_user_id, $other_user_id, $other_user_id, $current_user_id, $last_id]);
$result = $stmt->fetch();

header('Content-Type: application/json');
echo json_encode(['has_new' => $result['count'] > 0]);
?>