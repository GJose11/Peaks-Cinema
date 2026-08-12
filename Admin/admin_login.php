<?php
session_start();

// If already logged in as admin, go straight to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

include("../peakscinemas_database.php");

$errorMsg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $errorMsg = "Please fill in both fields.";
    } else {
        // Check against the dedicated admin table
        $admin = null;

        // Try dedicated admin table if it exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'admin'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("SELECT * FROM admin WHERE Email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                // Support both hashed and plain passwords
                $passMatch = password_verify($password, $row['Password'] ?? '')
                          || ($password === ($row['Password'] ?? ''));
                if ($passMatch) $admin = $row;
            }
        }

        if ($admin) {
            // Successful admin login
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_name']      = $admin['Name'] ?? $admin['Email'] ?? 'Admin';
            $_SESSION['admin_email']     = $admin['Email'] ?? $email;
            header("Location: dashboard.php");
            exit;
        } else {
            $errorMsg = "Invalid credentials or insufficient permissions.";
            // Small delay to slow brute force
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
    <title>Admin Login – PeaksCinemas</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Outfit', sans-serif;
            background: #0a0a0a;
            color: #F9F9F9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Background film strip effect */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: url('../movie-background-collage.jpg') center/cover no-repeat;
            opacity: 0.07;
            z-index: 0;
        }

        /* Vignette */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background: radial-gradient(ellipse at center, transparent 30%, #0a0a0a 80%);
            z-index: 1;
        }

        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        /* Brand */
        .brand {
            text-align: center;
            margin-bottom: 32px;
        }

        .brand-logo {
            display: flex;
            justify-content: center;
            margin-bottom: 8px;
        }
        .brand-logo img {
            height: 48px;
            width: auto;
            filter: invert(1);
            display: block;
        }

        .brand-name {
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #F9F9F9;
        }

        .brand-sub {
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #ff4d4d;
            margin-top: 4px;
        }

        /* Card */
        .login-card {
            background: rgba(26,26,26,0.95);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 18px;
            padding: 36px 32px;
            backdrop-filter: blur(20px);
            box-shadow: 0 24px 80px rgba(0,0,0,0.6);
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .card-sub {
            font-size: 0.8rem;
            color: rgba(249,249,249,0.35);
            margin-bottom: 28px;
        }

        /* Form */
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 16px;
        }

        .form-group label {
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: rgba(249,249,249,0.4);
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap .input-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.95rem;
            opacity: 0.4;
            pointer-events: none;
        }

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

        .form-group input:focus {
            border-color: rgba(255,77,77,0.5);
            background: #252525;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(249,249,249,0.3);
            cursor: pointer;
            font-size: 0.85rem;
            padding: 4px;
            transition: color 0.2s;
        }
        .password-toggle:hover { color: rgba(249,249,249,0.7); }

        /* Error message */
        .error-box {
            background: rgba(255,77,77,0.08);
            border: 1px solid rgba(255,77,77,0.25);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.8rem;
            color: #ff6b6b;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Submit */
        .btn-login {
            width: 100%;
            padding: 13px;
            margin-top: 8px;
            background: #ff4d4d;
            border: none;
            border-radius: 10px;
            color: #fff;
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            letter-spacing: 0.5px;
        }
        .btn-login:hover {
            background: #e03c3c;
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(255,77,77,0.3);
        }

        /* Back link */
        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: rgba(249,249,249,0.3);
            text-decoration: none;
            font-size: 0.78rem;
            transition: color 0.2s;
        }
        .back-link a:hover { color: rgba(249,249,249,0.7); }

        /* Divider */
        .divider {
            height: 1px;
            background: rgba(255,255,255,0.06);
            margin: 22px 0;
        }

        .security-note {
            font-size: 0.7rem;
            color: rgba(249,249,249,0.2);
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
    </style>
</head>
<body>

<div class="login-wrapper">

    <div class="brand">
        <div class="brand-logo"><img src="../peakscinematransparent.png" alt="Peak's Cinema Logo"></div>
        <div class="brand-sub">Admin Portal</div>
    </div>

    <div class="login-card">
        <h1 class="card-title">Welcome back</h1>
        <p class="card-sub">Sign in with your admin credentials to continue.</p>

        <?php if ($errorMsg): ?>
        <div class="error-box">
            <span>⚠️</span> <?= htmlspecialchars($errorMsg) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>" autocomplete="off">

            <div class="form-group">
                <label>Email Address</label>
                <div class="input-wrap">
                    <span class="input-icon">✉</span>
                    <input type="email" name="email" placeholder="admin@peakscinema.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-wrap">
                    <span class="input-icon">🔒</span>
                    <input type="password" name="password" id="passwordInput" placeholder="••••••••" required>
                    <button type="button" class="password-toggle" onclick="togglePassword()" id="toggleBtn">👁</button>
                </div>
            </div>

            <button type="submit" class="btn-login">Sign In to Admin Panel →</button>

        </form>

        <div class="divider"></div>

        <div class="security-note">
            🔐 This area is restricted to authorized staff only
        </div>
    </div>

    <div class="back-link">
        <a href="../home.php">← Back to PeaksCinemas</a>
    </div>

</div>

<script>
    function togglePassword() {
        const input = document.getElementById('passwordInput');
        const btn   = document.getElementById('toggleBtn');
        if (input.type === 'password') {
            input.type = 'text';
            btn.textContent = '🙈';
        } else {
            input.type = 'password';
            btn.textContent = '👁';
        }
    }
</script>
</body>
</html>
