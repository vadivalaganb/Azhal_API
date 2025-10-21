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

// Include DB config
include 'config.php';

// Helper to send JSON
function jsonResponse($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// --- GET: Fetch all categories with blog count ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = "
        SELECT c.id, c.name, c.slug, COUNT(b.id) AS blog_count
        FROM categories c
        LEFT JOIN blogs b ON b.category_id = c.id
        GROUP BY c.id, c.name, c.slug
        ORDER BY c.name ASC;
    ";

    $result = $conn->query($query);
    if (!$result) {
        jsonResponse(['success' => false, 'error' => $conn->error], 500);
    }

    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }

    jsonResponse(['success' => true, 'data' => $categories]);
}

// --- POST: Add new category ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');

    if ($name === '' || $slug === '') {
        jsonResponse(['success' => false, 'error' => 'Name and slug are required'], 400);
    }

    $stmt = $conn->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
    if (!$stmt) {
        jsonResponse(['success' => false, 'error' => $conn->error], 500);
    }

    $stmt->bind_param("ss", $name, $slug);
    if ($stmt->execute()) {
        jsonResponse(['success' => true, 'id' => $stmt->insert_id, 'message' => 'Category added']);
    } else {
        jsonResponse(['success' => false, 'error' => $stmt->error], 500);
    }
}

// --- PUT: Update category ---
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    parse_str(file_get_contents("php://input"), $_PUT);

    $id = (int)($_PUT['id'] ?? 0);
    $name = trim($_PUT['name'] ?? '');
    $slug = trim($_PUT['slug'] ?? '');

    if ($id <= 0 || $name === '' || $slug === '') {
        jsonResponse(['success' => false, 'error' => 'Invalid parameters'], 400);
    }

    $stmt = $conn->prepare("UPDATE categories SET name=?, slug=? WHERE id=?");
    if (!$stmt) {
        jsonResponse(['success' => false, 'error' => $conn->error], 500);
    }

    $stmt->bind_param("ssi", $name, $slug, $id);
    if ($stmt->execute()) {
        jsonResponse(['success' => true, 'message' => 'Category updated']);
    } else {
        jsonResponse(['success' => false, 'error' => $stmt->error], 500);
    }
}

// --- DELETE: Remove category ---
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str(file_get_contents("php://input"), $_DELETE);
    $id = (int)($_DELETE['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid ID'], 400);
    }

    $stmt = $conn->prepare("DELETE FROM categories WHERE id=?");
    if (!$stmt) {
        jsonResponse(['success' => false, 'error' => $conn->error], 500);
    }

    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        jsonResponse(['success' => true, 'message' => 'Category deleted']);
    } else {
        jsonResponse(['success' => false, 'error' => $stmt->error], 500);
    }
}

// --- Fallback ---
jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
?>
