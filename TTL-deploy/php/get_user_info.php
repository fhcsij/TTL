<?php
require_once __DIR__ . '/config.php';
session_start();
if (!isset($_SESSION['id']) || !isset($_SESSION['name'])) {
    echo json_encode(["success" => false, "message" => "未登入"]);
    exit;
}

$conn = get_db_connection();
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "連線失敗"]);
    exit;
}

$userId = $_SESSION['id'];
$stmt = $conn->prepare("SELECT name, avatar, points FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$avatar = $user["avatar"] ?? null;

echo json_encode([
    "success" => true,
    "name" => $user["name"],
    "avatar" => ttl_avatar_filename($avatar),
    "avatar_url" => ttl_avatar_url($avatar),
    "points" => intval($user["points"])
]);
