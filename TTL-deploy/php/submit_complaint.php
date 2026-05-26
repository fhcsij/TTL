<?php
require_once __DIR__ . '/config.php';
session_start();
if (!isset($_SESSION['id'])) {
  echo json_encode(["success" => false, "message" => "未登入"]);
  exit;
}

$userId = $_SESSION['id'];
$data = json_decode(file_get_contents("php://input"), true);

$orderId = $data['orderId'];
$reason = $data['reason'];

$conn = get_db_connection();
$stmt = $conn->prepare("INSERT INTO order_complaints (order_id, user_id, reason) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $orderId, $userId, $reason);

if ($stmt->execute()) {
  echo json_encode(["success" => true]);
} else {
  echo json_encode(["success" => false, "message" => "申訴儲存失敗"]);
}
