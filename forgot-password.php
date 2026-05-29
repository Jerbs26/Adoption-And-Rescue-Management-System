<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/vendor/autoload.php';
session_start_once();

if (is_logged_in()) {
    $user = current_user();
    redirect($user['role'] === 'admin'
        ? BASE_URL . '/modules/admin/dashboard.php'
        : BASE_URL . '/modules/adopter/dashboard.php'
    );
}

if (isset($_GET['resend'])) {
    $resend_email = $_SESSION['fp_email'] ?? '';
    if ($resend_email) {
        $stmt = db()->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$resend_email]);
        $resend_user = $stmt->fetch();
        if ($resend_user) {
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            db()->exec("CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                used TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            db()->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$resend_user['id']]);
            db()->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
                ->execute([$resend_user['id'], hash('sha256', $otp)]);

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
                $mail->addAddress($resend_user['email'], $resend_user['full_name'] ?? '');
                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = APP_NAME . ' - Password Reset Code';
                $mail->Body    = otp_email_template($resend_user['full_name'] ?? 'there', $otp, 'reset');
                $mail->AltBody = 'Your ' . APP_NAME . ' password reset code is: ' . $otp . ' (expires in 15 minutes).';
                $mail->send();
            } catch (Throwable $e) {
                error_log('Mailer Error: ' . $e->getMessage());
            }
        }
        $_SESSION['fp_step'] = 2;
    }
    redirect(BASE_URL . '/forgot-password.php?resent=1');
}

if (isset($_GET['restart'])) {
    unset($_SESSION['fp_step'], $_SESSION['fp_user_id'], $_SESSION['fp_email']);
    redirect(BASE_URL . '/forgot-password.php');
}

$step   = (int)($_SESSION['fp_step'] ?? 1);
$errors = [];
$email  = $_SESSION['fp_email'] ?? '';

// POST handler 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // send OTP 
    if ($action === 'send_otp') {
        $email = clean($_POST['email'] ?? '');
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
            $step = 1;
        } else {
            $stmt = db()->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                db()->exec("CREATE TABLE IF NOT EXISTS password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token VARCHAR(255) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    used TINYINT(1) DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");

                db()->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);
                // Use NOW() + INTERVAL entirely in MySQL to avoid PHP/MySQL timezone mismatch
                db()->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
                    ->execute([$user['id'], hash('sha256', $otp)]);

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
                    $mail->addAddress($user['email'], $user['full_name'] ?? '');
                    $mail->isHTML(true);
                    $mail->CharSet = 'UTF-8';
                    $mail->Subject = APP_NAME . ' - Password Reset Code';
                    $mail->Body    = otp_email_template($user['full_name'] ?? 'there', $otp, 'reset');
                    $mail->AltBody = 'Your ' . APP_NAME . ' password reset code is: ' . $otp . ' (expires in 15 minutes).';
                    $mail->send();
                    log_activity('forgot_password', 'user', $user['id'], 'Password reset OTP sent');
                } catch (Throwable $e) {
                    error_log('Mailer Error: ' . $e->getMessage());
                }
            }

            // Always advance (prevent enumeration)
            $_SESSION['fp_step']  = 2;
            $_SESSION['fp_email'] = $email;
            $step  = 2;
        }
    }

    // verify OTP 
    elseif ($action === 'verify_otp') {
        $email     = $_SESSION['fp_email'] ?? '';
        $otp_input = preg_replace('/\D/', '', trim($_POST['otp'] ?? ''));
        $step = 2;

        if (!$otp_input || strlen($otp_input) !== 6 || !ctype_digit($otp_input)) {
            $errors[] = 'Please enter the 6-digit code from your email.';
        } else {
            $stmt = db()->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            $valid = false;
            if ($user) {
                $stmt2 = db()->prepare("SELECT * FROM password_resets WHERE user_id = ? AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
                $stmt2->execute([$user['id']]);
                $row = $stmt2->fetch();
                if ($row && hash_equals($row['token'], hash('sha256', $otp_input))) {
                    $valid = true;
                }
            }

            if (!$valid) {
                $errors[] = 'The code is incorrect or has expired. Please try again.';
            } else {
                $_SESSION['fp_step']    = 3;
                $_SESSION['fp_user_id'] = (int)$user['id'];
                $step = 3;
            }
        }
    }

    // save new password 
    elseif ($action === 'save_password') {
        $uid      = (int)($_SESSION['fp_user_id'] ?? 0);
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $step = 3;

        if (!$uid) {
            $errors[] = 'Session expired. Please start over.';
            $_SESSION['fp_step'] = 1;
            $step = 1;
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        } else {
            // Verify OTP row still valid
            $stmt = db()->prepare("SELECT * FROM password_resets WHERE user_id = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
            $stmt->execute([$uid]);
            if (!$stmt->fetch()) {
                $errors[] = 'Your session expired. Please start over.';
                unset($_SESSION['fp_step'], $_SESSION['fp_user_id'], $_SESSION['fp_email']);
                $step = 1;
            } else {
                db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $uid]);
                db()->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ?")
                    ->execute([$uid]);

                unset($_SESSION['fp_step'], $_SESSION['fp_user_id'], $_SESSION['fp_email']);
                log_activity('reset_password', 'user', $uid, 'Password reset via OTP');

                flash('success', 'Your password has been reset. You can now sign in.');
                redirect(BASE_URL . '/login.php');
            }
        }
    }
}

$pageTitle = 'Forgot Password';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle . ' — ' . APP_NAME) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --green-deep:  #1e3a2f;
      --green-mid:   #2d5a45;
      --green-brand: #3d7a5f;
      --green-glow:  rgba(61,122,95,.18);
      --cream:       #faf8f4;
      --brown:       #3b2a1a;
      --text:        #1a2e25;
      --text-muted:  #6b8f7a;
      --border:      #d4e6da;
      --danger:      #c0392b;
      --danger-bg:   #fdf0ef;
      --success:     #2d5a45;
      --success-bg:  #e8f2ec;
      --shadow-card: 0 32px 80px rgba(30,58,47,.13), 0 4px 16px rgba(30,58,47,.07);
      --shadow-btn:  0 4px 20px rgba(61,122,95,.4);
    }

    html, body { height: 100%; font-family: 'Plus Jakarta Sans', sans-serif; background: var(--cream); color: var(--text); -webkit-font-smoothing: antialiased; }

    .page-bg { position: fixed; inset: 0; z-index: 0; background: var(--cream); overflow: hidden; }
    .page-bg::before { content: ''; position: absolute; top: -200px; right: -200px; width: 700px; height: 700px; background: radial-gradient(circle, rgba(61,122,95,.12) 0%, transparent 70%); border-radius: 50%; }
    .page-bg::after  { content: ''; position: absolute; bottom: -150px; left: -150px; width: 600px; height: 600px; background: radial-gradient(circle, rgba(93,163,120,.1) 0%, transparent 70%); border-radius: 50%; }
    .bg-dots { position: absolute; inset: 0; background-image: radial-gradient(circle, rgba(61,122,95,.08) 1px, transparent 1px); background-size: 28px 28px; }

    .paw-float { position: absolute; opacity: .045; color: var(--green-brand); font-size: 2.5rem; animation: floatPaw 12s ease-in-out infinite; }
    .paw-float:nth-child(2) { top: 10%; left: 8%;    font-size: 2rem;   animation-delay: 0s; }
    .paw-float:nth-child(3) { top: 25%; right: 6%;   font-size: 3.5rem; animation-delay: 2s; }
    .paw-float:nth-child(4) { bottom: 20%; left: 5%; font-size: 1.8rem; animation-delay: 4s; }
    .paw-float:nth-child(5) { bottom: 35%; right: 9%;font-size: 2.8rem; animation-delay: 6s; }
    .paw-float:nth-child(6) { top: 60%; left: 12%;   font-size: 1.5rem; animation-delay: 3s; }
    @keyframes floatPaw { 0%,100%{transform:translateY(0) rotate(-8deg)} 50%{transform:translateY(-18px) rotate(8deg)} }

    .auth-wrap { position: relative; z-index: 1; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }

    .auth-card { width: 100%; max-width: 440px; background: #fff; border-radius: 24px; box-shadow: var(--shadow-card); border: 1px solid rgba(212,230,218,.6); padding: 2.75rem 2.5rem 2.25rem; animation: cardIn .6s cubic-bezier(.22,1,.36,1) both; }
    @keyframes cardIn { from{opacity:0;transform:translateY(28px) scale(.97)} to{opacity:1;transform:translateY(0) scale(1)} }

    .auth-logo { text-align: center; margin-bottom: 1.75rem; }
    .brand-icon { width: 64px; height: 64px; background: linear-gradient(135deg, var(--green-mid), var(--green-brand)); border-radius: 18px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; color: #fff; margin-bottom: 1rem; box-shadow: 0 8px 24px rgba(61,122,95,.3); transition: transform .3s; cursor: default; }
    .brand-icon:hover { transform: rotate(-6deg) scale(1.08); }
    .auth-logo h1 { font-family: 'Instrument Serif', serif; font-size: 2rem; font-weight: 400; color: var(--brown); letter-spacing: -.5px; line-height: 1; margin-bottom: .35rem; }
    .auth-logo p { font-size: .875rem; color: var(--text-muted); font-weight: 500; }

    /* Step indicator */
    .steps { display: flex; align-items: center; justify-content: center; margin-bottom: 1.75rem; gap: 0; }
    .step-dot { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 700; border: 2px solid var(--border); background: #fff; color: var(--text-muted); transition: all .3s; }
    .step-dot.active { background: linear-gradient(135deg, var(--green-mid), var(--green-brand)); border-color: var(--green-brand); color: #fff; }
    .step-dot.done   { background: #e8f2ec; border-color: var(--green-brand); color: var(--green-brand); }
    .step-line { flex: 1; height: 2px; background: var(--border); max-width: 60px; transition: background .3s; }
    .step-line.done { background: var(--green-brand); }

    .alert { display: flex; align-items: flex-start; gap: .6rem; padding: .85rem 1rem; border-radius: 10px; font-size: .875rem; font-weight: 500; margin-bottom: 1.25rem; animation: alertIn .35s ease both; line-height: 1.5; }
    @keyframes alertIn { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
    .alert i { margin-top: 2px; flex-shrink: 0; }
    .alert-danger  { background: var(--danger-bg);  color: var(--danger);  border: 1px solid #f5c6c3; }
    .alert-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--border); }

    .hint { font-size: .85rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 1.5rem; }

    .field { margin-bottom: 1.1rem; }
    .field label { display: block; font-size: .75rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: var(--text-muted); margin-bottom: .45rem; }
    .input-wrap { position: relative; }
    .input-wrap .input-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: .85rem; pointer-events: none; transition: color .2s; }
    .input-wrap input { width: 100%; padding: .875rem 1rem .875rem 2.75rem; border: 1.5px solid var(--border); border-radius: 10px; font-family: inherit; font-size: .95rem; font-weight: 500; color: var(--text); background: var(--cream); transition: border-color .2s, box-shadow .2s, background .2s; outline: none; }
    .input-wrap input::placeholder { color: #b0c8bb; font-weight: 400; }
    .input-wrap input:focus { border-color: var(--green-brand); background: #fff; box-shadow: 0 0 0 4px var(--green-glow); }
    .input-wrap:focus-within .input-icon { color: var(--green-brand); }

    /* OTP boxes */
    .otp-wrap { display: flex; gap: .5rem; justify-content: center; margin-bottom: .5rem; }
    .otp-wrap input { width: 52px; height: 60px; text-align: center; font-size: 1.5rem; font-weight: 800; border: 2px solid var(--border); border-radius: 12px; background: var(--cream); color: var(--text); transition: border-color .2s, box-shadow .2s, background .2s; outline: none; padding: 0; caret-color: var(--green-brand); }
    .otp-wrap input:focus  { border-color: var(--green-brand); background: #fff; box-shadow: 0 0 0 4px var(--green-glow); }
    .otp-wrap input.filled { border-color: var(--green-brand); background: #f0f7f4; }
    #otp_value { display: none; }

    /* Password strength */
    .pw-toggle { position: absolute; right: .875rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: .25rem; font-size: .9rem; transition: color .2s; }
    .pw-toggle:hover { color: var(--green-brand); }
    .strength-wrap { display: flex; align-items: center; gap: .6rem; margin-top: .5rem; }
    .strength-bar  { flex: 1; height: 5px; background: var(--border); border-radius: 99px; overflow: hidden; }
    .strength-fill { height: 100%; width: 0; border-radius: 99px; transition: width .3s, background .3s; }
    .strength-label { font-size: .72rem; font-weight: 700; color: var(--text-muted); white-space: nowrap; }
    .requirements { display: flex; flex-wrap: wrap; gap: .35rem .75rem; margin: .6rem 0 1rem; padding: .75rem; background: var(--cream); border-radius: 10px; border: 1px solid var(--border); }
    .req-item { font-size: .75rem; color: var(--text-muted); display: flex; align-items: center; gap: .3rem; transition: color .2s; }
    .req-item.met { color: var(--green-brand); }
    .req-item i { font-size: .65rem; }

    .btn-submit { width: 100%; padding: .95rem; background: linear-gradient(135deg, var(--green-mid) 0%, var(--green-brand) 100%); color: #fff; border: none; border-radius: 10px; font-family: inherit; font-size: .95rem; font-weight: 700; letter-spacing: .02em; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: .6rem; box-shadow: var(--shadow-btn); transition: transform .2s, box-shadow .2s; position: relative; overflow: hidden; }
    .btn-submit::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg,rgba(255,255,255,.12),transparent); opacity: 0; transition: opacity .2s; }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(61,122,95,.5); }
    .btn-submit:hover::before { opacity: 1; }
    .btn-submit:active { transform: translateY(0); }

    .divider { border: none; border-top: 1px solid var(--border); margin: 1.5rem 0 1.25rem; }
    .auth-footer { text-align: center; }
    .auth-footer p { font-size: .85rem; color: var(--text-muted); margin-bottom: .5rem; }
    .auth-footer a { font-weight: 700; color: var(--green-brand); text-decoration: none; }
    .auth-footer a:hover { text-decoration: underline; }
    .back-home { display: inline-flex; align-items: center; gap: .4rem; font-size: .82rem; color: var(--text-muted) !important; font-weight: 500 !important; }
    .back-home:hover { color: var(--text) !important; text-decoration: none !important; }

    .resend-row { text-align: center; margin-top: .85rem; font-size: .82rem; color: var(--text-muted); }
    .resend-row a { color: var(--green-brand); font-weight: 600; text-decoration: none; }
    .resend-row a:hover { text-decoration: underline; }

    .email-chip { display: inline-flex; align-items: center; gap: .4rem; background: #e8f2ec; border: 1px solid var(--border); border-radius: 20px; padding: .3rem .85rem; font-size: .85rem; font-weight: 600; color: var(--green-deep); margin-bottom: 1.25rem; }
  </style>
</head>
<body>

<div class="page-bg">
  <div class="bg-dots"></div>
  <i class="fa-solid fa-paw paw-float"></i>
  <i class="fa-solid fa-paw paw-float"></i>
  <i class="fa-solid fa-paw paw-float"></i>
  <i class="fa-solid fa-paw paw-float"></i>
  <i class="fa-solid fa-paw paw-float"></i>
</div>

<div class="auth-wrap">
  <div class="auth-card">

    <?php if ($step === 1): ?>
    <div class="auth-logo">
      <div class="brand-icon"><i class="fa-solid fa-key"></i></div>
      <h1>Forgot Password?</h1>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i><?= e($errors[0]) ?></div>
    <?php endif; ?>

    <p class="hint">Enter the email linked to your account and we'll send you a 6-digit verification code.</p>

    <form method="post" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="send_otp">

      <div class="field">
        <label for="email">Email Address</label>
        <div class="input-wrap">
          <i class="fa-solid fa-envelope input-icon"></i>
          <input id="email" name="email" type="email" value="<?= e($email) ?>" placeholder="you@example.com" autocomplete="email" required autofocus>
        </div>
      </div>

      <button class="btn-submit" type="submit">
        <i class="fa-solid fa-paper-plane"></i> Send Code
      </button>
    </form>

    <hr class="divider">
    <div class="auth-footer">
      <p>Remembered it? <a href="<?= BASE_URL ?>/login.php">Sign In</a></p>
      <p style="margin-top:.5rem"><a href="<?= BASE_URL ?>/index.php" class="back-home"><i class="fa-solid fa-arrow-left"></i> Back to home</a></p>
    </div>


    <?php elseif ($step === 2): ?>

    <div class="auth-logo">
      <div class="brand-icon"><i class="fa-solid fa-shield-halved"></i></div>
      <h1>Check Your Email</h1>
      <p>Enter the 6-digit code we sent you</p>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($errors[0]) ?></span></div>
    <?php endif; ?>
    <?php if (isset($_GET['resent'])): ?>
    <div class="alert alert-success"><i class="fa-solid fa-check"></i> A new code has been sent to your email.</div>
    <?php endif; ?>

    <div style="text-align:center">
      <span class="email-chip"><i class="fa-solid fa-envelope"></i><?= e($email) ?></span>
    </div>

    <form method="post" action="" id="otpForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="verify_otp">

      <div class="field">
        <label style="text-align:center;display:block">Verification Code</label>
        <div class="otp-wrap" id="otpBoxes">
          <input type="text" inputmode="numeric" maxlength="1" class="otp-digit" autocomplete="one-time-code">
          <input type="text" inputmode="numeric" maxlength="1" class="otp-digit">
          <input type="text" inputmode="numeric" maxlength="1" class="otp-digit">
          <input type="text" inputmode="numeric" maxlength="1" class="otp-digit">
          <input type="text" inputmode="numeric" maxlength="1" class="otp-digit">
          <input type="text" inputmode="numeric" maxlength="1" class="otp-digit">
        </div>
        <input type="hidden" name="otp" id="otp_value">
      </div>

      <button class="btn-submit" type="submit">
        <i class="fa-solid fa-arrow-right"></i> Verify Code
      </button>
    </form>

    <div class="resend-row">Didn't get a code? <a href="<?= BASE_URL ?>/forgot-password.php?resend=1">Resend it</a></div>

    <hr class="divider">
    <div class="auth-footer">
      <p><a href="<?= BASE_URL ?>/forgot-password.php?restart=1"><i class="fa-solid fa-arrow-left" style="margin-right:.3rem"></i>Use a different email</a></p>
    </div>


    <?php elseif ($step === 3): ?>

    <div class="auth-logo">
      <div class="brand-icon"><i class="fa-solid fa-lock"></i></div>
      <h1>New Password</h1>
      <p>Choose a strong password</p>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($errors[0]) ?></span></div>
    <?php endif; ?>

    <form method="post" action="" id="resetForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_password">

      <div class="field">
        <label for="password">New Password</label>
        <div class="input-wrap">
          <i class="fa-solid fa-lock input-icon"></i>
          <input id="password" name="password" type="password" placeholder="At least 8 characters" autocomplete="new-password" required autofocus>
          <button type="button" class="pw-toggle" id="pwToggle1"><i class="fa-solid fa-eye" id="pwIcon1"></i></button>
        </div>
      </div>

      <div class="field">
        <label for="confirm_password">Confirm Password</label>
        <div class="input-wrap">
          <i class="fa-solid fa-lock input-icon"></i>
          <input id="confirm_password" name="confirm_password" type="password" placeholder="Re-enter your password" autocomplete="new-password" required>
          <button type="button" class="pw-toggle" id="pwToggle2"><i class="fa-solid fa-eye" id="pwIcon2"></i></button>
        </div>
      </div>

      <div class="strength-wrap">
        <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
        <span class="strength-label" id="strengthLabel">Enter a password</span>
      </div>
      <div class="requirements">
        <span class="req-item" id="req-len"><i class="fa-solid fa-circle"></i> 8+ characters</span>
        <span class="req-item" id="req-upper"><i class="fa-solid fa-circle"></i> Uppercase letter</span>
        <span class="req-item" id="req-num"><i class="fa-solid fa-circle"></i> Number</span>
        <span class="req-item" id="req-special"><i class="fa-solid fa-circle"></i> Special character</span>
      </div>

      <button class="btn-submit" type="submit">
        <i class="fa-solid fa-check"></i> Reset Password
      </button>
    </form>

    <hr class="divider">
    <div class="auth-footer">
      <p>Remembered it? <a href="<?= BASE_URL ?>/login.php">Sign In</a></p>
    </div>

    <?php endif; ?>

  </div><!-- /.auth-card -->
</div><!-- /.auth-wrap -->

<script src="<?= BASE_URL ?>/public/js/main.js"></script>
<script>
// Clean up query params from URL without reload 
(function () {
  const url = new URL(location.href);
  if (url.searchParams.has('resent')) {
    url.searchParams.delete('resent');
    history.replaceState(null, '', url.toString());
  }
})();

// OTP boxes (Step 2) 
(function () {
  const boxes  = document.querySelectorAll('.otp-digit');
  const hidden = document.getElementById('otp_value');
  const form   = document.getElementById('otpForm');
  if (!boxes.length) return;

  function sync() {
    hidden.value = [...boxes].map(b => b.value).join('');
  }

  boxes.forEach((box, i) => {
    if (i === 0) box.focus();

    box.addEventListener('input', () => {
      box.value = box.value.replace(/\D/g, '').slice(-1);
      box.classList.toggle('filled', box.value !== '');
      sync();
      if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
    });

    box.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !box.value && i > 0) {
        boxes[i - 1].value = '';
        boxes[i - 1].classList.remove('filled');
        boxes[i - 1].focus();
        sync();
      }
    });

    box.addEventListener('paste', e => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
      pasted.split('').forEach((ch, j) => { if (boxes[j]) { boxes[j].value = ch; boxes[j].classList.add('filled'); } });
      sync();
      boxes[Math.min(pasted.length, boxes.length - 1)].focus();
    });
  });

  if (form) {
    form.addEventListener('submit', e => {
      sync();
      // Guard: all 6 digits must be present before submitting
      if (!hidden.value || hidden.value.length !== 6) {
        e.preventDefault();
        boxes[0].focus();
      }
    });
  }
})();

// Password toggles (Step 3) 
function setupToggle(btnId, iconId, inputId) {
  const btn = document.getElementById(btnId);
  const icon = document.getElementById(iconId);
  const inp  = document.getElementById(inputId);
  if (!btn) return;
  btn.addEventListener('click', () => {
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    icon.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
  });
}
setupToggle('pwToggle1', 'pwIcon1', 'password');
setupToggle('pwToggle2', 'pwIcon2', 'confirm_password');

// Strength meter (Step 3) 
const pwInput = document.getElementById('password');
const fill    = document.getElementById('strengthFill');
const label   = document.getElementById('strengthLabel');
const checks  = {
  'req-len':     v => v.length >= 8,
  'req-upper':   v => /[A-Z]/.test(v),
  'req-num':     v => /[0-9]/.test(v),
  'req-special': v => /[^A-Za-z0-9]/.test(v),
};
const levels = [
  { pct: '0%',   color: 'transparent', text: 'Enter a password' },
  { pct: '25%',  color: '#e74c3c',     text: 'Weak' },
  { pct: '50%',  color: '#e67e22',     text: 'Fair' },
  { pct: '75%',  color: '#f1c40f',     text: 'Good' },
  { pct: '100%', color: '#2d5a45',     text: 'Strong' },
];
if (pwInput) {
  pwInput.addEventListener('input', () => {
    const v = pwInput.value;
    let score = 0;
    for (const [id, fn] of Object.entries(checks)) {
      const el = document.getElementById(id);
      const ok = fn(v);
      if (ok) score++;
      el.classList.toggle('met', ok);
      el.querySelector('i').className = ok ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle';
    }
    const lvl = v.length === 0 ? 0 : score;
    fill.style.width      = levels[lvl].pct;
    fill.style.background = levels[lvl].color;
    label.textContent     = levels[lvl].text;
    label.style.color     = levels[lvl].color === 'transparent' ? 'var(--text-muted)' : levels[lvl].color;
  });
}
</script>
</body>
</html>