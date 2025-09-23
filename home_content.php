<?php
// home_content.php
declare(strict_types=1);

include 'config.php'; // must define $conn (mysqli)
header('Content-Type: application/json');

// Allow cross-origin requests while developing (adjust origin for production)

// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
// header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');

header('Access-Control-Allow-Origin: https://azhalitsolutions.com');
header('Access-Control-Allow-Origin: https://admin.azhalitsolutions.com');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');


// Short-circuit preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Show errors for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// helper to send JSON
function jsonResponse($arr, $code = 200) {
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

// Verify DB connection
if (!isset($conn) || !$conn) {
    jsonResponse(['success' => false, 'error' => 'Database connection missing'], 500);
}

// upload helper
function handleFileUpload($fileInputName = 'file') {
    if (!isset($_FILES[$fileInputName])) {
        return ['success' => false, 'error' => 'No file field present', 'files' => $_FILES];
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

    // return relative path for DB (adjust if you want public URL)
    $relative = 'uploads/' . basename($targetFile);
    return ['success' => true, 'path' => $relative];
}


// --- CREATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    $header_name = $_POST['header_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;

    if (trim($header_name) === '' || trim($description) === '') {
        jsonResponse(['success' => false, 'error' => 'Header and description required'], 400);
    }

    $uploadResult = handleFileUpload('file');
    if (!$uploadResult['success']) {
        // send debug info so you can see why it failed (remove verbose debug later)
        jsonResponse(['success' => false, 'error' => $uploadResult['error'], 'debug' => $uploadResult], 400);
    }
    $file_path = $uploadResult['path'];

    $stmt = $conn->prepare("INSERT INTO home_contents (header_name, description, file_path, status) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        jsonResponse(['success' => false, 'error' => 'Prepare failed: ' . $conn->error], 500);
    }

    if (!$stmt->bind_param("sssi", $header_name, $description, $file_path, $status)) {
        jsonResponse(['success' => false, 'error' => 'bind_param failed: ' . $stmt->error], 500);
    }

    if ($stmt->execute()) {
        $newId = $stmt->insert_id ?? $conn->insert_id;
        $stmt->close();
        jsonResponse(['success' => true, 'id' => $newId], 201);
    } else {
        $err = $stmt->error;
        $stmt->close();
        jsonResponse(['success' => false, 'error' => 'Execute failed: ' . $err], 500);
    }
}

// --- READ ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query("SELECT * FROM home_contents ORDER BY created_at DESC");
    if (!$result) jsonResponse(['success' => false, 'error' => $conn->error], 500);
    $data = [];
    while ($row = $result->fetch_assoc()) $data[] = $row;
    jsonResponse($data, 200);
}

// --- UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    parse_str(file_get_contents("php://input"), $_PUT);
    $id = (int)($_PUT['id'] ?? 0);
    $header_name = $_PUT['header_name'] ?? '';
    $description = $_PUT['description'] ?? '';
    $status = isset($_PUT['status']) ? (int)$_PUT['status'] : 0;

    if ($id <= 0) jsonResponse(['success' => false, 'error' => 'Invalid ID'], 400);

    $stmt = $conn->prepare("UPDATE home_contents SET header_name=?, description=?, status=? WHERE id=?");
    if (!$stmt) jsonResponse(['success' => false, 'error' => $conn->error], 500);
    $stmt->bind_param("ssii", $header_name, $description, $status, $id);
    if ($stmt->execute()) {
        $stmt->close();
        jsonResponse(['success' => true], 200);
    } else {
        $err = $stmt->error;
        $stmt->close();
        jsonResponse(['success' => false, 'error' => $err], 500);
    }
}

// --- DELETE ---
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str(file_get_contents("php://input"), $_DELETE);
    $id = (int)($_DELETE['id'] ?? 0);
    if ($id <= 0) jsonResponse(['success' => false, 'error' => 'Invalid ID'], 400);

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

// If we reach here, method not allowed
jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
