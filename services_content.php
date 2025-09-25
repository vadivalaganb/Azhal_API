<?php
// ----------------- CORS -----------------
$allowedOrigins = [
    "https://azhalitsolutions.com",
    "https://admin.azhalitsolutions.com",
    "http://localhost:4200"
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ----------------- Content type & Errors -----------------
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', '1');

// ----------------- Database -----------------
include 'config.php'; // must define $conn (mysqli)

function jsonResponse($arr, $code = 200)
{
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

// Verify DB connection
if (!isset($conn) || ($conn instanceof mysqli && $conn->connect_errno)) {
    jsonResponse(['success' => false, 'error' => 'Database connection missing: ' . ($conn->connect_error ?? '')], 500);
}

// ----------------- GET -----------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT id, icon, header_name, description, status, created_at, updated_at 
            FROM services_content ORDER BY created_at DESC";
    $result = $conn->query($sql);
    if (!$result) jsonResponse(['success' => false, 'error' => $conn->error], 500);

    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    jsonResponse($rows, 200);
}

// ----------------- POST / PUT -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept data either as application/x-www-form-urlencoded OR JSON
    $input = $_POST;
    if (empty($input)) {
        $json = file_get_contents('php://input');
        $input = json_decode($json, true);
        if (!is_array($input)) $input = [];
    }

    $rawMethod   = $input['_method'] ?? null;
    $id          = isset($input['id']) ? (int)$input['id'] : 0;
    $isUpdate    = ($rawMethod && strtoupper($rawMethod) === 'PUT') || ($id > 0);

    $icon        = trim($input['icon'] ?? '');
    $header_name = trim($input['header_name'] ?? '');
    $description = trim($input['description'] ?? '');
    $status      = isset($input['status']) ? (int)$input['status'] : 1;

    if ($icon === '' || $header_name === '' || $description === '') {
        jsonResponse(['success' => false, 'error' => 'icon, header_name and description are required'], 400);
    }

    if ($isUpdate) {
        $stmt = $conn->prepare("UPDATE services_content SET icon=?, header_name=?, description=?, status=? WHERE id=?");
        if (!$stmt) jsonResponse(['success' => false, 'error' => $conn->error], 500);
        $stmt->bind_param("sssii", $icon, $header_name, $description, $status, $id);
        if ($stmt->execute()) {
            $stmt->close();
            jsonResponse(['success' => true, 'message' => 'Record updated'], 200);
        } else {
            $err = $stmt->error;
            $stmt->close();
            jsonResponse(['success' => false, 'error' => $err], 500);
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO services_content (icon, header_name, description, status) VALUES (?, ?, ?, ?)");
        if (!$stmt) jsonResponse(['success' => false, 'error' => $conn->error], 500);
        $stmt->bind_param("sssi", $icon, $header_name, $description, $status);
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $stmt->close();
            jsonResponse(['success' => true, 'id' => $newId], 201);
        } else {
            $err = $stmt->error;
            $stmt->close();
            jsonResponse(['success' => false, 'error' => $err], 500);
        }
    }
}

// ----------------- DELETE -----------------
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = 0;
    if (isset($_GET['id'])) $id = (int)$_GET['id'];
    else parse_str(file_get_contents("php://input"), $data) && $id = (int)($data['id'] ?? 0);

    if ($id <= 0) jsonResponse(['success' => false, 'error' => 'Invalid ID'], 400);

    $stmt = $conn->prepare("DELETE FROM services_content WHERE id=?");
    if (!$stmt) jsonResponse(['success' => false, 'error' => $conn->error], 500);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $stmt->close();
        jsonResponse(['success' => true], 200);
    } else {
        $err = $stmt->error;
        $stmt->close();
        jsonResponse(['success' => false, 'error' => $err], 500);
    }
}

// ----------------- Method Not Allowed -----------------
jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
