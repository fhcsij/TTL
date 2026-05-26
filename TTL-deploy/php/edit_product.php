<?php
require_once __DIR__ . '/config.php';
session_start();

if (!isset($_SESSION['id'])) {
  echo json_encode(['success' => false, 'message' => '未登入']);
  exit;
}

$userId = $_SESSION['id'];
$id = $_POST['id'] ?? '';
$name = $_POST['name'] ?? '';
$price = $_POST['price'] ?? '';
$desc = $_POST['description'] ?? '';
$categoryId = $_POST['category_id'] ?? '';

if (!$id || !$name || !$price) {
  echo json_encode(['success' => false, 'message' => '缺少必要欄位']);
  exit;
}

$conn = get_db_connection();

if ($conn->connect_error) {
  echo json_encode(['success' => false, 'message' => '資料庫連線失敗']);
  exit;
}

$imagePath = '';
if (!empty($_FILES['image']['tmp_name'])) {
  $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
  $fileName = 'product_' . $id . '_' . time() . '.' . $ext;
  $uploadDir = '../Image/uploads/products/';
  $destination = $uploadDir . $fileName;

  // ✅ 若資料夾不存在，自動建立
  if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
  }

  // ✅ 圖片搬移成功才更新資料庫
  if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
    // $imagePath = 'Image/uploads/products/' . $fileName;
    $imagePath = $fileName;
  } else {
    echo json_encode(['success' => false, 'message' => '圖片儲存失敗']);
    exit;
  }
}

// ✅ 決定要不要更新圖片欄位
if ($imagePath) {
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
  echo json_encode(['success' => false, 'message' => '資料未變更或無權限']);
}

$stmt->close();
$conn->close();
