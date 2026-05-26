<?php
require_once __DIR__ . '/config.php';

$conn = get_db_connection();
$result = $conn->query("SELECT * FROM categories");

$categories = [];
while ($row = $result->fetch_assoc()) {
  $categories[] = $row;
}

echo json_encode(["success" => true, "categories" => $categories]);
?>
