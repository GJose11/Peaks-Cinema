<?php
session_start();
include("peakscinemas_database.php");

// Handle logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
    header("Location: personal_info_form.php?logged_out=1");
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header("Location: personal_info_form.php");
    exit;
}

$stmt = $conn->prepare("SELECT Name, Email, PhoneNumber, CountryCode, Password, ProfilePhoto FROM customer WHERE Customer_ID = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_unset(); session_destroy();
    header("Location: personal_info_form.php?error=user_not_found");
    exit;
}

$user = $result->fetch_assoc();

$profile_photo = $user['ProfilePhoto'] ?? $_SESSION['profile_photo'] ?? null;
$nameParts     = explode(' ', trim($user['Name'] ?? ''));
$user_initials = strtoupper(substr($nameParts[0]??'',0,1) . substr(end($nameParts)??'',0,1));
if (strlen($user_initials) === 1) $user_initials = strtoupper(substr($nameParts[0]??'',0,2));

$message      = '';
$message_type = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phoneRaw = trim($_POST['phone'] ?? '');
    $phone    = preg_replace('/\D+/', '', $phoneRaw);
    if ($phone !== '' && preg_match('/^9\d{9}$/', $phone)) {
        $phone = '0' . $phone;
    }
    $password = trim($_POST['password'] ?? '');
    $removePhoto = ($_POST['remove_photo'] ?? '') === '1';

    $errors = [];
    if (empty($name) || strlen($name) > 100)          $errors[] = "Name must not be empty or longer than 100 characters.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $errors[] = "Invalid email format.";
    if (!empty($phone) && !preg_match('/^0[0-9]{10}$/', $phone)) $errors[] = "Phone number must be 11 digits starting with 0 (e.g. 09123456789).";
    if (!empty($password) && strlen($password) < 6)   $errors[] = "Password must be at least 6 characters.";

    // Handle File Upload
    $photoToSave = $user['ProfilePhoto'];
    if ($removePhoto) {
        if (!empty($user['ProfilePhoto']) && !str_starts_with($user['ProfilePhoto'], 'data:')) {
            if (file_exists($user['ProfilePhoto'])) unlink($user['ProfilePhoto']);
        }
        $photoToSave = null;
    } elseif (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_photo'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file['type'], $allowed)) {
            $errors[] = "Invalid image format. Please upload a JPG, PNG, WEBP, or GIF.";
        } elseif ($file['size'] > 3 * 1024 * 1024) {
            $errors[] = "Profile photo is too large (max 3 MB).";
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFilename = "user_" . $_SESSION['user_id'] . "_" . time() . "." . $ext;
            $uploadDir = "uploads/profile_photos/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $dest = $uploadDir . $newFilename;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                // Delete old file if it exists
                if (!empty($user['ProfilePhoto']) && !str_starts_with($user['ProfilePhoto'], 'data:')) {
                    if (file_exists($user['ProfilePhoto'])) unlink($user['ProfilePhoto']);
                }
                $photoToSave = $dest;
            } else {
                $errors[] = "Error saving uploaded photo.";
            }
        }
    }

    if (!empty($errors)) {
        $message      = implode('<br>', $errors);
        $message_type = 'err';
    } else {
        $hashedPassword = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : $user['Password'];

        $countryCode = $user['CountryCode'] ?? '+63';
        if (!empty($phone) && str_starts_with($phone, '0') && ($countryCode === '' || strcasecmp($countryCode, 'Philippine') === 0)) {
            $countryCode = '+63';
        }

        $upd = $conn->prepare("UPDATE customer SET Name=?, Email=?, PhoneNumber=?, CountryCode=?, Password=?, ProfilePhoto=? WHERE Customer_ID=?");
        $upd->bind_param("ssssssi", $name, $email, $phone, $countryCode, $hashedPassword, $photoToSave, $_SESSION['user_id']);

        if ($upd->execute()) {
            $message      = "Profile updated successfully!";
            $message_type = 'ok';
            $user['Name']        = $name;
            $user['Email']       = $email;
            $user['PhoneNumber'] = $phone;
            $user['CountryCode'] = $countryCode;
            $user['ProfilePhoto'] = $photoToSave;
            $profile_photo = $photoToSave;
            $_SESSION['profile_photo'] = $photoToSave;
            
            $np = explode(' ', trim($name));
            $user_initials = strtoupper(substr($np[0]??'',0,1).substr(end($np)??'',0,1));
            if (strlen($user_initials)===1) $user_initials = strtoupper(substr($np[0]??'',0,2));
        } else {
            $message      = "Error updating profile. Please try again.";
            $message_type = 'err';
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
<title>Edit Profile – Peak's Cinema</title>
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Outfit', sans-serif;
    background: #0f0f0f;
    color: #F9F9F9;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}
body::before {
    content: '';
    position: fixed; inset: 0;
    background: url('movie-background-collage.jpg') center/cover no-repeat;
    opacity: 0.12; z-index: 0; pointer-events: none;
}
body::after {
    content: '';
    position: fixed; inset: 0;
    background: radial-gradient(ellipse at center, transparent 10%, rgba(15,15,15,0.55) 60%, #0f0f0f 100%);
    z-index: 1; pointer-events: none;
}

header {
    background: #1C1C1C;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 30px;
    position: fixed; top: 0; left: 0; width: 100%;
    height: 60px; z-index: 1000;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
}
/* Standardized Logo System */
.brand-logo-wrap {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}
.brand-logo-wrap:hover {
    transform: scale(1.05);
}
.brand-logo {
    height: 42px; /* Desktop Default */
    width: auto;
    filter: invert(1);
    display: block;
}

@media (max-width: 1024px) {
    .brand-logo { height: 38px; }
}
@media (max-width: 480px) {
    .brand-logo { height: 32px; }
    header { padding: 0 15px; }
}
.header-right { display: flex; align-items: center; gap: 10px; }
.profile-btn {
    background: #F9F9F9; border: none; border-radius: 50%;
    width: 42px; height: 42px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; overflow: hidden; padding: 0;
    transition: all 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.profile-btn img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.profile-btn:hover { transform: scale(1.1); }
.profile-initials {
    width: 100%; height: 100%; border-radius: 50%;
    background: linear-gradient(135deg, #ff4d4d, #c0392b);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.82rem; font-weight: 800; color: #fff;
}
.btn-logout {
    color: #ff6b6b; text-decoration: none;
    font-size: 0.78rem; font-weight: 600;
    padding: 6px 14px; border-radius: 6px;
    border: 1px solid rgba(255,77,77,0.3);
    background: rgba(255,77,77,0.08);
    transition: all 0.2s;
}
.btn-logout:hover { background: rgba(255,77,77,0.18); color: #fff; }

.page-wrap {
    position: relative; z-index: 10;
    flex: 1;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 80px 20px 50px;
}
.profile-col { width: 100%; max-width: 480px; margin-top: 28px; }

.page-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #ff4d4d; margin-bottom: 5px; }
.page-title  { font-size: 1.7rem; font-weight: 800; margin-bottom: 20px; }

/* ── Avatar / photo upload ── */
.avatar-row {
    display: flex; align-items: center; gap: 16px;
    padding: 18px 20px;
    background: #1a1a1a;
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 14px;
    margin-bottom: 16px;
}
.avatar-photo-wrap {
    position: relative; flex-shrink: 0;
    width: 72px; height: 72px;
    cursor: pointer;
}
.avatar-circle {
    width: 72px; height: 72px;
    border-radius: 50%; overflow: hidden;
    border: 2px solid rgba(255,255,255,0.1);
    transition: border-color 0.2s;
}
.avatar-photo-wrap:hover .avatar-circle { border-color: rgba(255,77,77,0.5); }
.avatar-circle img { width: 100%; height: 100%; object-fit: cover; display: block; }
.avatar-initials {
    width: 100%; height: 100%; border-radius: 50%;
    background: linear-gradient(135deg, #ff4d4d, #c0392b);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; font-weight: 800; color: #fff;
}
/* Camera overlay on hover */
.avatar-overlay {
    position: absolute; inset: 0; border-radius: 50%;
    background: rgba(0,0,0,0.55);
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 2px;
    opacity: 0; transition: opacity 0.2s;
    pointer-events: none;
}
.avatar-photo-wrap:hover .avatar-overlay { opacity: 1; }
.avatar-overlay span:first-child { font-size: 1.1rem; }
.avatar-overlay span:last-child  { font-size: 0.5rem; font-weight: 700; letter-spacing: 0.5px; color: #fff; }

.avatar-info { flex: 1; min-width: 0; }
.avatar-info h3 { font-size: 1rem; font-weight: 700; margin-bottom: 3px; }
.avatar-info p  { font-size: 0.78rem; color: rgba(249,249,249,0.4); margin-bottom: 8px; }

/* Change photo button */
.btn-change-photo {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 7px;
    border: 1px solid rgba(255,77,77,0.3);
    background: rgba(255,77,77,0.08);
    color: #ff6b6b; font-family: 'Outfit', sans-serif;
    font-size: 0.75rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
}
.btn-change-photo:hover { background: rgba(255,77,77,0.18); color: #fff; border-color: #ff4d4d; }

/* Photo preview badge (shows after selecting) */
.photo-preview-badge {
    display: none; margin-top: 6px;
    font-size: 0.68rem; color: #81c784; font-weight: 600;
    align-items: center; gap: 4px;
}
.photo-preview-badge.visible { display: flex; }

/* Remove photo link */
.btn-remove-photo {
    display: none; margin-top: 4px;
    background: none; border: none;
    color: rgba(249,249,249,0.3); font-family: 'Outfit', sans-serif;
    font-size: 0.68rem; cursor: pointer; text-decoration: underline;
    padding: 0;
}
.btn-remove-photo:hover { color: #ff6b6b; }

.panel {
    background: #1a1a1a;
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 14px; overflow: hidden;
    margin-bottom: 16px;
}
.panel:last-child { margin-bottom: 0; }
.panel-header {
    padding: 14px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex; align-items: center; justify-content: space-between;
}
.panel-header h2 {
    font-size: 0.78rem; font-weight: 700;
    letter-spacing: 1.5px; text-transform: uppercase;
    color: rgba(249,249,249,0.45);
}
.panel-body { padding: 20px; }

.field { margin-bottom: 16px; }
.field:last-of-type { margin-bottom: 0; }
.field label {
    display: block;
    font-size: 0.68rem; font-weight: 700;
    letter-spacing: 1px; text-transform: uppercase;
    color: rgba(249,249,249,0.4);
    margin-bottom: 6px;
}
.field input {
    width: 100%; padding: 10px 13px;
    border-radius: 9px;
    border: 1px solid rgba(255,255,255,0.1);
    background: #222; color: #F9F9F9;
    font-family: 'Outfit', sans-serif; font-size: 0.88rem;
    outline: none; transition: border-color 0.2s;
}
.field input:focus { border-color: rgba(255,77,77,0.5); background: #252525; }
.field input::placeholder { color: rgba(249,249,249,0.2); }
.field input:-webkit-autofill,
.field input:-webkit-autofill:focus {
    -webkit-text-fill-color: #F9F9F9 !important;
    -webkit-box-shadow: 0 0 0 1000px #222 inset !important;
    caret-color: #F9F9F9;
}
.field-hint { font-size: 0.68rem; color: rgba(249,249,249,0.25); margin-top: 5px; }

.pw-wrap { position: relative; }
.pw-wrap input { padding-right: 60px; }
.pw-toggle {
    position: absolute; right: 12px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none;
    color: rgba(249,249,249,0.35);
    font-family: 'Outfit', sans-serif;
    font-size: 0.72rem; font-weight: 600;
    cursor: pointer; transition: color 0.2s; padding: 0;
}
.pw-toggle:hover { color: rgba(249,249,249,0.75); }

.msg-banner {
    padding: 12px 16px; border-radius: 9px;
    font-size: 0.82rem; margin-bottom: 16px;
    display: flex; align-items: flex-start; gap: 8px;
    animation: fadeUp 0.3s ease;
}
.msg-banner.ok  { background: rgba(76,175,80,0.08); border: 1px solid rgba(76,175,80,0.2); color: #81c784; }
.msg-banner.err { background: rgba(255,77,77,0.08); border: 1px solid rgba(255,77,77,0.2); border-left: 3px solid #ff4d4d; color: #ff6b6b; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

.btn-save {
    width: 100%; padding: 13px;
    border-radius: 9px; border: none;
    background: #ff4d4d; color: #fff;
    font-family: 'Outfit', sans-serif; font-size: 0.9rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s; margin-top: 4px;
}
.btn-save:hover { background: #e03c3c; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,77,77,0.3); }
.btn-back {
    display: block; width: 100%; padding: 11px;
    border-radius: 9px; border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04); color: rgba(249,249,249,0.5);
    font-family: 'Outfit', sans-serif; font-size: 0.88rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s; text-align: center; text-decoration: none;
    margin-top: 10px;
}
.btn-back:hover { background: rgba(255,255,255,0.08); color: #F9F9F9; }
@media (max-width: 600px) {
    .page-wrap { padding-top: 70px; padding-bottom: 30px; }
    .profile-col { margin-top: 15px; width: 100%; }
    .page-title { font-size: 1.4rem; margin-bottom: 15px; }
    .avatar-row { padding: 15px; gap: 12px; }
    .avatar-photo-wrap { width: 64px; height: 64px; }
    .avatar-circle { width: 64px; height: 64px; }
    .avatar-info h3 { font-size: 0.9rem; }
    .panel-header { padding: 12px 15px; }
    .panel-body { padding: 15px; }
    .btn-save { padding: 14px; font-size: 0.95rem; min-height: 48px; }
    .btn-back { padding: 12px; font-size: 0.85rem; min-height: 44px; }
}
</style>
</head>
<body>

<header id="mainHeader">
    <a href="home.php" class="brand-logo-wrap" title="Peak's Cinema - Home">
        <img src="peakscinematransparent.png" alt="Peak's Cinema Logo" class="brand-logo">
    </a>
    <div class="header-right">
        <button class="profile-btn" title="Profile Dashboard" id="headerAvatar" onclick="window.location.href='profile_dashboard.php'">
            <?php if (!empty($profile_photo)): ?>
                <img src="<?= htmlspecialchars($profile_photo) . '?' . time() ?>" alt="Profile" referrerpolicy="no-referrer" id="headerAvatarImg">
            <?php elseif (!empty($user_initials)): ?>
                <div class="profile-initials" id="headerAvatarInitials"><?= htmlspecialchars($user_initials) ?></div>
            <?php else: ?>
                <div class="profile-initials" id="headerAvatarInitials">?</div>
            <?php endif; ?>
        </button>
        <a href="?logout=1" class="btn-logout" onclick="return confirm('Log out of Peak\'s Cinema?')">→ Log Out</a>
    </div>
</header>

<div class="page-wrap">
<div class="profile-col">

    <p class="page-label">My Account</p>
    <h1 class="page-title">Edit Profile</h1>

    <?php if (!empty($message)): ?>
    <div class="msg-banner <?= $message_type ?>">
        <?= $message_type === 'ok' ? '✓' : '⚠' ?>&nbsp;<?= $message ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" id="profileForm" enctype="multipart/form-data">
        <!-- Hidden field carries the remove photo sentinel to PHP -->
        <input type="hidden" name="remove_photo" id="removePhotoInput" value="0">

        <!-- ── Avatar row with change photo ── -->
        <div class="avatar-row">
            <!-- Clickable avatar = opens file picker -->
            <div class="avatar-photo-wrap" onclick="document.getElementById('photoFileInput').click()" title="Change profile photo">
                <div class="avatar-circle">
                    <?php if (!empty($profile_photo)): ?>
                        <img src="<?= htmlspecialchars($profile_photo) . '?' . time() ?>" alt="Profile" referrerpolicy="no-referrer" id="avatarPreviewImg">
                    <?php else: ?>
                        <div class="avatar-initials" id="avatarInitials"><?= htmlspecialchars($user_initials ?: '?') ?></div>
                    <?php endif; ?>
                </div>
                <div class="avatar-overlay">
                    <span>📷</span>
                    <span>CHANGE</span>
                </div>
            </div>

            <div class="avatar-info">
                <h3 id="avatarName"><?= htmlspecialchars($user['Name']) ?></h3>
                <p><?= htmlspecialchars($user['Email']) ?></p>

                <!-- Change photo button -->
                <button type="button" class="btn-change-photo"
                        onclick="document.getElementById('photoFileInput').click()">
                    📷 Change Photo
                </button>

                <!-- Status after selecting -->
                <div class="photo-preview-badge" id="photoBadge">
                    ✓ New photo selected — save to apply
                </div>

                <!-- Remove photo (only shown when a custom photo exists) -->
                <?php if (!empty($profile_photo)): ?>
                <button type="button" class="btn-remove-photo" id="btnRemovePhoto"
                        onclick="removePhoto()" style="display:block;">
                    Remove photo
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Hidden file input -->
        <input type="file" id="photoFileInput" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif"
               style="display:none;" onchange="handlePhotoSelect(this)">

        <!-- Personal info panel -->
        <div class="panel">
            <div class="panel-header"><h2>👤 Personal Information</h2></div>
            <div class="panel-body">
                <div class="field">
                    <label>Full Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($user['Name']) ?>" placeholder="Your full name" required
                           oninput="document.getElementById('avatarName').textContent = this.value">
                </div>
                <div class="field">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['Email']) ?>" placeholder="you@example.com" required>
                </div>
                <div class="field">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($user['PhoneNumber']) ?>" placeholder="09XXXXXXXXX" pattern="0[0-9]{10}" maxlength="11" inputmode="numeric">
                    <p class="field-hint">Use 11 digits starting with 0, for example 09123456789.</p>
                </div>
            </div>
        </div>

        <!-- Password panel -->
        <div class="panel">
            <div class="panel-header"><h2>🔒 Change Password</h2></div>
            <div class="panel-body">
                <div class="field">
                    <label>New Password <span style="opacity:0.35;font-size:0.6rem;text-transform:none;letter-spacing:0;">(leave blank to keep current)</span></label>
                    <div class="pw-wrap">
                        <input type="password" id="pwInput" name="password" placeholder="Enter new password">
                        <button type="button" class="pw-toggle" id="pwToggle">Show</button>
                    </div>
                    <p class="field-hint">Minimum 6 characters.</p>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-save">Save Changes →</button>
    </form>

    <a href="profile_dashboard.php" class="btn-back">← Back to Dashboard</a>

</div>
</div>

<script>
// ── Password toggle ───────────────────────────────────────────
const pwInput  = document.getElementById('pwInput');
const pwToggle = document.getElementById('pwToggle');
pwToggle.addEventListener('click', () => {
    const show = pwInput.type === 'password';
    pwInput.type         = show ? 'text' : 'password';
    pwToggle.textContent = show ? 'Hide' : 'Show';
});

// ── Photo handling ────────────────────────────────────────────
function handlePhotoSelect(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];

    // Client-side size check — warn if > 3 MB
    if (file.size > 3 * 1024 * 1024) {
        alert('Photo is too large (max 3 MB). Please choose a smaller image or crop it first.');
        input.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const base64 = e.target.result;

        // Update avatar preview in the page
        updateAvatarPreview(base64);

        // Update header avatar live
        updateHeaderAvatar(base64);

        // Clear remove photo sentinel
        document.getElementById('removePhotoInput').value = '0';

        // Show badge + reveal remove button
        const badge = document.getElementById('photoBadge');
        badge.textContent = '✓ New photo selected — save to apply';
        badge.style.color = '#81c784';
        badge.classList.add('visible');
        
        const removeBtn = document.getElementById('btnRemovePhoto');
        if (removeBtn) removeBtn.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

function updateAvatarPreview(src) {
    const circle = document.querySelector('.avatar-circle');
    // Replace whatever is inside with an img
    circle.innerHTML = '<img src="' + src + '" alt="Preview" id="avatarPreviewImg" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
}

function updateHeaderAvatar(src) {
    const btn = document.getElementById('headerAvatar');
    if (!btn) return;
    btn.innerHTML = '<img src="' + src + '" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
}

function removePhoto() {
    // Clear the photo — set hidden input to special sentinel value
    document.getElementById('removePhotoInput').value = '1';

    // Revert avatar to initials
    const initials = '<?= addslashes($user_initials ?: "?") ?>';
    const circle   = document.querySelector('.avatar-circle');
    circle.innerHTML = '<div class="avatar-initials">' + initials + '</div>';

    // Revert header avatar
    const btn = document.getElementById('headerAvatar');
    if (btn) btn.innerHTML = '<div class="profile-initials">' + initials + '</div>';

    // Update badge
    const badge = document.getElementById('photoBadge');
    badge.textContent = '✓ Photo removed — save to apply';
    badge.classList.add('visible');
    badge.style.color = '#ff6b6b';

    // Clear file input
    document.getElementById('photoFileInput').value = '';
}

// ── Header hide on scroll ─────────────────────────────────────
(function(){
    const h=document.querySelector('header');let last=window.scrollY,tick=false;
    window.addEventListener('scroll',function(){if(!tick){requestAnimationFrame(function(){const cur=window.scrollY;h.style.transform=(cur>last&&cur>80)?'translateY(-100%)':'translateY(0)';last=cur;tick=false;});tick=true;}},{passive:true});
})();
</script>
</body>
</html>
