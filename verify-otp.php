<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
session_start_once();

// Guard — must have a pending OTP session
if (empty($_SESSION['otp_user_id'])) {
    redirect(BASE_URL . '/register.php');
}

$userId = (int)$_SESSION['otp_user_id'];
$email  = $_SESSION['otp_email'] ?? '';
$errors = [];
$success = false;

// Handle resend
if (isset($_GET['resend'])) {
    // Fetch user
    $u = db()->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $u->execute([$userId]);
    $u = $u->fetch();
    if ($u) {
        $otp  = generate_otp();
        save_otp($userId, $otp);
        $sent = send_otp_email($u['email'], $u['full_name'], $otp);
        if ($sent) {
            flash('success', 'A new verification code has been sent to your email.');
        } else {
            flash('error', 'Failed to resend. Please try again.');
        }
    }
    redirect(BASE_URL . '/verify-otp.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Collect the 6 digits — trim each to remove any stray whitespace
    $digits = [];
    for ($i = 1; $i <= 6; $i++) {
        $digits[] = trim(clean($_POST['d' . $i] ?? ''));
    }
    $enteredOtp = trim(implode('', $digits));

    if (strlen($enteredOtp) !== 6 || !ctype_digit($enteredOtp)) {
        $errors[] = 'Please enter the complete 6-digit code.';
    } else {
        // Look up valid OTP
        $stmt = db()->prepare("
            SELECT id FROM otp_verifications
            WHERE user_id = ? AND CAST(otp_code AS CHAR) = ? AND used = 0 AND expires_at > NOW()
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$userId, $enteredOtp]);
        $otpRow = $stmt->fetch();

        if (!$otpRow) {
            $errors[] = 'Invalid or expired code. Please try again or request a new one.';
        } else {
            // Mark OTP used + verify user
            db()->prepare("UPDATE otp_verifications SET used = 1 WHERE id = ?")
                ->execute([$otpRow['id']]);
            db()->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")
                ->execute([$userId]);

            log_activity('email_verified', 'user', $userId, 'User verified email via OTP');

            // Clear OTP session
            unset($_SESSION['otp_user_id'], $_SESSION['otp_email']);

            flash('success', 'Email verified! You can now sign in.');
            redirect(BASE_URL . '/login.php');
        }
    }
}

$flashSuccess = get_flash('success');
$flashError   = get_flash('error');
$pageTitle    = 'Verify Your Email';
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
      --green-pale:  #e8f2ec;
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
    html, body {
      min-height: 100%;
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--cream); color: var(--text);
      -webkit-font-smoothing: antialiased;
    }
    .page-bg { position: fixed; inset: 0; z-index: 0; overflow: hidden; }
    .page-bg::before {
      content: ''; position: absolute; top: -200px; right: -200px;
      width: 700px; height: 700px;
      background: radial-gradient(circle, rgba(61,122,95,.12) 0%, transparent 70%);
      border-radius: 50%;
    }
    .page-bg::after {
      content: ''; position: absolute; bottom: -150px; left: -150px;
      width: 600px; height: 600px;
      background: radial-gradient(circle, rgba(93,163,120,.1) 0%, transparent 70%);
      border-radius: 50%;
    }
    .bg-dots {
      position: absolute; inset: 0;
      background-image: radial-gradient(circle, rgba(61,122,95,.08) 1px, transparent 1px);
      background-size: 28px 28px;
    }
    .auth-wrap {
      position: relative; z-index: 1;
      min-height: 100vh; display: flex;
      align-items: center; justify-content: center;
      padding: 2rem 1rem;
    }
    .auth-card {
      width: 100%; max-width: 460px;
      background: #fff; border-radius: 24px;
      box-shadow: var(--shadow-card);
      border: 1px solid rgba(212,230,218,.6);
      padding: 2.75rem 2.5rem 2.25rem;
      animation: cardIn .6s cubic-bezier(.22,1,.36,1) both;
    }
    @keyframes cardIn {
      from { opacity: 0; transform: translateY(28px) scale(.97); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    .auth-logo { text-align: center; margin-bottom: 1.75rem; }
    .brand-icon {
      width: 64px; height: 64px;
      background: linear-gradient(135deg, var(--green-mid), var(--green-brand));
      border-radius: 18px; display: inline-flex;
      align-items: center; justify-content: center;
      font-size: 1.6rem; color: #fff; margin-bottom: 1rem;
      box-shadow: 0 8px 24px rgba(61,122,95,.3);
    }
    .auth-logo h1 {
      font-family: 'Instrument Serif', serif;
      font-size: 1.75rem; font-weight: 400;
      color: var(--brown); margin-bottom: .25rem;
    }
    .auth-logo p { font-size: .875rem; color: var(--text-muted); font-weight: 500; }
    .email-badge {
      display: inline-block;
      background: var(--green-pale); border: 1px solid var(--border);
      border-radius: 20px; padding: .3rem .9rem;
      font-size: .85rem; font-weight: 600; color: var(--green-mid);
      margin-top: .5rem;
    }
    .alert {
      display: flex; align-items: flex-start; gap: .6rem;
      padding: .85rem 1rem; border-radius: 10px;
      font-size: .875rem; font-weight: 500;
      margin-bottom: 1.25rem;
      animation: alertIn .35s ease both;
    }
    @keyframes alertIn {
      from { opacity: 0; transform: translateY(-8px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .alert-danger  { background: var(--danger-bg);  color: var(--danger);  border: 1px solid #f5c6c3; }
    .alert-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--border); }

    /* OTP digit inputs */
    .otp-group {
      display: flex; gap: .6rem; justify-content: center;
      margin: 1.75rem 0;
    }
    .otp-group input {
      width: 52px; height: 60px;
      text-align: center; font-size: 1.6rem; font-weight: 800;
      font-family: 'Plus Jakarta Sans', monospace;
      border: 2px solid var(--border); border-radius: 12px;
      background: var(--cream); color: var(--text);
      outline: none; transition: border-color .2s, box-shadow .2s, background .2s;
      caret-color: var(--green-brand);
    }
    .otp-group input:focus {
      border-color: var(--green-brand); background: #fff;
      box-shadow: 0 0 0 4px var(--green-glow);
    }
    .otp-group input.filled {
      border-color: var(--green-brand);
      background: var(--green-pale);
    }

    .btn-verify {
      width: 100%; padding: .95rem;
      background: linear-gradient(135deg, var(--green-mid) 0%, var(--green-brand) 100%);
      color: #fff; border: none; border-radius: 10px;
      font-family: inherit; font-size: .95rem; font-weight: 700;
      cursor: pointer; display: flex; align-items: center;
      justify-content: center; gap: .6rem;
      box-shadow: var(--shadow-btn);
      transition: transform .2s, box-shadow .2s;
    }
    .btn-verify:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(61,122,95,.5); }
    .btn-verify:active { transform: translateY(0); }
    .btn-verify i { transition: transform .3s; }
    .btn-verify:hover i { transform: translateX(4px); }

    .resend-row {
      text-align: center; margin-top: 1.25rem;
      font-size: .85rem; color: var(--text-muted);
    }
    .resend-row a {
      color: var(--green-brand); font-weight: 700;
      text-decoration: none;
    }
    .resend-row a:hover { text-decoration: underline; }

    .divider { border: none; border-top: 1px solid var(--border); margin: 1.5rem 0 1.25rem; }
    .back-link {
      display: flex; align-items: center; justify-content: center; gap: .4rem;
      font-size: .82rem; color: var(--text-muted); text-decoration: none; font-weight: 500;
    }
    .back-link:hover { color: var(--text); }

    /* Timer */
    #timer { font-weight: 700; color: var(--green-brand); }
    #timer.expired { color: var(--danger); }
  </style>
</head>
<body>
<div class="page-bg"><div class="bg-dots"></div></div>
<div class="auth-wrap">
  <div class="auth-card">

    <div class="auth-logo">
      <div class="brand-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
      <h1>Check Your Email</h1>
      <p>We sent a 6-digit code to</p>
      <span class="email-badge"><?= e($email) ?></span>
    </div>

    <?php if ($flashSuccess): ?>
    <div class="alert alert-success">
      <i class="fa-solid fa-check" style="flex-shrink:0;margin-top:.1rem"></i>
      <?= e($flashSuccess) ?>
    </div>
    <?php endif; ?>

    <?php if ($flashError || $errors): ?>
    <div class="alert alert-danger">
      <i class="fa-solid fa-circle-exclamation" style="flex-shrink:0;margin-top:.1rem"></i>
      <?= e($flashError ?: $errors[0]) ?>
    </div>
    <?php endif; ?>

    <form method="post" id="otpForm">
      <?= csrf_field() ?>
      <div class="otp-group" id="otpGroup">
        <?php for ($i = 1; $i <= 6; $i++): ?>
        <input type="text" name="d<?= $i ?>" id="d<?= $i ?>"
          maxlength="1" inputmode="numeric" pattern="[0-9]"
          autocomplete="one-time-code" required>
        <?php endfor; ?>
      </div>
      <button class="btn-verify" type="submit">
        Verify Email <i class="fa-solid fa-arrow-right"></i>
      </button>
    </form>

    <div class="resend-row">
      Code expires in <span id="timer">10:00</span> &nbsp;·&nbsp;
      <a href="<?= BASE_URL ?>/verify-otp.php?resend=1">Resend code</a>
    </div>

    <hr class="divider">
    <a href="<?= BASE_URL ?>/register.php" class="back-link">
      <i class="fa-solid fa-arrow-left"></i> Back to Register
    </a>

  </div>
</div>

<script>
// Auto-advance OTP inputs
const inputs = Array.from(document.querySelectorAll('#otpGroup input'));

inputs.forEach((input, idx) => {
  input.addEventListener('input', (e) => {
    const val = e.target.value.replace(/\D/g, '');
    e.target.value = val.slice(-1);
    e.target.classList.toggle('filled', val !== '');
    if (val && idx < inputs.length - 1) inputs[idx + 1].focus();
  });
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Backspace' && !input.value && idx > 0) {
      inputs[idx - 1].focus();
      inputs[idx - 1].value = '';
      inputs[idx - 1].classList.remove('filled');
    }
  });
  input.addEventListener('paste', (e) => {
    e.preventDefault();
    const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
    pasted.split('').slice(0, 6).forEach((ch, i) => {
      if (inputs[i]) {
        inputs[i].value = ch;
        inputs[i].classList.add('filled');
      }
    });
    const next = inputs[Math.min(pasted.length, 5)];
    if (next) next.focus();
  });
});

// Auto-submit when all 6 filled
inputs[inputs.length - 1].addEventListener('input', () => {
  if (inputs.every(inp => inp.value)) {
    document.getElementById('otpForm').submit();
  }
});

// Countdown timer (10 min)
let seconds = 600;
const timerEl = document.getElementById('timer');
const countdown = setInterval(() => {
  seconds--;
  if (seconds <= 0) {
    clearInterval(countdown);
    timerEl.textContent = 'Expired';
    timerEl.classList.add('expired');
    return;
  }
  const m = String(Math.floor(seconds / 60)).padStart(2, '0');
  const s = String(seconds % 60).padStart(2, '0');
  timerEl.textContent = `${m}:${s}`;
}, 1000);

// Focus first input
inputs[0].focus();
</script>
</body>
</html>