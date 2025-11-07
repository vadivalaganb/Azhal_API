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

if (!isset($conn) || ($conn instanceof mysqli && $conn->connect_errno)) {
    jsonResponse(['success' => false, 'error' => 'Database connection failed: ' . ($conn->connect_error ?? '')], 500);
}

// ----------------- CRUD Operations -----------------
$method = $_SERVER['REQUEST_METHOD'];

// ----------------- READ (GET all or single) -----------------
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        jsonResponse($row ?: []);
    } else {
        $result = $conn->query("SELECT * FROM courses ORDER BY created_at DESC");
        if (!$result) jsonResponse(['success' => false, 'error' => $conn->error], 500);
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        jsonResponse($rows);
    }
}

// ----------------- CREATE or UPDATE (POST) -----------------
if ($method === 'POST') {
    $id = $_POST['id'] ?? null;
    $header_name = $_POST['header_name'] ?? '';
    $short_description = $_POST['short_description'] ?? '';
    $description = $_POST['description'] ?? '';
    $course_duration = $_POST['course_duration'] ?? '';
    $course_level = $_POST['course_level'] ?? '';
    $course_instructor = $_POST['course_instructor'] ?? '';
    $max_students = $_POST['max_students'] ?? '';
    $status = isset($_POST['status']) && ($_POST['status'] === 'true' || $_POST['status'] === '1') ? 1 : 0;

    // ----------------- Handle File Upload -----------------
    $file_path = null;
    if (!empty($_FILES['file']['name'])) {
        $upload_dir = "uploads/courses/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $file_name = time() . '_' . basename($_FILES['file']['name']);
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
            $file_path = $target_path;
        }
    }

    // ----------------- UPDATE -----------------
    if (!empty($id)) {
        $sql = "UPDATE courses SET 
                header_name=?, short_description=?, description=?, 
                course_duration=?, course_level=?, course_instructor=?, max_students=?, status=?";
        if ($file_path) $sql .= ", file_path=?";
        $sql .= " WHERE id=?";

        $stmt = $conn->prepare($sql);
        if ($file_path) {
            $stmt->bind_param(
                "ssssssiisi",
                $header_name,
                $short_description,
                $description,
                $course_duration,
                $course_level,
                $course_instructor,
                $max_students,
                $status,
                $file_path,
                $id
            );
        } else {
            $stmt->bind_param(
                "ssssssiii",
                $header_name,
                $short_description,
                $description,
                $course_duration,
                $course_level,
                $course_instructor,
                $max_students,
                $status,
                $id
            );
        }

        if ($stmt->execute()) {
            jsonResponse(['success' => true, 'message' => 'Course updated successfully']);
        } else {
            jsonResponse(['success' => false, 'error' => $stmt->error], 500);
        }
    }

    // ----------------- CREATE -----------------
    $stmt = $conn->prepare("INSERT INTO courses 
            (header_name, short_description, description, course_duration, course_level, course_instructor, max_students, file_path, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "ssssssisi",
        $header_name,
        $short_description,
        $description,
        $course_duration,
        $course_level,
        $course_instructor,
        $max_students,
        $file_path,
        $status
    );


    if ($stmt->execute()) {
        jsonResponse(['success' => true, 'message' => 'Course added successfully']);
    } else {
        jsonResponse(['success' => false, 'error' => $stmt->error], 500);
    }
}

// ----------------- DELETE -----------------
if ($method === 'DELETE') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = $params['id'] ?? null;

    if (!$id) jsonResponse(['success' => false, 'error' => 'Missing ID'], 400);

    // Delete uploaded file if exists
    $fileCheck = $conn->prepare("SELECT file_path FROM courses WHERE id=?");
    $fileCheck->bind_param("i", $id);
    $fileCheck->execute();
    $fileResult = $fileCheck->get_result()->fetch_assoc();

    if ($fileResult && !empty($fileResult['file_path']) && file_exists($fileResult['file_path'])) {
        unlink($fileResult['file_path']);
    }

    $stmt = $conn->prepare("DELETE FROM courses WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        jsonResponse(['success' => true, 'message' => 'Course deleted successfully']);
    } else {
        jsonResponse(['success' => false, 'error' => $stmt->error], 500);
    }
}

jsonResponse(['success' => false, 'error' => 'Invalid request method'], 405);
