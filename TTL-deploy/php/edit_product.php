<?php
require_once __DIR__ . '/config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
  echo json_encode(['success' => false, 'message' => '未登入']);
  exit;
}

$userId = (int) $_SESSION['id'];
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$name = trim((string) ($_POST['name'] ?? ''));
$price = $_POST['price'] ?? '';
$desc = (string) ($_POST['description'] ?? '');
$categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;

if ($id <= 0 || $name === '' || $price === '' || $categoryId <= 0) {
  echo json_encode(['success' => false, 'message' => '請填寫完整商品資料']);
  exit;
}

$conn = get_db_connection();

if ($conn->connect_error) {
  echo json_encode(['success' => false, 'message' => '資料庫連線失敗']);
  exit;
}

$imagePath = '';
if (!empty($_FILES['image']['tmp_name'])) {
  $upload = ttl_store_product_upload($conn, $_FILES['image'], 'product_' . $id . '_');
  if (!$upload['success']) {
    echo json_encode(['success' => false, 'message' => $upload['message']]);
    exit;
  }
  $imagePath = $upload['filename'];
}

if ($imagePath !== '') {
  $stmt = $conn->prepare("UPDATE products SET name=?, price=?, description=?, image=?, category_id=? WHERE id=? AND user_id=?");
  $stmt->bind_param("sissiii", $name, $price, $desc, $imagePath, $categoryId, $id, $userId);
} else {
  $stmt = $conn->prepare("UPDATE products SET name=?, price=?, description=?, category_id=? WHERE id=? AND user_id=?");
  $stmt->bind_param("sisiii", $name, $price, $desc, $categoryId, $id, $userId);
}

$stmt->execute();

if ($stmt->affected_rows > 0) {
  echo json_encode(['success' => true]);
} else {
  echo json_encode(['success' => false, 'message' => '沒有資料被更新']);
}

$stmt->close();
$conn->close();
