<?php
require_once __DIR__ . '/config.php';
session_start();
if (!isset($_SESSION['id'])) {
  echo json_encode(["success" => false, "message" => "請先登入"]);
  exit;
}

$userId = $_SESSION['id'];
$productId = $_POST['product_id'];

$conn = get_db_connection();
if ($conn->connect_error) {
  echo json_encode(["success" => false, "message" => "連線失敗"]);
  exit;
}

// ✅ 檢查是否已加入過
$check = $conn->prepare("SELECT 1 FROM cart WHERE user_id = ? AND product_id = ?");
$check->bind_param("ii", $userId, $productId);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
  echo json_encode(["success" => false, "message" => "該商品已在購物車中"]);
  exit;
}
$check->close();

// ✅ 尚未存在才新增
$stmt = $conn->prepare("INSERT INTO cart (user_id, product_id) VALUES (?, ?)");
$stmt->bind_param("ii", $userId, $productId);
$success = $stmt->execute();

echo json_encode(["success" => $success]);
