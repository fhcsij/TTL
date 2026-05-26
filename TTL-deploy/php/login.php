<?php
require_once __DIR__ . '/config.php';
session_start();

// 1. 資料庫連線資訊 (建議之後寫在 config.php 再用 include 引入)
// 如果是上傳到 InfinityFree，請將下面的值換成你在控制面板看到的
$conn = get_db_connection();

if ($conn->connect_error) {
    die("連線失敗：" . $conn->connect_error);
}

// 2. 檢查是否有 POST 提交，避免直接打開網頁報錯
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // 3. 查詢帳號
    $stmt = $conn->prepare("SELECT id, name, password, role, avatar FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($id, $name, $hashed, $role, $avatar);
        $stmt->fetch();

        // 4. 驗證密碼
        if (password_verify($password, $hashed)) {
            // ✅ 登入成功，設定 Session
            $_SESSION['id'] = $id;
            $_SESSION['name'] = $name;
            $_SESSION['role'] = $role;
            $_SESSION['avatar'] = $avatar;

            // 5. 根據角色導向
            if ($role === 'buyer') {
                header("Location: ../buyer.html");
                exit(); // 加上 exit() 確保導向後停止執行後面程式碼
            } else if ($role === 'seller') {
                header("Location: ../seller.html");
                exit();
            } else {
                echo "❌ 無效的角色";
            }
        } else {
            echo "❌ 密碼錯誤";
        }
    } else {
        echo "❌ 找不到帳號";
    }
    $stmt->close();
}
$conn->close();
?>
