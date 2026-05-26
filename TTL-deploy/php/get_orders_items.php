<?php
require_once __DIR__ . '/config.php';
session_start();

if (!isset($_SESSION['id'])) {
  echo json_encode(["success" => false, "message" => "未登入"]);
  exit;
}

$userId = $_SESSION['id'];
$conn = get_db_connection();
if ($conn->connect_error) {
  echo json_encode(["success" => false, "message" => "資料庫連線失敗"]);
  exit;
}

// 取得使用者所有訂單（含是否已申訴）
$orderStmt = $conn->prepare("
  SELECT 
    o.id, 
    o.created_at, 
    o.total_price AS total_amount, 
    o.points_used,
    EXISTS (
      SELECT 1 FROM order_complaints c 
      WHERE c.order_id = o.id AND c.user_id = o.user_id
    ) AS complained
  FROM orders o
  WHERE o.user_id = ?
  ORDER BY o.created_at DESC
");
$orderStmt->bind_param("i", $userId);
$orderStmt->execute();
$orderResult = $orderStmt->get_result();

$orders = [];

while ($order = $orderResult->fetch_assoc()) {
  $orderId = $order['id'];

  // 取得該訂單所有商品
  $itemStmt = $conn->prepare("
    SELECT p.name, p.image, i.quantity, i.price
    FROM order_items i
    JOIN products p ON i.product_id = p.id
    WHERE i.order_id = ?
  ");
  $itemStmt->bind_param("i", $orderId);
  $itemStmt->execute();
  $itemResult = $itemStmt->get_result();

  $items = [];
  while ($item = $itemResult->fetch_assoc()) {
    $item['image'] = ttl_product_image_url($item['image']);
    $items[] = $item;
  }


  //改筆訂單的資訊 (包括點數)
  $orders[$orderId] = [
    "created_at" => $order['created_at'],
    "total_amount" => $order['total_amount'],
    "points_used" => $order['points_used'],
    "complained" => $order['complained'],
    "items" => $items
  ];
}

echo json_encode(["success" => true, "orders" => $orders]);
?>
