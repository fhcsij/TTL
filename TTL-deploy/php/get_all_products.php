<?php
require_once __DIR__ . '/config.php';

  $conn = get_db_connection();
  if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "連線失敗"]);
    exit;
  }

  $stmt = $conn->prepare("SELECT p.id, p.name, p.description, p.price, p.image, c.name AS category_name, u.name AS seller_name
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
LEFT JOIN users u ON p.user_id = u.id
WHERE p.donated = 0 AND p.is_sold = 0
ORDER BY p.created_at DESC
");
  $stmt->execute();
  $result = $stmt->get_result();

  $products = [];
  while ($row = $result->fetch_assoc()) {
    // 加上圖片完整路徑與時間戳記，防止快取
    $row['image'] = ttl_product_image_url($row['image']);
    $products[] = $row;
  }

  echo json_encode(["success" => true, "products" => $products]);
  ?> 


