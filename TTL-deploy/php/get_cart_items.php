<?php
require_once __DIR__ . '/config.php';
session_start();
if (!isset($_SESSION['id'])) {
  echo json_encode(["success" => false, "message" => "未登入"]);
  exit;
}

$conn = get_db_connection();
$userId = $_SESSION['id'];

$sql = "
  SELECT p.id AS product_id, p.name, p.price, p.image
  FROM cart c
  JOIN products p ON c.product_id = p.id
  WHERE c.user_id = ?
  ORDER BY c.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
  $row['image'] = ttl_product_image_url($row['image']);
  $items[] = $row;
}

echo json_encode(["success" => true, "items" => $items]);
