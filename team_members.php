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

// ----------------- Headers & Errors -----------------
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', '1');

// ----------------- Database -----------------
include 'config.php'; // must define $conn (mysqli)

function jsonResponse($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if (!isset($conn) || $conn->connect_errno) {
    jsonResponse(['success' => false, 'error' => 'DB connection failed'], 500);
}

/* =====================================================
   GET  → List Team / Intern
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $type = $_GET['type'] ?? '';
    $where = '';

    if (in_array($type, ['team', 'intern'])) {
        $where = " WHERE member_type = '$type'";
    }

    $sql = "SELECT id, name, designation, profile_image, social_links, 
                   status, member_type, created_at, updated_at
            FROM team_members $where
            ORDER BY created_at DESC";

    $res = $conn->query($sql);
    if (!$res) jsonResponse(['success' => false, 'error' => $conn->error], 500);

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $row['social_links'] = json_decode($row['social_links'], true);
        $rows[] = $row;
    }
    jsonResponse($rows);
}

/* =====================================================
   POST / PUT → Create or Update
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input = $_POST;

    $id           = isset($input['id']) ? (int)$input['id'] : 0;
    $rawMethod    = $input['_method'] ?? '';
    $isUpdate     = ($rawMethod === 'PUT' || $id > 0);

    $name         = trim($input['name'] ?? '');
    $designation  = trim($input['designation'] ?? '');
    $status       = isset($input['status']) ? (int)$input['status'] : 1;
    $member_type  = $input['member_type'] ?? 'team';
    $social_links = $input['social_links'] ?? '[]';

    if ($name === '' || $designation === '') {
        jsonResponse(['success' => false, 'error' => 'Name & Designation required'], 400);
    }

    if (!in_array($member_type, ['team', 'intern'])) {
        jsonResponse(['success' => false, 'error' => 'Invalid member type'], 400);
    }

    /* ---------- Image Upload ---------- */
    $profile_image = '';

    if (!empty($_FILES['profile_image']['name'])) {
        $uploadDir = "uploads/";
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . '_' . basename($_FILES['profile_image']['name']);
        $path = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $path)) {
            $profile_image = $path;
        }
    }

    /* ---------- UPDATE ---------- */
    if ($isUpdate) {

        if ($profile_image === '') {
            $q = $conn->prepare("SELECT profile_image FROM team_members WHERE id=?");
            $q->bind_param("i", $id);
            $q->execute();
            $profile_image = $q->get_result()->fetch_assoc()['profile_image'] ?? '';
            $q->close();
        }

        $stmt = $conn->prepare(
            "UPDATE team_members 
             SET name=?, designation=?, profile_image=?, social_links=?, 
                 status=?, member_type=? 
             WHERE id=?"
        );

        $stmt->bind_param(
            "ssssisi",
            $name,
            $designation,
            $profile_image,
            $social_links,
            $status,
            $member_type,
            $id
        );

        if ($stmt->execute()) {
            jsonResponse(['success' => true, 'message' => 'Updated successfully']);
        }

        jsonResponse(['success' => false, 'error' => $stmt->error], 500);
    }

    /* ---------- INSERT ---------- */
    $stmt = $conn->prepare(
        "INSERT INTO team_members 
         (name, designation, profile_image, social_links, status, member_type)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "ssssss",
        $name,
        $designation,
        $profile_image,
        $social_links,
        $status,
        $member_type
    );

    if ($stmt->execute()) {
        jsonResponse(['success' => true, 'id' => $stmt->insert_id], 201);
    }

    jsonResponse(['success' => false, 'error' => $stmt->error], 500);
}

/* =====================================================
   DELETE
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    parse_str(file_get_contents("php://input"), $data);
    $id = (int)($data['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(['success' => false, 'error' => 'Invalid ID'], 400);
    }

    $stmt = $conn->prepare("DELETE FROM team_members WHERE id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        jsonResponse(['success' => true]);
    }

    jsonResponse(['success' => false, 'error' => $stmt->error], 500);
}

// ----------------- Fallback -----------------
jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
