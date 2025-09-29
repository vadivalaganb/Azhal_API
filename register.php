<?php
// ------------------ CORS ------------------
$allowedOrigins = [
    "https://azhalitsolutions.com",
    "https://admin.azhalitsolutions.com",
    "http://localhost:4200"
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// ------------------ Errors ------------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ------------------ Include DB Config ------------------
include 'config.php'; // adjust path if needed

// ------------------ Handle JSON input ------------------
$data_json = file_get_contents('php://input');
$data = json_decode($data_json, true);
$action = $_POST['action'] ?? ($data['action'] ?? null);

// ------------------ GET ALL STUDENTS ------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare("
        SELECT id, first_name, last_name, contact, institution_name, 
               academic_year, dob, gender, address, department, course, 
               status, profile_image, created_at, updated_at
        FROM student_register 
        ORDER BY id DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    echo json_encode($students);
    $stmt->close();
    exit;
}

// ------------------ ACCOUNT REGISTRATION ------------------
if ($action === 'register') {
    $first_name = $data['first_name'] ?? $_POST['first_name'] ?? '';
    $last_name  = $data['last_name'] ?? $_POST['last_name'] ?? '';
    $contact    = $data['contact'] ?? $_POST['contact'] ?? '';
    $password   = $data['password'] ?? $_POST['password'] ?? '';

    if (!$first_name || !$last_name || !$contact || !$password) {
        echo json_encode(["success" => false, "message" => "Missing required fields"]);
        exit;
    }

    $check = $conn->prepare("SELECT id FROM student_register WHERE contact=?");
    $check->bind_param("s", $contact);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "Contact already exists"]);
        exit;
    }
    $check->close();

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO student_register (first_name, last_name, contact, password) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $first_name, $last_name, $contact, $hash);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Account created successfully",
            "user_id" => $stmt->insert_id
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Account creation failed", "error" => $stmt->error]);
    }
    $stmt->close();
    exit;
}

// ------------------ FULL REGISTRATION / PROFILE UPDATE ------------------
if ($action === 'register_full') {
    $id              = $_POST['user_id'] ?? '';
    $institutionName = $_POST['institutionName'] ?? '';
    $academicYear    = $_POST['academicYear'] ?? '';
    $dob             = $_POST['dob'] ?? '';
    $gender          = $_POST['gender'] ?? '';
    $address         = $_POST['address'] ?? '';
    $department      = $_POST['department'] ?? '';
    $course          = $_POST['course'] ?? '';
    $profile_image   = '';

    if (isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = uniqid() . '_' . basename($_FILES['profileImage']['name']);
        $targetPath = $uploadDir . $fileName;
        if (!move_uploaded_file($_FILES['profileImage']['tmp_name'], $targetPath)) {
            echo json_encode(["success" => false, "message" => "Failed to move uploaded file"]);
            exit;
        }
        $profile_image = "uploads/" . $fileName;
    }

    $stmt = $conn->prepare("
        UPDATE student_register SET
            institution_name=?, academic_year=?, dob=?, gender=?, address=?, department=?, course=?, profile_image=?
        WHERE id=?
    ");
    $stmt->bind_param(
        "ssssssssi",
        $institutionName, $academicYear, $dob, $gender, $address, $department, $course, $profile_image, $id
    );

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Profile updated successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Profile update failed", "error" => $stmt->error]);
    }
    $stmt->close();
    exit;
}

// ------------------ LOGIN ------------------
if ($action === 'login') {
    $contact  = $data['contact'] ?? $_POST['contact'] ?? '';
    $password = $data['password'] ?? $_POST['password'] ?? '';

    if (!$contact || !$password) {
        echo json_encode(["success" => false, "message" => "Missing credentials"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM student_register WHERE contact=?");
    $stmt->bind_param("s", $contact);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            echo json_encode([
                "success" => true,
                "token" => bin2hex(random_bytes(16)),
                "user"  => [
                    "id" => $row['id'],
                    "first_name" => $row['first_name'],
                    "last_name"  => $row['last_name'],
                    "contact"    => $row['contact'],
                    "profile_image" => $row['profile_image']
                ]
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Invalid password"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "User not found"]);
    }
    $stmt->close();
    exit;
}

// ------------------ DELETE STUDENT ------------------
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id || !is_numeric($id)) {
        echo json_encode(["success" => false, "message" => "Invalid student ID"]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM student_register WHERE id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Student deleted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to delete student", "error" => $stmt->error]);
    }
    $stmt->close();
    exit;
}

// ------------------ UPDATE STATUS ------------------
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents("php://input"), true);

    $id = $data['id'] ?? null;
    $status = $data['status'] ?? null;

    if (!$id || !is_numeric($id) || ($status !== 0 && $status !== 1)) {
        echo json_encode(["success" => false, "message" => "Invalid request data"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE student_register SET status=? WHERE id=?");
    $stmt->bind_param("ii", $status, $id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Status updated successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to update status", "error" => $stmt->error]);
    }
    $stmt->close();
    exit;
}

$conn->close();
