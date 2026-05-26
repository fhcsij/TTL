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

$stmt = $conn->prepare("
  SELECT p.id, p.name, p.price, p.description, p.image, c.name AS category_name
  FROM products p
  LEFT JOIN categories c ON p.category_id = c.id
  WHERE p.user_id = ? AND p.donated = 1
  ORDER BY p.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$products = [];

while ($row = $result->fetch_assoc()) {
  $row['image'] = ttl_product_image_url($row['image'], false);
  $products[] = $row;
}

echo json_encode(["success" => true, "products" => $products]);
