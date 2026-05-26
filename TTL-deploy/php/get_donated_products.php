<?php
require_once __DIR__ . '/config.php';

$conn = get_db_connection();

$sql = "
  SELECT p.id, p.name, p.description, p.price, p.image,
         c.name AS category_name,
         u.name AS donor_name
  FROM products p
  LEFT JOIN categories c ON p.category_id = c.id
  LEFT JOIN users u ON p.user_id = u.id
  WHERE p.donated = 1
  ORDER BY p.created_at DESC
";

$result = $conn->query($sql);
$products = [];
$timestamp = time();

while ($row = $result->fetch_assoc()) {
  $row['image'] = "Image/uploads/products/" . $row['image'] . "?t=" . $timestamp;
  $products[] = $row;
}

echo json_encode(["success" => true, "products" => $products]);
?>
