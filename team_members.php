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
    $sql = "SELECT id, name, designation, profile_image, social_links, status, created_at, updated_at 
            FROM team_members ORDER BY created_at DESC";
    $result = $conn->query($sql);
    if (!$result) jsonResponse(['success' => false, 'error' => $conn->error], 500);

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        // decode social_links JSON
        $row['social_links'] = json_decode($row['social_links'], true);
        $rows[] = $row;
    }
    jsonResponse($rows, 200);
}

// ----------------- POST / PUT -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;

    $rawMethod = $input['_method'] ?? null;
    $id        = isset($input['id']) ? (int)$input['id'] : 0;
    $isUpdate  = ($rawMethod && strtoupper($rawMethod) === 'PUT') || ($id > 0);

    $name        = trim($input['name'] ?? '');
    $designation = trim($input['designation'] ?? '');
    $status      = isset($input['status']) ? (int)$input['status'] : 1;
    $social_links = $input['social_links'] ?? '[]'; // JSON string

    if ($name === '' || $designation === '') {
        jsonResponse(['success' => false, 'error' => 'name and designation are required'], 400);
    }

    // Handle profile image upload
    $profile_image = '';
    if (isset($_FILES['profile_image'])) {
        $targetDir = "uploads/";
        if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
        $fileName = time() . '_' . basename($_FILES['profile_image']['name']);
        $targetFilePath = $targetDir . $fileName;
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFilePath)) {
            $profile_image = $targetFilePath;
        }
    }

    if ($isUpdate) {
        // keep old image if not uploaded
        if ($profile_image === '') {
            $check = $conn->prepare("SELECT profile_image FROM team_members WHERE id=?");
            $check->bind_param("i", $id);
            $check->execute();
            $res = $check->get_result()->fetch_assoc();
            $profile_image = $res['profile_image'];
            $check->close();
        }

        $stmt = $conn->prepare("UPDATE team_members SET name=?, designation=?, profile_image=?, social_links=?, status=? WHERE id=?");
        if (!$stmt) jsonResponse(['success' => false, 'error' => $conn->error], 500);

        $stmt->bind_param("ssssii", $name, $designation, $profile_image, $social_links, $status, $id);
        if ($stmt->execute()) {
            $stmt->close();
            jsonResponse(['success' => true, 'message' => 'Record updated'], 200);
        } else {
            $err = $stmt->error;
            $stmt->close();
            jsonResponse(['success' => false, 'error' => $err], 500);
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO team_members (name, designation, profile_image, social_links, status) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) jsonResponse(['success' => false, 'error' => $conn->error], 500);

        $stmt->bind_param("ssssi", $name, $designation, $profile_image, $social_links, $status);
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

    $stmt = $conn->prepare("DELETE FROM team_members WHERE id=?");
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
