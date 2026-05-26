<?php
require_once __DIR__ . '/config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
  echo json_encode(["success" => false, "msg" => "未登入"]);
  exit;
}

$user_id = (int) $_SESSION["id"];
$name = trim((string) ($_POST["name"] ?? ''));
$price = $_POST["price"] ?? '';
$desc = (string) ($_POST["description"] ?? '');
$category_id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;

if ($name === '' || $price === '' || $category_id <= 0) {
  echo json_encode(["success" => false, "msg" => "請填寫完整商品資料"]);
  exit;
}

$conn = get_db_connection();

if ($conn->connect_error) {
  echo json_encode(["success" => false, "msg" => "資料庫連線失敗"]);
  exit;
}

$upload = ttl_store_product_upload($conn, $_FILES["image"] ?? null, "prod_");
if (!$upload['success']) {
  echo json_encode(["success" => false, "msg" => $upload['message']]);
  exit;
}

$imageName = $upload['filename'];
$stmt = $conn->prepare("INSERT INTO products (name, description, price, image, user_id, category_id) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssdsii", $name, $desc, $price, $imageName, $user_id, $category_id);

if ($stmt->execute()) {
  echo json_encode([
    "success" => true,
    "image" => $imageName,
    "name" => $name,
    "price" => $price
  ]);
} else {
  echo json_encode(["success" => false, "msg" => "商品新增失敗"]);
}
