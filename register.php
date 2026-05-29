<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
session_start_once();

if (is_logged_in()) { redirect(BASE_URL . '/index.php'); }

$errors = [];
$data   = [
    'full_name'            => '',
    'email'                => '',
    'phone'                => '',
    'account_type'         => 'adopter',   // 'adopter' | 'rescue_org'
    'organization_name'    => '',
    'organization_type'    => '',
    'organization_address' => '',
    'organization_website' => '',
    'sec_registration_no'  => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $data['full_name']            = clean($_POST['full_name']            ?? '');
    $data['email']                = clean($_POST['email']                ?? '');
    $data['phone']                = clean($_POST['phone']                ?? '');
    $data['account_type']         = in_array($_POST['account_type'] ?? '', ['adopter','rescue_org'], true)
                                        ? $_POST['account_type']
                                        : 'adopter';
    $data['organization_name']    = clean($_POST['organization_name']    ?? '');
    $data['organization_type']    = clean($_POST['organization_type']    ?? '');
    $data['organization_address'] = clean($_POST['organization_address'] ?? '');
    $data['organization_website']  = clean($_POST['organization_website']  ?? '');
    $data['sec_registration_no']  = clean($_POST['sec_registration_no']  ?? '');

    $password = $_POST['password']         ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    // ── Common validations ────────────────────────────────────────────
    if (strlen($data['full_name']) < 2)                     $errors[] = 'Full name must be at least 2 characters.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!preg_match('/^\d{11}$/', $data['phone']))           $errors[] = 'Phone number must be exactly 11 digits (numbers only).';
    if (strlen($password) < 8)                              $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)                             $errors[] = 'Passwords do not match.';

    // ── Rescue org validations ────────────────────────────────────────
    if ($data['account_type'] === 'rescue_org') {
        if (strlen($data['organization_name']) < 2)
            $errors[] = 'Organization name must be at least 2 characters.';
        if (empty($data['organization_type']))
            $errors[] = 'Please select an organization type.';
        if (strlen($data['organization_address']) < 5)
            $errors[] = 'Please enter your organization address.';
        if ($data['organization_website'] !== '' &&
            !filter_var($data['organization_website'], FILTER_VALIDATE_URL))
            $errors[] = 'Website must be a valid URL (e.g. https://yourorg.com).';
    }

    if (!$errors) {
        // Check duplicate email
        $dup = db()->prepare("SELECT id FROM users WHERE email = ?");
        $dup->execute([$data['email']]);
        if ($dup->fetch()) {
            $errors[] = 'An account with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $role = $data['account_type'];   // 'adopter' or 'rescue_org'

            if ($role === 'rescue_org') {
                // Rescue org: inserted as inactive (is_active=0), pending admin approval.
                // is_verified=1 so no OTP step is needed — admin approval is the gate.

                // Detect which optional rescue-org columns exist in the users table
                // so the INSERT never crashes on an unmigrated schema.
                $__descRows    = db()->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
                $_existingCols = array_column($__descRows, 'Field');

                // Map: column name => value to bind
                // Covers both dashboard-expected cols and form-native cols
                $_optionalCols = [
                    'shelter_name'         => $data['organization_name'],
                    'sec_registration_no'  => $data['sec_registration_no'] ?: null,
                    'address'              => $data['organization_address'],
                    'organization_name'    => $data['organization_name'],
                    'organization_type'    => $data['organization_type'],
                    'organization_address' => $data['organization_address'],
                    'organization_website' => $data['organization_website'] ?: null,
                ];

                // Build the column list and value list using only columns that exist,
                // avoiding duplicate entries if both alias names happen to exist.
                $_insertCols = ['full_name', 'email', 'password_hash', 'role', 'phone', 'is_verified', 'is_active'];
                $_insertVals = [$data['full_name'], $data['email'], $hash, 'rescue_org', $data['phone'], 1, 0];

                foreach ($_optionalCols as $_col => $_val) {
                    // Skip if column doesn't exist in DB
                    if (!in_array($_col, $_existingCols, true)) continue;
                    // Skip if already queued (avoids duplicate column errors)
                    if (in_array($_col, $_insertCols, true)) continue;
                    $_insertCols[] = $_col;
                    $_insertVals[] = $_val;
                }

                $_colSQL      = implode(', ', $_insertCols);
                $_placeholders = implode(', ', array_fill(0, count($_insertVals), '?'));

                $ins = db()->prepare("INSERT INTO users ({$_colSQL}) VALUES ({$_placeholders})");
                $ins->execute($_insertVals);
                $userId = (int)db()->lastInsertId();

                log_activity('register', 'user', $userId, 'New rescue org registered — pending admin approval');

                // Notify all admins
                try {
                    $admins = db()->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll();
                    $notifStmt = db()->prepare(
                        "INSERT INTO notifications (user_id, title, message, link)
                         VALUES (?, ?, ?, ?)"
                    );
                    foreach ($admins as $admin) {
                        $notifStmt->execute([
                            $admin['id'],
                            'New Rescue Organization Registration',
                            $data['organization_name'] . ' has registered as a rescue organization and is awaiting approval.',
                            BASE_URL . '/modules/admin/users.php',
                        ]);
                    }
                } catch (Throwable) { /* non-fatal */ }

                flash('success', 'Your rescue organization account has been submitted! An admin will review and activate it shortly.');
                redirect(BASE_URL . '/login.php');

            } else {
                // Adopter flow: unchanged — OTP email verification
                $ins = db()->prepare(
                    "INSERT INTO users (full_name, email, password_hash, role, phone, is_verified)
                     VALUES (?, ?, ?, 'adopter', ?, 0)"
                );
                $ins->execute([$data['full_name'], $data['email'], $hash, $data['phone']]);
                $userId = (int)db()->lastInsertId();

                log_activity('register', 'user', $userId, 'New adopter registered — pending OTP verification');

                $otp  = generate_otp();
                save_otp($userId, $otp);
                $sent = send_otp_email($data['email'], $data['full_name'], $otp);

                if (!$sent) {
                    db()->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
                    $errors[] = 'Failed to send verification email. Please try again.';
                } else {
                    session_start_once();
                    $_SESSION['otp_user_id'] = $userId;
                    $_SESSION['otp_email']   = $data['email'];
                    redirect(BASE_URL . '/verify-otp.php');
                }
            }
        }
    }
}

$pageTitle = 'Create Account';
$orgTypes  = ['Shelter', 'Rescue Group', 'Foster Network', 'Veterinary Clinic', 'Other'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle . ' — ' . APP_NAME) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/styles.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --green-deep:  #1e3a2f;
      --green-mid:   #2d5a45;
      --green-brand: #3d7a5f;
      --green-light: #5a9e7c;
      --green-pale:  #e8f2ec;
      --green-glow:  rgba(61,122,95,.18);
      --cream:       #faf8f4;
      --brown:       #3b2a1a;
      --text:        #1a2e25;
      --text-muted:  #6b8f7a;
      --border:      #d4e6da;
      --danger:      #c0392b;
      --danger-bg:   #fdf0ef;
      --shadow-card: 0 32px 80px rgba(30,58,47,.13), 0 4px 16px rgba(30,58,47,.07);
      --shadow-btn:  0 4px 20px rgba(61,122,95,.4);
    }
    html, body {
      min-height: 100%;
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--cream);
      color: var(--text);
      -webkit-font-smoothing: antialiased;
    }
    .page-bg {
      position: fixed; inset: 0; z-index: 0; overflow: hidden;
    }
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
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      padding: 2rem 1rem;
    }
    .auth-card {
      width: 100%; max-width: 560px;
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
      border-radius: 18px;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 1.6rem; color: #fff; margin-bottom: 1rem;
      box-shadow: 0 8px 24px rgba(61,122,95,.3);
      transition: transform .3s;
    }
    .brand-icon:hover { transform: rotate(-6deg) scale(1.08); }
    .auth-logo h1 {
      font-family: 'Instrument Serif', serif;
      font-size: 1.75rem; font-weight: 400;
      color: var(--brown); letter-spacing: -.5px;
      margin-bottom: .25rem;
    }
    .auth-logo p { font-size: .875rem; color: var(--text-muted); font-weight: 500; }

    /* ── Account-type toggle ──────────────────────────────────────── */
    .type-toggle {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: .6rem; margin-bottom: 1.5rem;
    }
    .type-toggle input[type="radio"] { display: none; }
    .type-label {
      display: flex; flex-direction: column;
      align-items: center; gap: .45rem;
      padding: 1rem .75rem;
      border: 2px solid var(--border);
      border-radius: 14px;
      cursor: pointer;
      transition: border-color .2s, background .2s, box-shadow .2s;
      text-align: center;
    }
    .type-label .type-icon {
      width: 44px; height: 44px;
      border-radius: 12px;
      background: var(--green-pale);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.2rem; color: var(--green-brand);
      transition: background .2s, color .2s;
    }
    .type-label .type-title {
      font-size: .82rem; font-weight: 700;
      color: var(--text); letter-spacing: .01em;
    }
    .type-label .type-sub {
      font-size: .72rem; color: var(--text-muted);
      font-weight: 500; line-height: 1.4;
    }
    .type-toggle input[type="radio"]:checked + .type-label {
      border-color: var(--green-brand);
      background: var(--green-pale);
      box-shadow: 0 0 0 4px var(--green-glow);
    }
    .type-toggle input[type="radio"]:checked + .type-label .type-icon {
      background: var(--green-brand); color: #fff;
    }
    .type-label:hover {
      border-color: var(--green-light);
      background: #f8fcf9;
    }

    /* ── Org section ─────────────────────────────────────────────── */
    .org-section {
      overflow: hidden;
      max-height: 0;
      opacity: 0;
      transition: max-height .4s cubic-bezier(.22,1,.36,1), opacity .3s ease, margin .3s ease;
      margin-bottom: 0;
    }
    .org-section.visible {
      max-height: 900px;
      opacity: 1;
      margin-bottom: 0;
    }
    .org-section.visible.anim-done {
      overflow: visible;
    }
    .org-divider {
      display: flex; align-items: center; gap: .75rem;
      margin: .25rem 0 1.1rem;
    }
    .org-divider hr {
      flex: 1; border: none; border-top: 1.5px solid var(--border);
    }
    .org-divider span {
      font-size: .72rem; font-weight: 700; letter-spacing: .08em;
      text-transform: uppercase; color: var(--green-brand);
      white-space: nowrap;
      background: var(--green-pale);
      padding: .25rem .7rem; border-radius: 20px;
      border: 1px solid var(--border);
    }
    .pending-note {
      background: #fffbeb; border: 1px solid #f5d87a;
      border-radius: 10px; padding: .85rem 1rem;
      font-size: .8rem; color: #8a6a10;
      display: flex; align-items: flex-start; gap: .6rem;
      margin-bottom: 1rem;
      line-height: 1.5;
    }
    .pending-note i { margin-top: .1rem; flex-shrink: 0; color: #c49a0a; }

    /* ── Shared form styles ───────────────────────────────────────── */
    .alert {
      display: flex; align-items: flex-start; gap: .6rem;
      padding: .85rem 1rem; border-radius: 10px;
      font-size: .875rem; font-weight: 500;
      margin-bottom: 1.25rem;
      background: var(--danger-bg); color: var(--danger);
      border: 1px solid #f5c6c3;
      animation: alertIn .35s ease both;
    }
    @keyframes alertIn {
      from { opacity: 0; transform: translateY(-8px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .alert ul { margin: 0; padding-left: 1.1rem; }
    .alert ul li { margin-bottom: .2rem; }

    .section-label {
      font-size: .7rem; font-weight: 800; letter-spacing: .1em;
      text-transform: uppercase; color: var(--green-brand);
      margin-bottom: .9rem; margin-top: .25rem;
    }

    .field { margin-bottom: 1.1rem; }
    .field label {
      display: block; font-size: .75rem; font-weight: 700;
      letter-spacing: .07em; text-transform: uppercase;
      color: var(--text-muted); margin-bottom: .45rem;
    }
    .field label .req { color: #e74c3c; }
    .input-wrap { position: relative; }
    .input-wrap .input-icon {
      position: absolute; left: 1rem; top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted); font-size: .85rem;
      pointer-events: none; transition: color .2s;
    }
    .input-wrap input,
    .input-wrap select,
    .input-wrap textarea {
      width: 100%;
      padding: .875rem 1rem .875rem 2.75rem;
      border: 1.5px solid var(--border); border-radius: 10px;
      font-family: inherit; font-size: .95rem; font-weight: 500;
      color: var(--text); background: var(--cream);
      transition: border-color .2s, box-shadow .2s, background .2s;
      outline: none;
    }
    .input-wrap select { cursor: pointer; appearance: none; }
    .input-wrap textarea {
      resize: vertical; min-height: 80px;
      padding-top: .875rem; line-height: 1.5;
    }
    .input-wrap input::placeholder,
    .input-wrap textarea::placeholder { color: #b0c8bb; font-weight: 400; }
    .input-wrap input:focus,
    .input-wrap select:focus,
    .input-wrap textarea:focus {
      border-color: var(--green-brand); background: #fff;
      box-shadow: 0 0 0 4px var(--green-glow);
    }
    .input-wrap:focus-within .input-icon { color: var(--green-brand); }
    /* select caret */
    .input-wrap .select-caret {
      position: absolute; right: 1rem; top: 50%;
      transform: translateY(-50%);
      pointer-events: none; color: var(--text-muted); font-size: .75rem;
    }
    .pw-toggle {
      position: absolute; right: 1rem; top: 50%;
      transform: translateY(-50%);
      background: none; border: none;
      color: var(--text-muted); cursor: pointer;
      padding: 0; font-size: .85rem; transition: color .2s;
    }
    .pw-toggle:hover { color: var(--green-brand); }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 480px) {
      .field-row { grid-template-columns: 1fr; }
      .auth-card { padding: 2rem 1.25rem 1.75rem; }
    }

    .btn-submit {
      width: 100%; padding: .95rem;
      background: linear-gradient(135deg, var(--green-mid) 0%, var(--green-brand) 100%);
      color: #fff; border: none; border-radius: 10px;
      font-family: inherit; font-size: .95rem; font-weight: 700;
      letter-spacing: .02em; cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: .6rem;
      box-shadow: var(--shadow-btn);
      transition: transform .2s, box-shadow .2s;
      margin-top: .25rem;
    }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(61,122,95,.5); }
    .btn-submit:active { transform: translateY(0); }
    .btn-submit i { transition: transform .3s; }
    .btn-submit:hover i { transform: translateX(4px); }

    .divider { border: none; border-top: 1px solid var(--border); margin: 1.5rem 0 1.25rem; }
    .auth-footer { text-align: center; }
    .auth-footer p { font-size: .85rem; color: var(--text-muted); margin-bottom: .5rem; }
    .auth-footer a { font-weight: 700; color: var(--green-brand); text-decoration: none; }
    .auth-footer a:hover { text-decoration: underline; }
    .back-home {
      display: inline-flex; align-items: center; gap: .4rem;
      font-size: .82rem; color: var(--text-muted) !important; font-weight: 500 !important;
    }
    .back-home:hover { color: var(--text) !important; text-decoration: none !important; }
  </style>
</head>
<body>
<div class="page-bg"><div class="bg-dots"></div></div>
<div class="auth-wrap">
  <div class="auth-card">

    <div class="auth-logo">
      <div class="brand-icon"><i class="fa-solid fa-paw"></i></div>
      <h1>Create Your Account</h1>
      <p>Join <?= e(APP_NAME) ?> and make a difference</p>
    </div>

    <?php if ($errors): ?>
    <div class="alert">
      <i class="fa-solid fa-circle-exclamation" style="margin-top:.15rem;flex-shrink:0"></i>
      <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="post" id="registerForm">
      <?= csrf_field() ?>

      <!-- ── Account type selector ─────────────────────────────── -->
      <div class="type-toggle">
        <input type="radio" name="account_type" id="type_adopter" value="adopter"
          <?= $data['account_type'] !== 'rescue_org' ? 'checked' : '' ?>>
        <label class="type-label" for="type_adopter">
          <span class="type-icon"><i class="fa-solid fa-heart"></i></span>
          <span class="type-title">Personal Adopter</span>
          <span class="type-sub">I want to adopt a pet</span>
        </label>

        <input type="radio" name="account_type" id="type_staff" value="rescue_org"
          <?= $data['account_type'] === 'rescue_org' ? 'checked' : '' ?>>
        <label class="type-label" for="type_staff">
          <span class="type-icon"><i class="fa-solid fa-house-chimney-medical"></i></span>
          <span class="type-title">Rescue Organization</span>
          <span class="type-sub">I run a shelter or rescue group</span>
        </label>
      </div>

      <!-- ── Pending approval notice (rescue org only) ───────── -->
      <div class="pending-note" id="pendingNote" style="display:none">
        <i class="fa-solid fa-clock"></i>
        <div>
          Your organization account will be <strong>reviewed by an admin</strong> before activation.
          You'll be able to log in and list pets once approved.
        </div>
      </div>

      <!-- ── Personal info ─────────────────────────────────────── -->
      <div class="field">
        <label for="full_name">
          <span id="nameLabel">Full Name</span> <span class="req">*</span>
        </label>
        <div class="input-wrap">
          <i class="fa-solid fa-user input-icon"></i>
          <input id="full_name" name="full_name" type="text"
            value="<?= e($data['full_name']) ?>"
            placeholder="Your full name" required>
        </div>
      </div>

      <div class="field">
        <label for="email">Email Address <span class="req">*</span></label>
        <div class="input-wrap">
          <i class="fa-solid fa-envelope input-icon"></i>
          <input id="email" name="email" type="email"
            value="<?= e($data['email']) ?>" placeholder="you@example.com" required>
        </div>
      </div>

      <div class="field">
        <label for="phone">Phone Number <span class="req">*</span></label>
        <div class="input-wrap">
          <i class="fa-solid fa-phone input-icon"></i>
          <input id="phone" name="phone" type="tel"
            value="<?= e($data['phone']) ?>" placeholder="09XX-XXX-XXXX"
            maxlength="11" pattern="\d{11}" inputmode="numeric"
            oninput="this.value=this.value.replace(/\D/g,'').slice(0,11)"
            title="Phone number must be exactly 11 digits" required>
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="password">Password <span class="req">*</span></label>
          <div class="input-wrap">
            <i class="fa-solid fa-lock input-icon"></i>
            <input id="password" name="password" type="password"
              placeholder="Min. 8 characters" required>
            <button type="button" class="pw-toggle" onclick="togglePw('password','pwIcon1')">
              <i class="fa-solid fa-eye" id="pwIcon1"></i>
            </button>
          </div>
        </div>
        <div class="field">
          <label for="password_confirm">Confirm Password <span class="req">*</span></label>
          <div class="input-wrap">
            <i class="fa-solid fa-lock input-icon"></i>
            <input id="password_confirm" name="password_confirm" type="password"
              placeholder="Repeat password" required>
            <button type="button" class="pw-toggle" onclick="togglePw('password_confirm','pwIcon2')">
              <i class="fa-solid fa-eye" id="pwIcon2"></i>
            </button>
          </div>
          <div id="pw-match-msg" style="font-size:.75rem;margin-top:.4rem;display:none"></div>
        </div>
      </div>

      <!-- ── Rescue Organization fields (staff only) ───────────── -->
      <div class="org-section" id="orgSection">
        <div class="org-divider">
          <hr><span><i class="fa-solid fa-house-chimney-medical" style="margin-right:.35rem"></i>Organization Details</span><hr>
        </div>

        <div class="field">
          <label for="organization_name">Organization Name <span class="req">*</span></label>
          <div class="input-wrap">
            <i class="fa-solid fa-building input-icon"></i>
            <input id="organization_name" name="organization_name" type="text"
              value="<?= e($data['organization_name']) ?>"
              placeholder="e.g. Paws &amp; Hearts Rescue">
          </div>
        </div>

        <div class="field-row">
          <div class="field">
            <label for="organization_type">Type <span class="req">*</span></label>
            <div class="input-wrap">
              <i class="fa-solid fa-tag input-icon"></i>
              <select id="organization_type" name="organization_type">
                <option value="">Select type…</option>
                <?php foreach ($orgTypes as $ot): ?>
                <option value="<?= e($ot) ?>" <?= $data['organization_type'] === $ot ? 'selected' : '' ?>>
                  <?= e($ot) ?>
                </option>
                <?php endforeach; ?>
              </select>
              <i class="fa-solid fa-chevron-down select-caret"></i>
            </div>
          </div>

          <div class="field">
            <label for="sec_registration_no">SEC Reg. No. <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
            <div class="input-wrap">
              <i class="fa-solid fa-id-card input-icon"></i>
              <input id="sec_registration_no" name="sec_registration_no" type="text"
                value="<?= e($data['sec_registration_no']) ?>"
                placeholder="e.g. CS201012345">
            </div>
          </div>
        </div>


        <div class="field">
          <label for="organization_address">Organization Address <span class="req">*</span></label>
          <div class="input-wrap" style="position:relative">
            <i class="fa-solid fa-location-dot input-icon" style="top:1.15rem;transform:none"></i>
            <textarea id="organization_address" name="organization_address"
              placeholder="Street, City, Province"
              style="padding-left:2.75rem"><?= e($data['organization_address']) ?></textarea>
          </div>
        </div>
      </div><!-- /org-section -->

      <button class="btn-submit" type="submit" id="submitBtn">
        <span id="submitLabel">Create Account</span>
        <i class="fa-solid fa-arrow-right"></i>
      </button>
    </form>

    <hr class="divider">
    <div class="auth-footer">
      <p>Already have an account? <a href="<?= BASE_URL ?>/login.php">Sign in</a></p>
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
  /* ── Password toggle ─────────────────────────────────────────────── */
  function togglePw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    const show  = input.type === 'password';
    input.type     = show ? 'text' : 'password';
    icon.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
  }

  /* ── Password match indicator ────────────────────────────────────── */
  const pwInput   = document.getElementById('password');
  const pwConfirm = document.getElementById('password_confirm');
  const pwMsg     = document.getElementById('pw-match-msg');

  function checkMatch() {
    if (!pwConfirm.value) { pwMsg.style.display = 'none'; return; }
    const match = pwInput.value === pwConfirm.value;
    pwMsg.style.display = 'block';
    pwMsg.innerHTML = match
      ? '<i class="fa-solid fa-circle-check" style="color:#3d7a5f"></i> <span style="color:#3d7a5f;font-weight:600">Passwords match</span>'
      : '<i class="fa-solid fa-circle-xmark" style="color:#c0392b"></i> <span style="color:#c0392b;font-weight:600">Passwords do not match</span>';
    pwConfirm.style.borderColor = match ? '#3d7a5f' : '#c0392b';
  }
  pwConfirm.addEventListener('input', checkMatch);
  pwInput.addEventListener('input', checkMatch);

  /* ── Account type toggle ─────────────────────────────────────────── */
  const orgSection   = document.getElementById('orgSection');
  const pendingNote  = document.getElementById('pendingNote');
  const submitLabel  = document.getElementById('submitLabel');
  const nameLabel    = document.getElementById('nameLabel');
  const orgName      = document.getElementById('organization_name');
  const orgType      = document.getElementById('organization_type');
  const orgAddr      = document.getElementById('organization_address');
  const orgWeb       = document.getElementById('organization_website');

  // Fields that should be required only for staff
  const orgRequiredFields = [orgName, orgType, orgAddr];

  function applyAccountType(type) {
    const isOrg = type === 'rescue_org';

    if (isOrg) {
      orgSection.classList.add('visible');
      pendingNote.style.display = 'flex';
      submitLabel.textContent = 'Submit for Approval';
      nameLabel.textContent   = 'Full Name (Contact Person)';
      orgRequiredFields.forEach(f => f.setAttribute('required', ''));
    } else {
      orgSection.classList.remove('visible');
      orgSection.classList.remove('anim-done');
      pendingNote.style.display = 'none';
      submitLabel.textContent = 'Create Account';
      nameLabel.textContent   = 'Full Name';
      orgRequiredFields.forEach(f => f.removeAttribute('required'));
    }
  }

  // Once the open animation finishes, allow overflow so dropdowns aren't clipped
  orgSection.addEventListener('transitionend', function(e) {
    if (e.propertyName === 'max-height' && orgSection.classList.contains('visible')) {
      orgSection.classList.add('anim-done');
    }
  });

  document.querySelectorAll('input[name="account_type"]').forEach(radio => {
    radio.addEventListener('change', () => applyAccountType(radio.value));
  });

  // Run on load to restore PHP-repopulated state after validation error
  const checkedType = document.querySelector('input[name="account_type"]:checked');
  if (checkedType) {
    applyAccountType(checkedType.value);
    // If already open on load (e.g. after PHP validation error), skip animation and allow overflow immediately
    if (checkedType.value === 'rescue_org') {
      orgSection.classList.add('anim-done');
    }
  }
</script>
</body>
</html>