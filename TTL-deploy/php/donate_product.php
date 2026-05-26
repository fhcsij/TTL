<?php
require_once __DIR__ . '/config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
  echo json_encode(["success" => false, "msg" => "未登入"]);
  exit;
}

$userId = (int) $_SESSION['id'];
$name = trim((string) ($_POST["name"] ?? ''));
$description = (string) ($_POST["description"] ?? '');
$categoryId = isset($_POST["category_id"]) && is_numeric($_POST["category_id"]) ? (int) $_POST["category_id"] : 0;

if ($name === '') {
  echo json_encode(["success" => false, "msg" => "請填寫捐贈品名稱"]);
  exit;
}

if ($categoryId <= 0) {
  echo json_encode(["success" => false, "msg" => "請選擇分類"]);
  exit;
}

$conn = get_db_connection();
if ($conn->connect_error) {
  echo json_encode(["success" => false, "msg" => "資料庫連線失敗"]);
  exit;
}

$upload = ttl_store_product_upload($conn, $_FILES["image"] ?? null, "donate_");
if (!$upload['success']) {
  echo json_encode(["success" => false, "msg" => $upload['message']]);
  exit;
}

$filename = $upload['filename'];
$stmt = $conn->prepare("INSERT INTO products (user_id, name, description, image, category_id, price, donated) VALUES (?, ?, ?, ?, ?, 0, 1)");
$stmt->bind_param("isssi", $userId, $name, $description, $filename, $categoryId);

if ($stmt->execute()) {
  $conn->query("UPDATE users SET points = points + 10 WHERE id = $userId");
  echo json_encode(["success" => true]);
} else {
  echo json_encode(["success" => false, "msg" => "捐贈資料儲存失敗"]);
}
