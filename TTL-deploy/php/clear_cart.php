<?php
require_once __DIR__ . '/config.php';
session_start();
if (!isset($_SESSION['id'])) {
  echo json_encode(["success" => false, "message" => "未登入"]);
  exit;
}

$conn = get_db_connection();
$userId = $_SESSION['id'];

$stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$success = $stmt->execute();

echo json_encode(["success" => $success]);
