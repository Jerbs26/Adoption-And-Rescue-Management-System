<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
session_start_once();

if (!is_logged_in() && isset($_COOKIE['remember_token'])) {
    $raw_token  = $_COOKIE['remember_token'];
    $token_hash = hash('sha256', $raw_token);

    try {
        $stmt = db()->prepare(
            "SELECT u.*,
                    rt.id         AS rt_id,
                    rt.token_hash AS rt_token_hash,
                    rt.expires_at AS rt_expires_at
              FROM remember_tokens rt
              JOIN users u ON u.id = rt.user_id
              WHERE rt.token_hash = ? AND rt.expires_at > NOW() AND u.is_active = 1
              LIMIT 1"
        );
        $stmt->execute([$token_hash]);
        $row = $stmt->fetch();

        if ($row) {
            // Rotate token (prevent token re-use attacks)
            $new_raw  = bin2hex(random_bytes(32));
            $new_hash = hash('sha256', $new_raw);
            $expires  = date('Y-m-d H:i:s', strtotime('+30 days'));

            db()->prepare("UPDATE remember_tokens SET token_hash = ?, expires_at = ? WHERE token_hash = ?")
                  ->execute([$new_hash, $expires, $token_hash]);

            setcookie('remember_token', $new_raw, [
                'expires'  => strtotime('+30 days'),
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => isset($_SERVER['HTTPS']),
            ]);

            // Strip aliased token columns before passing to login_user
            $user = array_diff_key($row, array_flip(['rt_id', 'rt_token_hash', 'rt_expires_at']));
            login_user($user);
            log_activity('login', 'user', $user['id'], 'User auto-logged in via Remember Me');

            // Redirect immediately after auto-login
            redirect(role_dashboard($user['role']));
        } else {
            // Invalid/expired token — clear the cookie
            setcookie('remember_token', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    } catch (PDOException $e) {
        setcookie('remember_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (is_logged_in()) {
    redirect(role_dashboard(current_user()['role']));
}

$errors = [];
// Pre-fill email from "remembered_email" cookie (set on logout when Remember Me was active)
$email        = isset($_COOKIE['remembered_email']) ? clean($_COOKIE['remembered_email']) : '';
$isRemembered = $email !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email       = clean($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);

    if (!$email || !$password) {
        $errors[] = 'Please enter your username and password.';
    } else {
        $stmt = db()->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            login_user($user);
            log_activity('login', 'user', $user['id'], 'User logged in');

            // Clear the remembered_email cookie on successful login
            setcookie('remembered_email', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);

            // Set Remember Me cookie & token 
            if ($remember_me) {
                $raw_token  = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $raw_token);
                $expires    = date('Y-m-d H:i:s', strtotime('+30 days'));

                try {
                    // Purge expired tokens for this user (housekeeping)
                    db()->prepare("DELETE FROM remember_tokens WHERE user_id = ? AND expires_at < NOW()")
                          ->execute([$user['id']]);

                    db()->prepare("INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)")
                          ->execute([$user['id'], $token_hash, $expires]);

                    setcookie('remember_token', $raw_token, [
                        'expires'  => strtotime('+30 days'),
                        'path'     => '/',
                        'httponly' => true,
                        'samesite' => 'Lax',
                        'secure'   => isset($_SERVER['HTTPS']),
                    ]);
                } catch (PDOException $e) {
                    // Table missing — login still succeeds, just without persistence
                }
            }

            $redirect = clean($_GET['redirect'] ?? '');
            if ($redirect &&
                str_starts_with($redirect, '/') &&
                !str_starts_with($redirect, '//') &&
                filter_var(BASE_URL . $redirect, FILTER_VALIDATE_URL)
            ) {
                redirect(BASE_URL . $redirect);
            }
            if ($user['role'] === 'admin') {
                redirect(BASE_URL . '/modules/admin/dashboard.php');
            } else {
                redirect(role_dashboard($user['role']));
            }
        } else {
            $errors[] = 'Invalid username or password.';
        }
    }
}

$pageTitle = 'Login';
$bodyClass = 'auth-page';
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
      --green-deep:   #1e3a2f;
      --green-mid:    #2d5a45;
      --green-brand:  #3d7a5f;
      --green-light:  #5a9e7c;
      --green-pale:   #e8f2ec;
      --green-glow:   rgba(61,122,95,.18);
      --cream:        #faf8f4;
      --cream-dark:   #f2ede4;
      --brown:        #3b2a1a;
      --text:         #1a2e25;
      --text-muted:   #6b8f7a;
      --border:       #d4e6da;
      --danger:       #c0392b;
      --danger-bg:    #fdf0ef;
      --success:      #2d5a45;
      --success-bg:   #e8f2ec;
      --shadow-card:  0 32px 80px rgba(30,58,47,.13), 0 4px 16px rgba(30,58,47,.07);
      --shadow-btn:   0 4px 20px rgba(61,122,95,.4);
    }

    html, body {
      height: 100%;
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--cream);
      color: var(--text);
      -webkit-font-smoothing: antialiased;
    }

    .page-bg {
      position: fixed; inset: 0; z-index: 0;
      background: var(--cream);
      overflow: hidden;
    }
    .page-bg::before {
      content: '';
      position: absolute;
      top: -200px; right: -200px;
      width: 700px; height: 700px;
      background: radial-gradient(circle, rgba(61,122,95,.12) 0%, transparent 70%);
      border-radius: 50%;
    }
    .page-bg::after {
      content: '';
      position: absolute;
      bottom: -150px; left: -150px;
      width: 600px; height: 600px;
      background: radial-gradient(circle, rgba(93,163,120,.1) 0%, transparent 70%);
      border-radius: 50%;
    }
    .bg-dots {
      position: absolute; inset: 0;
      background-image: radial-gradient(circle, rgba(61,122,95,.08) 1px, transparent 1px);
      background-size: 28px 28px;
    }

    .paw-float {
      position: absolute;
      opacity: .045;
      color: var(--green-brand);
      font-size: 2.5rem;
      animation: floatPaw 12s ease-in-out infinite;
    }
    .paw-float:nth-child(2) { top: 10%; left: 8%;  font-size: 2rem;   animation-delay: 0s; }
    .paw-float:nth-child(3) { top: 25%; right: 6%; font-size: 3.5rem; animation-delay: 2s; }
    .paw-float:nth-child(4) { bottom: 20%; left: 5%; font-size: 1.8rem; animation-delay: 4s; }
    .paw-float:nth-child(5) { bottom: 35%; right: 9%; font-size: 2.8rem; animation-delay: 6s; }
    .paw-float:nth-child(6) { top: 60%; left: 12%; font-size: 1.5rem; animation-delay: 3s; }
    @keyframes floatPaw {
      0%, 100% { transform: translateY(0) rotate(-8deg); }
      50%       { transform: translateY(-18px) rotate(8deg); }
    }

    .auth-wrap {
      position: relative; z-index: 1;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
    }

    .auth-card {
      width: 100%;
      max-width: 440px;
      background: #fff;
      border-radius: 24px;
      box-shadow: var(--shadow-card);
      border: 1px solid rgba(212,230,218,.6);
      padding: 2.75rem 2.5rem 2.25rem;
      animation: cardIn .6s cubic-bezier(.22,1,.36,1) both;
    }
    @keyframes cardIn {
      from { opacity: 0; transform: translateY(28px) scale(.97); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    .auth-logo {
      text-align: center;
      margin-bottom: 2rem;
    }
    .brand-icon {
      width: 64px; height: 64px;
      background: linear-gradient(135deg, var(--green-mid), var(--green-brand));
      border-radius: 18px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      color: #fff;
      margin-bottom: 1rem;
      box-shadow: 0 8px 24px rgba(61,122,95,.3);
      transition: transform .3s ease;
      cursor: default;
    }
    .brand-icon:hover { transform: rotate(-6deg) scale(1.08); }
    .auth-logo h1 {
      font-family: 'Instrument Serif', serif;
      font-size: 2rem;
      font-weight: 400;
      color: var(--brown);
      letter-spacing: -.5px;
      line-height: 1;
      margin-bottom: .35rem;
    }
    .auth-logo p {
      font-size: .875rem;
      color: var(--text-muted);
      font-weight: 500;
    }

    .alert {
      display: flex;
      align-items: center;
      gap: .6rem;
      padding: .85rem 1rem;
      border-radius: 10px;
      font-size: .875rem;
      font-weight: 500;
      margin-bottom: 1.25rem;
      animation: alertIn .35s ease both;
    }
    @keyframes alertIn {
      from { opacity: 0; transform: translateY(-8px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .alert-danger  { background: var(--danger-bg);  color: var(--danger);  border: 1px solid #f5c6c3; }
    .alert-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--border); }

    .field { margin-bottom: 1.1rem; }
    .field label {
      display: block;
      font-size: .75rem;
      font-weight: 700;
      letter-spacing: .07em;
      text-transform: uppercase;
      color: var(--text-muted);
      margin-bottom: .45rem;
    }
    .input-wrap { position: relative; }
    .input-wrap .input-icon {
      position: absolute;
      left: 1rem; top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      font-size: .85rem;
      pointer-events: none;
      transition: color .2s;
    }
    .input-wrap input {
      width: 100%;
      padding: .875rem 1rem .875rem 2.75rem;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      font-family: inherit;
      font-size: .95rem;
      font-weight: 500;
      color: var(--text);
      background: var(--cream);
      transition: border-color .2s, box-shadow .2s, background .2s;
      outline: none;
    }
    .input-wrap input::placeholder { color: #b0c8bb; font-weight: 400; }
    .input-wrap input:focus {
      border-color: var(--green-brand);
      background: #fff;
      box-shadow: 0 0 0 4px var(--green-glow);
    }
    .input-wrap:focus-within .input-icon { color: var(--green-brand); }

    .pw-toggle {
      position: absolute;
      right: 1rem; top: 50%;
      transform: translateY(-50%);
      background: none; border: none;
      color: var(--text-muted);
      cursor: pointer;
      padding: 0;
      font-size: .85rem;
      transition: color .2s;
    }
    .pw-toggle:hover { color: var(--green-brand); }

    .remember-forgot-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin: -.2rem 0 1.25rem;
    }
    .remember-label {
      display: flex;
      align-items: center;
      gap: .5rem;
      cursor: pointer;
      user-select: none;
    }
    .remember-label input[type="checkbox"] {
      appearance: none;
      -webkit-appearance: none;
      width: 17px; height: 17px;
      border: 1.5px solid var(--border);
      border-radius: 5px;
      background: var(--cream);
      cursor: pointer;
      flex-shrink: 0;
      transition: border-color .2s, background .2s, box-shadow .2s;
      position: relative;
    }
    .remember-label input[type="checkbox"]:checked {
      background: var(--green-brand);
      border-color: var(--green-brand);
      box-shadow: 0 0 0 3px var(--green-glow);
    }
    .remember-label input[type="checkbox"]:checked::after {
      content: '';
      position: absolute;
      left: 50%; top: 50%;
      width: 9px; height: 5px;
      border-left: 2px solid #fff;
      border-bottom: 2px solid #fff;
      transform: translate(-50%, -65%) rotate(-45deg);
    }
    .remember-label input[type="checkbox"]:focus-visible {
      outline: none;
      box-shadow: 0 0 0 4px var(--green-glow);
    }
    .remember-label span {
      font-size: .8rem;
      font-weight: 600;
      color: var(--text-muted);
    }
    .remember-label:hover span { color: var(--text); }
    .remember-label:hover input[type="checkbox"] { border-color: var(--green-brand); }

    .forgot-link {
      font-size: .8rem;
      font-weight: 600;
      color: var(--green-brand);
      text-decoration: none;
    }
    .forgot-link:hover { text-decoration: underline; }

    .btn-signin {
      width: 100%;
      padding: .95rem;
      background: linear-gradient(135deg, var(--green-mid) 0%, var(--green-brand) 100%);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-family: inherit;
      font-size: .95rem;
      font-weight: 700;
      letter-spacing: .02em;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: .6rem;
      box-shadow: var(--shadow-btn);
      transition: transform .2s, box-shadow .2s, filter .2s;
      position: relative;
      overflow: hidden;
    }
    .btn-signin::before {
      content: '';
      position: absolute; inset: 0;
      background: linear-gradient(135deg, rgba(255,255,255,.12), transparent);
      opacity: 0;
      transition: opacity .2s;
    }
    .btn-signin:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 28px rgba(61,122,95,.5);
    }
    .btn-signin:hover::before { opacity: 1; }
    .btn-signin:active { transform: translateY(0); }
    .btn-signin i { transition: transform .3s; }
    .btn-signin:hover i { transform: translateX(4px); }

    .divider {
      border: none;
      border-top: 1px solid var(--border);
      margin: 1.5rem 0 1.25rem;
    }

    .auth-footer { text-align: center; }
    .auth-footer p {
      font-size: .85rem;
      color: var(--text-muted);
      margin-bottom: .5rem;
    }
    .auth-footer a {
      font-weight: 700;
      color: var(--green-brand);
      text-decoration: none;
    }
    .auth-footer a:hover { text-decoration: underline; }
    .back-home {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      font-size: .82rem;
      color: var(--text-muted) !important;
      font-weight: 500 !important;
    }
    .back-home:hover { color: var(--text) !important; text-decoration: none !important; }
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

    <div class="auth-logo">
      <div class="brand-icon"><i class="fa-solid fa-paw"></i></div>
      <h1><?= APP_NAME ?></h1>
      <p>Sign in to your account</p>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-danger">
      <i class="fa-solid fa-circle-exclamation"></i>
      <?= e($errors[0]) ?>
    </div>
    <?php endif; ?>

    <?php $msg = get_flash('success'); if ($msg): ?>
    <div class="alert alert-success" data-auto-dismiss>
      <i class="fa-solid fa-check"></i>
      <?= e($msg) ?>
    </div>
    <?php endif; ?>

    <form method="post" action="">
      <?= csrf_field() ?>

      <div class="field">
        <label for="email">Username</label>
        <div class="input-wrap">
          <i class="fa-solid fa-user input-icon"></i>
          <input
            id="email"
            name="email"
            type="text"
            value="<?= e($email) ?>"
            placeholder="Enter your username"
            autocomplete="username"
            required
          >
        </div>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="input-wrap">
          <i class="fa-solid fa-lock input-icon"></i>
          <input
            id="password"
            name="password"
            type="password"
            placeholder="Enter your password"
            autocomplete="current-password"
            required
          >
          <button type="button" class="pw-toggle" id="pwToggle" aria-label="Toggle password visibility">
            <i class="fa-solid fa-eye" id="pwIcon"></i>
          </button>
        </div>
      </div>

      <div class="remember-forgot-row">
        <label class="remember-label">
          <input type="checkbox" name="remember_me" id="rememberMe" <?= $isRemembered ? 'checked' : '' ?>>
          <span>Remember me</span>
        </label>
        <a href="<?= BASE_URL ?>/forgot-password.php" class="forgot-link">Forgot password?</a>
      </div>

      <button class="btn-signin" type="submit">
        Sign In <i class="fa-solid fa-arrow-right"></i>
      </button>
    </form>

    <hr class="divider">

    <div class="auth-footer">
      <p>Don't have an account? <a href="<?= BASE_URL ?>/register.php">Sign Up</a></p>
      <p style="margin-top:.5rem">
        <a href="<?= BASE_URL ?>/index.php" class="back-home">
          <i class="fa-solid fa-arrow-left"></i> Back to home
        </a>
      </p>
    </div>

  </div>
</div>

<script src="<?= BASE_URL ?>/public/js/main.js"></script>
<script>
  const pwToggle = document.getElementById('pwToggle');
  const pwInput  = document.getElementById('password');
  const pwIcon   = document.getElementById('pwIcon');
  pwToggle.addEventListener('click', () => {
    const show = pwInput.type === 'password';
    pwInput.type = show ? 'text' : 'password';
    pwIcon.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
  });
</script>
</body>
</html>