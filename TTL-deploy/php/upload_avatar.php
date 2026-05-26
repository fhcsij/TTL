<?php
require_once __DIR__ . '/config.php';
session_start();
if (!isset($_SESSION['id'])) {
    echo json_encode(["success" => false, "message" => "未登入"]);
    exit();
}

$userId = $_SESSION['id'];

if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $tmp = $_FILES['avatar']['tmp_name'];
    $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
    $fileName = 'avatar_' . $userId . '.' . $ext;
    $destination = '../Image/uploads/' . $fileName;


    if (move_uploaded_file($tmp, $destination)) {
        // ✅ 更新資料庫
        $conn = get_db_connection();
        $stmt = $conn->prepare("UPDATE users SET avatar=? WHERE id=?");
        $stmt->bind_param("si", $fileName, $userId);
        $stmt->execute();

        // ✅ 更新 session
        $_SESSION['avatar'] = $fileName;

        echo json_encode(["success" => true, "path" => "Image/uploads/" . $fileName]);
    } else {
        echo json_encode(["success" => false, "message" => "儲存失敗"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "檔案錯誤"]);
}
?>
