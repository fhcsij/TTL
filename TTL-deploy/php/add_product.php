<?php
require_once __DIR__ . '/config.php';
session_start();

// ✅ 確認登入
if (!isset($_SESSION['id'])) {
  echo json_encode(["success" => false, "msg" => "未登入"]);
  exit;
}

$user_id = $_SESSION["id"]; // ✅ 修正這裡

// ✅ 連接資料庫
$conn = get_db_connection();
if ($conn->connect_error) {
  echo json_encode(["success" => false, "msg" => "連線失敗：" . $conn->connect_error]);
  exit;
}

// ✅ 處理圖片上傳
$uploadDir = "../Image/uploads/products/";
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

if (!isset($_FILES["image"]) || $_FILES["image"]["error"] !== 0) {
  echo json_encode(["success" => false, "msg" => "圖片上傳失敗"]);
  exit;
}

$ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'gif'];
if (!in_array($ext, $allowed)) {
  echo json_encode(["success" => false, "msg" => "不支援的圖片格式"]);
  exit;
}

$imageName = uniqid("prod_", true) . "." . $ext;
$imagePath = $uploadDir . $imageName;
if (!move_uploaded_file($_FILES["image"]["tmp_name"], $imagePath)) {
  echo json_encode(["success" => false, "msg" => "圖片儲存失敗"]);
  exit;
}

// ✅ 接收商品資料
$name = $_POST["name"] ?? '';
$price = $_POST["price"] ?? '';
$desc = $_POST["description"] ?? '';
//$category_id = 1; // 預設分類
$category_id = $_POST['category_id'];

// ✅ 防呆檢查
if (empty($name) || empty($price)) {
  echo json_encode(["success" => false, "msg" => "名稱與價格不能為空"]);
  exit;
}

// ✅ 寫入資料表
$stmt = $conn->prepare("INSERT INTO products (name, description, price, image, user_id, category_id) VALUES (?, ?, ?, ?, ?, ? )");
$stmt->bind_param("ssdsii", $name, $desc, $price, $imageName, $user_id, $category_id);

if ($stmt->execute()) {
  echo json_encode([
    "success" => true,
    "image" => $imageName,
    "name" => $name,
    "price" => $price
  ]);
} else {
  echo json_encode(["success" => false, "msg" => "資料寫入失敗：" . $stmt->error]);
}
?>
