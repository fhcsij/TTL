<?php
require_once __DIR__ . '/config.php';
session_start();
if (!isset($_SESSION['id'])) {
  echo json_encode(['success' => false, 'message' => '未登入']);
  exit;
}

$userId = $_SESSION['id'];
$productId = $_POST['id'] ?? null;

if (!$productId) {
  echo json_encode(['success' => false, 'message' => '缺少商品 ID']);
  exit;
}

$conn = get_db_connection();
$stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $productId, $userId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
  echo json_encode(['success' => true]);
} else {
  echo json_encode(['success' => false, 'message' => '刪除失敗或無權限']);
}

$stmt->close();
$conn->close();
