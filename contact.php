<?php
// ---------- CORS ----------
$allowedOrigins = [
    "https://azhalitsolutions.com",
    "https://admin.azhalitsolutions.com",
    "http://localhost:4200"
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? "";
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
}
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// ---------- DB ----------
include 'config.php';
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

// ---------- CREATE ----------
if ($method === "POST") {
    if (!$data || !isset($data['name'], $data['email'], $data['subject'], $data['message'])) {
        echo json_encode(["success" => false, "message" => "Invalid input"]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message, status) VALUES (?, ?, ?, ?, 'new')");
    $stmt->bind_param("ssss", $data['name'], $data['email'], $data['subject'], $data['message']);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Message created successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Insert failed", "error" => $stmt->error]);
    }
    $stmt->close();
}

// ---------- READ ----------
elseif ($method === "GET") {
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT * FROM contact_messages WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        echo json_encode($result ?: ["success" => false, "message" => "Message not found"]);
        $stmt->close();
    } else {
        $result = $conn->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        echo json_encode($messages);
    }
}

// ---------- UPDATE ----------
elseif ($method === "PUT") {
    if (!isset($_GET['id'])) {
        echo json_encode(["success" => false, "message" => "ID required"]);
        exit;
    }
    $id = intval($_GET['id']);

    if (!$data) {
        echo json_encode(["success" => false, "message" => "Invalid input"]);
        exit;
    }

    // Update status if provided, else update full message
    if (isset($data['status'])) {
        $stmt = $conn->prepare("UPDATE contact_messages SET status=? WHERE id=?");
        $stmt->bind_param("si", $data['status'], $id);
    } elseif (isset($data['name'], $data['email'], $data['subject'], $data['message'])) {
        $stmt = $conn->prepare("UPDATE contact_messages SET name=?, email=?, subject=?, message=? WHERE id=?");
        $stmt->bind_param("ssssi", $data['name'], $data['email'], $data['subject'], $data['message'], $id);
    } else {
        echo json_encode(["success" => false, "message" => "Nothing to update"]);
        exit;
    }

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Updated successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Update failed", "error" => $stmt->error]);
    }
    $stmt->close();
}

// ---------- DELETE ----------
elseif ($method === "DELETE") {
    if (!isset($_GET['id'])) {
        echo json_encode(["success" => false, "message" => "ID required"]);
        exit;
    }
    $id = intval($_GET['id']);

    $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Message deleted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Delete failed", "error" => $stmt->error]);
    }
    $stmt->close();
}

$conn->close();
