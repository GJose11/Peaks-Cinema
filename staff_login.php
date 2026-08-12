<?php
date_default_timezone_set('Asia/Manila');
session_start();
if (isset($_SESSION['staff_logged_in']) && $_SESSION['staff_logged_in'] === true) {
    header("Location: staff_dashboard.php"); exit;
}
include(__DIR__ . "/peakscinemas_database.php");
$errorMsg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    if (!$email || !$password) {
        $errorMsg = "Please fill in both fields.";
    } else {
        $staff = null;
        $tableCheck = $conn->query("SHOW TABLES LIKE 'staff'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("SELECT * FROM staff WHERE Email = ? LIMIT 1");
            $stmt->bind_param("s", $email); $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                $passOk = password_verify($password, $row['Password']) || $password === $row['Password'];
                if ($passOk) $staff = $row;
            }
        }
        if ($staff) {
            $_SESSION['staff_logged_in'] = true;
            $_SESSION['staff_name']      = $staff['Name']  ?? 'Staff';
            $_SESSION['staff_email']     = $staff['Email'] ?? $email;
            $_SESSION['staff_id']        = $staff['Staff_ID'] ?? 0;
            header("Location: staff_dashboard.php"); exit;
        } else {
            $errorMsg = "Invalid credentials. Please try again.";
            sleep(1);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <title>Staff Login – Peak's Cinema</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Outfit', sans-serif;
            background: #0f0f0f;
            color: #F9F9F9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: url('movie-background-collage.jpg') center/cover no-repeat;
            opacity: 0.05;
            z-index: 0;
        }
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background: radial-gradient(ellipse at center, transparent 30%, #0f0f0f 80%);
            z-index: 1;
        }

        .login-wrap {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .brand { text-align: center; margin-bottom: 32px; }
        .brand-logo { display: flex; justify-content: center; margin-bottom: 8px; }
        .brand-logo img { height: 48px; width: auto; filter: invert(1); display: block; }
        .brand-sub  { font-size: 0.7rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #ff4d4d; margin-top: 4px; }

        .login-card {
            background: rgba(26,26,26,0.95);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 18px;
            padding: 36px 32px;
            backdrop-filter: blur(20px);
            box-shadow: 0 24px 80px rgba(0,0,0,0.6);
        }

        .card-title { font-size: 1.2rem; font-weight: 800; margin-bottom: 4px; }
        .card-sub   { font-size: 0.8rem; color: rgba(249,249,249,0.35); margin-bottom: 28px; }

        .error-box {
            background: rgba(255,77,77,0.08);
            border: 1px solid rgba(255,77,77,0.25);
            border-left: 3px solid #ff4d4d;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.8rem;
            color: #ff6b6b;
            margin-bottom: 18px;
            display: flex; align-items: center; gap: 8px;
        }

        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
        .form-group label { font-size: 0.7rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.4); }
        .input-wrap { position: relative; }
        .input-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); opacity: 0.35; font-size: 0.9rem; pointer-events: none; }
        .form-group input {
            width: 100%;
            background: #222;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: #F9F9F9;
            font-family: 'Outfit', sans-serif;
            font-size: 0.9rem;
            padding: 11px 13px 11px 38px;
            outline: none;
            transition: border-color 0.2s, background 0.2s;
        }
        .form-group input:focus { border-color: rgba(255,77,77,0.5); background: #252525; }
        .pw-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: rgba(249,249,249,0.3); cursor: pointer; font-size: 0.85rem; transition: color 0.2s; }
        .pw-toggle:hover { color: rgba(249,249,249,0.7); }

        .btn-login {
            width: 100%; padding: 13px; margin-top: 8px;
            background: #ff4d4d;
            border: none; border-radius: 10px;
            color: #fff; font-family: 'Outfit', sans-serif;
            font-size: 0.95rem; font-weight: 700; cursor: pointer;
            letter-spacing: 0.5px;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
        }
        .btn-login:hover { background: #e03c3c; transform: translateY(-1px); box-shadow: 0 8px 24px rgba(255,77,77,0.3); }

        .divider { height: 1px; background: rgba(255,255,255,0.06); margin: 22px 0; }
        .security-note { font-size: 0.7rem; color: rgba(249,249,249,0.2); text-align: center; display: flex; align-items: center; justify-content: center; gap: 5px; }

        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: rgba(249,249,249,0.3); text-decoration: none; font-size: 0.78rem; transition: color 0.2s; }
        .back-link a:hover { color: rgba(249,249,249,0.7); }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="brand">
        <div class="brand-logo"><img src="peakscinematransparent.png" alt="Peak's Cinema Logo"></div>
        <div class="brand-sub">Staff Portal</div>
    </div>

    <div class="login-card">
        <h1 class="card-title">Staff Sign In</h1>
        <p class="card-sub">Use your assigned staff credentials to continue.</p>

        <?php if ($errorMsg): ?>
        <div class="error-box">⚠️ <?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label>Email Address</label>
                <div class="input-wrap">
                    <span class="input-icon">✉</span>
                    <input type="email" name="email" placeholder="staff@peakscinema.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                </div>
            </div>
            <div class="form-group">
                <label>Password</label>
                <div class="input-wrap">
                    <span class="input-icon">🔒</span>
                    <input type="password" name="password" id="pwInput" placeholder="••••••••" required>
                    <button type="button" class="pw-toggle" onclick="togglePw()">👁</button>
                </div>
            </div>
            <button type="submit" class="btn-login">Sign In to Staff Panel →</button>
        </form>

        <div class="divider"></div>
        <div class="security-note">🎟 Authorized cinema staff only</div>
    </div>

    <div class="back-link">
        <a href="index.php">← Back to Peak's Cinema</a>
    </div>
</div>
<script>
function togglePw() {
    const i = document.getElementById('pwInput');
    i.type = i.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
