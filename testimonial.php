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
function generateToken($length = 32)
{
    return bin2hex(random_bytes($length / 2));
}

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
    // Action: create_invite (POST)
    case 'create_invite':
        if ($method === 'POST') {
            $type = $_POST['type'];
            if (!in_array($type, ['intern', 'client'])) {
                jsonResponse(['success' => false, 'error' => 'Type must be intern or client'], 400);
            }
            $token = generateToken();
            $sql = "INSERT INTO testimonial_invites (token, type) VALUES ('$token', '$type')";
            if ($conn->query($sql) === TRUE) {
                // Construct link, you can send by email or show in UI
                $reviewUrl = 'https://azhalitsolutions.com/review.html?token=' . $token;
                jsonResponse(['success' => true, 'link' => $reviewUrl, 'token' => $token]);
            } else {
                jsonResponse(['success' => false, 'error' => $conn->error], 500);
            }
        }
        break;

    // Action: validate_token (GET)
    case 'validate_token':
        $token = $_GET['token'];
        $sql = "SELECT * FROM testimonial_invites WHERE token='$token' AND used=0";
        $result = $conn->query($sql);
        if ($row = $result->fetch_assoc()) {
            jsonResponse(['valid' => true, 'type' => $row['type']]);
        } else {
            jsonResponse(['valid' => false]);
        }
        break;

    // Action: submit_review (POST)
    case 'submit_review':
        // Accepts name, profession, message, image (optional), token
        $token = $conn->real_escape_string($_POST['token'] ?? '');
        $name = $conn->real_escape_string($_POST['name'] ?? '');
        $profession = $conn->real_escape_string($_POST['profession'] ?? '');
        $message = $conn->real_escape_string($_POST['message'] ?? '');
        $status = 1;
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

        // Lookup token
        $sql = "SELECT * FROM testimonial_invites WHERE token='$token' AND used=0";
        $result = $conn->query($sql);
        if (!$row = $result->fetch_assoc()) {
            jsonResponse(['success' => false, 'error' => 'Invalid or used token'], 400);
        }
        $type = $row['type'];
        // Insert testimonial
        $sql = "INSERT INTO testimonials (name, profession, message, image, status, type) VALUES
            ('$name', '$profession', '$message', '" . ($imagePath ?? '') . "', $status, '$type')";
        if ($conn->query($sql) === TRUE) {
            // Mark token used
            $conn->query("UPDATE testimonial_invites SET used=1, used_at=NOW() WHERE id=" . $row['id']);
            jsonResponse(["success" => true, "message" => "Testimonial submitted"]);
        } else {
            jsonResponse(["success" => false, "error" => $conn->error], 500);
        }
        break;
    default:
        jsonResponse(["success" => false, "error" => "Invalid action"], 400);
        break;
}

$conn->close();
?>
