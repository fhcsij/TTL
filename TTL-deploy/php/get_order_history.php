<?php
require_once __DIR__ . '/config.php';
session_start();
if (!isset($_SESSION['id'])) {
  echo json_encode(["success" => false, "message" => "未登入"]);
  exit;
}

$userId = $_SESSION['id'];
$conn = get_db_connection();

$stmt = $conn->prepare("
  SELECT 
    o.id, 
    o.total_price, 
    o.points_used,
    EXISTS(
      SELECT 1 FROM order_complaints c 
      WHERE c.order_id = o.id AND c.user_id = o.user_id
    ) AS complained
  FROM orders o
  WHERE o.user_id = ?
  ORDER BY o.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
  $orders[] = $row;
}

echo json_encode(["success" => true, "orders" => $orders]);
