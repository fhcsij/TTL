<?php
require_once __DIR__ . '/config.php';
session_start(); // 🔹 若未來要直接登入，可預留

// 資料庫連線
$conn = get_db_connection();
if ($conn->connect_error) die("連線失敗：" . $conn->connect_error);

// 接收表單資料
$name = $_POST['name'];
$username = $_POST['username'];
$password = $_POST['password'];
$confirm = $_POST['confirm'];

// ✅ 1. 檢查空值（可選）
if (empty($name) || empty($username) || empty($password) || empty($confirm)) {
    echo "❌ 所有欄位都必須填寫";
    exit;
}

// ✅ 2. 密碼一致性檢查
if ($password !== $confirm) {
    echo "❌ 密碼不一致";
    exit;
}

// ✅ 3. 檢查帳號是否存在
$check = $conn->prepare("SELECT id FROM users WHERE username = ?");
$check->bind_param("s", $username);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    echo "❌ 帳號已存在";
    exit;
}

// ✅ 4. 加密密碼
$hashed = password_hash($password, PASSWORD_DEFAULT);

// ✅ 5. 預設頭像欄位為 default.png（要確保 DB 有 avatar 欄位）
$stmt = $conn->prepare("INSERT INTO users (name, username, password, role, avatar) VALUES (?, ?, ?, 'buyer', ?)");
$defaultAvatar = "default.png";
$stmt->bind_param("ssss", $name, $username, $hashed, $defaultAvatar);

// ✅ 6. 寫入成功處理
if ($stmt->execute()) {
    header("Location: ../login.html"); // ✅ 註冊成功跳轉登入頁
    exit;
} else {
    echo "❌ 註冊失敗：" . $stmt->error;
}
?>
