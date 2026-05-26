<?php
require_once __DIR__ . '/config.php';
session_start();

if (!isset($_SESSION['id'])) {
  echo json_encode(["success" => false, "message" => "未登入"]);
  exit;
}

$userId = $_SESSION['id'];
$data = json_decode(file_get_contents("php://input"), true);
$orderId = $data["orderId"] ?? null;

if (!$orderId) {
  echo json_encode(["success" => false, "message" => "缺少訂單編號"]);
  exit;
}

$conn = get_db_connection();
if ($conn->connect_error) {
  echo json_encode(["success" => false, "message" => "資料庫連線失敗"]);
  exit;
}

$stmt = $conn->prepare("DELETE FROM order_complaints WHERE user_id = ? AND order_id = ?");
$stmt->bind_param("ii", $userId, $orderId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
  echo json_encode(["success" => true]);
} else {
  echo json_encode(["success" => false, "message" => "找不到申訴紀錄"]);
}
?>
