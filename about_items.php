<?php
declare(strict_types=1);

$allowedOrigins = [
    "https://azhalitsolutions.com",
    "https://admin.azhalitsolutions.com",
    "http://localhost:4200"
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
}

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

include 'config.php'; // must define $conn (mysqli)

// helper
function jsonResponse($arr, $code = 200) {
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

if (!isset($conn) || ($conn instanceof mysqli && $conn->connect_errno)) {
    jsonResponse(['success' => false, 'error' => 'Database connection missing: ' . ($conn->connect_error ?? '')], 500);
}

// ---------------- GET ----------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
    if ($sectionId > 0) {
        $stmt = $conn->prepare("SELECT * FROM about_items WHERE section_id = ? ORDER BY created_at ASC");
        $stmt->bind_param("i", $sectionId);
    } else {
        $stmt = $conn->prepare("SELECT * FROM about_items ORDER BY created_at ASC");
    }
    if (!$stmt) jsonResponse(['success' => false, 'error' => $conn->error], 500);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    jsonResponse($rows, 200);
}

// ---------------- POST (create or update) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept JSON or form-encoded. Try to read JSON if content-type is application/json
    $input = $_POST;
    $raw = file_get_contents("php://input");
    if (empty($input) && $raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $input = array_merge($input, $decoded);
    }

    $methodOverride = $input['_method'] ?? null;
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $isUpdate = ($methodOverride && strtoupper($methodOverride) === 'PUT') || ($id > 0);

    // required fields
    $section_id = isset($input['section_id']) ? (int)$input['section_id'] : 0;
    $icon = trim($input['icon'] ?? '');
    $subtitle = trim($input['subtitle'] ?? '');
    $description = trim($input['description'] ?? '');
    $status = isset($input['status']) ? (int)$input['status'] : 1;

    if ($section_id <= 0 || $icon === '' || $subtitle === '' || $description === '') {
        jsonResponse(['success' => false, 'error' => 'section_id, icon, subtitle and description are required'], 400);
    }

    // Ensure referenced section exists (optional but recommended)
    $stmtCheck = $conn->prepare("SELECT id FROM about_sections WHERE id = ?");
    $stmtCheck->bind_param("i", $section_id);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();
    if (!$resCheck->fetch_assoc()) {
        $stmtCheck->close();
        jsonResponse(['success' => false, 'error' => 'Referenced section not found'], 400);
    }
    $stmtCheck->close();

    if ($isUpdate) {
        // UPDATE
        $stmt = $conn->prepare("UPDATE about_items SET section_id=?, icon=?, subtitle=?, description=?, status=? WHERE id=?");
        if (!$stmt) jsonResponse(['success'=>false,'error'=>$conn->error],500);
        $stmt->bind_param("isssii", $section_id, $icon, $subtitle, $description, $status, $id);
        if ($stmt->execute()) {
            $stmt->close();
            jsonResponse(['success' => true, 'message' => 'Item updated'], 200);
        } else {
            $err = $stmt->error;
            $stmt->close();
            jsonResponse(['success' => false, 'error' => $err], 500);
        }
    } else {
        // INSERT
        $stmt = $conn->prepare("INSERT INTO about_items (section_id, icon, subtitle, description, status) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) jsonResponse(['success'=>false,'error'=>$conn->error],500);
        $stmt->bind_param("isssi", $section_id, $icon, $subtitle, $description, $status);
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

// ---------------- DELETE ----------------
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = 0;
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
    } else {
        parse_str(file_get_contents("php://input"), $data);
        $id = (int)($data['id'] ?? 0);
    }
    if ($id <= 0) jsonResponse(['success' => false, 'error' => 'Invalid ID'], 400);

    $stmt = $conn->prepare("DELETE FROM about_items WHERE id = ?");
    if (!$stmt) jsonResponse(['success'=>false,'error'=>$conn->error],500);
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

jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
