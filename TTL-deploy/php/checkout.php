<?php
require_once __DIR__ . '/config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
  echo json_encode(["success" => false, "message" => "未登入"]);
  exit;
}

$conn = get_db_connection();
if ($conn->connect_error) {
  echo json_encode(["success" => false, "message" => "資料庫連線失敗"]);
  exit;
}

$userId = $_SESSION['id'];

// 取得購物車資料
$sql = "SELECT product_id, quantity FROM cart WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo json_encode(["success" => false, "message" => "購物車為空"]);
  exit;
}

// 建立訂單並插入訂單商品
$conn->begin_transaction();

try {
  $conn->query("INSERT INTO orders (user_id, created_at) VALUES ($userId, NOW())");
  $orderId = $conn->insert_id;

  $insertItem = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, ?)");
  while ($row = $result->fetch_assoc()) {
    $insertItem->bind_param("iii", $orderId, $row['product_id'], $row['quantity']);
    $insertItem->execute();
  }

  // 清空購物車
  $conn->query("DELETE FROM cart WHERE user_id = $userId");

  $conn->commit();
  echo json_encode(["success" => true, "message" => "訂單已建立"]);

} catch (Exception $e) {
  $conn->rollback();
  echo json_encode(["success" => false, "message" => "訂單建立失敗", "error" => $e->getMessage()]);
}
