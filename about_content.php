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

// Show errors for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Include DB config (must define $conn)
include 'config.php';

// Helper to send JSON and exit
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

/**
 * Handle file upload from input name (default 'file').
 * Returns ['success'=>bool, 'path'=>string] or ['success'=>false,'error'=>string]
 */
function handleFileUpload($fileInputName = 'file')
{
    if (!isset($_FILES[$fileInputName])) {
        return ['success' => false, 'error' => 'No file field present'];
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
    $unique = uniqid('', true) . '_' . $safeName;
    $targetFile = $targetDir . $unique;

    if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
        return ['success' => false, 'error' => 'move_uploaded_file failed'];
    }

    // return relative path to store in DB (adjust to your public path if needed)
    return ['success' => true, 'path' => 'uploads/' . $unique];
}

/**
 * Remove a file if it exists and is inside uploads/ folder.
 */
function removeUploadedFileIfExists(string $relativePath)
{
    // avoid deleting paths outside uploads; only allow relative paths starting with uploads/
    if (!$relativePath) return;
    $relativePath = str_replace(['../', '..\\'], '', $relativePath);
    if (strpos($relativePath, 'uploads/') !== 0) return;
    $full = __DIR__ . '/' . $relativePath;
    if (is_file($full)) {
        @unlink($full);
    }
}

// ----------------- GET: list all sections -----------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT id, section_key, header_name, description, file_path, status, created_at, updated_at
            FROM about_sections
            ORDER BY created_at DESC";
    $result = $conn->query($sql);
    if (!$result) {
        jsonResponse(['success' => false, 'error' => $conn->error], 500);
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    jsonResponse($rows, 200);
}

// ----------------- POST: create or update -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // allow method override via _method
    $rawMethod = $_POST['_method'] ?? null;
    $isUpdate = false;
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($rawMethod && strtoupper($rawMethod) === 'PUT') {
        $isUpdate = true;
    } elseif ($id > 0) {
        $isUpdate = true;
    }

    // common fields
    $section_key = $_POST['section_key'] ?? '';
    $header_name = $_POST['header_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;

    if (trim($section_key) === '' || trim($header_name) === '' || trim($description) === '') {
        jsonResponse(['success' => false, 'error' => 'section_key, header_name and description are required'], 400);
    }

    // If a file was uploaded, handle it; otherwise detect existing_file to keep it (update case)
    $file_path = null;
    if (!empty($_FILES['file']['name'])) {
        $uploadResult = handleFileUpload('file');
        if (!$uploadResult['success']) {
            jsonResponse(['success' => false, 'error' => $uploadResult['error']], 400);
        }
        $file_path = $uploadResult['path'];
    } else {
        $file_path = $_POST['existing_file'] ?? null;
    }

    if ($isUpdate) {
        // fetch current record to get old file path (so we can delete if replaced)
        $stmtFetch = $conn->prepare("SELECT file_path FROM about_sections WHERE id = ?");
        if (!$stmtFetch) jsonResponse(['success' => false, 'error' => 'Prepare failed: ' . $conn->error], 500);
        $stmtFetch->bind_param("i", $id);
        $stmtFetch->execute();
        $resFetch = $stmtFetch->get_result();
        $current = $resFetch->fetch_assoc() ?: null;
        $stmtFetch->close();

        $oldFile = $current['file_path'] ?? null;

        // Update query: include file_path only if we have a value, otherwise leave as is
        if ($file_path !== null) {
            $stmt = $conn->prepare("UPDATE about_sections SET section_key = ?, header_name = ?, description = ?, file_path = ?, status = ? WHERE id = ?");
            if (!$stmt) jsonResponse(['success' => false, 'error' => 'Prepare failed: ' . $conn->error], 500);
            $stmt->bind_param("ssssii", $section_key, $header_name, $description, $file_path, $status, $id);
        } else {
            $stmt = $conn->prepare("UPDATE about_sections SET section_key = ?, header_name = ?, description = ?, status = ? WHERE id = ?");
            if (!$stmt) jsonResponse(['success' => false, 'error' => 'Prepare failed: ' . $conn->error], 500);
            $stmt->bind_param("sssii", $section_key, $header_name, $description, $status, $id);
        }

        if ($stmt->execute()) {
            $stmt->close();
            // If new file uploaded and old file exists, delete old file
            if ($file_path !== null && $oldFile) {
                removeUploadedFileIfExists($oldFile);
            }
            jsonResponse(['success' => true, 'message' => 'Record updated'], 200);
        } else {
            $err = $stmt->error;
            $stmt->close();
            jsonResponse(['success' => false, 'error' => $err], 500);
        }
    } else {
        // INSERT
        $stmt = $conn->prepare("INSERT INTO about_sections (section_key, header_name, description, file_path, status) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) jsonResponse(['success' => false, 'error' => 'Prepare failed: ' . $conn->error], 500);
        // file_path may be null; bind as string or null
        $fp = $file_path;
        $stmt->bind_param("ssssi", $section_key, $header_name, $description, $fp, $status);
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

    // prefer query string id
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
    } else {
        parse_str(file_get_contents("php://input"), $data);
        $id = (int)($data['id'] ?? 0);
    }

    if ($id <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid ID'], 400);
    }

    // fetch file path to remove file
    $stmtFetch = $conn->prepare("SELECT file_path FROM about_sections WHERE id = ?");
    if (!$stmtFetch) jsonResponse(['success' => false, 'error' => $conn->error], 500);
    $stmtFetch->bind_param("i", $id);
    $stmtFetch->execute();
    $resFetch = $stmtFetch->get_result();
    $row = $resFetch->fetch_assoc() ?: null;
    $stmtFetch->close();
    $oldFile = $row['file_path'] ?? null;

    $stmt = $conn->prepare("DELETE FROM about_sections WHERE id = ?");
    if (!$stmt) jsonResponse(['success' => false, 'error' => $conn->error], 500);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $stmt->close();
        if ($oldFile) {
            removeUploadedFileIfExists($oldFile);
        }
        jsonResponse(['success' => true], 200);
    } else {
        $err = $stmt->error;
        $stmt->close();
        jsonResponse(['success' => false, 'error' => $err], 500);
    }
}

// If none matched
jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
