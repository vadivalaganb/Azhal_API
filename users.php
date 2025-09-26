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

switch ($method) {
    case 'GET':
        if ($id) {
            $stmt = $conn->prepare("
                SELECT u.*, r.role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id=?
            ");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if ($user) jsonResponse(['success' => true, 'user' => $user]);
            else jsonResponse(['success' => false, 'message' => 'User not found'], 404);
        } else {
            $result = $conn->query("
                SELECT u.*, r.role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                ORDER BY u.id DESC
            ");
            $users = $result->fetch_all(MYSQLI_ASSOC);
            jsonResponse(['success' => true, 'users' => $users]);
        }
        break;

    case 'POST':
        if (isset($input['signin']) && $input['signin'] === true) {
            // Sign-in logic
            $email = trim($input['email'] ?? '');
            $password = $input['password'] ?? '';

            if (!$email || !$password) jsonResponse(['success' => false, 'message' => 'Email and password required'], 400);

            $stmt = $conn->prepare("
                SELECT u.*, r.role_name
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.email=? AND u.status=1 LIMIT 1
            ");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if ($user && password_verify($password, $user['password'])) {
                unset($user['password']);
                jsonResponse(['success' => true, 'user' => $user]);
            } else {
                jsonResponse(['success' => false, 'message' => 'Invalid credentials'], 401);
            }
        } else {
            // Add new user
            $username = $input['username'] ?? null;
            $first_name = $input['first_name'] ?? null;
            $last_name = $input['last_name'] ?? null;
            $email = $input['email'] ?? null;
            $password = $input['password'] ?? null;
            $avatar_url = $input['avatar_url'] ?? null;
            $role_id = $input['role_id'] ?? null;
            $status = isset($input['status']) ? (int)$input['status'] : 1;
            $is_email_verified = isset($input['is_email_verified']) ? (int)$input['is_email_verified'] : 0;

            if (!$email || !$password || !$role_id) {
                jsonResponse(['success' => false, 'message' => 'Email, password and role_id required'], 400);
            }

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO users (username, first_name, last_name, email, password, avatar_url, role_id, status, is_email_verified)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('ssssssiii', $username, $first_name, $last_name, $email, $password_hash, $avatar_url, $role_id, $status, $is_email_verified);

            if ($stmt->execute()) {
                jsonResponse(['success' => true, 'message' => 'User added', 'id' => $conn->insert_id], 201);
            } else {
                jsonResponse(['success' => false, 'message' => 'Insert failed: ' . $conn->error], 500);
            }
        }
        break;

    case 'PUT':
        if (!$id) jsonResponse(['success' => false, 'message' => 'User ID required for update'], 400);

        $username = $input['username'] ?? null;
        $first_name = $input['first_name'] ?? null;
        $last_name = $input['last_name'] ?? null;
        $avatar_url = $input['avatar_url'] ?? null;
        $role_id = $input['role_id'] ?? null;
        $status = isset($input['status']) ? (int)$input['status'] : null;
        $is_email_verified = isset($input['is_email_verified']) ? (int)$input['is_email_verified'] : null;

        $fields = [];
        $params = [];
        $types = '';

        if ($username !== null) {
            $fields[] = 'username=?';
            $params[] = $username;
            $types .= 's';
        }
        if ($first_name !== null) {
            $fields[] = 'first_name=?';
            $params[] = $first_name;
            $types .= 's';
        }
        if ($last_name !== null) {
            $fields[] = 'last_name=?';
            $params[] = $last_name;
            $types .= 's';
        }
        if ($avatar_url !== null) {
            $fields[] = 'avatar_url=?';
            $params[] = $avatar_url;
            $types .= 's';
        }
        if ($role_id !== null) {
            $fields[] = 'role_id=?';
            $params[] = $role_id;
            $types .= 'i';
        }
        if ($status !== null) {
            $fields[] = 'status=?';
            $params[] = $status;
            $types .= 'i';
        }
        if ($is_email_verified !== null) {
            $fields[] = 'is_email_verified=?';
            $params[] = $is_email_verified;
            $types .= 'i';
        }

        if (empty($fields)) jsonResponse(['success' => false, 'message' => 'No fields to update'], 400);

        $sql = "UPDATE users SET " . implode(',', $fields) . " WHERE id=?";
        $params[] = $id;
        $types .= 'i';

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            jsonResponse(['success' => true, 'message' => 'User updated']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Update failed: ' . $conn->error], 500);
        }
        break;

    case 'DELETE':
        if (!$id) jsonResponse(['success' => false, 'message' => 'User ID required for delete'], 400);

        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            jsonResponse(['success' => true, 'message' => 'User deleted']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Delete failed: ' . $conn->error], 500);
        }
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}
