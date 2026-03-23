<?php
// auth/send_membership_request.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/mailer.php';

$pdo = db(); // ? added

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
    $recaptcha_data = [
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $recaptcha_response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query($recaptcha_data)
        ]
    ];
    $context = stream_context_create($options);
    $result = file_get_contents($recaptcha_url, false, $context);
    $resultJson = json_decode($result);

    $threshold = isset($_ENV['RECAPTCHA_THRESHOLD']) ? floatval($_ENV['RECAPTCHA_THRESHOLD']) : 0.5;
    if (!$resultJson->success || $resultJson->score < $threshold) {
        die("reCAPTCHA validation failed.");
    }

    $username = $_POST['username'];
    $email = $_POST['email'];

    $stmt = $pdo->prepare("INSERT INTO membership_requests (username, email) VALUES (?, ?)");
    $stmt->execute([$username, $email]);

    sendConfirmationEmail($email, $username);
    echo "Membership request submitted successfully.";
}
?>
