<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/bootstrap.php';

function gov_mailer(): PHPMailer\PHPMailer\PHPMailer {
    require_once __DIR__ . '/../vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    $host = (string)($_ENV['SMTP_HOST'] ?? '');
    $port = (int)($_ENV['SMTP_PORT'] ?? 587);
    $user = (string)($_ENV['SMTP_USER'] ?? ($_ENV['SMTP_USERNAME'] ?? ''));
    $pass = (string)($_ENV['SMTP_PASS'] ?? ($_ENV['SMTP_PASSWORD'] ?? ''));
    $from = (string)($_ENV['SMTP_FROM'] ?? $user);
    $fromName = (string)($_ENV['SMTP_FROM_NAME'] ?? 'Ghosts of Velen');
    $secure = strtolower((string)($_ENV['SMTP_SECURE'] ?? 'tls'));

    $mail->isSMTP();
    $mail->Host       = $host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $user;
    $mail->Password   = $pass;
    $mail->Port       = $port;
    $mail->Timeout    = (int)($_ENV['SMTP_TIMEOUT'] ?? 15);
    $mail->SMTPSecure = ($secure === 'ssl'
        ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS);

    $mail->setFrom($from, $fromName);
    if (!empty($_ENV['SMTP_REPLY_TO'])) {
        $mail->addReplyTo((string)$_ENV['SMTP_REPLY_TO'], (string)($_ENV['SMTP_REPLY_TO_NAME'] ?? ''));
    }
    return $mail;
}

function gov_send_activation_email(string $email, string $username, string $token): void {
    $base = $_ENV['APP_URL'] ?? 'https://ghostsofvelen.com';
    $link = rtrim($base, '/') . '/auth/activate.php?token=' . urlencode($token);

    $subj = 'Activate your Ghosts of Velen account';
    $text = "Hi {$username},\n\nPlease activate your account:\n{$link}\n\nIf you didn’t expect this, ignore this message.";
    $html = nl2br(htmlentities($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

    $mail = gov_mailer();
    $mail->addAddress($email, $username);
    $mail->Subject = $subj;
    $mail->Body    = $html;
    $mail->AltBody = $text;
    $mail->send();
}
