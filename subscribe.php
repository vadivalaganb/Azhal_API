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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');

// ----------------- Database Connection -----------------
include 'config.php'; // must define $conn (mysqli)
// OPTIONALLY: define SMTP credentials in config.php, e.g. $smtpUser, $smtpPass
$smtpUser = $smtpUser ?? 'hr@azhalitsolutions.com';
$smtpPass = $smtpPass ?? 'Vadi@123Valagan'; // <-- replace or store securely

function jsonResponse($arr, $code = 200) {
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

if (!isset($conn) || ($conn instanceof mysqli && $conn->connect_errno)) {
    jsonResponse(['success' => false, 'message' => 'Database connection failed.'], 500);
}

// ----------------- Read Input -----------------
$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => 'Invalid email']);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// helper: test connection to host:port
function testConnection(string $host, int $port, int $timeout = 10) {
    $errNo = 0; $errStr = '';
    $sock = @fsockopen($host, $port, $errNo, $errStr, $timeout);
    if ($sock) {
        fclose($sock);
        return ['ok' => true];
    }
    return ['ok' => false, 'errno' => $errNo, 'errstr' => $errStr];
}

try {
    // Insert subscriber
    $stmt = $conn->prepare("INSERT INTO subscribers (email, created_at) VALUES (?, NOW())");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    // ----------------- Prepare PHPMailer -----------------
    $mail = new PHPMailer(true);
    $emailErrors = [];

    // We'll attempt in order:
    // 1) Local relay (localhost:25) - useful on GoDaddy hosting
    // 2) smtp.secureserver.net:587 (STARTTLS)
    // 3) smtp.secureserver.net:465 (SSL)
    $attempts = [
        ['host' => 'localhost', 'port' => 25,  'auth' => false, 'secure' => null, 'desc' => 'Local relay (no auth)'],
        ['host' => 'smtp.secureserver.net', 'port' => 587, 'auth' => true,  'secure' => 'tls',  'desc' => 'GoDaddy STARTTLS port 587'],
        ['host' => 'smtp.secureserver.net', 'port' => 465, 'auth' => true,  'secure' => 'ssl',  'desc' => 'GoDaddy SSL port 465'],
    ];

    $sent = false;
    foreach ($attempts as $try) {
        // quick connectivity test
        $connTest = testConnection($try['host'], $try['port'], 8);
        if (!$connTest['ok']) {
            $emailErrors[] = "{$try['desc']} - cannot connect to {$try['host']}:{$try['port']} (errno={$connTest['errno']}, err=\"{$connTest['errstr']}\")";
            continue; // try next option
        }

        try {
            $mail->clearAllRecipients();
            $mail->clearAttachments();
            // SMTP setup
            $mail->isSMTP();
            $mail->Host = $try['host'];
            $mail->Port = $try['port'];

            if ($try['auth']) {
                $mail->SMTPAuth = true;
                $mail->Username = $smtpUser;
                $mail->Password = $smtpPass;
            } else {
                $mail->SMTPAuth = false;
            }

            if ($try['secure'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($try['secure'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                // no encryption for localhost relay
                $mail->SMTPSecure = false;
            }

            // Optional: Enable for debugging while testing (0 for production)
            $mail->SMTPDebug = 0;
            $mail->Debugoutput = function($str, $level) use (&$emailErrors, $try) {
                $emailErrors[] = "{$try['desc']} debug: {$str}";
            };

            // Sender / recipient
            $mail->setFrom('hr@azhalitsolutions.com', 'Azhal IT Solutions');
            $mail->addAddress($email);
            $mail->addReplyTo('hr@azhalitsolutions.com', 'Azhal IT Solutions');

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Subscription Confirmation';
            $mail->Body = '
                <h2>Welcome to Azhal IT Solutions!</h2>
                <p>Thank you for subscribing to our newsletter.</p>
                <p>You will now receive the latest updates and insights from our team.</p>
                <br>
                <p>Best Regards,<br><strong>Azhal IT Solutions Team</strong></p>
            ';
            $mail->AltBody = 'Thank you for subscribing to Azhal IT Solutions.';

            $mail->send();
            $sent = true;
            break;
        } catch (Exception $e) {
            $emailErrors[] = "{$try['desc']} - PHPMailer Error: " . $mail->ErrorInfo;
            // continue to next attempt
        }
    }

    if ($sent) {
        jsonResponse(['success' => true, 'message' => 'Subscribed successfully! Confirmation email sent.']);
    } else {
        jsonResponse([
            'success' => true,
            'message' => 'Subscribed successfully! But email could not be sent.',
            'emailError' => implode(" | ", $emailErrors),
            'suggestions' => [
                'Ensure $smtpPass is correct and set in config.php (do not hardcode on public repos).',
                'If hosted on GoDaddy, try using local relay (localhost:25) — that usually works on GoDaddy hosting.',
                'If using a non-GoDaddy host, ask your provider to allow outbound SMTP to smtp.secureserver.net ports 465/587.',
                'Run these tests from the server shell: "telnet smtp.secureserver.net 587" or "openssl s_client -connect smtp.secureserver.net:465".'
            ]
        ]);
    }

} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) {
        jsonResponse(['success' => false, 'message' => 'This email is already subscribed.']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

$stmt->close();
$conn->close();
?>
