<?php
// ----------------- PHPMailer -----------------
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

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
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ----------------- Content type -----------------
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', '1');

// ----------------- Database -----------------
include 'config.php'; // must define $conn (mysqli)

// JSON Response Helper
function jsonResponse($arr, $code = 200) {
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

// Verify DB connection
if (!isset($conn) || ($conn instanceof mysqli && $conn->connect_errno)) {
    jsonResponse(['success' => false, 'message' => 'Database connection failed: ' . ($conn->connect_error ?? '')], 500);
}

// ----------------- Read Input -----------------
$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => 'Invalid email']);
}

// ----------------- Insert into DB -----------------
$stmt = $conn->prepare("INSERT INTO subscribers (email, created_at) VALUES (?, NOW())");
$stmt->bind_param("s", $email);

if ($stmt->execute()) {

    // ----------------- Send Confirmation Email -----------------
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtpout.secureserver.net'; // GoDaddy SMTP
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@azhalitsolutions.com'; // your GoDaddy email
        $mail->Password = 'YOUR_EMAIL_PASSWORD';           // your email password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('no-reply@azhalitsolutions.com', 'Azhal IT Solutions');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Subscription Confirmation';
        $mail->Body    = "
            <h2>Hello!</h2>
            <p>Thank you for subscribing to our newsletter. You will now receive the latest updates from Azhal IT Solutions.</p>
            <p>Best Regards,<br>Azhal IT Solutions Team</p>
        ";

        $mail->send();
        jsonResponse(['success' => true, 'message' => 'Subscribed successfully! Confirmation email sent.']);

    } catch (Exception $e) {
        jsonResponse(['success' => true, 'message' => 'Subscribed successfully! But email could not be sent.']);
    }

} else {
    jsonResponse(['success' => false, 'message' => 'Failed to subscribe']);
}

// Close DB connection
$stmt->close();
$conn->close();
?>
