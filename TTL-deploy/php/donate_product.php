<?php
require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

// ✅ 確認登入
if (!isset($_SESSION['id'])) {
  echo json_encode(["success" => false, "msg" => "未登入"]);
  exit;
}

$userId = $_SESSION['id'];

// ✅ 檢查欄位
if (empty($_POST["name"])) {
  echo json_encode(["success" => false, "msg" => "名稱不能為空"]);
  exit;
}

$categoryId = isset($_POST["category_id"]) && is_numeric($_POST["category_id"]) ? (int)$_POST["category_id"] : null;
if (is_null($categoryId)) {
  echo json_encode(["success" => false, "msg" => "請選擇分類"]);
  exit;
}

// ✅ 檢查圖片
if (!isset($_FILES["image"]) || $_FILES["image"]["error"] !== 0) {
  echo json_encode(["success" => false, "msg" => "圖片上傳失敗"]);
  exit;
}

// ✅ 處理圖片
$uploadDir = "../Image/uploads/products/";
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$ext = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
$filename = uniqid("donate_") . "." . $ext;
$filepath = $uploadDir . $filename;

if (!move_uploaded_file($_FILES["image"]["tmp_name"], $filepath)) {
  echo json_encode(["success" => false, "msg" => "圖片儲存失敗"]);
  exit;
}

// ✅ 連接資料庫
$conn = get_db_connection();
if ($conn->connect_error) {
  echo json_encode(["success" => false, "msg" => "連線失敗"]);
  exit;
}

// ✅ 插入資料（價格固定為 0）
$stmt = $conn->prepare("INSERT INTO products (user_id, name, description, image, category_id, price, donated) VALUES (?, ?, ?, ?, ?, 0, 1)");
$stmt->bind_param("isssi", $userId, $_POST["name"], $_POST["description"], $filename, $categoryId);

if ($stmt->execute()) {
  // 捐贈成功，加 10 點points
  $conn->query("UPDATE users SET points = points + 10 WHERE id = $userId");

  echo json_encode(["success" => true]);
} else {
  echo json_encode(["success" => false, "msg" => "資料儲存失敗：" . $conn->error]);
}

?>
