<?php
require_once __DIR__ . '/config.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in first.']);
    exit;
}

$conn = get_db_connection();
$userId = (int) $_SESSION['id'];
$upload = ttl_store_avatar_upload($conn, $_FILES['avatar'] ?? null, $userId);

if (!($upload['success'] ?? false)) {
    echo json_encode(['success' => false, 'message' => $upload['message'] ?? 'Avatar upload failed.']);
    exit;
}

$fileName = (string) $upload['filename'];
$stmt = $conn->prepare('UPDATE users SET avatar=? WHERE id=?');
$stmt->bind_param('si', $fileName, $userId);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Unable to update avatar.']);
    exit;
}

$_SESSION['avatar'] = $fileName;

echo json_encode([
    'success' => true,
    'avatar' => $fileName,
    'path' => ttl_avatar_url($fileName),
]);
