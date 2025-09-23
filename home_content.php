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

// Show errors for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Include DB config (must define $conn)
include 'config.php';

function jsonResponse($arr, $code = 200)
{
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

if (!isset($conn) || $conn->connect_errno) {
    jsonResponse(['success' => false, 'error' => 'Database connection missing: ' . ($conn->connect_error ?? '')], 500);
}

// file upload helper
function handleFileUpload($fileInputName = 'file')
{
    if (!isset($_FILES[$fileInputName]) || empty($_FILES[$fileInputName]['name'])) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }
    $file = $_FILES[$fileInputName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error code: ' . $file['error']];
    }

    $targetDir = __DIR__ . '/uploads/';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
        return ['success' => false, 'error' => 'Cannot create uploads directory'];
    }
    if (!is_writable($targetDir)) {
        return ['success' => false, 'error' => 'Uploads directory not writable'];
    }

    $fileName = basename($file['name']);
    $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $fileName);
    $targetFile = $targetDir . uniqid('', true) . '_' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
        return ['success' => false, 'error' => 'move_uploaded_file failed'];
    }

    // return relative path for DB
    $relative = 'uploads/' . basename($targetFile);
    return ['success' => true, 'path' => $relative];
}

// --- READ ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query("SELECT * FROM home_contents ORDER BY created_at DESC");
    if (!$result) jsonResponse(['success' => false, 'error' => $conn->error], 500);
    $data = [];
    while ($row = $result->fetch_assoc()) $data[] = $row;
    jsonResponse($data, 200);
}

// --- POST (CREATE or UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $header_name = trim($_POST['header_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;

    if ($header_name === '' || $description === '') {
        jsonResponse(['success' => false, 'error' => 'Header and description required'], 400);
    }

    // Determine file path: prefer new uploaded file, else use existing_file if provided
    $file_path = null;
    if (isset($_FILES['file']) && !empty($_FILES['file']['name'])) {
        $uploadResult = handleFileUpload('file');
        if (!$uploadResult['success']) {
            jsonResponse(['success' => false, 'error' => $uploadResult['error'], 'debug' => $uploadResult], 400);
        }
        $file_path = $uploadResult['path'];
    } else {
        // keep existing file if provided (from client)
        $file_path = $_POST['existing_file'] ?? null;
    }

    if ($id > 0) {
        // UPDATE
        if ($file_path !== null && $file_path !== '') {
            $stmt = $conn->prepare("UPDATE home_contents SET header_name=?, description=?, file_path=?, status=? WHERE id=?");
            if (!$stmt) jsonResponse(['success' => false, 'error' => 'Prepare failed: ' . $conn->error], 500);
            if (!$stmt->bind_param("sssii", $header_name, $description, $file_path, $status, $id)) {
                jsonResponse(['success' => false, 'error' => 'bind_param failed: ' . $stmt->error], 500);
            }
        } else {
            $stmt = $conn->prepare("UPDATE home_contents SET header_name=?, description=?, status=? WHERE id=?");
            if (!$stmt) jsonResponse(['success' => false, 'error' => 'Prepare failed: ' . $conn->error], 500);
            if (!$stmt->bind_param("ssii", $header_name, $description, $status, $id)) {
                jsonResponse(['success' => false, 'error' => 'bind_param failed: ' . $stmt->error], 500);
            }
        }

        if ($stmt->execute()) {
            $stmt->close();
            jsonResponse(['success' => true, 'message' => 'Record updated']);
        } else {
            $err = $stmt->error;
            $stmt->close();
            jsonResponse(['success' => false, 'error' => $err], 500);
        }
    } else {
        // INSERT
        $stmt = $conn->prepare("INSERT INTO home_contents (header_name, description, file_path, status) VALUES (?, ?, ?, ?)");
        if (!$stmt) jsonResponse(['success' => false, 'error' => 'Prepare failed: ' . $conn->error], 500);
        if (!$stmt->bind_param("sssi", $header_name, $description, $file_path, $status)) {
            jsonResponse(['success' => false, 'error' => 'bind_param failed: ' . $stmt->error], 500);
        }
        if ($stmt->execute()) {
            $newId = $stmt->insert_id ?? $conn->insert_id;
            $stmt->close();
            jsonResponse(['success' => true, 'id' => $newId, 'message' => 'Record added'], 201);
        } else {
            $err = $stmt->error;
            $stmt->close();
            jsonResponse(['success' => false, 'error' => $err], 500);
        }
    }
}

// --- DELETE ---
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str(file_get_contents("php://input"), $_DELETE);
    $id = 0;
    if (isset($_GET['id'])) $id = (int)$_GET['id'];
    else $id = (int)($_DELETE['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid ID'], 400);
    }

    $stmt = $conn->prepare("DELETE FROM home_contents WHERE id=?");
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

// If nothing matched
jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
