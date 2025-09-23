<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

include 'config.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['name']) || !isset($data['contact']) || !isset($data['subject']) || !isset($data['message'])) {
    echo json_encode(["success" => false, "message" => "Invalid input"]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO contact_messages (name, contact, subject, message) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $data['name'], $data['contact'], $data['subject'], $data['message']);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Message sent successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to save message", "error" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
