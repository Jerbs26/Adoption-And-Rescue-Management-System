<?php

define('APP_NAME',    'Adoptly');
define('APP_VERSION', '2.0.0');
define('BASE_URL', 'http://localhost:8080/PET-ADOPTION');
define('UPLOAD_DIR',  __DIR__ . '/../public/uploads/pets/');
define('UPLOAD_URL',  BASE_URL . '/public/uploads/pets/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// Mail (PHPMailer + Gmail SMTP)
define('MAIL_HOST',       'smtp.gmail.com');
define('MAIL_PORT',       587);
define('MAIL_USERNAME',   'ardientejerby26@gmail.com');
define('MAIL_PASSWORD',   'urit ifcp espm ucgz');
define('MAIL_FROM_EMAIL', 'ardientejerby26@gmail.com');
define('MAIL_FROM_NAME',  APP_NAME);

// Valid roles
const ROLES = ['admin', 'staff', 'adopter', 'rescue_org'];

// Role → default dashboard map
const ROLE_DASHBOARDS = [
    'admin'      => '/modules/admin/dashboard.php',
    'staff'      => '/modules/staff/dashboard.php',
    'adopter'    => '/modules/adopter/dashboard.php',
    'rescue_org' => '/modules/staff/dashboard.php',
];

// Session
function session_start_once(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('WB_SID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

// Auth helpers
function current_user(): ?array {
    session_start_once();
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function require_login(string $redirect = '/index.php'): void {
    if (!is_logged_in()) {
        redirect($redirect . '?msg=login_required');
    }
}

function require_role(string ...$roles): void {
    $user = current_user();
    if (!$user || !in_array($user['role'] ?? '', $roles, true)) {
        redirect(BASE_URL . '/index.php?msg=unauthorized');
    }
}

/**
 * Returns the default dashboard URL for the given role.
 */
function role_dashboard(string $role): string {
    return BASE_URL . (ROLE_DASHBOARDS[$role] ?? '/index.php');
}

function login_user(array $user): void {
    session_start_once();

    if (empty($_SESSION['user'])) {
        session_regenerate_id(true);
    }

    $_SESSION['user'] = [
        'id'        => $user['id'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'role'      => $user['role'],
        'photo'     => $user['profile_photo'] ?? $user['photo'] ?? null,
    ];
}

function logout_user(): void {
    session_start_once();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

// Output helpers
function e(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function flash(string $key, string $message): void {
    session_start_once();
    $_SESSION['flash'][$key] = $message;
}

function get_flash(string $key): ?string {
    session_start_once();
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function csrf_token(): string {
    session_start_once();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

// Sanitize
function clean(string $v): string {
    return trim(strip_tags($v));
}

// Status badge
function status_badge(string $status): string {
    $map = [
        // Pet statuses
        'Available'    => 'badge-success',
        'Pending'      => 'badge-warning',
        'Adopted'      => 'badge-muted',
        'Rescued'      => 'badge-info',
        'In Treatment' => 'badge-danger',
        // Application statuses
        'Under Review' => 'badge-warning',
        'Approved'     => 'badge-success',
        'Rejected'     => 'badge-danger',
        'Withdrawn'    => 'badge-muted',
        // User roles
        'admin'        => 'badge-info',
        'staff'        => 'badge-warning',
        'adopter'      => 'badge-success',
        'rescue_org'   => 'badge-info',
    ];
    $cls = $map[$status] ?? 'badge-muted';
    return '<span class="badge ' . $cls . '">' . e($status) . '</span>';
}

// Pet image
function pet_image_url(?string $path): string {
    if (!$path) return BASE_URL . '/public/assets/pet-placeholder.svg';
    if (str_starts_with($path, 'http')) return $path;
    return UPLOAD_URL . ltrim($path, '/');
}

// Log activity
function log_activity(string $action, ?string $targetType = null, ?int $targetId = null, ?string $detail = null): void {
    try {
        $user   = current_user();
        $userId = $user['id'] ?? null;
        $ip     = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt   = db()->prepare("INSERT INTO activity_log (user_id, action, target_type, target_id, detail, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $targetType, $targetId, $detail, $ip]);
    } catch (Throwable) { /* non-fatal */ }
}

// Pagination helper
function paginate(int $total, int $perPage, int $current): array {
    $pages = (int)ceil($total / $perPage);
    return ['total' => $total, 'per_page' => $perPage, 'current' => $current, 'pages' => $pages];
}

// OTP helper
function generate_otp(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function save_otp(int $userId, string $otp): void {
    db()->prepare("UPDATE otp_verifications SET used = 1 WHERE user_id = ? AND used = 0")
        ->execute([$userId]);
    db()->prepare("INSERT INTO otp_verifications (user_id, otp_code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))")
        ->execute([$userId, trim((string)$otp)]);
}


/**
 * Send an account-activated email to a rescue organisation.
 */
function send_activation_email(string $toEmail, string $toName, string $orgName): bool {
    require_once __DIR__ . '/../vendor/autoload.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        $subject       = APP_NAME . ' — Your Organization Account Has Been Activated!';
        $mail->Subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $mail->Body    = activation_email_template($toName, $orgName);
        $mail->AltBody = "Hi $toName, your rescue organization account for \"$orgName\" on " . APP_NAME . " has been activated. You can now log in and start listing pets.";

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Activation Mailer Error: ' . $e->getMessage());
        return false;
    }
}

function activation_email_template(string $name, string $orgName): string {
    $appName  = APP_NAME;
    $loginUrl = BASE_URL . '/login.php';
    $year     = date('Y');
    return "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#f5f5f0;font-family:sans-serif'>
  <table width='100%' cellpadding='0' cellspacing='0' style='background:#f5f5f0;padding:40px 0'>
    <tr><td align='center'>
    <table width='520' cellpadding='0' cellspacing='0' style='background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)'>
      <tr>
        <td style='background:linear-gradient(135deg,#2d5a45,#3d7a5f);padding:32px;text-align:center'>
          <h1 style='color:#fff;margin:8px 0 0;font-size:1.5rem;letter-spacing:-.5px'>{$appName}</h1>
          <p style='color:rgba(255,255,255,.75);margin:6px 0 0;font-size:.9rem'>Organization Account Activated</p>
        </td>
      </tr>
      <tr>
        <td style='padding:36px 40px'>
          <div style='text-align:center;margin-bottom:24px'>
            <div style='display:inline-block;width:64px;height:64px;line-height:64px;border-radius:50%;background:#e8f5ee;font-size:2rem;text-align:center'>&#127881;</div>
          </div>
          <h2 style='color:#1e3a2f;margin:0 0 8px;font-size:1.2rem;text-align:center'>You&rsquo;re approved, {$name}!</h2>
          <p style='color:#4a6b58;margin:0 0 20px;line-height:1.6;text-align:center'>
            Your rescue organization account for <strong>{$orgName}</strong> has been reviewed and
            <strong style='color:#2d7a4f'>activated</strong> by our admin team.
            You can now log in and start listing pets for adoption.
          </p>
          <div style='text-align:center;margin:28px 0'>
            <a href='{$loginUrl}'
               style='display:inline-block;padding:14px 36px;background:linear-gradient(135deg,#2d5a45,#3d7a5f);color:#fff;
                      text-decoration:none;border-radius:10px;font-weight:700;font-size:.95rem;
                      box-shadow:0 4px 16px rgba(61,122,95,.35)'>
              Log In to Your Account &rarr;
            </a>
          </div>
          <div style='background:#f8fdf9;border:1px solid #d4e6da;border-radius:10px;padding:16px 20px;margin-top:8px'>
            <p style='margin:0;font-size:.82rem;color:#4a6b58;line-height:1.8'>
              <strong>What you can do now:</strong><br>
              &#x2705;&nbsp; List dogs and cats available for adoption<br>
              &#x2705;&nbsp; Review and manage adoption applications<br>
              &#x2705;&nbsp; Receive and respond to animal reports from adopters
            </p>
          </div>
        </td>
      </tr>
      <tr>
        <td style='background:#f0f7f4;padding:20px 40px;text-align:center;border-top:1px solid #d4e6da'>
          <p style='color:#9ab5a5;font-size:.75rem;margin:0'>&copy; {$year} {$appName}. All rights reserved.</p>
        </td>
      </tr>
    </table>
    </td></tr>
  </table>
</body>
</html>";
}

function send_otp_email(string $toEmail, string $toName, string $otp, string $context = 'verify'): bool {
    require_once __DIR__ . '/../vendor/autoload.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $subject = $context === 'reset'
            ? APP_NAME . ' - Password Reset Code'
            : APP_NAME . ' - Email Verification Code';
        $mail->Subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $mail->Body    = otp_email_template($toName, $otp, $context);
        $mail->AltBody = "Hi $toName, your verification code is: $otp. It expires in 10 minutes.";

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Mailer Error: ' . $e->getMessage());
        return false;
    }
}

function otp_email_template(string $name, string $otp, string $context = 'verify'): string {
    $appName = APP_NAME;
    $year    = date('Y');
    $digits  = implode('', array_map(
        fn($c) => "<span style='display:inline-block;width:44px;height:54px;line-height:54px;text-align:center;background:#f0f7f4;border:2px solid #d4e6da;border-radius:10px;font-size:1.6rem;font-weight:800;color:#1e3a2f;margin:0 3px;font-family:monospace'>$c</span>",
        str_split($otp)
    ));

    if ($context === 'reset') {
        $subtitle          = 'Password Reset Code';
        $body              = "We received a request to reset the password for your <strong>{$appName}</strong> account. Use the code below to reset your password. This code expires in <strong>15 minutes</strong>.";
        $footer_note       = "&#9888;&nbsp;<strong>Do not share this code</strong> with anyone. {$appName} will never ask for your OTP.";
        $footer_style      = "background:#fff8f0;border:1px solid #f5d9b8;border-radius:10px;padding:14px 16px;margin-top:20px";
        $footer_text_style = "margin:0;font-size:12px;color:#a06030";
    } else {
        $subtitle          = 'Email Verification';
        $body              = "Thanks for signing up! Use the code below to verify your email address. This code expires in <strong>10 minutes</strong>.";
        $footer_note       = "If you didn't create an account, you can safely ignore this email.";
        $footer_style      = "margin-top:20px;text-align:center";
        $footer_text_style = "color:#7a9e8a;font-size:.85rem;margin:0";
    }

    return "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#f5f5f0;font-family:sans-serif'>
    <table width='100%' cellpadding='0' cellspacing='0' style='background:#f5f5f0;padding:40px 0'>
    <tr><td align='center'>
    <table width='520' cellpadding='0' cellspacing='0' style='background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)'>
        <tr>
            <td style='background:linear-gradient(135deg,#2d5a45,#3d7a5f);padding:32px;text-align:center'>
            <h1 style='color:#fff;margin:8px 0 0;font-size:1.5rem;letter-spacing:-.5px'>{$appName}</h1>
            <p style='color:rgba(255,255,255,.75);margin:6px 0 0;font-size:.9rem'>{$subtitle}</p>
        </td>
    </tr>
        <tr>
        <td style='padding:36px 40px'>
            <h2 style='color:#1e3a2f;margin:0 0 8px;font-size:1.2rem'>Hi {$name},</h2>
            <p style='color:#4a6b58;margin:0 0 28px;line-height:1.6'>{$body}</p>
            <div style='text-align:center;margin:0 0 8px;padding:24px;background:#f8fdf9;border-radius:14px;border:1px solid #d4e6da'>{$digits}</div>
            <div style='{$footer_style}'>
            <p style='{$footer_text_style}'>{$footer_note}</p>
            </div>
            </td>
        </tr>
        <tr>
            <td style='background:#f0f7f4;padding:20px 40px;text-align:center;border-top:1px solid #d4e6da'>
            <p style='color:#9ab5a5;font-size:.75rem;margin:0'>&copy; {$year} {$appName}. All rights reserved.</p>
            </td>
        </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>";
}