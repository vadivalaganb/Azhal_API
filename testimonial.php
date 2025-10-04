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

// ----------------- CRUD -----------------
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {

    // CREATE testimonial
    case 'create':
        if ($method === 'POST') {
            $name = $conn->real_escape_string($_POST['name'] ?? '');
            $profession = $conn->real_escape_string($_POST['profession'] ?? '');
            $message = $conn->real_escape_string($_POST['message'] ?? '');
            $status = isset($_POST['status']) ? intval($_POST['status']) : 1;

            // Handle file upload
            $imagePath = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/testimonials/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $fileTmp = $_FILES['image']['tmp_name'];
                $fileName = time() . '_' . basename($_FILES['image']['name']);
                $targetFile = $uploadDir . $fileName;

                if (move_uploaded_file($fileTmp, $targetFile)) {
                    $imagePath = $targetFile;
                } else {
                    jsonResponse(['success' => false, 'error' => 'Image upload failed'], 500);
                }
            }

            $sql = "INSERT INTO testimonials (name, profession, message, image, status) 
                    VALUES ('$name', '$profession', '$message', '" . ($imagePath ?? '') . "', $status)";
            if ($conn->query($sql) === TRUE) {
                jsonResponse(["success" => true, "message" => "Testimonial added successfully"]);
            } else {
                jsonResponse(["success" => false, "error" => $conn->error], 500);
            }
        }
        break;

    // READ testimonials
    case 'read':
        $sql = "SELECT * FROM testimonials ORDER BY id DESC";
        $result = $conn->query($sql);

        $testimonials = [];
        while ($row = $result->fetch_assoc()) {
            $testimonials[] = $row;
        }
        jsonResponse($testimonials);
        break;

    // UPDATE testimonial
    case 'update':
        if ($method === 'POST') {
            $id = intval($_POST['id'] ?? 0);
            $name = $conn->real_escape_string($_POST['name'] ?? '');
            $profession = $conn->real_escape_string($_POST['profession'] ?? '');
            $message = $conn->real_escape_string($_POST['message'] ?? '');
            $status = isset($_POST['status']) ? intval($_POST['status']) : 1;

            // Handle file upload
            $imageSql = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/testimonials/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $fileTmp = $_FILES['image']['tmp_name'];
                $fileName = time() . '_' . basename($_FILES['image']['name']);
                $targetFile = $uploadDir . $fileName;

                if (move_uploaded_file($fileTmp, $targetFile)) {
                    $imageSql = ", image='$targetFile'";
                } else {
                    jsonResponse(['success' => false, 'error' => 'Image upload failed'], 500);
                }
            }

            $sql = "UPDATE testimonials SET 
                        name='$name', 
                        profession='$profession', 
                        message='$message', 
                        status=$status
                        $imageSql
                    WHERE id=$id";

            if ($conn->query($sql) === TRUE) {
                jsonResponse(["success" => true, "message" => "Testimonial updated successfully"]);
            } else {
                jsonResponse(["success" => false, "error" => $conn->error], 500);
            }
        }
        break;

    // DELETE testimonial
    case 'delete':
        if ($method === 'POST') {
            $id = intval($_POST['id'] ?? 0);
            $sql = "DELETE FROM testimonials WHERE id=$id";
            if ($conn->query($sql) === TRUE) {
                jsonResponse(["success" => true, "message" => "Testimonial deleted successfully"]);
            } else {
                jsonResponse(["success" => false, "error" => $conn->error], 500);
            }
        }
        break;

    default:
        jsonResponse(["success" => false, "error" => "Invalid action"], 400);
        break;
}

$conn->close();
?>
