<?php

declare(strict_types=1);

// ----------------------
// CORS & Headers
// ----------------------
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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

include 'config.php'; // $conn should be defined here

if (!isset($conn) || $conn->connect_errno) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection error']);
    exit;
}

// ----------------------
// Helpers
// ----------------------
function jsonResponse($arr, $code = 200)
{
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

function handleFileUpload($fileInputName = 'file')
{
    if (!isset($_FILES[$fileInputName])) return ['success' => false, 'error' => 'No file uploaded'];

    $file = $_FILES[$fileInputName];
    if ($file['error'] !== UPLOAD_ERR_OK) return ['success' => false, 'error' => 'Upload error code: ' . $file['error']];

    $targetDir = __DIR__ . '/uploads/';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) return ['success' => false, 'error' => 'Cannot create uploads dir'];

    $fileName = basename($file['name']);
    $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $fileName);
    $targetFile = $targetDir . uniqid('', true) . '_' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $targetFile)) return ['success' => false, 'error' => 'Failed to move file'];

    return ['success' => true, 'path' => 'uploads/' . basename($targetFile)];
}

function generateSlug($string)
{
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string)));
}

// ----------------------
// GET Blogs
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';

    $query = "
        SELECT b.*, c.name AS category_name, c.slug AS category_slug
        FROM blogs b
        LEFT JOIN categories c ON b.category_id = c.id
        WHERE b.status = 1
    ";

    if ($category !== '') {
        $query .= " AND c.slug = '" . $conn->real_escape_string($category) . "'";
    }

    if ($search !== '') {
        $searchEsc = $conn->real_escape_string($search);
        $query .= " AND (b.header_name LIKE '%$searchEsc%' OR b.short_description LIKE '%$searchEsc%')";
    }

    $query .= " ORDER BY b.created_at DESC";

    $result = $conn->query($query);
    if (!$result) jsonResponse(['success' => false, 'error' => $conn->error], 500);

    $data = [];
    while ($row = $result->fetch_assoc()) $data[] = $row;
    jsonResponse(['success' => true, 'data' => $data]);
}

// ----------------------
// POST / CREATE / UPDATE
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $header_name = $_POST['header_name'] ?? '';
    $short_description = $_POST['short_description'] ?? '';
    $description = $_POST['description'] ?? '';
    $category_id = (int)($_POST['category_id'] ?? 0);
    $status = isset($_POST['status']) && ($_POST['status'] === 'true' || $_POST['status'] == '1') ? 1 : 0;

    if (trim($header_name) === '' || trim($description) === '') {
        jsonResponse(['success' => false, 'error' => 'Header and description required'], 400);
    }

    $slug = generateSlug($header_name);

    // Handle file upload
    $file_path = $_POST['existing_file'] ?? null;
    if (!empty($_FILES['file']['name'])) {
        $uploadResult = handleFileUpload('file');
        if (!$uploadResult['success']) jsonResponse(['success' => false, 'error' => $uploadResult['error']], 400);
        $file_path = $uploadResult['path'];
    }

    if ($id > 0) {
        // UPDATE
        $stmt = $conn->prepare("
            UPDATE blogs 
            SET header_name=?, slug=?, short_description=?, description=?, category_id=?, file_path=?, status=?, updated_at=NOW() 
            WHERE id=?
        ");
        $stmt->bind_param("ssssisii", $header_name, $slug, $short_description, $description, $category_id, $file_path, $status, $id);
        if ($stmt->execute()) jsonResponse(['success' => true, 'message' => 'Blog updated']);
        else jsonResponse(['success' => false, 'error' => $stmt->error], 500);
    } else {
        // INSERT
        $stmt = $conn->prepare("
            INSERT INTO blogs (header_name, slug, short_description, description, category_id, file_path, status)
            VALUES (?,?,?,?,?,?,?)
        ");
        $stmt->bind_param("ssssisi", $header_name, $slug, $short_description, $description, $category_id, $file_path, $status);
        if ($stmt->execute()) {
            jsonResponse(['success' => true, 'id' => $stmt->insert_id, 'message' => 'Blog created successfully']);
        } else {
            jsonResponse(['success' => false, 'error' => $stmt->error], 500);
        }
    }
}

// ----------------------
// DELETE
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid blog ID'], 400);
    }

    $stmt = $conn->prepare("DELETE FROM blogs WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        jsonResponse(['success' => true, 'message' => 'Blog deleted successfully']);
    } else {
        jsonResponse(['success' => false, 'error' => $stmt->error], 500);
    }
}

jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);

?>
