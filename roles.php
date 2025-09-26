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
include 'config.php'; // $conn = new mysqli(...)

// -------- Get HTTP Method and ID --------
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

$input = json_decode(file_get_contents('php://input'), true);

function jsonResponse($arr, $code = 200)
{
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

// ----------------- CRUD -----------------

try {
    if ($method === 'GET') {
        if ($id) {
            $sql = "SELECT * FROM roles WHERE id=$id";
            $res = $conn->query($sql);
            $role = $res->fetch_assoc();
            jsonResponse(['data' => $role]);
        } else {
            $sql = "SELECT * FROM roles";
            $res = $conn->query($sql);
            $roles = [];
            while ($row = $res->fetch_assoc()) {
                $roles[] = $row;
            }
            jsonResponse(['data' => $roles]);
        }
    }

    if ($method === 'POST') {
        $role_name = $conn->real_escape_string($input['role_name'] ?? '');
        if ($role_name) {
            $sql = "INSERT INTO roles (role_name) VALUES ('$role_name')";
            if ($conn->query($sql)) {
                jsonResponse(['success' => true, 'id' => $conn->insert_id]);
            } else {
                jsonResponse(['error' => $conn->error], 500);
            }
        } else {
            jsonResponse(['error' => 'role_name is required'], 400);
        }
    }

    if ($method === 'PUT') {
        parse_str(file_get_contents('php://input'), $putInput);
        $role_name = $conn->real_escape_string($putInput['role_name'] ?? '');
        if ($id && $role_name) {
            $sql = "UPDATE roles SET role_name='$role_name' WHERE id=$id";
            if ($conn->query($sql)) {
                jsonResponse(['success' => true]);
            } else {
                jsonResponse(['error' => $conn->error], 500);
            }
        } else {
            jsonResponse(['error' => 'id and role_name required'], 400);
        }
    }

    if ($method === 'DELETE') {
        if ($id) {
            $sql = "DELETE FROM roles WHERE id=$id";
            if ($conn->query($sql)) {
                jsonResponse(['success' => true]);
            } else {
                jsonResponse(['error' => $conn->error], 500);
            }
        } else {
            jsonResponse(['error' => 'id required'], 400);
        }
    }

    jsonResponse(['error' => 'Method not allowed'], 405);

} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
