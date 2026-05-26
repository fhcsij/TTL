<?php
require_once __DIR__ . '/config.php';
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION["id"])) {
  echo json_encode(["success" => false, "message" => "未登入"]);
  exit;
}

$conn = get_db_connection();
if ($conn->connect_error) {
  echo json_encode(["success" => false, "message" => "連線失敗"]);
  exit;
}

$userId = $_SESSION["id"];
$requestedPoints = intval($_POST["points_used"] ?? 0);
$totalBeforeDiscount = intval($_POST["total"] ?? 0);

// ✅ 1. 取得目前點數
$stmt = $conn->prepare("SELECT points FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
  echo json_encode(["success" => false, "message" => "找不到使用者"]);
  exit;
}

$currentPoints = intval($user["points"]);
$actualPointsUsed = min($requestedPoints, $currentPoints, $totalBeforeDiscount);
$finalTotal = $totalBeforeDiscount;

// ✅ 2. 扣除點數
$newPoints = $currentPoints - $actualPointsUsed;
$stmt = $conn->prepare("UPDATE users SET points = ? WHERE id = ?");
$stmt->bind_param("ii", $newPoints, $userId);
$stmt->execute();

// ✅ 3. 從購物車撈出商品
$stmt = $conn->prepare("SELECT c.product_id, p.name, p.price FROM cart c 
                        JOIN products p ON c.product_id = p.id 
                        WHERE c.user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
  $items[] = $row;
}
if (count($items) === 0) {
  echo json_encode(["success" => false, "message" => "購物車為空"]);
  exit;
}

// ✅ 4. 建立訂單（注意 total_price 替代 total_amount）
$stmt = $conn->prepare("INSERT INTO orders (user_id, total_price, points_used, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iii", $userId, $finalTotal, $actualPointsUsed);
$stmt->execute();
$orderId = $stmt->insert_id;

// ✅ 5. 寫入 order_items
$stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
foreach ($items as $item) {
  $productId = $item['product_id'];
  $price = $item['price'];
  $qty = 1;
  $stmt_item->bind_param("iiid", $orderId, $productId, $qty, $price);
  $stmt_item->execute();
}

// ✅ 6. 商品標記為已售出
foreach ($items as $item) {
  $productId = $item['product_id'];
  $conn->query("UPDATE products SET is_sold = 1 WHERE id = $productId");
}

// ✅ 7. 清空購物車
$conn->query("DELETE FROM cart WHERE user_id = $userId");

// ✅ 8. 回傳成功
echo json_encode([
  "success" => true,
  "final_total" => $finalTotal,
  "points_used" => $actualPointsUsed,
  "remaining_points" => $newPoints
]);
?>
