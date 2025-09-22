<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';

class Mail {
    public static function isEnabled(): bool {
        return defined('SMTP_ENABLE') && SMTP_ENABLE === true;
    }

    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody, ?string $altBody=null): array {
        if (!self::isEnabled()) {
            return ['success'=>false,'error'=>'SMTP disabled'];
        }
        $result = ['success'=>false,'error'=>null];
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            if (SMTP_SECURE === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($toEmail, $toName ?: $toEmail);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body    = $htmlBody;
            $mail->AltBody = $altBody !== null ? $altBody : strip_tags($htmlBody);
            $mail->send();
            $result['success'] = true;
            return $result;
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
            return $result;
        }
    }
}
