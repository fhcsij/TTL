<?php
require_once __DIR__ . '/config.php';
session_start();
if (!isset($_SESSION['id'])) {
  echo json_encode(["success" => false, "message" => "未登入"]);
  exit;
}

$conn = get_db_connection();
$userId = $_SESSION['id'];
$productId = $_POST['product_id'] ?? 0;

$stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ? LIMIT 1");
$stmt->bind_param("ii", $userId, $productId);
$success = $stmt->execute();

echo json_encode(["success" => $success]);
